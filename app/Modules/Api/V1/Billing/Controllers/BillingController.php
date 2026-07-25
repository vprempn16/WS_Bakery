<?php

namespace App\Modules\Api\V1\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FieldModelManager;
use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\Billing\Requests\StoreBillingRequest;
use App\Modules\Api\V1\Billing\Requests\UpdateBillingRequest;
use App\Modules\Api\V1\Billing\Resources\BillingResource;
use App\Modules\Api\V1\Billing\Services\BillingStockService;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(private BillingStockService $stockService)
    {
    }

    public function index(Request $request)
    {
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('per_page', 20);

        $billings = Billing::with('branch')
            ->where('organization_id', $orgId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $fieldList = FieldModelManager::make('Billing', 'DetailView', false)->getApiFormFields();

        return $this->paginated(BillingResource::collection($billings)->resource, $fieldList);
    }

    public function show(Request $request, $id)
    {
        try {
            /** @var Billing $billing */
            $billing = RecordObject::make('Billing', $id, [], 'DetailView');
            $billing->load(['branch', 'items.product']);

            $fieldList = FieldModelManager::make('Billing', 'DetailView', false)->getApiFormFields();

            return $this->success([
                'fields' => $fieldList,
                'values' => new BillingResource($billing),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Bill not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function createForm()
    {
        $fields = FieldModelManager::make('Billing', 'CreateView', false)->getApiFormFields();

        return $this->success(['fields' => $fields]);
    }

    public function headerfields()
    {
        $fields = FieldModelManager::make('Billing', 'DetailView', false)->getApiFormFields();

        return $this->success(['fields' => $fields]);
    }

    public function store(StoreBillingRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->input('data.values') ?? [];
                $itemsData = $request->input('data.relatedRecords.items') ?? [];
                $orgId = AuthUser::organizationId();

                try {
                    RecordObject::make('Branch', $data['branchId'], [], 'DetailView');
                } catch (\Exception $e) {
                    return $this->error('The selected branch does not exist or access is denied.');
                }

                $this->assertProductsAccessible($itemsData);
                $this->stockService->deductForSale($orgId, $data['branchId'], $itemsData);

                /** @var Billing $billing */
                $billing = RecordObject::make('Billing', null, $data, 'CreateView');
                $billing->organization_id = $orgId;
                $billing->branch_id = $data['branchId'];
                $billing->bill_number = 'BILL-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                $billing->discount_amount = $data['discountAmount'] ?? $billing->discount_amount ?? 0;
                $billing->tax_amount = $data['taxAmount'] ?? $billing->tax_amount ?? 0;
                $billing->payment_method = $data['paymentMethod'] ?? $billing->payment_method ?? 'cash';
                $billing->payment_status = $data['paymentStatus'] ?? $billing->payment_status ?? 'paid';
                $billing->billing_date = now();
                $billing->save();

                $subTotal = 0;
                foreach ($itemsData as $itemData) {
                    $totalPrice = $itemData['quantity'] * $itemData['unitPrice'];
                    $subTotal += $totalPrice;

                    $item = new BillingItem();
                    $item->billing_id = $billing->id;
                    $item->product_id = $itemData['productId'];
                    $item->quantity = $itemData['quantity'];
                    $item->unit_price = $itemData['unitPrice'];
                    $item->total_price = $totalPrice;
                    $item->unit = $itemData['unit'] ?? null;
                    $item->category = $itemData['category'] ?? null;
                    $item->save();
                }

                $billing->sub_total = $subTotal;
                $billing->grand_total = ($subTotal - (float) $billing->discount_amount) + (float) $billing->tax_amount;
                $billing->save();

                return $this->success(
                    new BillingResource($billing->load(['branch', 'items.product'])),
                    'Bill created successfully'
                );
            });
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to create bill: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function update(UpdateBillingRequest $request, $id)
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $data = $request->input('data.values', []);
                $itemsData = $request->input('data.relatedRecords.items');
                $orgId = AuthUser::organizationId();

                /** @var Billing $billing */
                $billing = RecordObject::make('Billing', $id, [], 'EditView');
                $billing->load('items');

                $oldBranchId = $billing->branch_id;
                $oldItems = $billing->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->all();

                if (!empty($data)) {
                    $billing = RecordObject::make('Billing', $id, $data, 'EditView');
                    $billing->load('items');
                }

                $newBranchId = $data['branchId'] ?? $billing->branch_id;
                if (!empty($data['branchId'])) {
                    try {
                        RecordObject::make('Branch', $data['branchId'], [], 'DetailView');
                    } catch (\Exception $e) {
                        return $this->error('The selected branch does not exist or access is denied.');
                    }
                    $billing->branch_id = $data['branchId'];
                }

                $subTotal = (float) $billing->sub_total;

                if (is_array($itemsData)) {
                    $this->assertProductsAccessible($itemsData);

                    $existingItemIds = $billing->items()->pluck('id')->toArray();
                    $newItemIds = [];
                    $subTotal = 0;

                    foreach ($itemsData as $itemData) {
                        $totalPrice = $itemData['quantity'] * $itemData['unitPrice'];
                        $subTotal += $totalPrice;

                        if (isset($itemData['id']) && in_array($itemData['id'], $existingItemIds, true)) {
                            $item = BillingItem::find($itemData['id']);
                            $item->product_id = $itemData['productId'];
                            $item->quantity = $itemData['quantity'];
                            $item->unit_price = $itemData['unitPrice'];
                            $item->total_price = $totalPrice;
                            if (isset($itemData['unit'])) {
                                $item->unit = $itemData['unit'];
                            }
                            if (isset($itemData['category'])) {
                                $item->category = $itemData['category'];
                            }
                            $item->save();
                            $newItemIds[] = $item->id;
                        } else {
                            $item = new BillingItem();
                            $item->billing_id = $billing->id;
                            $item->product_id = $itemData['productId'];
                            $item->quantity = $itemData['quantity'];
                            $item->unit_price = $itemData['unitPrice'];
                            $item->total_price = $totalPrice;
                            $item->unit = $itemData['unit'] ?? null;
                            $item->category = $itemData['category'] ?? null;
                            $item->save();
                            $newItemIds[] = $item->id;
                        }
                    }

                    $itemsToDelete = array_diff($existingItemIds, $newItemIds);
                    if (!empty($itemsToDelete)) {
                        BillingItem::whereIn('id', $itemsToDelete)->delete();
                    }

                    $this->stockService->reconcileSale(
                        $orgId,
                        $oldBranchId,
                        $newBranchId,
                        $oldItems,
                        $itemsData
                    );

                    $billing->sub_total = $subTotal;
                    $billing->grand_total = ($subTotal - (float) $billing->discount_amount) + (float) $billing->tax_amount;
                } else {
                    $billing->grand_total = ((float) $billing->sub_total - (float) $billing->discount_amount) + (float) $billing->tax_amount;
                }

                if (isset($data['discountAmount'])) {
                    $billing->discount_amount = $data['discountAmount'];
                }
                if (isset($data['taxAmount'])) {
                    $billing->tax_amount = $data['taxAmount'];
                }
                if (isset($data['paymentMethod'])) {
                    $billing->payment_method = $data['paymentMethod'];
                }
                if (isset($data['paymentStatus'])) {
                    $billing->payment_status = $data['paymentStatus'];
                }

                $billing->grand_total = ((float) $billing->sub_total - (float) $billing->discount_amount) + (float) $billing->tax_amount;
                $billing->save();

                return $this->success(
                    new BillingResource($billing->load(['branch', 'items.product'])),
                    'Bill updated successfully'
                );
            });
        } catch (ModelNotFoundException $e) {
            return $this->error('Bill not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to update bill: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function getPosProducts(Request $request)
    {
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('per_page', 20);
        $category = strtolower((string) $request->query('category', 'all'));
        $branchId = $request->header('X-Branch-Id') ?: $request->query('branch_id');

        $query = Product::where('organization_id', $orgId)
            ->select('id', 'name', 'price', 'unit', 'category')
            ->orderBy('name');

        // "all" / "All" means no category filter
        if ($category && $category !== 'all') {
            $query->whereRaw('LOWER(category) = ?', [$category]);
        }

        $paginator = $query->paginate($perPage);
        $productIds = collect($paginator->items())->pluck('id')->all();

        $stockByProduct = [];
        if ($branchId && !empty($productIds)) {
            $stockByProduct = BranchStock::where('organization_id', $orgId)
                ->where('branch_id', $branchId)
                ->whereIn('product_id', $productIds)
                ->pluck('current_stock', 'product_id')
                ->map(fn ($stock) => (float) $stock)
                ->all();
        }

        $formatted = collect($paginator->items())->map(function ($item) use ($stockByProduct) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
                'unit' => $item->unit,
                'category' => $item->category,
                'currentStock' => (float) ($stockByProduct[$item->id] ?? 0),
            ];
        });

        $fields = ModuleFieldConfig::getApiFieldsForView('products', 'DetailView');

        $data = [
            'fields' => $fields,
            'list' => $formatted,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        return $this->success($data, 'POS Products retrieved successfully');
    }

    public function getPosCategories(Request $request)
    {
        $categories = [
            ['value' => 'all', 'label' => 'All'],
            ['value' => 'bread', 'label' => 'Bread'],
            ['value' => 'sweet', 'label' => 'Sweet'],
            ['value' => 'cake', 'label' => 'Cake'],
            ['value' => 'snack', 'label' => 'Snack'],
            ['value' => 'beverage', 'label' => 'Beverage'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        return $this->success($categories, 'POS Categories retrieved successfully');
    }

    /**
     * Validate each unique product once via RecordObject (bounded cart size).
     */
    private function assertProductsAccessible(array $itemsData): void
    {
        $productIds = collect($itemsData)->pluck('productId')->filter()->unique()->values();
        foreach ($productIds as $productId) {
            try {
                RecordObject::make('Product', $productId, [], 'DetailView');
            } catch (\Exception $e) {
                throw new \RuntimeException('The selected product does not exist or access is denied.');
            }
        }
    }
}
