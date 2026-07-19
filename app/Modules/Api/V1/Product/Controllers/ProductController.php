<?php

namespace App\Modules\Api\V1\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FieldModelManager;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Product\Resources\ProductResource;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('per_page', 20);

        $query = Product::where('organization_id', $orgId);

        $query->when($request->query('search'), function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('product_number', 'like', "%{$search}%");
            });
        });

        $query->when($request->query('unit'), function ($q, $unit) {
            $q->where('unit', $unit);
        });

        $query->when($request->query('stockStatus'), function ($q, $stockStatus) {
            if ($stockStatus === 'out_of_stock') {
                $q->where('current_stock', 0);
            } elseif ($stockStatus === 'in_stock') {
                $q->where('current_stock', '>', 0);
            }
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'products', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'products', $rules);
            }
        }

        $products = $query->paginate($perPage);
        $fieldList = FieldModelManager::make('Product', 'DetailView', false)->getApiFormFields();

        return $this->paginated(ProductResource::collection($products)->resource, $fieldList);
    }

    public function store(Request $request)
    {
        $values = $request->input('data.values') ?? [];
        if (empty($values['currentStock'])) {
            $values['currentStock'] = 0;
        }

        try {
            /** @var Product $product */
            $product = RecordObject::make('Product', null, $values, 'CreateView');
            $product->organization_id = AuthUser::organizationId();
            if (empty($product->current_stock)) {
                $product->current_stock = 0;
            }
            $product->save();

            return $this->success(new ProductResource($product), 'Product created successfully.', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function show($id)
    {
        try {
            /** @var Product $product */
            $product = RecordObject::make('Product', $id, [], 'DetailView');
            $resource = new ProductResource($product);
            $fieldList = FieldModelManager::make('Product', 'DetailView', false)->getApiFormFields();

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Product not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $values = $request->input('data.values') ?? [];
            /** @var Product $product */
            $product = RecordObject::make('Product', $id, $values, 'EditView');
            $product->save();

            return $this->success(new ProductResource($product));
        } catch (ModelNotFoundException $e) {
            return $this->error('Product not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function destroy($id)
    {
        try {
            /** @var Product $product */
            $product = RecordObject::make('Product', $id, [], 'EditView');
            $product->deleteRecord();

            return $this->success(null, 'Product successfully deleted.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Product not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }
}
