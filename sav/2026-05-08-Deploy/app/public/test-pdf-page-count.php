<?php
require '../config/config.php';

$pdfPath = SOURCE_PDF;

if (!file_exists($pdfPath)) {
    echo "PDF file not found: $pdfPath\n";
    exit;
}

// Use grep to count /Type /Page entries in the PDF
$output = shell_exec('grep -ao "/Type\s*/Page\s*[^/]*" "' . $pdfPath . '" | wc -l');
$pageCount = intval(trim($output)) / 2; // Each page entry might appear twice

echo "PDF file: $pdfPath\n";
echo "File size: " . filesize($pdfPath) . " bytes\n";
echo "Last modified: " . date('Y-m-d H:i:s', filemtime($pdfPath)) . "\n";

// A more accurate way: count /MediaBox entries (one per page)
$content = file_get_contents($pdfPath);
$mediaboxCount = substr_count($content, '/MediaBox');
echo "Page count (via /MediaBox): $mediaboxCount\n";

// List which pages are text pages in book.json
$book = BookManager::load();
$textPages = array_filter($book['pages'], fn($p) => ($p['type'] ?? '') === 'text');
echo "\nText pages in book.json: " . count($textPages) . "\n";
echo "Text page numbers: " . implode(', ', array_map(fn($p) => $p['pageNumber'], $textPages)) . "\n";
