<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalInlineEditController extends Controller
{
    private array $moduleToModelMap = [
        'User' => \App\Modules\Api\V1\User\Models\User::class,
        'Vendor' => \App\Modules\Api\V1\Vendor\Models\Vendor::class,
        'Ingredient' => \App\Modules\Api\V1\Ingredient\Models\Ingredient::class,
        'MaterialIssue' => \App\Modules\Api\V1\MaterialIssue\Models\MaterialIssue::class,
        'InventoryTransaction' => \App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction::class,
        'Product' => \App\Modules\Api\V1\Product\Models\Product::class,
        'Recipe' => \App\Modules\Api\V1\Recipe\Models\Recipe::class,
        'Branch' => \App\Modules\Api\V1\Branch\Models\Branch::class,
        'ProductionBatch' => \App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch::class,
        'ProductionPlan' => \App\Modules\Api\V1\ProductionPlan\Models\ProductionPlan::class,
        'BranchStock' => \App\Modules\Api\V1\BranchTransfer\Models\BranchStock::class,
        'BranchTransfer' => \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer::class,
        'BranchDailyReport' => \App\Modules\Api\V1\BranchSales\Models\BranchDailyReport::class,
        'Billing' => \App\Modules\Api\V1\Billing\Models\Billing::class,
    ];

    /** Ledger / stock / security / financial fields must never be mutated via inline edit. */
    private array $blockedColumns = [
        'current_stock',
        'quantity',
        'quantity_produced',
        'quantity_required',
        'quantity_sold',
        'quantity_returned',
        'sub_total',
        'grand_total',
        'discount_amount',
        'tax_amount',
        'unit_price',
        'total_price',
        'payment_status',
        'payment_method',
        'organization_id',
        'branch_id',
        'password',
        'remember_token',
        'role',
        'is_active',
        'is_admin',
        'email',
        'price',
        'bill_number',
        'transfer_number',
        'batch_number',
        'status',
    ];

    public function update(Request $request, string $module, string $id)
    {
        $request->validate([
            'field' => 'required|string',
        ]);

        $field = $request->input('field');
        $value = $request->input('value');
        $orgId = $request->user()->organization_id;

        $resolvedModule = ucfirst($module);
        if (! isset($this->moduleToModelMap[$resolvedModule])) {
            if (isset($this->moduleToModelMap[$module])) {
                $resolvedModule = $module;
            } else {
                return $this->error("Invalid module '{$module}'.", null, null, null, 400);
            }
        }

        $modelClass = $this->moduleToModelMap[$resolvedModule];
        $column = Str::snake($field);

        if (in_array($column, $this->blockedColumns, true)) {
            return $this->error(
                "Field '{$field}' cannot be updated inline. Use the proper workflow.",
                null,
                null,
                null,
                403
            );
        }

        $user = $request->user();
        $permissionService = new PermissionService($user);
        if (! $permissionService->hasPermission($resolvedModule, 'edit')) {
            return $this->error("You don't have permission to edit {$resolvedModule}.", null, null, null, 403);
        }

        // Settings-sensitive modules: admins only via settings island, not inline
        if (in_array($resolvedModule, ['User'], true) && (! method_exists($user, 'isFullAdmin') || ! $user->isFullAdmin())) {
            return $this->error("You don't have permission to edit {$resolvedModule}.", null, null, null, 403);
        }

        try {
            $record = $modelClass::where('organization_id', $orgId)->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error("Record not found in module '{$resolvedModule}'.", null, null, null, 404);
        }

        if (! $record->isFillable($column) && ! empty($record->getGuarded()) && $record->isGuarded($column)) {
            return $this->error("Field '{$field}' is not allowed to be updated inline.", null, null, null, 403);
        }

        if (in_array($column, ['id', 'organization_id', 'created_at', 'updated_at', 'created_by', 'deleted'], true)) {
            return $this->error("Field '{$field}' is not allowed to be updated inline.", null, null, null, 403);
        }

        try {
            $record->$column = $value;
            $record->save();

            return $this->success([
                'id' => $record->id,
                'field' => $field,
                'value' => $record->$column,
            ], "Successfully updated '{$field}'.");
        } catch (\Exception $e) {
            return $this->error('Failed to update record.', null, null, null, 500);
        }
    }
}
