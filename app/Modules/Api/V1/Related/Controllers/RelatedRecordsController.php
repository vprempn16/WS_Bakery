<?php

namespace App\Modules\Api\V1\Related\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Services\AuthUser;
use App\Services\BranchAccess;
use App\Services\CRM\RecordObject;
use Illuminate\Http\Request;

/**
 * Related lists for detail tabs (histories / ingredients used / etc.).
 */
class RelatedRecordsController extends Controller
{
    public function productProductionHistory(Request $request, string $id)
    {
        $this->assertProduct($id);
        $orgId = AuthUser::organizationId();
        $rows = ProductionBatch::with('product')
            ->where('organization_id', $orgId)
            ->where('product_id', $id)
            ->orderByDesc('production_date')
            ->limit(100)
            ->get()
            ->map(fn (ProductionBatch $b) => [
                'id' => $b->id,
                'batchNumber' => $b->batch_number,
                'quantityProduced' => (float) $b->quantity_produced,
                'unit' => $b->product?->unit,
                'productionDate' => optional($b->production_date)?->format('Y-m-d'),
                'expiryDate' => optional($b->expiry_timestamp)?->format('Y-m-d'),
                'expiryTime' => optional($b->expiry_timestamp)?->format('H:i'),
                'status' => $b->status,
            ]);

        return $this->success(['list' => $rows]);
    }

    public function productSalesHistory(Request $request, string $id)
    {
        $this->assertProduct($id);
        $orgId = AuthUser::organizationId();
        $rows = BillingItem::query()
            ->join('billings', 'billings.id', '=', 'billing_items.billing_id')
            ->where('billings.organization_id', $orgId)
            ->where('billing_items.product_id', $id)
            ->whereRaw('LOWER(billings.payment_status) = ?', ['paid'])
            ->orderByDesc('billings.billing_date')
            ->limit(100)
            ->get([
                'billing_items.id',
                'billings.id as billing_id',
                'billings.bill_number',
                'billings.billing_date',
                'billing_items.quantity',
                'billing_items.unit_price',
                'billing_items.total_price',
                'billing_items.unit',
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'billingId' => $r->billing_id,
                'billNumber' => $r->bill_number,
                'billingDate' => $r->billing_date,
                'quantity' => (float) $r->quantity,
                'unitPrice' => (float) $r->unit_price,
                'totalPrice' => (float) $r->total_price,
                'unit' => $r->unit,
            ]);

        return $this->success(['list' => $rows]);
    }

    public function ingredientStockHistory(Request $request, string $id)
    {
        /** @var Ingredient $ingredient */
        $ingredient = $this->assertIngredient($id);
        $orgId = AuthUser::organizationId();
        $rows = InventoryTransaction::where('organization_id', $orgId)
            ->where('ingredient_id', $id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (InventoryTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'quantity' => (float) $t->quantity,
                'unit' => $ingredient->unit,
                'referenceNote' => $t->reference_note,
                'createdAt' => optional($t->created_at)?->format('Y-m-d H:i:s'),
            ]);

        return $this->success(['list' => $rows]);
    }

    public function ingredientVendors(Request $request, string $id)
    {
        /** @var Ingredient $ingredient */
        $ingredient = $this->assertIngredient($id);
        $list = [];
        if ($ingredient->vendor_id) {
            $vendor = Vendor::where('organization_id', AuthUser::organizationId())
                ->where('id', $ingredient->vendor_id)
                ->first();
            if ($vendor) {
                $list[] = [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'contactPerson' => $vendor->contact_person,
                    'phone' => $vendor->phone,
                    'email' => $vendor->email,
                ];
            }
        }

        return $this->success(['list' => $list]);
    }

    public function ingredientUsageInProducts(Request $request, string $id)
    {
        /** @var Ingredient $ingredient */
        $ingredient = $this->assertIngredient($id);
        $orgId = AuthUser::organizationId();
        $rows = Recipe::query()
            ->where('ingredient_id', $id)
            ->whereHas('product', fn ($q) => $q->where('organization_id', $orgId))
            ->with('product')
            ->limit(100)
            ->get()
            ->map(fn (Recipe $r) => [
                'id' => $r->id,
                'productId' => $r->product_id,
                'productName' => $r->product?->name,
                'productNumber' => $r->product?->product_number,
                'quantityRequired' => (float) $r->quantity_required,
                'unit' => $ingredient->unit,
            ]);

        return $this->success(['list' => $rows]);
    }

