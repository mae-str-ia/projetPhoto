<?php
require '../config/config.php';

$book = BookManager::load();

echo "Ordre réel des pages dans book['pages']:\n";
echo "Position → pageNumber\n\n";

for ($idx = 0; $idx < 30; $idx++) {
    if (!isset($book['pages'][$idx])) break;
    $page = $book['pages'][$idx];
    $pageNum = $page['pageNumber'];
    echo "Index $idx: pageNumber=$pageNum\n";
}
