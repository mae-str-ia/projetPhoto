<?php
/**
 * Upload Endpoint for Text PDF
 * Accepts texte.pdf uploads from local PDF generation
 * Protected by SYNC_TOKEN
 */

require '../../config/config.php';

header('Content-Type: application/json');

try {
    $token = $_GET['token'] ?? '';

    // Validate token
    if (empty($token) || $token !== (defined('SYNC_TOKEN') ? SYNC_TOKEN : '')) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: invalid or missing token'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($_FILES['pdf'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No PDF file provided'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $file = $_FILES['pdf'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File upload incomplete',
            UPLOAD_ERR_NO_FILE => 'No file provided',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary directory missing',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
        ];
        echo json_encode([
            'error' => 'Upload failed',
            'details' => $errorMessages[$file['error']] ?? 'Unknown error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mimeType !== 'application/pdf') {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid file type',
            'expected' => 'application/pdf',
            'received' => $mimeType
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Ensure output directory exists
    if (!is_dir(OUTPUTS_DIR)) {
        if (!mkdir(OUTPUTS_DIR, 0755, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create output directory'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $targetPath = OUTPUTS_DIR . '/texte.pdf';

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save PDF file'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Create version entry
    if (!is_dir(SYNC_DIR . '/versions')) {
        mkdir(SYNC_DIR . '/versions', 0755, true);
    }

    $versionFiles = glob(SYNC_DIR . '/versions/book.v*.json');
    $versionNum = count($versionFiles) + 1;
    $versionFile = SYNC_DIR . '/versions/book.v' . str_pad($versionNum, 3, '0', STR_PAD_LEFT) . '.json';

    // Create version metadata
    $versionData = [
        'version' => $versionNum,
        'timestamp' => date('c'),
        'filename' => 'texte.pdf',
        'size' => filesize($targetPath),
        'hash' => hash_file('sha256', $targetPath)
    ];

    file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'PDF uploaded successfully',
        'path' => $targetPath,
        'size' => filesize($targetPath),
        'size_formatted' => formatFileSize(filesize($targetPath)),
        'version' => $versionNum,
        'version_file' => $versionFile,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
