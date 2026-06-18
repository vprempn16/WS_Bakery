<?php

$filePath = __DIR__ . '/api_docs.md';
$content = file_get_contents($filePath);

// Split by lines
$lines = explode("\n", $content);
$newLines = [];
$currentEndpointName = null;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];

    // Check if it's a new endpoint header, e.g., "### 1.5 Search Organizations"
    if (preg_match('/^###\s+\d+\.\d+\s+(.*)$/', $line, $matches)) {
        // If we are already inside an endpoint, close it before starting a new one
        if ($currentEndpointName !== null) {
            // But wait, the previous block might have ended with "---" or another header.
            // Actually, a better approach is to close it right before this header.
            // Let's pop empty lines from $newLines until we insert the ending.
            $emptyLinesBuffer = [];
            while (count($newLines) > 0 && trim(end($newLines)) === '' || trim(end($newLines)) === '---') {
                $emptyLinesBuffer[] = array_pop($newLines);
            }
            
            $newLines[] = "";
            $newLines[] = "**{$currentEndpointName} ending**";
            $newLines[] = "";
            
            // Put the empty lines back (in reverse order because we popped them)
            foreach (array_reverse($emptyLinesBuffer) as $el) {
                $newLines[] = $el;
            }
        }
        
        $currentEndpointName = trim($matches[1]);
        $newLines[] = $line;
        $newLines[] = "";
        $newLines[] = "**{$currentEndpointName} starting**";
        
    } elseif (preg_match('/^##\s+\d+\./', $line) && $currentEndpointName !== null) {
        // We hit a major section header (e.g., "## 2. User Management"), so close the previous endpoint
        $emptyLinesBuffer = [];
        while (count($newLines) > 0 && trim(end($newLines)) === '' || trim(end($newLines)) === '---') {
            $emptyLinesBuffer[] = array_pop($newLines);
        }
        
        $newLines[] = "";
        $newLines[] = "**{$currentEndpointName} ending**";
        $newLines[] = "";
        $currentEndpointName = null;
        
        foreach (array_reverse($emptyLinesBuffer) as $el) {
            $newLines[] = $el;
        }
        $newLines[] = $line;
    } else {
        $newLines[] = $line;
    }
}

// Close the very last endpoint if still open
if ($currentEndpointName !== null) {
    $emptyLinesBuffer = [];
    while (count($newLines) > 0 && trim(end($newLines)) === '' || trim(end($newLines)) === '---') {
        $emptyLinesBuffer[] = array_pop($newLines);
    }
    
    $newLines[] = "";
    $newLines[] = "**{$currentEndpointName} ending**";
    $newLines[] = "";
    
    foreach (array_reverse($emptyLinesBuffer) as $el) {
        $newLines[] = $el;
    }
}

file_put_contents($filePath, implode("\n", $newLines));
echo "Successfully updated api_docs.md with starting and ending tags.\n";
