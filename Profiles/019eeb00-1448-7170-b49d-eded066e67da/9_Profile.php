<?php

return array (
  'name' => 'Sales Staff',
  'description' => 'Default sales / POS profile — billing, product view, branch stock, daily report.',
  'status' => 'Active',
  'modules' => 
  array (
    'Billing' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'BranchDailyReport' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 1,
        'edit' => 1,
        'delete' => 0,
      ),
    ),
    'BranchStock' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 0,
        'edit' => 0,
        'delete' => 0,
      ),
    ),
    'Product' => 
    array (
      'permissions' => 
      array (
        'view' => 1,
        'create' => 0,
        'edit' => 0,
        'delete' => 0,
      ),
    ),
  ),
);
