<?php
require '../config/config.php';

$spread = BookManager::getSpread(4);

echo "=== Spread 4 Debug ===\n";
echo "Left Page:\n";
echo "  pageNumber: " . $spread['leftPage']['pageNumber'] . "\n";
echo "  type: " . $spread['leftPage']['type'] . "\n";
echo "  pdfPage: " . ($spread['leftPage']['pdfPage'] ?? '(empty)') . "\n";
echo "  side: " . ($spread['leftPage']['side'] ?? '?') . "\n";

echo "\nRight Page:\n";
echo "  pageNumber: " . $spread['rightPage']['pageNumber'] . "\n";
echo "  type: " . $spread['rightPage']['type'] . "\n";
echo "  pdfPage: " . ($spread['rightPage']['pdfPage'] ?? '(empty)') . "\n";
echo "  side: " . ($spread['rightPage']['side'] ?? '?') . "\n";

echo "\nInline calculation:\n";
$pdfPage = $spread['rightPage']['pdfPage'] ?? $spread['rightPage']['pageNumber'];
echo "pdfPage to display: $pdfPage\n";

echo "\n=== Full book pages around 8-9 ===\n";
$book = BookManager::load();
for ($i = 7; $i < 12; $i++) {
    $page = $book['pages'][$i - 1]; // pages array is 0-indexed, but pageNumber starts at 1
    echo "Index " . ($i - 1) . ": pageNumber={$page['pageNumber']}, type={$page['type']}, pdfPage={$page['pdfPage']}\n";
}
