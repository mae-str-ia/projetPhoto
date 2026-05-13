<?php
require '../config/config.php';

$book = BookManager::load();

// Find all text pages and create a mapping to their sequential PDF page numbers
$textPageIndex = 0;
$pageMapping = [];

foreach ($book['pages'] as &$page) {
    if (($page['type'] ?? '') === 'text') {
        $textPageIndex++;
        $page['pdfPage'] = $textPageIndex;  // Correct PDF page number
        $pageMapping[$page['pageNumber']] = $textPageIndex;
    } else {
        // Photo pages don't have pdfPage
        unset($page['pdfPage']);
    }
}
unset($page);

// Save the corrected book
BookManager::save($book);

echo "✓ Fixed pdfPage values in book.json\n\n";
echo "Summary of changes:\n";
echo "- Total text pages: $textPageIndex\n";
echo "- Sample corrections:\n";

$samples = [3, 4, 5, 9, 11, 13, 15];
foreach ($samples as $pageNum) {
    if (isset($pageMapping[$pageNum])) {
        echo "  Book page $pageNum → PDF page " . $pageMapping[$pageNum] . "\n";
    }
}

echo "\nVerification:\n";
// Verify the fix
$verifyBook = BookManager::load();
$spreads = BookManager::getAllSpreads();
echo "Spread 4 (pages 8-9): PDF page " . ($spreads[3]['rightPage']['pdfPage'] ?? '?') . " (should be 5)\n";
echo "Spread 5 (pages 10-11): PDF page " . ($spreads[4]['rightPage']['pdfPage'] ?? '?') . " (should be 6)\n";
