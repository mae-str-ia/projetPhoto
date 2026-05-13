<?php
require '../config/config.php';

$book = BookManager::load();

echo "Vérification du contenu des pages:\n\n";

// Pages à vérifier
$pagesToCheck = [1, 2, 3, 8, 9, 10, 11];

foreach ($pagesToCheck as $pageNum) {
    $page = null;
    foreach ($book['pages'] as $p) {
        if ($p['pageNumber'] === $pageNum) {
            $page = $p;
            break;
        }
    }

    if (!$page) continue;

    echo "Page $pageNum (type={$page['type']}, side={$page['side']}):\n";

    if ($page['type'] === 'photo') {
        $photoCount = count($page['photos'] ?? []);
        echo "  Photos: $photoCount\n";
        if ($photoCount > 0) {
            $firstPhoto = $page['photos'][0];
            echo "    Première photo: {$firstPhoto['filename']}\n";
        }
    } else {
        $pdfPage = $page['pdfPage'] ?? 'N/A';
        echo "  PDF page: $pdfPage\n";
    }
    echo "\n";
}
