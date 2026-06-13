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
        $orgId = $request->query('organizationId');

        $query = Product::query();

        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        $products = $query->get();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $values = $request->input('data.values');

        $product = Product::create([
            'organization_id' => $values['organizationId'],
            'name' => $values['name'],
            'description' => $values['description'] ?? null,
            'price' => $values['price'] ?? null,
            'unit' => $values['unit'] ?? 'pcs',
            'shelf_life_days' => $values['shelfLifeDays'] ?? null,
            'current_stock' => 0,
        ]);

        return new ProductResource($product);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, $id)
    {
        $product = Product::findOrFail($id);
        $values = $request->input('data.values');

        $product->update([
            'organization_id' => $values['organizationId'],
            'name' => $values['name'],
            'description' => $values['description'] ?? null,
            'price' => $values['price'] ?? null,
            'unit' => $values['unit'] ?? 'pcs',
            'shelf_life_days' => $values['shelfLifeDays'] ?? null,
        ]);

        return new ProductResource($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Product successfully deleted.'
        ]);
    }
}
