<?php
require '../config/config.php';

$spreads = BookManager::getAllSpreads();
echo "Nombre total de spreads: " . count($spreads) . "\n\n";

echo "Première spread qui doit s'afficher...\n";
$spread = $spreads[0] ?? null;
if ($spread) {
    echo "Spread " . $spread['spreadNumber'] . ":\n";
    echo "  Left: " . ($spread['leftPage'] ? "page " . $spread['leftPage']['pageNumber'] : "NULL") . "\n";
    echo "  Right: " . ($spread['rightPage'] ? "page " . $spread['rightPage']['pageNumber'] : "NULL") . "\n";
} else {
    echo "Aucune spread trouvée!\n";
}

echo "\n\nSpreads 1-7:\n";
for ($i = 0; $i < 7 && $i < count($spreads); $i++) {
    $spread = $spreads[$i];
    $left = $spread['leftPage']['pageNumber'] ?? "?";
    $right = $spread['rightPage']['pageNumber'] ?? "?";
    echo "Spread " . ($i+1) . ": pages $left-$right\n";
}
