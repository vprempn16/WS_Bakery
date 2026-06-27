<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalInlineEditController extends Controller
{
    private array $moduleToModelMap = [
        'User' => \App\Modules\Api\V1\User\Models\User::class,
        'Vendor' => \App\Modules\Api\V1\Vendor\Models\Vendor::class,
        'Ingredient' => \App\Modules\Api\V1\Ingredient\Models\Ingredient::class,
        'InventoryTransaction' => \App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction::class,
        'Product' => \App\Modules\Api\V1\Product\Models\Product::class,
        'Recipe' => \App\Modules\Api\V1\Recipe\Models\Recipe::class,
        'Branch' => \App\Modules\Api\V1\Branch\Models\Branch::class,
        'ProductionBatch' => \App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch::class,
        'BranchStock' => \App\Modules\Api\V1\BranchTransfer\Models\BranchStock::class,
        'BranchTransfer' => \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer::class,
        'BranchDailyReport' => \App\Modules\Api\V1\BranchSales\Models\BranchDailyReport::class,
        'Billing' => \App\Modules\Api\V1\Billing\Models\Billing::class,
    ];

    public function update(Request $request, string $module, string $id)
    {
        $request->validate([
            'field' => 'required|string',
            // value can be anything (string, int, null, etc.) depending on the field
        ]);

        $field = $request->input('field');
        $value = $request->input('value');
        $orgId = $request->user()->organization_id;

        // 1. Resolve Module to Model Class
        // Allow fallback to exact match if it's already properly cased, otherwise try ucfirst
        $resolvedModule = ucfirst($module);
        if (!isset($this->moduleToModelMap[$resolvedModule])) {
            // Also check exactly what was passed in case it's something like BranchDailyReport
            if (isset($this->moduleToModelMap[$module])) {
                $resolvedModule = $module;
            } else {
                return $this->error("Invalid module '{$module}'.", null, null, null, 400);
            }
        }

        $modelClass = $this->moduleToModelMap[$resolvedModule];

        // 2. Convert field to database column name
        $column = Str::snake($field);

        // 3. Find the record
        try {
            $record = $modelClass::where('organization_id', $orgId)->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error("Record not found in module '{$resolvedModule}'.", null, null, null, 404);
        }

        // 4. Security: Check if column is fillable
        if (!$record->isFillable($column)) {
            return $this->error("Field '{$field}' is not allowed to be updated inline.", null, null, null, 403);
        }

        // 5. Update and Save
        try {
            $record->$column = $value;
            $record->save();

            return $this->success([
                'id' => $record->id,
                'field' => $field,
                'value' => $record->$column
            ], "Successfully updated '{$field}'.");

        } catch (\Exception $e) {
            return $this->error("Failed to update record: " . $e->getMessage(), null, null, null, 500);
        }
    }
}
