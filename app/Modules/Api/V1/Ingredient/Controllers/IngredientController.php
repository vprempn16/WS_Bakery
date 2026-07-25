<?php

namespace App\Modules\Api\V1\Ingredient\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FieldModelManager;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Ingredient\Resources\IngredientResource;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('per_page', 20);

        $query = Ingredient::where('organization_id', $orgId);

        $query->when($request->query('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        $query->when($request->query('vendorId'), function ($q, $vendorId) {
            $q->where('vendor_id', $vendorId);
        });

        $query->when($request->query('stockStatus'), function ($q, $stockStatus) {
            if ($stockStatus === 'low') {
                $q->whereColumn('current_stock', '<', 'minimum_stock_level');
            } elseif ($stockStatus === 'in_stock') {
                $q->whereColumn('current_stock', '>=', 'minimum_stock_level');
            }
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'ingredients', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'ingredients', $rules);
            }
        }

        $ingredients = $query->paginate($perPage);
        $fieldList = FieldModelManager::make('Ingredient', 'DetailView', false)->getApiFormFields();

        return $this->paginated(IngredientResource::collection($ingredients)->resource, $fieldList);
    }

    public function store(Request $request)
    {
        $values = $request->input('data.values') ?? [];
        if (empty($values['currentStock'])) {
            $values['currentStock'] = 0;
        }

        try {
            if (!empty($values['vendorId'])) {
                try {
                    RecordObject::make('Vendor', $values['vendorId'], [], 'DetailView');
                } catch (\Exception $e) {
                    return $this->error('The selected vendor does not exist or access is denied.');
                }
            }

            /** @var Ingredient $ingredient */
            $ingredient = RecordObject::make('Ingredient', null, $values, 'CreateView');
            $ingredient->organization_id = AuthUser::organizationId();
            if (empty($ingredient->current_stock)) {
                $ingredient->current_stock = 0;
            }
            $ingredient->save();

            return $this->success(new IngredientResource($ingredient), 'Ingredient created successfully.', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            /** @var Ingredient $ingredient */
            $ingredient = RecordObject::make('Ingredient', $id, [], 'DetailView');
            $resource = new IngredientResource($ingredient);
            $fieldList = FieldModelManager::make('Ingredient', 'DetailView', false)->getApiFormFields();

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Ingredient not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $values = $request->input('data.values') ?? [];
            if (!empty($values['vendorId'])) {
                try {
                    RecordObject::make('Vendor', $values['vendorId'], [], 'DetailView');
                } catch (\Exception $e) {
                    return $this->error('The selected vendor does not exist or access is denied.');
                }
            }

            /** @var Ingredient $ingredient */
            $ingredient = RecordObject::make('Ingredient', $id, $values, 'EditView');
            $ingredient->save();

            return $this->success(new IngredientResource($ingredient));
        } catch (ModelNotFoundException $e) {
            return $this->error('Ingredient not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            /** @var Ingredient $ingredient */
            $ingredient = RecordObject::make('Ingredient', $id, [], 'EditView');
            $ingredient->deleteRecord();

            return $this->success(null, 'Ingredient successfully deleted.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Ingredient not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function lowStock(Request $request)
    {
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('per_page', 20);
        $ingredients = Ingredient::where('organization_id', $orgId)
            ->whereColumn('current_stock', '<', 'minimum_stock_level')
            ->paginate($perPage);

        $fieldList = FieldModelManager::make('Ingredient', 'DetailView', false)->getApiFormFields();

        return $this->paginated(IngredientResource::collection($ingredients)->resource, $fieldList);
    }
}
