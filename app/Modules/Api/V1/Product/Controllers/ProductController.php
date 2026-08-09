<?php

namespace App\Modules\Api\V1\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FieldModelManager;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Product\Requests\StoreProductRequest;
use App\Modules\Api\V1\Product\Requests\UpdateProductRequest;
use App\Modules\Api\V1\Product\Resources\ProductResource;
use App\Modules\Api\V1\Product\Services\ProductNumberService;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    /**
     * GET /Product/check-product-number?productNumber=03&excludeId=optional
     */
    public function checkProductNumber(Request $request)
    {
        $productNumber = $request->query('productNumber');
        $excludeId = $request->query('excludeId');
        $orgId = AuthUser::organizationId();

        if (!$orgId) {
            return $this->error('Organization required.', null, null, null, 403);
        }

        $result = ProductNumberService::checkAvailability(
            $orgId,
            $productNumber !== null ? (string) $productNumber : null,
            $excludeId ? (string) $excludeId : null
        );

        return $this->success($result);
    }

    public function store(StoreProductRequest $request)
    {
        $values = $request->input('data.values') ?? [];
        if (empty($values['currentStock'])) {
            $values['currentStock'] = 0;
        }

        if (!empty($values['productNumber'])) {
            $normalized = ProductNumberService::normalize((string) $values['productNumber']);
            if ($normalized !== null) {
                $values['productNumber'] = $normalized;
            }
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
        } catch (ValidationException $e) {
            throw $e;
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

    public function update(UpdateProductRequest $request, $id)
    {
        try {
            $values = $request->input('data.values') ?? [];

            if (array_key_exists('productNumber', $values) && $values['productNumber'] !== null && $values['productNumber'] !== '') {
                $normalized = ProductNumberService::normalize((string) $values['productNumber']);
                if ($normalized !== null) {
                    $values['productNumber'] = $normalized;
                }
            }

            /** @var Product $product */
            $product = RecordObject::make('Product', $id, $values, 'EditView');
            $product->save();

            return $this->success(new ProductResource($product));
        } catch (ValidationException $e) {
            throw $e;
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
