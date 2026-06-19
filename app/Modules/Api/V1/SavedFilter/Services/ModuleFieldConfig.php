<?php

namespace App\Modules\Api\V1\SavedFilter\Services;

/**
 * Central config for module field definitions.
 * Maps each module to its full list of fields with fieldname (camelCase) and fieldlabel.
 */
class ModuleFieldConfig
{
    /**
     * Complete field definitions for every module.
     * These match exactly what each module's Resource returns.
     */
    private static array $moduleFields = [
        'organizations' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text'],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text'],
            ['fieldname' => 'description', 'fieldlabel' => 'Description', 'fieldtype' => 'textarea'],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email'],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone'],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea'],
        ],
        'users' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text'],
            ['fieldname' => 'firstName', 'fieldlabel' => 'First Name', 'fieldtype' => 'text'],
            ['fieldname' => 'lastName', 'fieldlabel' => 'Last Name', 'fieldtype' => 'text'],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email'],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone'],
            ['fieldname' => 'role', 'fieldlabel' => 'Role', 'fieldtype' => 'picklist'],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization ID', 'fieldtype' => 'relationPickList'],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch ID', 'fieldtype' => 'relationPickList'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date'],
        ],
        'vendors' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text'],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text'],
            ['fieldname' => 'contactPerson', 'fieldlabel' => 'Contact Person', 'fieldtype' => 'text'],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone'],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email'],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date'],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date'],
        ],
        'ingredients' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text'],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text'],
            ['fieldname' => 'unit', 'fieldlabel' => 'Unit', 'fieldtype' => 'picklist'],
            ['fieldname' => 'vendorId', 'fieldlabel' => 'Vendor ID', 'fieldtype' => 'relationPickList'],
            ['fieldname' => 'minimumStockLevel', 'fieldlabel' => 'Minimum Stock Level', 'fieldtype' => 'decimal'],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock', 'fieldtype' => 'decimal'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date'],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date'],
        ],
        'inventory_transactions' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text'],
            ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient ID', 'fieldtype' => 'relationPickList'],
            ['fieldname' => 'type', 'fieldlabel' => 'Type', 'fieldtype' => 'picklist'],
            ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity', 'fieldtype' => 'decimal'],
            ['fieldname' => 'referenceNote', 'fieldlabel' => 'Reference Note', 'fieldtype' => 'textarea'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date'],
        ],
        'products' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text'],
            ['fieldname' => 'productNumber', 'fieldlabel' => 'Product Number', 'fieldtype' => 'text'],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text'],
            ['fieldname' => 'description', 'fieldlabel' => 'Description', 'fieldtype' => 'textarea'],
            ['fieldname' => 'price', 'fieldlabel' => 'Price', 'fieldtype' => 'currency'],
            ['fieldname' => 'unit', 'fieldlabel' => 'Unit', 'fieldtype' => 'picklist'],
            ['fieldname' => 'shelfLifeDays', 'fieldlabel' => 'Shelf Life Days', 'fieldtype' => 'integer/number'],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock', 'fieldtype' => 'decimal'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date'],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date'],
        ],
        'recipes' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text'],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product ID', 'fieldtype' => 'relationPickList'],
            ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient ID', 'fieldtype' => 'relationPickList'],
            ['fieldname' => 'quantityRequired', 'fieldlabel' => 'Quantity Required', 'fieldtype' => 'decimal'],
        ],
        'branches' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text'],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization ID', 'fieldtype' => 'relationPickList'],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text'],
            ['fieldname' => 'type', 'fieldlabel' => 'Type', 'fieldtype' => 'picklist'],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea'],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date'],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date'],
        ],
    ];

    /**
     * Map PascalCase module names to lowercase keys.
     */
    private static array $moduleAliases = [
        'Organization' => 'organizations',
        'User' => 'users',
        'Vendor' => 'vendors',
        'Ingredient' => 'ingredients',
        'InventoryTransaction' => 'inventory_transactions',
        'Product' => 'products',
        'Recipe' => 'recipes',
        'Branch' => 'branches',
    ];

    /**
     * Get all fields for a given module.
     */
    public static function getFields(string $module): ?array
    {
        $normalizedModule = self::normalizeModule($module);
        return self::$moduleFields[$normalizedModule] ?? null;
    }

    /**
     * Get all supported module names (lowercase keys).
     */
    public static function getModuleNames(): array
    {
        return array_keys(self::$moduleFields);
    }

    /**
     * Normalize module name to lowercase key.
     */
    public static function normalizeModule(string $module): string
    {
        return self::$moduleAliases[$module] ?? $module;
    }
}
