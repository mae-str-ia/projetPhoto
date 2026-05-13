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

echo "=== What's on each PDF page ===\n\n";

echo "PDF page 9 contains book page: " . $textPages[8] . "\n";
echo "PDF page 11 contains book page: " . (isset($textPages[10]) ? $textPages[10] : 'N/A') . "\n";
echo "PDF page 15 contains book page: " . (isset($textPages[14]) ? $textPages[14] : 'N/A') . "\n";

echo "\n=== The bug in action ===\n";
echo "Spread 4 (pages 8-9):\n";
echo "  Should display: PDF page 5 (which has book page 9)\n";
echo "  Currently displays: PDF page 9 (which has book page " . $textPages[8] . ")\n";
echo "  User sees: content from book page " . $textPages[8] . "\n";

echo "\nSpread 5 (pages 10-11):\n";
echo "  Should display: PDF page 6 (which has book page 11)\n";
echo "  Currently displays: PDF page 11 (which has book page " . (isset($textPages[10]) ? $textPages[10] : 'N/A') . ")\n";
echo "  User sees: content from book page " . (isset($textPages[10]) ? $textPages[10] : 'N/A') . "\n";

echo "\n=== Comparing user's report vs actual bug ===\n";
echo "User said:\n";
echo "- Spread 4 displays pages 10-11\n";
echo "- Spread 5 displays pages 14-15\n";
echo "\nBut actual PDF content:\n";
echo "- Spread 4 (requesting PDF 9) gets book page " . $textPages[8] . "\n";
echo "- Spread 5 (requesting PDF 11) gets book page " . (isset($textPages[10]) ? $textPages[10] : 'N/A') . "\n";
