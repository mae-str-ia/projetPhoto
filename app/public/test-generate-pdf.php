<?php
require '../config/config.php';

echo "=== Test Génération PDF Texte ===\n\n";

try {
    echo "1. Calling MarkdownPdfManager::generateTextPdf()...\n";
    $result = MarkdownPdfManager::generateTextPdf(true);

    echo "\n✓ Génération réussie!\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    // Verify files
    echo "\n2. Vérification des fichiers générés:\n";
    $files = [
        'PDF final (SOURCE_PDF)' => SOURCE_PDF,
        'PDF processed' => OUTPUTS_DIR . '/texte.processed.pdf',
        'Page map' => MARKDOWN_BUILD_DIR . '/page-map.json',
    ];

    foreach ($files as $label => $path) {
        if (file_exists($path)) {
            echo "   ✓ $label: " . filesize($path) . " bytes\n";
        } else {
            echo "   ✗ $label: NOT FOUND\n";
        }
    }

    // Verify book.json
    echo "\n3. Vérification book.json:\n";
    $book = BookManager::load();
    echo "   totalPages: " . $book['totalPages'] . "\n";
    echo "   pages count: " . count($book['pages']) . "\n";

    // Sample page checks
    echo "\n4. Vérification sample pages:\n";
    foreach ([3, 9, 11, 13] as $pageNum) {
        $page = null;
        foreach ($book['pages'] as $p) {
            if ($p['pageNumber'] === $pageNum) {
                $page = $p;
                break;
            }
        }
        if ($page) {
            $pdfPage = $page['pdfPage'] ?? 'N/A';
            echo "   Page $pageNum: type=" . $page['type'] . ", pdfPage=$pdfPage\n";
        }
    }

    // PDF page count
    echo "\n5. Page count dans PDF:\n";
    $pdfPath = SOURCE_PDF;
    if (file_exists($pdfPath)) {
        exec('"C:/Devs/Python/Python399/python.exe" -c "from PyPDF2 import PdfReader; r=PdfReader(\'' . $pdfPath . '\'); print(len(r.pages))"', $output);
        echo "   PDF pages: " . trim($output[0] ?? 'ERROR') . "\n";
    }

} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
