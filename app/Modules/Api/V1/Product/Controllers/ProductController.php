<?php

namespace App\Modules\Api\V1\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Product\Requests\StoreProductRequest;
use App\Modules\Api\V1\Product\Requests\UpdateProductRequest;
use App\Modules\Api\V1\Product\Resources\ProductResource;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;
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

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'products', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'products', $rules);
            }
        }

        $products = $query->paginate($perPage);

        $fieldManager = \App\Models\FieldModelManager::make('Product', 'DetailView', false);
        $fieldList = $fieldManager->getApiFormFields();

        return $this->paginated(ProductResource::collection($products)->resource, $fieldList);
    }

    public function store(Request $request)
    {
        $values = $request->input('data.values');
        $orgId = $request->user()->organization_id;

        $product = new Product();
        $product->organization_id = $orgId;
        // set default values for numeric fields to prevent null error if DB doesn't have defaults, wait, the DB might have defaults, but let BaseModel fill them.
        if (empty($values['currentStock'])) {
            $product->current_stock = 0;
        }
        $product->fill($values);
        $product->save();

        return $this->success(new ProductResource($product), 'Product created successfully.', 201);
    }

    public function show($id)
    {
        try {
            $product = Product::findOrFail($id);
            $resource = new ProductResource($product);
            
            $fieldManager = \App\Models\FieldModelManager::make('Product', 'DetailView', false);
            $fieldList = $fieldManager->getApiFormFields();
            
            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request())
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Product not found.', null, null, null, 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $orgId = $request->user()->organization_id;
            $product = Product::where('organization_id', $orgId)->findOrFail($id);
            $values = $request->input('data.values');

            $product->fill($values);
            $product->save();

            return $this->success(new ProductResource($product));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Product not found.', null, null, null, 404);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return $this->success(null, 'Product successfully deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Product not found.', null, null, null, 404);
        }
    }
}
