<?php
require '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    if ($action === 'get_page') {
        $pdfPage = intval($input['pdfPage'] ?? 0);
        $pdfHalf = in_array($input['pdfHalf'] ?? 'right', ['left', 'right']) ? $input['pdfHalf'] : 'right';

        if ($pdfPage < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid page number']);
            exit;
        }

        $imageUrl = PdfManager::getPageImage($pdfPage);

        if (!$imageUrl) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Could not convert PDF page']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'url' => $imageUrl,
            'pdfPage' => $pdfPage,
            'pdfHalf' => $pdfHalf,
        ]);
    } elseif ($action === 'generateFinalPdf') {
        // Generate final PDF with photos and text merged (Windows only)
        if (PHP_OS_FAMILY !== 'Windows') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La génération du PDF final ne peut être lancée que depuis l\'ordinateur local (Windows).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $captureDir = dirname(__DIR__, 3) . '/capture';
        $nodeExe = 'C:\\Program Files\\nodejs\\node.exe';

        if (!is_dir($captureDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Capture directory not found']);
            exit;
        }

        if (!file_exists($nodeExe)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Node.js not found']);
            exit;
        }

        // Run the scripts in sequence
        $commands = [
            "cd \"$captureDir\" && \"$nodeExe\" capture-pages.js",
            "cd \"$captureDir\" && \"$nodeExe\" merge-hq-pdf-v2.js",
            "cd \"$captureDir\" && \"$nodeExe\" fix-pdf-size.js"
        ];

        foreach ($commands as $cmd) {
            exec($cmd . ' 2>&1', $output, $returnCode);
            if ($returnCode !== 0) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Script failed: ' . implode("\n", $output)
                ]);
                exit;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'PDF complet généré avec succès',
            'file' => 'livre.print.pdf'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
