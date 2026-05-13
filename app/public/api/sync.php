<?php
/**
 * Data Synchronization Endpoint
 * Exports book data, markdown, and photo filenames for local PDF generation
 * Protected by SYNC_TOKEN
 */

require '../../config/config.php';

header('Content-Type: application/json');

try {
    $token = $_GET['token'] ?? '';
    $action = $_GET['action'] ?? '';

    // Validate token
    if (empty($token) || $token !== (defined('SYNC_TOKEN') ? SYNC_TOKEN : '')) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: invalid or missing token'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'export') {
        // Export book.json, markdown, and photo manifest
        $book = BookManager::load();

        // Get list of photo filenames that exist locally
        $photoFiles = [];
        if (isset($book['media']) && is_array($book['media'])) {
            foreach ($book['media'] as $media) {
                $filename = $media['filename'] ?? null;
                if ($filename && file_exists(UPLOAD_DIR . '/' . $filename)) {
                    $photoFiles[] = $filename;
                }
            }
        }

        // Read markdown file
        $markdownContent = '';
        if (file_exists(MARKDOWN_DIR . '/livre.md')) {
            $markdownContent = file_get_contents(MARKDOWN_DIR . '/livre.md');
        }

        echo json_encode([
            'success' => true,
            'book' => $book,
            'markdown' => $markdownContent,
            'photos' => $photoFiles,
            'baseUrl' => BASE_URL . '/uploads/photos/',
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'photo') {
        // Download individual photo file
        $file = basename($_GET['file'] ?? '');

        if (empty($file)) {
            http_response_code(400);
            echo json_encode(['error' => 'Photo filename required'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $path = UPLOAD_DIR . '/' . $file;

        // Prevent directory traversal
        if (realpath($path) === false || strpos(realpath($path), realpath(UPLOAD_DIR)) !== 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!file_exists($path)) {
            http_response_code(404);
            echo json_encode(['error' => 'Photo not found'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Return binary photo
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($path);
        exit;

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
}
