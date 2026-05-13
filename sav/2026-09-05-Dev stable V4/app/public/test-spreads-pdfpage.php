<?php
require '../config/config.php';

$spreads = BookManager::getAllSpreads();

echo "Spreads et leurs pdfPage affichées:\n\n";

for ($i = 0; $i < 10; $i++) {
    $spread = $spreads[$i];
    $leftPage = $spread['leftPage'];
    $rightPage = $spread['rightPage'];

    $leftNum = $leftPage['pageNumber'] ?? '?';
    $rightNum = $rightPage['pageNumber'] ?? '?';

    // La page PDF affichée vient de la page droite
    $pdfPageDisplayed = $rightPage['pdfPage'] ?? $rightPage['pageNumber'];

    echo "Spread " . ($i+1) . " (pages $leftNum-$rightNum):\n";
    echo "  Affiche PDF page: $pdfPageDisplayed\n";
    echo "  (devrait être: $rightNum)\n";
    if ($pdfPageDisplayed != $rightNum) {
        echo "  ❌ DÉCALAGE!\n";
    }
    echo "\n";
}
