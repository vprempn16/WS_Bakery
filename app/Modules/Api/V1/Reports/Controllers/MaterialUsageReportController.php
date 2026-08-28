<?php

namespace App\Modules\Api\V1\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\MaterialIssue\Models\MaterialIssue;
use App\Modules\Api\V1\MaterialIssue\Models\MaterialIssueItem;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Services\AuthUser;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Compare raw materials taken (Material Issue) vs recipe-expected use from production batches.
 */
class MaterialUsageReportController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthUser::requireUser();
        $permissionService = new \App\Services\PermissionService($user);
        $canView = ! $permissionService->denyMessage('MaterialIssue', 'view')
            || ! $permissionService->denyMessage('ProductionBatch', 'view')
            || ! $permissionService->denyMessage('Ingredient', 'view');
        if (! $canView) {
            return $this->error("You don't have permission to view material usage.", null, null, null, 403);
        }

        $orgId = AuthUser::organizationId();
        $dateFrom = $request->query('dateFrom')
            ? Carbon::parse($request->query('dateFrom'))->startOfDay()
            : Carbon::today()->startOfDay();
        $dateTo = $request->query('dateTo')
            ? Carbon::parse($request->query('dateTo'))->endOfDay()
            : Carbon::today()->endOfDay();

        $issueIds = MaterialIssue::where('organization_id', $orgId)
            ->whereDate('issue_date', '>=', $dateFrom->toDateString())
            ->whereDate('issue_date', '<=', $dateTo->toDateString())
            ->whereRaw('LOWER(status) != ?', ['cancelled'])
            ->pluck('id');

        $takenByIngredient = MaterialIssueItem::whereIn('material_issue_id', $issueIds)
            ->selectRaw('ingredient_id, SUM(quantity) as total')
            ->groupBy('ingredient_id')
            ->pluck('total', 'ingredient_id');

        $batches = ProductionBatch::where('organization_id', $orgId)
            ->whereDate('production_date', '>=', $dateFrom->toDateString())
            ->whereDate('production_date', '<=', $dateTo->toDateString())
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw('LOWER(status) != ?', ['cancelled']);
            })
            ->get(['product_id', 'quantity_produced']);

        $expectedByIngredient = [];
        foreach ($batches as $batch) {
            $recipes = Recipe::where('product_id', $batch->product_id)->get();
            $qty = (float) $batch->quantity_produced;
            foreach ($recipes as $recipe) {
                $ingredientId = $recipe->ingredient_id;
                $expectedByIngredient[$ingredientId] =
                    ($expectedByIngredient[$ingredientId] ?? 0) + ((float) $recipe->quantity_required * $qty);
            }
        }

        $ingredientIds = collect($takenByIngredient->keys())
            ->merge(array_keys($expectedByIngredient))
            ->unique()
            ->values();

        $ingredients = Ingredient::where('organization_id', $orgId)
            ->whereIn('id', $ingredientIds)
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($ingredientIds as $ingredientId) {
            $ingredient = $ingredients->get($ingredientId);
            if (!$ingredient) {
                continue;
            }
            $taken = round((float) ($takenByIngredient[$ingredientId] ?? 0), 2);
            $expected = round((float) ($expectedByIngredient[$ingredientId] ?? 0), 2);
            $difference = round($taken - $expected, 2);

            $rows[] = [
                'ingredientId' => $ingredient->id,
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'taken' => $taken,
                'expectedFromRecipes' => $expected,
                'difference' => $difference,
                'currentStock' => round((float) $ingredient->current_stock, 2),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $this->success([
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'rows' => $rows,
        ], 'Material usage report generated.');
    }
}
