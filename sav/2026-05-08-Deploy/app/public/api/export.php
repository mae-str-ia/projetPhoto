<?php
require '../../config/config.php';
require '../../vendor/autoload.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    if ($action === 'export') {
        handleExport();
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Handle Word export
 */
function handleExport() {
    try {
        // Generate Word document
        $docxPath = ExportManager::generateWord();

        if (!file_exists($docxPath)) {
            throw new Exception('Failed to generate document');
        }

        // Read file
        $fileContent = file_get_contents($docxPath);
        if ($fileContent === false) {
            throw new Exception('Failed to read generated document');
        }

        // Clean up temp file
        unlink($docxPath);

        // Send as download
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="ProjetPhoto.docx"');
        header('Content-Length: ' . strlen($fileContent));

        echo $fileContent;
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Export failed: ' . $e->getMessage()
        ]);
    }
}
