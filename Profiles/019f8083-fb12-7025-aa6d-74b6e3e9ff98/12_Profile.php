<?php

return array (
  'name' => 'Warehouse Staff',
  'description' => 'Default warehouse profile — ingredients, production, transfers, stock.',
  'status' => 'Active',
  'modules' => 
  array (
    'Branch' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 0,
        'edit' => 0,
        'delete' => 0,
      ),
    ),
    'BranchStock' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'BranchTransfer' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'Ingredient' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'InventoryTransaction' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'Product' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'ProductionBatch' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'Recipe' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'Vendor' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
  ),
);
