<?php

namespace App\Modules\Api\V1\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FieldModelManager;
use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\Billing\Requests\StoreBillingRequest;
use App\Modules\Api\V1\Billing\Requests\UpdateBillingRequest;
use App\Modules\Api\V1\Billing\Resources\BillingResource;
use App\Modules\Api\V1\Billing\Services\BillingPriceService;
use App\Modules\Api\V1\Billing\Services\BillingStockService;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Services\AuthUser;
use App\Services\BranchAccess;
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
        $user = AuthUser::user();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('Billing', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $perPage = \App\Support\ApiPagination::perPage($request);

        $query = Billing::with('branch')
            ->where('organization_id', $orgId);

        if ($user && ! $user->isFullAdmin()) {
            if (! $user->branch_id) {
                return $this->error('No branch assigned to this user.', null, null, null, 403);
            }
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->query('branchId')) {
            $branchId = (string) $request->query('branchId');
            try {
                BranchAccess::assertCanAccessBranch($user, $branchId);
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage(), null, null, null, 403);
            }
            $query->where('branch_id', $branchId);
        }

        $billings = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $fieldList = FieldModelManager::make('Billing', 'DetailView', false)->getApiFormFields();

        return $this->paginated(BillingResource::collection($billings)->resource, $fieldList);
    }

    public function show(Request $request, $id)
    {
        try {
            /** @var Billing $billing */
            $billing = RecordObject::make('Billing', $id, [], 'DetailView');
            BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $billing->branch_id);
            $billing->load(['branch', 'items.product']);

            $fieldList = FieldModelManager::make('Billing', 'DetailView', false)->getApiFormFields();

            return $this->success([
                'fields' => $fieldList,
                'values' => new BillingResource($billing),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Bill not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
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

    /**
     * Pending / held POS bills for the active branch (draft drawer).
     */
    public function drafts(Request $request)
    {
        $user = AuthUser::user();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('Billing', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = AuthUser::organizationId();
        $branchId = $request->header('X-Branch-Id') ?: $request->query('branch_id') ?: $request->query('branchId');

        if ($user && ! $user->isFullAdmin()) {
            if (! $user->branch_id) {
                return $this->error('No branch assigned to this user.', null, null, null, 403);
            }
            $branchId = $user->branch_id;
        }

        if (! $branchId) {
            return $this->error('Select a branch to load draft bills.', null, null, null, 422);
        }

        try {
            BranchAccess::assertCanAccessBranch($user, (string) $branchId);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }

        $bills = Billing::with(['items.product'])
            ->where('organization_id', $orgId)
            ->where('branch_id', $branchId)
            ->whereRaw('LOWER(payment_status) = ?', ['pending'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $list = $bills->map(function (Billing $billing) {
            return [
                'id' => $billing->id,
                'bill_number' => $billing->bill_number,
                'billNumber' => $billing->bill_number,
                'customer_name' => $billing->customer_name,
                'customerName' => $billing->customer_name,
                'customer_phone' => $billing->customer_phone,
                'customerPhone' => $billing->customer_phone,
                'subtotal' => (float) $billing->sub_total,
                'tax' => (float) $billing->tax_amount,
                'total' => (float) $billing->grand_total,
                'status' => 'pending',
                'paymentStatus' => 'pending',
                'branch_id' => $billing->branch_id,
                'branchId' => $billing->branch_id,
                'created_at' => optional($billing->created_at)?->toIso8601String(),
                'updated_at' => optional($billing->updated_at)?->toIso8601String(),
                'items' => $billing->items->map(function (BillingItem $item) {
                    return [
                        'productId' => $item->product_id,
                        'productName' => $item->product?->name,
                        'price' => (float) $item->unit_price,
                        'quantity' => (float) $item->quantity,
                        'tax_rate' => 0,
                        'total' => (float) $item->total_price,
                        'unit' => $item->unit,
                        'category' => $item->category,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return $this->success([
            'list' => $list,
            'meta' => [
                'total' => count($list),
            ],
        ], 'Draft bills retrieved successfully');
    }

    public function store(StoreBillingRequest $request)
    {
        try {
            $idempotencyKey = $request->header('Idempotency-Key');
            if ($idempotencyKey) {
                $cacheKey = 'idempotency:billing:' . AuthUser::id() . ':' . hash('sha256', $idempotencyKey);
                $cached = cache()->get($cacheKey);
                if (is_array($cached)) {
                    return response()->json($cached['body'], $cached['status']);
                }
            }

            $response = DB::transaction(function () use ($request) {
                $data = $request->input('data.values') ?? [];
                $itemsData = $request->input('data.relatedRecords.items') ?? [];
                $orgId = AuthUser::organizationId();
                $paymentStatus = strtolower((string) ($data['paymentStatus'] ?? 'paid'));
                $paymentMethod = strtolower((string) ($data['paymentMethod'] ?? 'cash'));
                $paymentMethodDb = match ($paymentMethod) {
                    'card' => 'Card',
                    'upi' => 'UPI',
                    default => 'Cash',
                };
                $paymentStatusDb = match ($paymentStatus) {
                    'pending' => 'Pending',
                    'cancelled' => 'Cancelled',
                    default => 'Paid',
                };

                $branch = \App\Modules\Api\V1\Branch\Models\Branch::where('organization_id', $orgId)
                    ->where('id', $data['branchId'])
                    ->first();
                if (!$branch) {
                    return $this->error('The selected branch does not exist or access is denied.', null, null, null, 403);
                }

                BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $data['branchId']);
                $pricedItems = $this->resolveCatalogPrices($orgId, $itemsData);

                // Deduct stock only for completed (paid) sales — pending/hold must not touch stock
                if ($paymentStatus === 'paid') {
                    $this->stockService->deductForSale($orgId, $data['branchId'], $pricedItems);
                }

                /** @var Billing $billing */
                $billing = RecordObject::make('Billing', null, $data, 'CreateView');
                $billing->organization_id = $orgId;
                $billing->branch_id = $data['branchId'];
                $billing->bill_number = 'BILL-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                $billing->payment_method = $paymentMethodDb;
                $billing->payment_status = $paymentStatusDb;
                $billing->billing_date = now();
                $billing->save();

                $subTotal = 0;
                foreach ($pricedItems as $itemData) {
                    $totalPrice = (float) $itemData['totalPrice'];
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

                $discount = max(0, (float) ($data['discountAmount'] ?? 0));
                $tax = max(0, (float) ($data['taxAmount'] ?? 0));
                if ($discount > $subTotal) {
                    throw new \RuntimeException('Discount cannot exceed subtotal.');
                }
                // Tax hard cap: 100% of (subtotal - discount) to block absurd client values
                $taxable = $subTotal - $discount;
                if ($tax > $taxable) {
                    throw new \RuntimeException('Tax amount is unreasonably high.');
                }

                $billing->sub_total = $subTotal;
                $billing->discount_amount = $discount;
                $billing->tax_amount = $tax;
                $billing->grand_total = ($subTotal - $discount) + $tax;
                $billing->save();

                return $this->success(
                    new BillingResource($billing->load(['branch', 'items.product'])),
                    'Bill created successfully'
                );
            });

            if ($idempotencyKey && method_exists($response, 'getStatusCode') && $response->getStatusCode() < 400) {
                $cacheKey = 'idempotency:billing:' . AuthUser::id() . ':' . hash('sha256', $idempotencyKey);
                cache()->put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getData(true),
                ], now()->addHours(24));
            }

            return $response;
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to create bill.', null, null, null, 500);
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
                BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $billing->branch_id);

                $oldStatus = strtolower((string) $billing->payment_status);
                $oldBranchId = $billing->branch_id;
                $oldItems = $billing->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->all();

                if ($oldStatus === 'cancelled') {
                    return $this->error('Cancelled bills cannot be edited.', null, null, null, 400);
                }

                $newStatus = isset($data['paymentStatus'])
                    ? strtolower((string) $data['paymentStatus'])
                    : $oldStatus;

                // Cancel paid bill → restore stock
                if ($newStatus === 'cancelled' && $oldStatus === 'paid') {
                    $restoreItems = $billing->items->map(fn ($item) => [
                        'productId' => $item->product_id,
                        'quantity' => $item->quantity,
                    ])->all();
                    $this->stockService->restoreForSale($orgId, $billing->branch_id, $restoreItems);
                    $billing->payment_status = 'Cancelled';
                    $billing->save();
                    return $this->success(
                        new BillingResource($billing->load(['branch', 'items.product'])),
                        'Bill cancelled and stock restored'
                    );
                }

                // Paid → pending (re-hold): restore stock so a later pending→paid does not double-deduct
                if ($oldStatus === 'paid' && $newStatus === 'pending') {
                    $restoreItems = $billing->items->map(fn ($item) => [
                        'productId' => $item->product_id,
                        'quantity' => $item->quantity,
                    ])->all();
                    $this->stockService->restoreForSale($orgId, $billing->branch_id, $restoreItems);
                }

                // Pending → paid: deduct stock now
                if ($oldStatus === 'pending' && $newStatus === 'paid') {
                    $itemsForDeduct = is_array($itemsData)
                        ? $this->resolveCatalogPrices($orgId, $itemsData)
                        : $billing->items->map(fn ($item) => [
                            'productId' => $item->product_id,
                            'quantity' => $item->quantity,
                        ])->all();
                    $this->stockService->deductForSale($orgId, $data['branchId'] ?? $billing->branch_id, $itemsForDeduct);
                }

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
                    BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $data['branchId']);
                    $billing->branch_id = $data['branchId'];
                }

                $subTotal = (float) $billing->sub_total;

                if (is_array($itemsData)) {
                    $pricedItems = $this->resolveCatalogPrices($orgId, $itemsData);
                    $itemsData = $pricedItems;

                    $existingItemIds = $billing->items()->pluck('id')->toArray();
                    $newItemIds = [];
                    $subTotal = 0;

                    foreach ($itemsData as $itemData) {
                        $totalPrice = (float) $itemData['totalPrice'];
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

                    // Only reconcile stock when the bill is (or remains) paid
                    if ($newStatus === 'paid' && $oldStatus === 'paid') {
                        $this->stockService->reconcileSale(
                            $orgId,
                            $oldBranchId,
                            $newBranchId,
                            $oldItems,
                            $itemsData
                        );
                    }

                    $billing->sub_total = $subTotal;
                }

                if (isset($data['discountAmount'])) {
                    $billing->discount_amount = max(0, (float) $data['discountAmount']);
                }
                if (isset($data['taxAmount'])) {
                    $billing->tax_amount = max(0, (float) $data['taxAmount']);
                }
                if (isset($data['paymentMethod'])) {
                    $method = strtolower((string) $data['paymentMethod']);
                    $billing->payment_method = match ($method) {
                        'card' => 'Card',
                        'upi' => 'UPI',
                        default => 'Cash',
                    };
                }
                if (isset($data['paymentStatus'])) {
                    $billing->payment_status = match ($newStatus) {
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                        default => 'Paid',
                    };
                }

                $subTotal = (float) $billing->sub_total;
                $discount = (float) $billing->discount_amount;
                $tax = (float) $billing->tax_amount;
                if ($discount > $subTotal) {
                    throw new \RuntimeException('Discount cannot exceed subtotal.');
                }
                if ($tax > max(0, $subTotal - $discount)) {
                    throw new \RuntimeException('Tax amount is unreasonably high.');
                }

                $billing->grand_total = ($subTotal - $discount) + $tax;
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
            return $this->error('Failed to update bill.', null, null, null, 500);
        }
    }

    public function getPosProducts(Request $request)
    {
        $user = AuthUser::user();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('Billing', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = AuthUser::organizationId();
        $perPage = \App\Support\ApiPagination::perPage($request);
        $category = strtolower((string) $request->query('category', 'all'));
        $branchId = $request->header('X-Branch-Id') ?: $request->query('branch_id');

        if ($user && ! $user->isFullAdmin() && $user->branch_id) {
            $branchId = $user->branch_id;
        }

        if ($branchId) {
            try {
                BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $branchId);
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage(), null, null, null, 403);
            }
        }

        $query = Product::where('organization_id', $orgId)
            ->whereRaw('LOWER(COALESCE(status, ?)) = ?', ['active', 'active'])
            ->select('id', 'name', 'price', 'unit', 'category', 'status', 'product_number')
            ->orderBy('name');

        if ($category && $category !== 'all') {
            $query->whereRaw('LOWER(category) = ?', [$category]);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $normalizedNumber = \App\Modules\Api\V1\Product\Services\ProductNumberService::normalize($search);
            $query->where(function ($q) use ($like, $search, $normalizedNumber) {
                $q->where('name', 'like', $like);
                if ($normalizedNumber !== null && preg_match('/^\d+$/', trim($search))) {
                    $q->orWhere('product_number', $normalizedNumber)
                        ->orWhere('product_number', 'like', $like);
                } else {
                    $q->orWhere('product_number', 'like', $like);
                }
            });
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
                'productNumber' => $item->product_number,
                'price' => (float) $item->price,
                'unit' => $item->unit,
                'category' => $item->category,
                'status' => strtolower((string) ($item->status ?? 'active')) === 'inactive' ? 'inactive' : 'active',
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
     * Enforce org product access and overwrite client unit prices with catalog prices.
     *
     * @param  array<int, array<string, mixed>>  $itemsData
     * @return array<int, array<string, mixed>>
     */
    private function resolveCatalogPrices(string $orgId, array $itemsData): array
    {
        $resolved = [];
        foreach ($itemsData as $itemData) {
            $productId = (string) ($itemData['productId'] ?? '');
            if ($productId === '') {
                throw new \RuntimeException('Product is required for each bill line.');
            }

            try {
                RecordObject::make('Product', $productId, [], 'DetailView');
            } catch (\Exception $e) {
                throw new \RuntimeException('The selected product does not exist or access is denied.');
            }

            $product = Product::where('organization_id', $orgId)->where('id', $productId)->first();
            if (! $product) {
                throw new \RuntimeException('The selected product does not exist or access is denied.');
            }

            if (! $product->isSellable()) {
                throw new \RuntimeException("Product \"{$product->name}\" is inactive and cannot be sold.");
            }

            $qty = (float) ($itemData['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new \RuntimeException('Quantity must be greater than zero.');
            }

            $unit = $itemData['unit'] ?? $product->unit;
            $catalogPrice = (float) $product->price;
            $totalPrice = BillingPriceService::lineTotal($qty, $catalogPrice, $unit);

            $resolved[] = [
                'id' => $itemData['id'] ?? null,
                'productId' => $productId,
                'quantity' => $qty,
                'unitPrice' => $catalogPrice,
                'totalPrice' => $totalPrice,
                'unit' => $unit,
                'category' => $itemData['category'] ?? $product->category,
            ];
        }

        return $resolved;
    }
}
