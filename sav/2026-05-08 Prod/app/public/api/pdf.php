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
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
