<?php
require '../config/config.php';

$book = BookManager::load();

// Find all text pages in order
$textPages = [];
foreach ($book['pages'] as $page) {
    if (($page['type'] ?? '') === 'text') {
        $textPages[] = $page['pageNumber'];
    }
}

echo "=== Correct PDF Page Mapping ===\n";
echo "Found " . count($textPages) . " text pages\n\n";

echo "Current (WRONG) mapping:\n";
echo "Book Page → PDF Page (current, expected)\n";
for ($i = 0; $i < min(20, count($textPages)); $i++) {
    $bookPageNum = $textPages[$i];
    $pdfPageNum = $i + 1; // PDF pages are 1-indexed and sequential
    echo "$bookPageNum → " . ($i + 1) . " (currently: $bookPageNum, should be: " . ($i + 1) . ")\n";
}

echo "\n...and the issue at page 8-9 (spread 4):\n";
// Find the PDF page number for book page 9
$pdfPageFor9 = array_search(9, $textPages) + 1;
$pdfPageFor11 = array_search(11, $textPages) + 1;
echo "Book page 9 should map to PDF page $pdfPageFor9 (currently mapped to 9)\n";
echo "Book page 11 should map to PDF page $pdfPageFor11 (currently mapped to 11)\n";

echo "\nBut the user sees:\n";
echo "- Spread 4 (pages 8-9) displays PDF page 11\n";
echo "- Spread 5 (pages 10-11) displays PDF page 15\n";

$pdfPageFor9 = array_search(9, $textPages) + 1;
$pdfPageFor11 = array_search(11, $textPages) + 1;
$pdfPageFor13 = array_search(13, $textPages) + 1;
$pdfPageFor15 = array_search(15, $textPages) + 1;

echo "\nWith CORRECT mapping:\n";
echo "Book page 9 → PDF page $pdfPageFor9\n";
echo "Book page 11 → PDF page $pdfPageFor11\n";
echo "Book page 13 → PDF page $pdfPageFor13\n";
echo "Book page 15 → PDF page $pdfPageFor15\n";
