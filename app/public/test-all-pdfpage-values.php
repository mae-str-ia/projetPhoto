<?php
require '../config/config.php';

$spreads = BookManager::getAllSpreads();

echo "=== Checking pdfPage values for all spreads ===\n\n";

$mismatches = [];
for ($i = 0; $i < count($spreads); $i++) {
    $spread = $spreads[$i];
    $left = $spread['leftPage'];
    $right = $spread['rightPage'];
    $rightNum = $right['pageNumber'] ?? '?';
    $pdfPage = $right['pdfPage'] ?? $rightNum;

    if ((int)$pdfPage !== (int)$rightNum) {
        $mismatches[] = [
            'spreadNum' => $i + 1,
            'pages' => $left['pageNumber'] . '-' . $right['pageNumber'],
            'rightPageNum' => $rightNum,
            'pdfPage' => $pdfPage
        ];
    }
}

if (count($mismatches) > 0) {
    echo "MISMATCHES FOUND:\n";
    foreach ($mismatches as $m) {
        echo "Spread " . $m['spreadNum'] . " (pages " . $m['pages'] . "): pdfPage=$m[pdfPage] but rightPageNum=$m[rightPageNum]\n";
    }
} else {
    echo "✓ All spreads have correct pdfPage values\n";
}

echo "\nTotal spreads checked: " . count($spreads) . "\n";
echo "Mismatches: " . count($mismatches) . "\n";
