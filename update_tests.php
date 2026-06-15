<?php
$files = ['tests/Feature/AuthTest.php', 'tests/Feature/Phase2Test.php', 'tests/Feature/FilterTest.php'];
foreach($files as $f) {
    if(!file_exists($f)) continue;
    $c = file_get_contents($f);
    $c = str_replace('/api/v1/organization', '/api/v1/Organization', $c);
    $c = str_replace('/api/v1/vendors', '/api/v1/Vendor', $c);
    $c = str_replace('/api/v1/ingredients', '/api/v1/Ingredient', $c);
    $c = str_replace('/api/v1/inventory-transactions', '/api/v1/InventoryTransaction', $c);
    $c = str_replace('/api/v1/products', '/api/v1/Product', $c);
    file_put_contents($f, $c);
    echo "Updated " . $f . "\n";
}
