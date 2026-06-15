<?php
$files = ['tests/Feature/Phase2Test.php', 'tests/Feature/FilterTest.php', 'tests/Feature/AuthTest.php'];
foreach($files as $f) {
    if(!file_exists($f)) continue;
    $c = file_get_contents($f);
    
    // Replace API endpoints with regex to catch all variants like /new, ?, /id, etc.
    $replacements = [
        '#/api/v1/organizations?(/?.*?)(\'|\"|\?|$)#' => '/api/v1/Organization$1$2',
        '#/api/v1/settings/users?(/?.*?)(\'|\"|\?|$)#' => '/api/v1/settings/User$1$2',
        '#/api/v1/vendors?(/?.*?)(\'|\"|\?|$)#' => '/api/v1/Vendor$1$2',
        '#/api/v1/ingredients?(/?.*?)(\'|\"|\?|$)#' => '/api/v1/Ingredient$1$2',
        '#/api/v1/inventory-transactions?(/?.*?)(\'|\"|\?|$)#' => '/api/v1/InventoryTransaction$1$2',
        '#/api/v1/products?(/?.*?)(\'|\"|\?|$)#' => '/api/v1/Product$1$2',
    ];

    foreach ($replacements as $pattern => $replacement) {
        $c = preg_replace($pattern, $replacement, $c);
    }

    // Also fix the filter 'module' values
    $c = preg_replace("/'module' => 'products?'/", "'module' => 'Product'", $c);
    $c = preg_replace("/'module' => 'users?'/", "'module' => 'User'", $c);
    $c = preg_replace("/\?module=products?/", "?module=Product", $c);
    $c = preg_replace("/\?module=users?/", "?module=User", $c);

    file_put_contents($f, $c);
    echo "Updated " . $f . "\n";
}
