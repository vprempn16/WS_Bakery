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
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'description', 'fieldlabel' => 'Description', 'fieldtype' => 'textarea', 'displaytype' => 1],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email', 'displaytype' => 1],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone', 'displaytype' => 1],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea', 'displaytype' => 1],
        ],
        'users' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'firstName', 'fieldlabel' => 'First Name', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'lastName', 'fieldlabel' => 'Last Name', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email', 'displaytype' => 1],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone', 'displaytype' => 1],
            [
                'fieldname' => 'role',
                'fieldlabel' => 'Role',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'options' => [
                    ['value' => 'owner', 'label' => 'Owner'],
                    ['value' => 'admin', 'label' => 'Admin'],
                    ['value' => 'manager', 'label' => 'Manager'],
                    ['value' => 'staff', 'label' => 'Staff']
                ]
            ],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization ID', 'fieldtype' => 'relationPickList', 'displaytype' => 2],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'vendors' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'contactPerson', 'fieldlabel' => 'Contact Person', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone', 'displaytype' => 1],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email', 'displaytype' => 1],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'ingredients' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1],
            [
                'fieldname' => 'unit',
                'fieldlabel' => 'Unit',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'options' => [
                    ['value' => 'g', 'label' => 'Grams (g)'],
                    //['value' => 'kg', 'label' => 'Kilograms (kg)'],
                    ['value' => 'ml', 'label' => 'Milliliters (ml)'],
                    //['value' => 'l', 'label' => 'Liters (l)'],
                    ['value' => 'pcs', 'label' => 'Pieces (pcs)']
                ]
            ],
            ['fieldname' => 'vendorId', 'fieldlabel' => 'Vendor ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'minimumStockLevel', 'fieldlabel' => 'Minimum Stock Level', 'fieldtype' => 'decimal', 'displaytype' => 1],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock', 'fieldtype' => 'decimal', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'inventory_transactions' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            [
                'fieldname' => 'type',
                'fieldlabel' => 'Type',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'options' => [
                    ['value' => 'in', 'label' => 'Stock In'],
                    ['value' => 'out', 'label' => 'Stock Out']
                ]
            ],
            ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity', 'fieldtype' => 'decimal', 'displaytype' => 1],
            ['fieldname' => 'referenceNote', 'fieldlabel' => 'Reference Note', 'fieldtype' => 'textarea', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'products' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'productNumber', 'fieldlabel' => 'Product Number', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'description', 'fieldlabel' => 'Description', 'fieldtype' => 'textarea', 'displaytype' => 1],
            ['fieldname' => 'price', 'fieldlabel' => 'Price', 'fieldtype' => 'currency', 'displaytype' => 1],
            [
                'fieldname' => 'unit',
                'fieldlabel' => 'Unit',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'options' => [
                    ['value' => 'Piece', 'label' => 'Piece'],
                    // ['value' => 'Kg', 'label' => 'Kg'],
                    // ['value' => 'Box', 'label' => 'Box'],
                    ['value' => 'Packet', 'label' => 'Packet'],
                    ['value' => 'Gram', 'label' => 'Gram'],
                    // ['value' => 'Dozen', 'label' => 'Dozen'],
                    ['value' => 'Liter', 'label' => 'Liter'],
                    ['value' => 'ml', 'label' => 'Milliliters (ml)'],
                    ['value' => 'l', 'label' => 'Liters (l)']
                ]
            ],
            [
                'fieldname' => 'category',
                'fieldlabel' => 'Category',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'options' => [
                    ['value' => 'Bread', 'label' => 'Bread'],
                    ['value' => 'Sweet', 'label' => 'Sweet'],
                    ['value' => 'Cake', 'label' => 'Cake'],
                    ['value' => 'Snack', 'label' => 'Snack'],
                    ['value' => 'Beverage', 'label' => 'Beverage'],
                    ['value' => 'Other', 'label' => 'Other']
                ]
            ],
            ['fieldname' => 'shelfLifeDays', 'fieldlabel' => 'Shelf Life Days', 'fieldtype' => 'integer/number', 'displaytype' => 1],
            ['fieldname' => 'shelfLifeHours', 'fieldlabel' => 'Shelf Life Hours', 'fieldtype' => 'integer/number', 'displaytype' => 1],
            [
                'fieldname' => 'tier',
                'fieldlabel' => 'Tier',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'options' => [
                    ['value' => 'tier_1', 'label' => 'Tier 1 (Hours)'],
                    ['value' => 'tier_2', 'label' => 'Tier 2 (Days)'],
                    ['value' => 'tier_3', 'label' => 'Tier 3 (Custom)']
                ]
            ],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock', 'fieldtype' => 'decimal', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'recipes' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'quantityRequired', 'fieldlabel' => 'Quantity Required', 'fieldtype' => 'decimal', 'displaytype' => 1],
        ],
        'branches' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1],
            [
                'fieldname' => 'type',
                'fieldlabel' => 'Type',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'options' => [
                    ['value' => 'warehouse', 'label' => 'Warehouse'],
                    ['value' => 'retail', 'label' => 'Retail Store']
                ]
            ],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea', 'displaytype' => 1],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'production_batches' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'batchNumber', 'fieldlabel' => 'Batch Number', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'quantityProduced', 'fieldlabel' => 'Quantity Produced', 'fieldtype' => 'decimal', 'displaytype' => 1],
            ['fieldname' => 'productionDate', 'fieldlabel' => 'Production Date', 'fieldtype' => 'date', 'displaytype' => 1],
            ['fieldname' => 'expiryTimestamp', 'fieldlabel' => 'Expiry Timestamp', 'fieldtype' => 'date', 'displaytype' => 2],
            [
                'fieldname' => 'status',
                'fieldlabel' => 'Status',
                'fieldtype' => 'text',
                'displaytype' => 2
            ],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes', 'fieldtype' => 'textarea', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'branch_stocks' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 1],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock', 'fieldtype' => 'number', 'displaytype' => 1],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'branch_transfers' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 1],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'transferNumber', 'fieldlabel' => 'Transfer Number', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity', 'fieldtype' => 'number', 'displaytype' => 1],
            ['fieldname' => 'transferDate', 'fieldlabel' => 'Transfer Date', 'fieldtype' => 'date', 'displaytype' => 1],
            ['fieldname' => 'status', 'fieldlabel' => 'Status', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes', 'fieldtype' => 'textarea', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'branch_daily_reports' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 1],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'reportDate', 'fieldlabel' => 'Report Date', 'fieldtype' => 'date', 'displaytype' => 1],
            ['fieldname' => 'totalRevenue', 'fieldlabel' => 'Total Revenue', 'fieldtype' => 'number', 'displaytype' => 1],
            ['fieldname' => 'totalWasteAmount', 'fieldlabel' => 'Total Waste Amount', 'fieldtype' => 'number', 'displaytype' => 1],
            ['fieldname' => 'status', 'fieldlabel' => 'Status', 'fieldtype' => 'text', 'displaytype' => 2],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
        ],
        'billings' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 1],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch ID', 'fieldtype' => 'relationPickList', 'displaytype' => 1],
            ['fieldname' => 'billNumber', 'fieldlabel' => 'Bill Number', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'customerName', 'fieldlabel' => 'Customer Name', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'customerPhone', 'fieldlabel' => 'Customer Phone', 'fieldtype' => 'text', 'displaytype' => 1],
            ['fieldname' => 'customerEmail', 'fieldlabel' => 'Customer Email', 'fieldtype' => 'email', 'displaytype' => 1],
            ['fieldname' => 'subTotal', 'fieldlabel' => 'Sub Total', 'fieldtype' => 'number', 'displaytype' => 1],
            ['fieldname' => 'discountAmount', 'fieldlabel' => 'Discount', 'fieldtype' => 'number', 'displaytype' => 1],
            ['fieldname' => 'taxAmount', 'fieldlabel' => 'Tax', 'fieldtype' => 'number', 'displaytype' => 1],
            ['fieldname' => 'grandTotal', 'fieldlabel' => 'Grand Total', 'fieldtype' => 'number', 'displaytype' => 1],
            ['fieldname' => 'paymentMethod', 'fieldlabel' => 'Payment Method', 'fieldtype' => 'picklist', 'displaytype' => 1, 'options' => ['Cash', 'Card', 'UPI']],
            ['fieldname' => 'paymentStatus', 'fieldlabel' => 'Payment Status', 'fieldtype' => 'picklist', 'displaytype' => 1, 'options' => ['Paid', 'Pending', 'Cancelled']],
            ['fieldname' => 'billingDate', 'fieldlabel' => 'Billing Date', 'fieldtype' => 'date', 'displaytype' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2],
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
        'ProductionBatch' => 'production_batches',
        'BranchStock' => 'branch_stocks',
        'BranchTransfer' => 'branch_transfers',
        'BranchDailyReport' => 'branch_daily_reports',
        'Billing' => 'billings',
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
     * Get the mapped field definitions for standard API responses (index/show).
     * Includes fieldname, fieldlabel, fieldtype, and optionally options.
     */
    public static function getMappedFields(string $module): ?array
    {
        $fields = self::getFields($module);
        if (!$fields)
            return null;

        return array_map(function ($field) {
            $mapped = [
                'fieldname' => $field['fieldname'],
                'fieldlabel' => $field['fieldlabel'],
                'fieldtype' => $field['fieldtype'],
                'displaytype' => $field['displaytype']
            ];
            if (isset($field['options'])) {
                $mapped['options'] = $field['options'];
            }
            return $mapped;
        }, $fields);
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
