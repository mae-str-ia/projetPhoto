<?php
require '../config/config.php';

$book = BookManager::load();
$spreads = BookManager::getAllSpreads();

echo "Spreads retournées par getAllSpreads():\n\n";
echo "Total: " . count($spreads) . "\n\n";

echo "Premières 10 spreads (comme elles seraient affichées):\n";
foreach (array_slice($spreads, 0, 10) as $idx => $spread) {
    $leftPage = $spread['leftPage'];
    $rightPage = $spread['rightPage'];

    $leftNum = $leftPage ? $leftPage['pageNumber'] : 'NULL';
    $rightNum = $rightPage ? $rightPage['pageNumber'] : 'NULL';
    $spreadNum = $spread['spreadNumber'] ?? '?';

    echo "Index $idx: Spread #$spreadNum → Pages $leftNum-$rightNum\n";
}
