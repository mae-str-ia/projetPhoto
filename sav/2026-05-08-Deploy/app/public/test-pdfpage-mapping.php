<?php
require '../config/config.php';

$book = BookManager::load();

echo "Vérification du mapping pdfPage:\n\n";
echo "pageNumber → pdfPage (devrait être égal pour pages texte):\n\n";

for ($i = 1; $i <= 25; $i++) {
    $page = null;
    foreach ($book['pages'] as $p) {
        if ($p['pageNumber'] === $i) {
            $page = $p;
            break;
        }
    }

    if (!$page) continue;

    $pdfPage = $page['pdfPage'] ?? 'N/A';
    $type = $page['type'];

    if ($type === 'text') {
        $status = ($pdfPage === $i) ? "✓" : "✗ DÉCALAGE";
        echo "Page $i: pdfPage=$pdfPage ($status)\n";
    } else {
        echo "Page $i: (photo, pas de pdfPage)\n";
    }
}
