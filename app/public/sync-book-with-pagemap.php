<?php
require '../config/config.php';

$pageMapPath = MARKDOWN_BUILD_DIR . '/page-map.json';
$pageMapData = json_decode(file_get_contents($pageMapPath), true);
$book = BookManager::load();

if (!$pageMapData || !isset($pageMapData['sourceToFinal'])) {
    echo "❌ Invalid page-map.json\n";
    exit(1);
}

$sourceToFinal = $pageMapData['sourceToFinal'];
$blanksBefore = $pageMapData['blanksBefore'] ?? [];

echo "=== Synchronizing pdfPage with page-map.json ===\n";
echo "sourceToFinal entries: " . count($sourceToFinal) . "\n";
echo "blanksBefore entries: " . count($blanksBefore) . "\n\n";

$updates = 0;
foreach ($book['pages'] as &$page) {
    $pageNum = (int)$page['pageNumber'];

    if (($page['type'] ?? '') === 'text') {
        // For text pages, pdfPage should match the final page number from sourceToFinal
        $finalPageNum = (int)($sourceToFinal[$pageNum] ?? $pageNum);
        $page['pdfPage'] = $finalPageNum;
        $updates++;
    } else {
        // Photo pages don't have pdfPage
        unset($page['pdfPage']);
    }
}
unset($page);

BookManager::save($book);

echo "✓ Updated $updates text pages with correct pdfPage values\n\n";

// Verify sample pages
echo "Sample verification:\n";
foreach ([3, 4, 5, 7, 9, 11, 13, 15] as $pageNum) {
    foreach ($book['pages'] as $p) {
        if ($p['pageNumber'] === $pageNum && ($p['type'] ?? '') === 'text') {
            $finalPage = $sourceToFinal[$pageNum] ?? $pageNum;
            echo "Page $pageNum (text): pdfPage=" . $p['pdfPage'] . " (should be $finalPage)\n";
            break;
        }
    }
}
