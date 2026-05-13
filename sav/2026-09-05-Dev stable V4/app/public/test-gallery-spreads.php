<?php
require '../config/config.php';

$book = BookManager::load();
$spreads = BookManager::getAllSpreads();

echo "Gallery spreads test:\n";
echo "Total spreads from getAllSpreads(): " . count($spreads) . "\n\n";

echo "Affichage des data-spread de chaque spread:\n";
for ($i = 0; $i < 10 && $i < count($spreads); $i++) {
    $spread = $spreads[$i];
    $leftPage = $spread['leftPage'];
    $rightPage = $spread['rightPage'];

    if (!$leftPage || !$rightPage) {
        echo "❌ Spread $i: leftPage=" . ($leftPage ? "OK" : "NULL") . ", rightPage=" . ($rightPage ? "OK" : "NULL") . "\n";
    } else {
        echo "✓ Spread $i (spreadNumber=" . $spread['spreadNumber'] . "): pages " . $leftPage['pageNumber'] . "-" . $rightPage['pageNumber'] . "\n";
    }
}