    public function vendorIngredients(Request $request, string $id)
    {
        $this->assertVendor($id);
        $rows = Ingredient::where('organization_id', AuthUser::organizationId())
            ->where('vendor_id', $id)
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'currentStock' => (float) $i->current_stock,
                'minimumStockLevel' => (float) $i->minimum_stock_level,
            ]);

        return $this->success(['list' => $rows]);
    }

    public function vendorPurchaseHistory(Request $request, string $id)
    {
        $this->assertVendor($id);
        $orgId = AuthUser::organizationId();
        $ingredientIds = Ingredient::where('organization_id', $orgId)->where('vendor_id', $id)->pluck('id');
        $rows = InventoryTransaction::with('ingredient')
            ->where('organization_id', $orgId)
            ->whereIn('ingredient_id', $ingredientIds)
            ->whereRaw('LOWER(type) = ?', ['in'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (InventoryTransaction $t) => [
                'id' => $t->id,
                'ingredientId' => $t->ingredient_id,
                'ingredientName' => $t->ingredient?->name,
                'quantity' => (float) $t->quantity,
                'unit' => $t->ingredient?->unit,
                'referenceNote' => $t->reference_note,
                'createdAt' => optional($t->created_at)?->format('Y-m-d H:i:s'),
            ]);

        return $this->success(['list' => $rows]);
    }

    public function vendorContact(Request $request, string $id)
    {
        /** @var Vendor $vendor */
        $vendor = $this->assertVendor($id);

        return $this->success([
            'values' => [
                'name' => $vendor->name,
                'contactPerson' => $vendor->contact_person,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
                'address' => $vendor->address,
            ],
        ]);
    }

    public function branchTransferHistory(Request $request, string $id)
    {
        try {
            $this->assertBranch($id);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
        $rows = BranchTransfer::with('creator')
            ->where('organization_id', AuthUser::organizationId())
            ->where('branch_id', $id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function (BranchTransfer $t) {
                $by = $t->creator
                    ? trim(($t->creator->first_name ?? '') . ' ' . ($t->creator->last_name ?? ''))
                    : null;

                return [
                    'id' => $t->id,
                    'transferNumber' => $t->transfer_number,
                    'transferDate' => optional($t->transfer_date)?->format('Y-m-d'),
                    'status' => $t->status,
                    'createdBy' => $by,
                    'createdAt' => optional($t->created_at)?->format('Y-m-d H:i:s'),
                ];
            });

        return $this->success(['list' => $rows]);
    }

    public function branchInventory(Request $request, string $id)
    {
        try {
            $this->assertBranch($id);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
        $rows = BranchStock::with('product')
            ->where('organization_id', AuthUser::organizationId())
            ->where('branch_id', $id)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (BranchStock $s) => [
                'id' => $s->id,
                'productId' => $s->product_id,
                'productName' => $s->product?->name,
                'productNumber' => $s->product?->product_number,
                'unit' => $s->product?->unit,
                'currentStock' => (float) $s->current_stock,
            ]);

        return $this->success(['list' => $rows]);
    }

    public function productionIngredientsUsed(Request $request, string $id)
    {
        /** @var ProductionBatch $batch */
        $batch = RecordObject::make('ProductionBatch', $id, [], 'DetailView');
        $batch->load('product.recipes.ingredient');
        $qty = (float) $batch->quantity_produced;
        $rows = ($batch->product?->recipes ?? collect())->map(function ($recipe) use ($qty) {
            $needed = (float) $recipe->quantity_required * $qty;

            return [
                'id' => $recipe->id,
                'ingredientId' => $recipe->ingredient_id,
                'ingredientName' => $recipe->ingredient?->name,
                'unit' => $recipe->ingredient?->unit,
                'quantityPerUnit' => (float) $recipe->quantity_required,
                'quantityUsed' => $needed,
            ];
        })->values();

        return $this->success([
            'list' => $rows,
            'meta' => [
                'quantityProduced' => $qty,
                'productUnit' => $batch->product?->unit,
            ],
        ]);
    }

    public function productionQualitySummary(Request $request, string $id)
    {
        /** @var ProductionBatch $batch */
        $batch = RecordObject::make('ProductionBatch', $id, [], 'DetailView');
        $batch->load('product');
        $expiry = $batch->expiry_timestamp;

        return $this->success([
            'values' => [
                'batchNumber' => $batch->batch_number,
                'productName' => $batch->product?->name,
                'quantityProduced' => (float) $batch->quantity_produced,
                'pieces' => $batch->pieces !== null ? (int) $batch->pieces : null,
                'productUnit' => $batch->product?->unit,
                'productionDate' => optional($batch->production_date)?->format('Y-m-d'),
                'expiryDate' => $expiry ? $expiry->format('Y-m-d') : null,
                'expiryTime' => $expiry ? $expiry->format('H:i') : null,
                'status' => $batch->status,
                'notes' => $batch->notes,
            ],
        ]);
    }

    public function productionDispatch(Request $request, string $id)
    {
        /** @var ProductionBatch $batch */
        $batch = RecordObject::make('ProductionBatch', $id, [], 'DetailView');
        $orgId = AuthUser::organizationId();
        $prodDate = optional($batch->production_date)?->format('Y-m-d') ?? '1970-01-01';

        $rows = BranchTransfer::with(['branch', 'items'])
            ->where('organization_id', $orgId)
            ->whereDate('transfer_date', '>=', $prodDate)
            ->whereHas('items', fn ($q) => $q->where('product_id', $batch->product_id))
            ->orderByDesc('transfer_date')
            ->limit(50)
            ->get()
            ->map(function (BranchTransfer $t) use ($batch) {
                $qty = $t->items->where('product_id', $batch->product_id)->sum('quantity');

                return [
                    'id' => $t->id,
                    'transferNumber' => $t->transfer_number,
                    'branchName' => $t->branch?->name,
                    'transferDate' => optional($t->transfer_date)?->format('Y-m-d'),
                    'quantity' => (float) $qty,
                    'unit' => $batch->product?->unit,
                    'status' => $t->status,
                ];
            });

        return $this->success(['list' => $rows]);
    }

    private function assertProduct(string $id): Product
    {
        return RecordObject::make('Product', $id, [], 'DetailView');
    }

    private function assertIngredient(string $id): Ingredient
    {
        return RecordObject::make('Ingredient', $id, [], 'DetailView');
    }

    private function assertVendor(string $id): Vendor
    {
        return RecordObject::make('Vendor', $id, [], 'DetailView');
    }

    private function assertBranch(string $id)
    {
        $branch = RecordObject::make('Branch', $id, [], 'DetailView');
        BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $id);

        return $branch;
    }
}
