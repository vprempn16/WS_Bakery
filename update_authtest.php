<?php
$f = 'tests/Feature/AuthTest.php';
$c = file_get_contents($f);
$c = str_replace('/api/v1/organization', '/api/v1/Organization', $c);
file_put_contents($f, $c);
echo "Updated AuthTest.php\n";
