<?php

namespace App\Modules\Api\V1\SavedFilter\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class QueryFilterService
{
    private static $fieldMappings = [
        'users' => [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'email' => 'email',
            'role' => 'role',
            'createdAt' => 'created_at',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'created_at' => 'created_at',
        ],
        'vendors' => [
            'name' => 'name',
            'contactPerson' => 'contact_person',
            'contact_person' => 'contact_person',
            'email' => 'email',
            'phone' => 'phone',
            'createdAt' => 'created_at',
            'created_at' => 'created_at',
        ],
        'ingredients' => [
            'name' => 'name',
            'unit' => 'unit',
            'minimumStockLevel' => 'minimum_stock_level',
            'minimum_stock_level' => 'minimum_stock_level',
            'currentStock' => 'current_stock',
            'current_stock' => 'current_stock',
            'vendorId' => 'vendor_id',
            'vendor_id' => 'vendor_id',
            'createdAt' => 'created_at',
            'created_at' => 'created_at',
        ],
        'inventory_transactions' => [
            'type' => 'type',
            'quantity' => 'quantity',
            'ingredientId' => 'ingredient_id',
            'ingredient_id' => 'ingredient_id',
            'createdAt' => 'created_at',
            'created_at' => 'created_at',
        ],
        'products' => [
            'name' => 'name',
            'productNumber' => 'product_number',
            'product_number' => 'product_number',
            'price' => 'price',
            'unit' => 'unit',
            'shelfLife' => 'shelf_life',
            'shelf_life' => 'shelf_life',
            'expiryDate' => 'expiry_date',
            'expiry_date' => 'expiry_date',
            'currentStock' => 'current_stock',
            'current_stock' => 'current_stock',
            'createdAt' => 'created_at',
            'created_at' => 'created_at',
        ],
    ];

    private static $allowedOperators = [
        '=', '!=', '>', '<', '>=', '<=', 'like', 'LIKE', 'in', 'IN'
    ];

    /**
     * Apply rules dynamically to an Eloquent query builder.
     *
     * @param  array{orgId: string, branchId: string}|null  $branchStockContext
     *         When set for products, currentStock filters use branch_stocks for that branch.
     *
     * @throws ValidationException
     */
    public static function apply(Builder $query, string $module, array $rules, ?array $branchStockContext = null): void
    {
        $logicalOperator = strtolower($rules['logical_operator'] ?? 'and');
        $conditions = $rules['conditions'] ?? [];

        if (empty($conditions)) {
            return;
        }

        $query->where(function ($subQuery) use ($module, $conditions, $logicalOperator, $branchStockContext) {
            foreach ($conditions as $condition) {
                $clientField = $condition['field'] ?? null;
                $operator = $condition['operator'] ?? null;
                $value = $condition['value'] ?? null;

                // 1. Validate field exists in whitelist for the module
                if (!isset(self::$fieldMappings[$module]) || !isset(self::$fieldMappings[$module][$clientField])) {
                    throw ValidationException::withMessages([
                        'rules' => ["Field '{$clientField}' is not allowed for module '{$module}'."]
                    ]);
                }
                $dbField = self::$fieldMappings[$module][$clientField];

                // 2. Validate operator is allowed
                if (!in_array($operator, self::$allowedOperators)) {
                    throw ValidationException::withMessages([
                        'rules' => ["Operator '{$operator}' is not allowed."]
                    ]);
                }

                // Branch-scoped product stock: filter on branch_stocks, not warehouse products.current_stock
                if (
                    $module === 'products'
                    && $branchStockContext
                    && in_array($clientField, ['currentStock', 'current_stock'], true)
                    && ! empty($branchStockContext['orgId'])
                    && ! empty($branchStockContext['branchId'])
                ) {
                    $expr = 'COALESCE((SELECT bs.current_stock FROM branch_stocks bs WHERE bs.product_id = products.id AND bs.organization_id = ? AND bs.branch_id = ? LIMIT 1), 0)';
                    $bindings = [(string) $branchStockContext['orgId'], (string) $branchStockContext['branchId']];
                    if (strtolower((string) $operator) === 'in') {
                        $vals = is_array($value) ? $value : [$value];
                        $placeholders = implode(',', array_fill(0, count($vals), '?'));
                        $sql = "{$expr} IN ({$placeholders})";
                        $bindings = array_merge($bindings, $vals);
                        if ($logicalOperator === 'or') {
                            $subQuery->orWhereRaw($sql, $bindings);
                        } else {
                            $subQuery->whereRaw($sql, $bindings);
                        }
                    } else {
                        $sql = "{$expr} {$operator} ?";
                        $bindings[] = $value;
                        if ($logicalOperator === 'or') {
                            $subQuery->orWhereRaw($sql, $bindings);
                        } else {
                            $subQuery->whereRaw($sql, $bindings);
                        }
                    }
                    continue;
                }

                // 3. Formulate the method and apply to query builder
                $method = $logicalOperator === 'or' ? 'orWhere' : 'where';

                if (strtolower($operator) === 'in') {
                    $inMethod = $logicalOperator === 'or' ? 'orWhereIn' : 'whereIn';
                    $subQuery->$inMethod($dbField, is_array($value) ? $value : [$value]);
                } elseif ($dbField === 'created_at' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $dateMethod = $logicalOperator === 'or' ? 'orWhereDate' : 'whereDate';
                    $subQuery->$dateMethod($dbField, $operator, $value);
                } else {
                    $subQuery->$method($dbField, $operator, $value);
                }
            }
        });
    }
}
