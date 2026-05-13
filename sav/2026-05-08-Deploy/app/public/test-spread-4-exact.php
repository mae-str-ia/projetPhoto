<?php
require '../config/config.php';

$spreads = BookManager::getAllSpreads();

// Chercher la spread 4 (index 3, puisque c'est 0-based)
// En zip 1, c'est la 4ème spread affichée (après la frontmatter)
$spread = $spreads[3];  // Spread #4

$leftPage = $spread['leftPage'];
$rightPage = $spread['rightPage'];

echo "Simulation de la spread 4 affichée en gallery.php:\n\n";

echo "leftPage:\n";
echo "  pageNumber: " . $leftPage['pageNumber'] . "\n";
echo "  type: " . $leftPage['type'] . "\n";
echo "\n";

echo "rightPage:\n";
echo "  pageNumber: " . $rightPage['pageNumber'] . "\n";
echo "  type: " . $rightPage['type'] . "\n";
echo "  pdfPage: " . ($rightPage['pdfPage'] ?? $rightPage['pageNumber']) . "\n";
echo "\n";

// Cela affichera le label "p.8-9"
$label_left = $leftPage['pageNumber'];
$label_right = $rightPage['pageNumber'];
echo "Label affiché: p.$label_left-$label_right\n";

// Cela affichera le PDF
$pdfPage = $rightPage['pdfPage'] ?? $rightPage['pageNumber'];
echo "PDF page affiché: $pdfPage\n";
