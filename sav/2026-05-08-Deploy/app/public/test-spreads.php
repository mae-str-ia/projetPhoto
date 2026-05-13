<?php
require '../config/config.php';

echo "Test des spreads:\n\n";

for ($i = 1; $i <= 7; $i++) {
    $leftPageNum = $i * 2;
    $rightPageNum = $i * 2 + 1;

    $spread = BookManager::getSpread($i);
    $leftExists = $spread['leftPage'] ? "✓" : "✗";
    $rightExists = $spread['rightPage'] ? "✓" : "✗";

    echo "Spread $i (pages $leftPageNum-$rightPageNum): left=$leftExists, right=$rightExists\n";

    if (!$spread['leftPage']) {
        echo "  ERROR: Left page $leftPageNum not found!\n";
    }
    if (!$spread['rightPage']) {
        echo "  ERROR: Right page $rightPageNum not found!\n";
    }
}
