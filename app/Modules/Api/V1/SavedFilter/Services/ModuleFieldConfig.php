<?php

namespace App\Modules\Api\V1\SavedFilter\Services;

/**
 * Central config for module field definitions.
 *
 * displaytype:
 *   1 = editable / shown in create-edit forms
 *   2 = system/hidden (not on forms; still shown in Profile settings for visibility)
 *   3 = view-only (detail/list; not editable)
 *
 * mandatory: required on create/edit when displaytype is 1
 */
class ModuleFieldConfig
{
    private static array $moduleFields = [
        'organizations' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'description', 'fieldlabel' => 'Description', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'users' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'firstName', 'fieldlabel' => 'First Name', 'fieldtype' => 'text', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'lastName', 'fieldlabel' => 'Last Name', 'fieldtype' => 'text', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone', 'displaytype' => 1, 'mandatory' => 0],
            // Options filled by FE from Role API list — keep options empty (clears DB picklist on migrate).
            [
                'fieldname' => 'role',
                'fieldlabel' => 'Role',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 1,
                'options' => [],
            ],
            // Create-only (FE hides on detail/edit). Strength: min 8 + uppercase + number.
            [
                'fieldname' => 'password',
                'fieldlabel' => 'Password',
                'fieldtype' => 'password',
                'displaytype' => 1,
                'mandatory' => 1,
            ],
            [
                'fieldname' => 'confirmPassword',
                'fieldlabel' => 'Confirm Password',
                'fieldtype' => 'confirmPassword',
                'displaytype' => 1,
                'mandatory' => 1,
            ],
            // is_admin is derived from users.role (admin/superadmin) — not a separate Status field.
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'vendors' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'contactPerson', 'fieldlabel' => 'Contact Person', 'fieldtype' => 'text', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'email', 'fieldlabel' => 'Email', 'fieldtype' => 'email', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'ingredients' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1, 'mandatory' => 1],
            [
                'fieldname' => 'unit',
                'fieldlabel' => 'Unit',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 1,
                'options' => [
                    ['value' => 'gm', 'label' => 'Gram (gm)'],
                    ['value' => 'ml', 'label' => 'Milliliters (ml)'],
                    ['value' => 'pcs', 'label' => 'Pieces (pcs)'],
                ],
            ],
            ['fieldname' => 'vendorId', 'fieldlabel' => 'Vendor', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'minimumStockLevel', 'fieldlabel' => 'Minimum Stock Level', 'fieldtype' => 'decimal', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock', 'fieldtype' => 'decimal', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'inventory_transactions' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            [
                'fieldname' => 'type',
                'fieldlabel' => 'Type',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 1,
                'options' => [
                    ['value' => 'in', 'label' => 'Stock In'],
                    ['value' => 'out', 'label' => 'Stock Out'],
                ],
            ],
            ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity', 'fieldtype' => 'decimal', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'referenceNote', 'fieldlabel' => 'Reference Note', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'products' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'productNumber', 'fieldlabel' => 'Product Number', 'fieldtype' => 'integer/number', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'description', 'fieldlabel' => 'Description', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'price', 'fieldlabel' => 'Price', 'fieldtype' => 'currency', 'displaytype' => 1, 'mandatory' => 1],
            [
                'fieldname' => 'unit',
                'fieldlabel' => 'Unit',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 1,
                'options' => [
                    ['value' => 'gm', 'label' => 'Gram (gm)'],
                    ['value' => 'pcs', 'label' => 'Pieces (pcs)'],
                    ['value' => 'ml', 'label' => 'Milliliters (ml)'],
                ],
            ],
            [
                'fieldname' => 'category',
                'fieldlabel' => 'Category',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 0,
                'options' => [
                    ['value' => 'bread', 'label' => 'Bread'],
                    ['value' => 'sweet', 'label' => 'Sweet'],
                    ['value' => 'cake', 'label' => 'Cake'],
                    ['value' => 'snack', 'label' => 'Snack'],
                    ['value' => 'spices', 'label' => 'Spices'],
                    ['value' => 'beverage', 'label' => 'Beverage'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
            [
                'fieldname' => 'status',
                'fieldlabel' => 'Status',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 1,
                'options' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                ],
            ],
            [
                'fieldname' => 'shelfLife',
                'fieldlabel' => 'Shelf Life',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 0,
                'options' => [
                    ['value' => '6', 'label' => '6 Hours'],
                    ['value' => '12', 'label' => 'Half Day (12h)'],
                    ['value' => '24', 'label' => '1 Day'],
                    ['value' => '48', 'label' => '2 Days'],
                    ['value' => '72', 'label' => '3 Days'],
                    ['value' => '120', 'label' => '5 Days'],
                    ['value' => '168', 'label' => '7 Days (1 Week)'],
                    ['value' => '336', 'label' => '14 Days'],
                    ['value' => '720', 'label' => '30 Days'],
                ],
            ],
            [
                'fieldname' => 'tier',
                'fieldlabel' => 'Tier',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 0,
                'options' => [
                    ['value' => 'tier_1', 'label' => 'Tier 1 (Hours)'],
                    ['value' => 'tier_2', 'label' => 'Tier 2 (Days)'],
                    ['value' => 'tier_3', 'label' => 'Tier 3 (Custom)'],
                ],
            ],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock', 'fieldtype' => 'decimal', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'recipes' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'quantityRequired', 'fieldlabel' => 'Quantity Required', 'fieldtype' => 'decimal', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'branches' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'name', 'fieldlabel' => 'Name', 'fieldtype' => 'text', 'displaytype' => 1, 'mandatory' => 1],
            [
                'fieldname' => 'type',
                'fieldlabel' => 'Type',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 1,
                'options' => [
                    ['value' => 'warehouse', 'label' => 'Warehouse'],
                    ['value' => 'retail', 'label' => 'Retail Store'],
                ],
            ],
            ['fieldname' => 'address', 'fieldlabel' => 'Address', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'phone', 'fieldlabel' => 'Phone', 'fieldtype' => 'phone', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'production_batches' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'text', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'batchNumber', 'fieldlabel' => 'Batch Number', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'quantityProduced', 'fieldlabel' => 'Quantity Produced', 'fieldtype' => 'decimal', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'pieces', 'fieldlabel' => 'Pieces', 'fieldtype' => 'integer/number', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'productionDate', 'fieldlabel' => 'Production Date', 'fieldtype' => 'date', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'expiryDate', 'fieldlabel' => 'Expiry Date', 'fieldtype' => 'date', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'expiryTime', 'fieldlabel' => 'Expiry Time', 'fieldtype' => 'time', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'expiryTimestamp', 'fieldlabel' => 'Expiry Timestamp', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            [
                'fieldname' => 'status',
                'fieldlabel' => 'Status',
                'fieldtype' => 'text',
                'displaytype' => 3,
                'mandatory' => 0,
            ],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'branch_stocks' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock', 'fieldtype' => 'number', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'updatedDate', 'fieldlabel' => 'Date', 'fieldtype' => 'date', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'updatedTime', 'fieldlabel' => 'Time', 'fieldtype' => 'time', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'material_issues' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'issueNumber', 'fieldlabel' => 'Issue Number', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'issueDate', 'fieldlabel' => 'Issue Date', 'fieldtype' => 'date', 'displaytype' => 1, 'mandatory' => 1],
            [
                'fieldname' => 'status',
                'fieldlabel' => 'Status',
                'fieldtype' => 'text',
                'displaytype' => 3,
                'mandatory' => 0,
            ],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdBy', 'fieldlabel' => 'Issued By', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'material_issue_items' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity', 'fieldtype' => 'number', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'unit', 'fieldlabel' => 'Unit', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'branch_transfers' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'branchId', 'fieldlabel' => 'To Branch', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'transferNumber', 'fieldlabel' => 'Transfer Number', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'transferDate', 'fieldlabel' => 'Transfer Date', 'fieldtype' => 'date', 'displaytype' => 1, 'mandatory' => 1],
            [
                'fieldname' => 'status',
                'fieldlabel' => 'Status',
                'fieldtype' => 'text',
                'displaytype' => 3,
                'mandatory' => 0,
            ],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdBy', 'fieldlabel' => 'Transferred By', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Transferred At', 'fieldtype' => 'date', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'branch_transfer_items' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity', 'fieldtype' => 'number', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'unit', 'fieldlabel' => 'Unit', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'pieces', 'fieldlabel' => 'Pieces', 'fieldtype' => 'number', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'production_plans' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'planDate', 'fieldlabel' => 'Plan Date', 'fieldtype' => 'date', 'displaytype' => 1, 'mandatory' => 1],
            [
                'fieldname' => 'status',
                'fieldlabel' => 'Status',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 0,
                'options' => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ],
            ],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes', 'fieldtype' => 'textarea', 'displaytype' => 1, 'mandatory' => 0],
            ['fieldname' => 'createdBy', 'fieldlabel' => 'Created By', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'production_plan_items' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'plannedQuantity', 'fieldlabel' => 'Planned Quantity', 'fieldtype' => 'number', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'producedQuantity', 'fieldlabel' => 'Produced Quantity', 'fieldtype' => 'number', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'branch_daily_reports' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            // Branch is selected globally through BranchSwitcher / X-Branch-Id.
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch', 'fieldtype' => 'relationPickList', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'reportDate', 'fieldlabel' => 'Report Date', 'fieldtype' => 'date', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'totalRevenue', 'fieldlabel' => 'Total Revenue', 'fieldtype' => 'number', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'totalWasteAmount', 'fieldlabel' => 'Total Waste Amount', 'fieldtype' => 'number', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'status', 'fieldlabel' => 'Status', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'billings' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'billNumber', 'fieldlabel' => 'Bill Number', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'subTotal', 'fieldlabel' => 'Sub Total', 'fieldtype' => 'number', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'discountAmount', 'fieldlabel' => 'Discount', 'fieldtype' => 'number', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'taxAmount', 'fieldlabel' => 'Tax', 'fieldtype' => 'number', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'grandTotal', 'fieldlabel' => 'Grand Total', 'fieldtype' => 'number', 'displaytype' => 3, 'mandatory' => 0],
            [
                'fieldname' => 'paymentMethod',
                'fieldlabel' => 'Payment Method',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 1,
                'options' => [
                    ['value' => 'cash', 'label' => 'Cash'],
                    ['value' => 'card', 'label' => 'Card'],
                    ['value' => 'upi', 'label' => 'UPI'],
                ],
            ],
            [
                'fieldname' => 'paymentStatus',
                'fieldlabel' => 'Payment Status',
                'fieldtype' => 'picklist',
                'displaytype' => 1,
                'mandatory' => 1,
                'options' => [
                    ['value' => 'paid', 'label' => 'Paid'],
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ],
            ],
            ['fieldname' => 'billingDate', 'fieldlabel' => 'Billing Date', 'fieldtype' => 'date', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At', 'fieldtype' => 'date', 'displaytype' => 2, 'mandatory' => 0],
        ],
        'billing_items' => [
            ['fieldname' => 'id', 'fieldlabel' => 'ID', 'fieldtype' => 'uuid', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'billingId', 'fieldlabel' => 'Billing', 'fieldtype' => 'relationPickList', 'displaytype' => 2, 'mandatory' => 0],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity', 'fieldtype' => 'number', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'unitPrice', 'fieldlabel' => 'Unit Price', 'fieldtype' => 'number', 'displaytype' => 1, 'mandatory' => 1],
            ['fieldname' => 'totalPrice', 'fieldlabel' => 'Total Price', 'fieldtype' => 'number', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'unit', 'fieldlabel' => 'Unit', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['fieldname' => 'category', 'fieldlabel' => 'Category', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
        ],
    ];

    private static array $moduleAliases = [
        'Organization' => 'organizations',
        'User' => 'users',
        'Vendor' => 'vendors',
        'Ingredient' => 'ingredients',
        'MaterialIssue' => 'material_issues',
        'MaterialIssueItem' => 'material_issue_items',
        'InventoryTransaction' => 'inventory_transactions',
        'Product' => 'products',
        'Recipe' => 'recipes',
        'Branch' => 'branches',
        'ProductionBatch' => 'production_batches',
        'ProductionPlan' => 'production_plans',
        'ProductionPlanItem' => 'production_plan_items',
        'BranchStock' => 'branch_stocks',
        'BranchTransfer' => 'branch_transfers',
        'BranchTransferItem' => 'branch_transfer_items',
        'BranchDailyReport' => 'branch_daily_reports',
        'Billing' => 'billings',
        'BillingItem' => 'billing_items',
    ];

    public static function getFields(string $module): ?array
    {
        $normalizedModule = self::normalizeModule($module);

        return self::$moduleFields[$normalizedModule] ?? null;
    }

    /**
     * Normalize API field payload (boolean mandatory, int displaytype).
     */
    public static function formatField(array $field): array
    {
        $mapped = [
            'fieldname' => $field['fieldname'],
            'fieldlabel' => $field['fieldlabel'] ?? $field['fieldname'],
            'fieldtype' => $field['fieldtype'] ?? 'text',
            'displaytype' => (int) ($field['displaytype'] ?? 1),
            'mandatory' => (bool) (int) ($field['mandatory'] ?? 0),
        ];

        if (!empty($field['id'])) {
            $mapped['id'] = $field['id'];
        }
        if (array_key_exists('is_custom_field', $field)) {
            $mapped['is_custom_field'] = (bool) (int) $field['is_custom_field'];
        }
        if (isset($field['options'])) {
            $mapped['options'] = $field['options'];
        }
        if (array_key_exists('module', $field)) {
            $mapped['module'] = $field['module'];
        }

        return $mapped;
    }

    public static function getMappedFields(string $module): ?array
    {
        $fields = self::getFields($module);
        if (!$fields) {
            return null;
        }

        return array_map([self::class, 'formatField'], $fields);
    }

    /**
     * Resolve PascalCase module name (Product, BranchTransfer, …).
     */
    public static function resolveModuleName(string $module): string
    {
        foreach (self::$moduleAliases as $pascal => $key) {
            if (strcasecmp($module, $pascal) === 0 || strcasecmp($module, $key) === 0) {
                return $pascal;
            }
        }

        return \Illuminate\Support\Str::studly(\Illuminate\Support\Str::singular($module));
    }

    /**
     * Fields for a view type, preferring crm_fields via FieldModelManager.
     * CreateView/EditView → displaytype 1 only
     * DetailView/ListView → displaytype 1 and 3
     * ProfileView → 1, 2, 3
     */
    public static function getApiFieldsForView(string $module, string $viewType = 'DetailView'): array
    {
        $moduleName = self::resolveModuleName($module);

        try {
            return \App\Models\FieldModelManager::make($moduleName, $viewType, false)->getApiFormFields();
        } catch (\Throwable $e) {
            $mapped = self::getMappedFields($moduleName) ?? [];
            $allowed = match ($viewType) {
                'CreateView', 'EditView' => [1],
                'ProfileView' => [1, 2, 3],
                default => [1, 3],
            };

            return array_values(array_filter(
                $mapped,
                fn ($f) => in_array((int) ($f['displaytype'] ?? 1), $allowed, true)
            ));
        }
    }

    public static function getModuleNames(): array
    {
        return array_keys(self::$moduleFields);
    }

    public static function getModuleAliases(): array
    {
        return self::$moduleAliases;
    }

    public static function normalizeModule(string $module): string
    {
        return self::$moduleAliases[$module] ?? $module;
    }
}
