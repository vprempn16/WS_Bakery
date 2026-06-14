<?php

namespace App\Modules\Api\V1\InventoryTransaction\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\InventoryTransaction\Requests\StoreInventoryTransactionRequest;
use App\Modules\Api\V1\InventoryTransaction\Resources\InventoryTransactionResource;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;

        $query = InventoryTransaction::where('organization_id', $orgId);

        $query->when($request->query('ingredientId'), function ($q, $ingredientId) {
            $q->where('ingredient_id', $ingredientId);
        });

        $query->when($request->query('type'), function ($q, $type) {
            $q->where('type', $type);
        });

        $query->when($request->query('startDate'), function ($q, $startDate) {
            $q->whereDate('created_at', '>=', $startDate);
        });

        $query->when($request->query('endDate'), function ($q, $endDate) {
            $q->whereDate('created_at', '<=', $endDate);
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'inventory_transactions', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'inventory_transactions', $rules);
            }
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        return InventoryTransactionResource::collection($transactions);
    }

    public function store(StoreInventoryTransactionRequest $request)
    {
        $values = $request->input('data.values');

        $transaction = DB::transaction(function () use ($values) {
            $transaction = InventoryTransaction::create([
                'organization_id' => $values['organizationId'],
                'ingredient_id' => $values['ingredientId'],
                'type' => $values['type'],
                'quantity' => $values['quantity'],
                'reference_note' => $values['referenceNote'] ?? null,
            ]);

            // Update ingredient stock
            $ingredient = Ingredient::findOrFail($values['ingredientId']);
            if ($values['type'] === 'in') {
                $ingredient->current_stock += $values['quantity'];
            } else {
                $ingredient->current_stock -= $values['quantity'];
            }
            $ingredient->save();

            return $transaction;
        });

        return new InventoryTransactionResource($transaction);
    }
}
