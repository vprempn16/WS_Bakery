<?php

/**
 * BkPortal — portal module seed (visibility, order, status).
 * Used by setup.sh and ModuleService; not an Eloquent model.
 */

return [
    ['id' => null, 'modulename' => 'Vendor', 'modulelabel' => 'Vendor', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 1],
    ['id' => null, 'modulename' => 'Ingredient', 'modulelabel' => 'Ingredient', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 2],
    ['id' => null, 'modulename' => 'MaterialIssue', 'modulelabel' => 'Material Withdrawal', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 2],
    ['id' => null, 'modulename' => 'MaterialIssueItem', 'modulelabel' => 'Material Withdrawal Item', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 2, 'parent_modulename' => 'MaterialIssue'],
    ['id' => null, 'modulename' => 'Product', 'modulelabel' => 'Product', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 3],
    ['id' => null, 'modulename' => 'Branch', 'modulelabel' => 'Branch', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 4],
    ['id' => null, 'modulename' => 'User', 'modulelabel' => 'User', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 5],
    ['id' => null, 'modulename' => 'Billing', 'modulelabel' => 'Billing', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 6],
    ['id' => null, 'modulename' => 'BranchDailyReport', 'modulelabel' => 'Branch Daily Report', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 7],
    ['id' => null, 'modulename' => 'BranchTransfer', 'modulelabel' => 'Branch Transfer', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 8],
    ['id' => null, 'modulename' => 'BranchTransferItem', 'modulelabel' => 'Branch Transfer Item', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 8, 'parent_modulename' => 'BranchTransfer'],
    ['id' => null, 'modulename' => 'BranchStock', 'modulelabel' => 'Branch Stock', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 9],
    ['id' => null, 'modulename' => 'InventoryTransaction', 'modulelabel' => 'Inventory Transaction', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 10],
    ['id' => null, 'modulename' => 'ProductionBatch', 'modulelabel' => 'Production Batch', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 11],
    ['id' => null, 'modulename' => 'ProductionPlan', 'modulelabel' => 'Production Plan', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 11],
    ['id' => null, 'modulename' => 'ProductionPlanItem', 'modulelabel' => 'Production Plan Item', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 11, 'parent_modulename' => 'ProductionPlan'],
    ['id' => null, 'modulename' => 'Recipe', 'modulelabel' => 'Recipe', 'is_entity' => 1, 'status' => 'Active', 'sort_order' => 12],
];
