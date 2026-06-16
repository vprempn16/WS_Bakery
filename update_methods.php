<?php

// 1. Update routes/api.php
$apiPhpPath = __DIR__ . '/routes/api.php';
if (file_exists($apiPhpPath)) {
    $content = file_get_contents($apiPhpPath);
    $content = str_replace("Route::put('{id}'", "Route::post('{id}'", $content);
    file_put_contents($apiPhpPath, $content);
    echo "Updated routes/api.php\n";
}

// 2. Update api_docs.md
$apiDocsPath = __DIR__ . '/api_docs.md';
if (file_exists($apiDocsPath)) {
    $content = file_get_contents($apiDocsPath);
    $content = str_replace("`PUT /api/v1/", "`POST /api/v1/", $content);
    file_put_contents($apiDocsPath, $content);
    echo "Updated api_docs.md\n";
}

// 3. Update tests
$testFiles = [
    __DIR__ . '/tests/Feature/AuthTest.php',
    __DIR__ . '/tests/Feature/Phase2Test.php',
    __DIR__ . '/tests/Feature/FilterTest.php',
];

foreach ($testFiles as $testFile) {
    if (file_exists($testFile)) {
        $content = file_get_contents($testFile);
        $content = str_replace("putJson", "postJson", $content);
        file_put_contents($testFile, $content);
        echo "Updated " . basename($testFile) . "\n";
    }
}
