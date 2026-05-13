<?php
require '../config/config.php';

$spreads = BookManager::getAllSpreads();

echo "=== First 10 spreads ===\n";
for ($i = 0; $i < min(10, count($spreads)); $i++) {
    $spread = $spreads[$i];
    $left = $spread['leftPage'];
    $right = $spread['rightPage'];
    $leftNum = $left['pageNumber'] ?? '?';
    $rightNum = $right['pageNumber'] ?? '?';
    $pdfPage = $right['pdfPage'] ?? $rightNum;

    echo "Spread " . ($i + 1) . ": pages $leftNum-$rightNum, pdfPage=$pdfPage\n";

    if ($i === 3) {  // Spread 4 (pages 8-9)
        echo "  [Spread 4 detail]\n";
        echo "  Left: " . json_encode(['pageNumber' => $left['pageNumber'], 'type' => $left['type']]) . "\n";
        echo "  Right: " . json_encode(['pageNumber' => $right['pageNumber'], 'type' => $right['type'], 'pdfPage' => ($right['pdfPage'] ?? 'EMPTY')]) . "\n";
    }
}

echo "\n=== Total spreads ===\n";
echo "Count: " . count($spreads) . "\n";
