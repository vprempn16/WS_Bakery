<?php

namespace App\Modules\Api\V1\Billing\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\Billing\Requests\StoreBillingRequest;
use App\Modules\Api\V1\Billing\Resources\BillingResource;
use Illuminate\Support\Str;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        
        $billings = Billing::with('branch')
            ->where('organization_id', $request->user()->organization_id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $fieldList = ModuleFieldConfig::getMappedFields('Billing');

        return $this->paginated(BillingResource::collection($billings)->resource, $fieldList);
    }

    public function show(Request $request, $id)
    {
        $billing = Billing::with(['branch', 'items.product'])
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $fieldList = ModuleFieldConfig::getMappedFields('Billing');

        return $this->success([
            'fields' => $fieldList,
            'values' => new BillingResource($billing)
        ]);
    }

    public function createForm()
    {
        $fields = ModuleFieldConfig::getMappedFields('Billing');
        return $this->success(['fields' => $fields]);
    }

    public function headerfields()
    {
        $fields = ModuleFieldConfig::getMappedFields('Billing');
        return $this->success(['fields' => $fields]);
    }

    public function store(StoreBillingRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->input('data.values');
            $itemsData = $request->input('data.relatedRecords.items');

            $orgId = $request->user()->organization_id;

            // Generate unique bill number (e.g. BILL-YYYYMMDD-XXXX)
            $billNumber = 'BILL-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $billing = new Billing();
            $billing->organization_id = $orgId;
            $billing->branch_id = $data['branchId'];
            $billing->bill_number = $billNumber;
            // $billing->customer_name = $data['customerName'] ?? null;
            // $billing->customer_phone = $data['customerPhone'] ?? null;
            // $billing->customer_email = $data['customerEmail'] ?? null;
            $billing->discount_amount = $data['discountAmount'] ?? 0;
            $billing->tax_amount = $data['taxAmount'] ?? 0;
            $billing->payment_method = $data['paymentMethod'] ?? 'cash';
            $billing->payment_status = $data['paymentStatus'] ?? 'paid';
            $billing->billing_date = now();

            $subTotal = 0;

            $billing->save();

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
            $billing->grand_total = ($subTotal - $billing->discount_amount) + $billing->tax_amount;
            $billing->save();

            DB::commit();

            return $this->success(new BillingResource($billing->load(['branch', 'items.product'])), 'Bill created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create bill: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function update(\App\Modules\Api\V1\Billing\Requests\UpdateBillingRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $billing = Billing::where('organization_id', $request->user()->organization_id)->findOrFail($id);
            $data = $request->input('data.values', []);
            $itemsData = $request->input('data.relatedRecords.items');

            if (isset($data['branchId'])) $billing->branch_id = $data['branchId'];
            // if (isset($data['customerName'])) $billing->customer_name = $data['customerName'];
            // if (isset($data['customerPhone'])) $billing->customer_phone = $data['customerPhone'];
            // if (isset($data['customerEmail'])) $billing->customer_email = $data['customerEmail'];
            if (isset($data['discountAmount'])) $billing->discount_amount = $data['discountAmount'];
            if (isset($data['taxAmount'])) $billing->tax_amount = $data['taxAmount'];
            if (isset($data['paymentMethod'])) $billing->payment_method = $data['paymentMethod'];
            if (isset($data['paymentStatus'])) $billing->payment_status = $data['paymentStatus'];

            $subTotal = 0;

            if (is_array($itemsData)) {
                // Get current item IDs to detect deletions
                $existingItemIds = $billing->items()->pluck('id')->toArray();
                $newItemIds = [];

                foreach ($itemsData as $itemData) {
                    $totalPrice = $itemData['quantity'] * $itemData['unitPrice'];
                    $subTotal += $totalPrice;

                    if (isset($itemData['id']) && in_array($itemData['id'], $existingItemIds)) {
                        $item = BillingItem::find($itemData['id']);
                        $item->product_id = $itemData['productId'];
                        $item->quantity = $itemData['quantity'];
                        $item->unit_price = $itemData['unitPrice'];
                        $item->total_price = $totalPrice;
                        if (isset($itemData['unit'])) $item->unit = $itemData['unit'];
                        if (isset($itemData['category'])) $item->category = $itemData['category'];
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

                // Delete items that were removed
                $itemsToDelete = array_diff($existingItemIds, $newItemIds);
                if (!empty($itemsToDelete)) {
                    BillingItem::whereIn('id', $itemsToDelete)->delete();
                }
                
                $billing->sub_total = $subTotal;
                $billing->grand_total = ($subTotal - $billing->discount_amount) + $billing->tax_amount;
            } else {
                // If items weren't updated in the payload, recalculate grand_total just in case tax/discount changed
                $billing->grand_total = ($billing->sub_total - $billing->discount_amount) + $billing->tax_amount;
            }

            $billing->save();

            DB::commit();

            return $this->success(new BillingResource($billing->load(['branch', 'items.product'])), 'Bill updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update bill: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function getPosProducts(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $perPage = $request->query('per_page', 20);
        $category = $request->query('category', 'all');

        $query = \App\Modules\Api\V1\Product\Models\Product::where('organization_id', $orgId)
                        ->select('id', 'name', 'price', 'unit', 'category')
                        ->orderBy('name');

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        $paginator = $query->paginate($perPage);

        $formatted = collect($paginator->items())->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
                'unit' => $item->unit,
                'category' => $item->category,
            ];
        });

        $fields = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('products');

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
            ['value' => 'other', 'label' => 'Other']
        ];
        return $this->success($categories, 'POS Categories retrieved successfully');
    }
}
