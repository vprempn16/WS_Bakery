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
        $ingredientId = $request->query('ingredientId');

        $query = InventoryTransaction::query();

        if ($ingredientId) {
            $query->where('ingredient_id', $ingredientId);
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
