<?php
require '../config/config.php';

$book = BookManager::load();

// Restore pdfPage values to equal pageNumber for text pages
foreach ($book['pages'] as &$page) {
    if (($page['type'] ?? '') === 'text') {
        // For text pages, pdfPage should equal pageNumber (original state)
        $page['pdfPage'] = $page['pageNumber'];
    } else {
        // Photo pages shouldn't have pdfPage
        unset($page['pdfPage']);
    }
}
unset($page);

BookManager::save($book);

echo "✓ Reverted pdfPage values to original state\n";
echo "Spread 4: PDF page " . BookManager::getSpread(4)['rightPage']['pdfPage'] . " (restored to 9)\n";
echo "Spread 5: PDF page " . BookManager::getSpread(5)['rightPage']['pdfPage'] . " (restored to 11)\n";
