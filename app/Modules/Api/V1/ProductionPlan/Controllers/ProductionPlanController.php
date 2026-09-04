<?php

namespace App\Modules\Api\V1\ProductionPlan\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionPlan\Models\ProductionPlan;
use App\Modules\Api\V1\ProductionPlan\Models\ProductionPlanItem;
use App\Modules\Api\V1\ProductionPlan\Requests\StoreProductionPlanRequest;
use App\Modules\Api\V1\ProductionPlan\Requests\UpdateProductionPlanRequest;
use App\Modules\Api\V1\ProductionPlan\Resources\ProductionPlanResource;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionPlanController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthUser::requireUser();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('ProductionPlan', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = AuthUser::organizationId();
        $perPage = \App\Support\ApiPagination::perPage($request);

        $query = ProductionPlan::with(['creator'])
            ->withCount('items')
            ->where('organization_id', $orgId)
            ->where('status', '!=', 'cancelled');

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'production_plans', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'production_plans', $rules);
            }
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('plan_date', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('plan_date', '<=', $request->query('dateTo'));
        }

        $plans = $query->orderBy('plan_date', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);
        $fieldList = ModuleFieldConfig::getApiFieldsForView('ProductionPlan', 'DetailView');

        return $this->paginated(ProductionPlanResource::collection($plans)->resource, $fieldList);
    }

    public function store(StoreProductionPlanRequest $request)
    {
        $values = $request->input('data.values');
        $itemsData = $request->input('data.relatedRecords.items', []);
        $orgId = AuthUser::organizationId();
        $userId = AuthUser::id();

        try {
            return DB::transaction(function () use ($values, $itemsData, $orgId, $userId) {
                foreach ($itemsData as $itemData) {
                    try {
                        RecordObject::make('Product', $itemData['productId'], [], 'DetailView');
                    } catch (\Exception $e) {
                        throw new \RuntimeException('A selected product does not exist or access is denied.');
                    }
                }

                /** @var ProductionPlan $plan */
                $plan = RecordObject::make('ProductionPlan', null, [
                    'planDate' => $values['planDate'],
                    'notes' => $values['notes'] ?? null,
                    'status' => 'draft',
                ], 'CreateView');
                $plan->organization_id = $orgId;
                $plan->plan_date = $values['planDate'];
                $plan->notes = $values['notes'] ?? null;
                $plan->status = 'draft';
                $plan->created_by = $userId;
                $plan->save();

                $this->replaceItems($plan, $itemsData, $orgId);
                $plan->load(['creator', 'items.product']);

                return $this->success(
                    new ProductionPlanResource($plan),
                    'Production plan saved.',
                    201
                );
            });
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to save production plan: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function show($id)
    {
        try {
            /** @var ProductionPlan $plan */
            $plan = RecordObject::make('ProductionPlan', $id, [], 'DetailView');
            $plan->load(['creator', 'items.product']);
            $resource = new ProductionPlanResource($plan);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('ProductionPlan', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Production plan not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function update(UpdateProductionPlanRequest $request, $id)
    {
        try {
            $values = $request->input('data.values') ?? [];
            $itemsData = $request->input('data.relatedRecords.items');
            $orgId = AuthUser::organizationId();

            return DB::transaction(function () use ($id, $values, $itemsData, $orgId) {
                /** @var ProductionPlan $plan */
                $plan = RecordObject::make('ProductionPlan', $id, [], 'EditView');

                if (strtolower((string) $plan->status) === 'cancelled') {
                    throw new \RuntimeException('Cancelled production plans cannot be edited.');
                }

                if (isset($values['planDate'])) {
                    $plan->plan_date = $values['planDate'];
                }
                if (array_key_exists('notes', $values)) {
                    $plan->notes = $values['notes'];
                }

                $plan->save();

                if (is_array($itemsData)) {
                    foreach ($itemsData as $itemData) {
                        try {
                            RecordObject::make('Product', $itemData['productId'], [], 'DetailView');
                        } catch (\Exception $e) {
                            throw new \RuntimeException('A selected product does not exist or access is denied.');
                        }
                    }
                    $this->replaceItems($plan, $itemsData, $orgId);
                }

                $plan->load(['creator', 'items.product']);

                return $this->success(new ProductionPlanResource($plan), 'Production plan updated.');
            });
        } catch (ModelNotFoundException $e) {
            return $this->error('Production plan not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function destroy($id)
    {
        try {
            /** @var ProductionPlan $plan */
            $plan = RecordObject::make('ProductionPlan', $id, [], 'EditView');

            if (strtolower((string) $plan->status) === 'cancelled') {
                return $this->error('Production plan is already cancelled.', null, null, null, 400);
            }

            $plan->status = 'cancelled';
            $plan->save();
            $plan->load(['creator', 'items.product']);

            return $this->success(new ProductionPlanResource($plan), 'Production plan cancelled.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Production plan not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    /**
     * Preview raw materials required for this plan (recipe × planned qty vs current stock).
     * Does not change stock.
     */
    public function materials($id)
    {
        try {
            /** @var ProductionPlan $plan */
            $plan = RecordObject::make('ProductionPlan', $id, [], 'DetailView');
            $plan->load('items.product');
            $orgId = AuthUser::organizationId();

            $needed = [];
            $warnings = [];
            $products = [];

            foreach ($plan->items as $item) {
                $product = Product::where('organization_id', $orgId)->where('id', $item->product_id)->first();
                if (!$product) {
                    continue;
                }

                $plannedQty = (float) $item->planned_quantity;
                $productWarnings = [];
                $productMaterials = [];

                $recipes = Recipe::where('product_id', $product->id)->get();
                if ($product->isBought()) {
                    $msg = "{$product->name} is a bought (outside brand) product — no materials needed. Receive stock instead of baking.";
                    $warnings[] = $msg;
                    $productWarnings[] = $msg;
                } elseif ($recipes->isEmpty()) {
                    $msg = "{$product->name} has no recipe (BOM). Material need cannot be calculated for this product.";
                    $warnings[] = $msg;
                    $productWarnings[] = $msg;
                } else {
                    $perProductNeeded = [];
                    foreach ($recipes as $recipe) {
                        $ingredientId = $recipe->ingredient_id;
                        $qty = (float) $recipe->quantity_required * $plannedQty;
                        if (!isset($perProductNeeded[$ingredientId])) {
                            $perProductNeeded[$ingredientId] = 0.0;
                        }
                        $perProductNeeded[$ingredientId] += $qty;

                        if (!isset($needed[$ingredientId])) {
                            $needed[$ingredientId] = 0.0;
                        }
                        $needed[$ingredientId] += $qty;
                    }

                    foreach ($perProductNeeded as $ingredientId => $qtyNeeded) {
                        $row = $this->buildMaterialRow($orgId, $ingredientId, $qtyNeeded);
                        if ($row) {
                            $productMaterials[] = $row;
                        }
                    }

                    usort($productMaterials, fn ($a, $b) => strcmp($a['name'], $b['name']));
                }

                $products[] = [
                    'productId' => $product->id,
                    'productName' => $product->name,
                    'plannedQuantity' => round($plannedQty, 2),
                    'unit' => $product->unit,
                    'materials' => $productMaterials,
                    'warnings' => $productWarnings,
                ];
            }

            $materials = [];
            foreach ($needed as $ingredientId => $qtyNeeded) {
                $row = $this->buildMaterialRow($orgId, $ingredientId, $qtyNeeded);
                if ($row) {
                    $materials[] = $row;
                }
            }

            usort($materials, fn ($a, $b) => strcmp($a['name'], $b['name']));

            return $this->success([
                'planId' => $plan->id,
                'planDate' => $plan->plan_date ? $plan->plan_date->format('Y-m-d') : null,
                'products' => $products,
                'materials' => $materials,
                'warnings' => $warnings,
            ], 'Material requirement preview calculated.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Production plan not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildMaterialRow(string $orgId, string $ingredientId, float $qtyNeeded): ?array
    {
        $ingredient = Ingredient::where('organization_id', $orgId)->where('id', $ingredientId)->first();
        if (!$ingredient) {
            return null;
        }
        $stock = (float) $ingredient->current_stock;
        $shortfall = max(0, $qtyNeeded - $stock);
        $status = $shortfall > 0
            ? ($stock <= 0 ? 'critical' : 'short')
            : 'ok';

        return [
            'ingredientId' => $ingredient->id,
            'name' => $ingredient->name,
            'unit' => $ingredient->unit,
            'needed' => round($qtyNeeded, 2),
            'currentStock' => round($stock, 2),
            'shortfall' => round($shortfall, 2),
            'status' => $status,
        ];
    }

    private function replaceItems(ProductionPlan $plan, array $itemsData, string $orgId): void
    {
        ProductionPlanItem::where('production_plan_id', $plan->id)->delete();

        foreach ($itemsData as $itemData) {
            $row = new ProductionPlanItem();
            $row->organization_id = $orgId;
            $row->production_plan_id = $plan->id;
            $row->product_id = $itemData['productId'];
            $row->planned_quantity = (float) $itemData['plannedQuantity'];
            $row->produced_quantity = isset($itemData['producedQuantity']) && $itemData['producedQuantity'] !== ''
                ? (float) $itemData['producedQuantity']
                : null;
            $row->save();
        }
    }
}
