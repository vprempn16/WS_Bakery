<?php
$files = ['tests/Feature/Phase2Test.php', 'tests/Feature/FilterTest.php', 'tests/Feature/AuthTest.php'];
foreach($files as $f) {
    if(!file_exists($f)) continue;
    $c = file_get_contents($f);
    
    // AuthTest specific replacements
    $c = str_replace("'/api/v1/organization/new'", "'/api/v1/Organization/new'", $c);
    $c = str_replace("'/api/v1/organizations'", "'/api/v1/Organization'", $c);
    $c = str_replace("'/api/v1/organizations/'", "'/api/v1/Organization/'", $c);
    $c = str_replace("'/api/v1/settings/user'", "'/api/v1/settings/User'", $c);
    $c = str_replace("'/api/v1/settings/users'", "'/api/v1/settings/User'", $c);

    // FilterTest and Phase2Test
    $c = str_replace("'/api/v1/vendor/new'", "'/api/v1/Vendor/new'", $c);
    $c = str_replace("'/api/v1/vendors'", "'/api/v1/Vendor'", $c);
    $c = str_replace("'/api/v1/vendors/'", "'/api/v1/Vendor/'", $c);
    
    $c = str_replace("'/api/v1/ingredient/new'", "'/api/v1/Ingredient/new'", $c);
    $c = str_replace("'/api/v1/ingredients/new'", "'/api/v1/Ingredient/new'", $c);
    $c = str_replace("'/api/v1/ingredients'", "'/api/v1/Ingredient'", $c);
    $c = str_replace("'/api/v1/ingredients/'", "'/api/v1/Ingredient/'", $c);

    $c = str_replace("'/api/v1/inventory-transaction/new'", "'/api/v1/InventoryTransaction/new'", $c);
    $c = str_replace("'/api/v1/inventory-transactions'", "'/api/v1/InventoryTransaction'", $c);
    $c = str_replace("'/api/v1/inventory-transactions/'", "'/api/v1/InventoryTransaction/'", $c);

    $c = str_replace("'/api/v1/product/new'", "'/api/v1/Product/new'", $c);
    $c = str_replace("'/api/v1/products/new'", "'/api/v1/Product/new'", $c);
    $c = str_replace("'/api/v1/products'", "'/api/v1/Product'", $c);
    $c = str_replace("'/api/v1/products?'", "'/api/v1/Product?'", $c);

    // Also fix the filter 'module' values
    $c = str_replace("'module' => 'products'", "'module' => 'Product'", $c);
    $c = str_replace("'module' => 'users'", "'module' => 'User'", $c);
    $c = str_replace("?module=products", "?module=Product", $c);
    $c = str_replace("?module=users", "?module=User", $c);

    file_put_contents($f, $c);
    echo "Updated " . $f . "\n";
}
