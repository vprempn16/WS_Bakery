<?php
$files = ['tests/Feature/Phase2Test.php', 'tests/Feature/FilterTest.php', 'tests/Feature/AuthTest.php'];
foreach($files as $f) {
    if(!file_exists($f)) continue;
    $c = file_get_contents($f);
    $c = str_replace('data.values.', 'data.', $c);
    $c = str_replace('data.0.values.', 'data.0.', $c);
    $c = str_replace("json('data.values')", "json('data')", $c);
    $c = str_replace("'values' => [", "", $c);
    
    // Quick hack to remove the closing bracket for values wrapping
    // We'll just rely on the fact that test array structures are specific.
    // Actually, a simpler way is just to manually patch the tests!
}
