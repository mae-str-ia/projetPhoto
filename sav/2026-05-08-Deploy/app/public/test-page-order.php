<?php
require '../config/config.php';

$book = BookManager::load();

echo "Vérification de l'ordre des pages dans book.json:\n\n";
echo "Pages 1 à 25:\n";

$pagesByNumber = [];
foreach ($book['pages'] as $page) {
    $pagesByNumber[$page['pageNumber']] = $page;
}

for ($i = 1; $i <= 25; $i++) {
    if (isset($pagesByNumber[$i])) {
        $page = $pagesByNumber[$i];
        echo "✓ Page $i: type={$page['type']}, side={$page['side']}\n";
    } else {
        echo "✗ Page $i: MANQUANTE\n";
    }
}

echo "\n\nOrdre réel des pages dans le tableau book.json:\n";
echo "Premières 25 entrées:\n";
for ($idx = 0; $idx < 25 && $idx < count($book['pages']); $idx++) {
    $page = $book['pages'][$idx];
    $pageNum = $page['pageNumber'];
    echo "  Index $idx: pageNumber=$pageNum\n";
}
