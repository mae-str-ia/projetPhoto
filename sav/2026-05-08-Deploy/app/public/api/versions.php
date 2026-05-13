<?php
/**
 * Versions Endpoint
 * List and retrieve historical versions of book.json
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

    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        // List all versions
        $versionsDir = SYNC_DIR . '/versions';

        if (!is_dir($versionsDir)) {
            echo json_encode([
                'success' => true,
                'versions' => [],
                'count' => 0
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $versionFiles = glob($versionsDir . '/book.v*.json');
        rsort($versionFiles);  // Most recent first

        $versions = [];
        foreach ($versionFiles as $file) {
            $basename = basename($file);
            $data = @json_decode(file_get_contents($file), true);

            $versions[] = [
                'file' => $basename,
                'version' => (int)preg_replace('/[^0-9]/', '', $basename),
                'timestamp' => $data['timestamp'] ?? filemtime($file),
                'size' => filesize($file),
                'size_formatted' => formatFileSize(filesize($file)),
                'hash' => $data['hash'] ?? null
            ];
        }

        echo json_encode([
            'success' => true,
            'versions' => $versions,
            'count' => count($versions)
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'get') {
        // Get specific version
        $version = $_GET['version'] ?? '';

        if (empty($version)) {
            http_response_code(400);
            echo json_encode(['error' => 'Version parameter required'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Sanitize filename to prevent directory traversal
        $filename = basename($version);
        $file = SYNC_DIR . '/versions/' . $filename;

        if (!file_exists($file)) {
            http_response_code(404);
            echo json_encode(['error' => 'Version not found'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Return the JSON file directly
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);

    } elseif ($action === 'info') {
        // Get info about a specific version
        $version = $_GET['version'] ?? '';

        if (empty($version)) {
            http_response_code(400);
            echo json_encode(['error' => 'Version parameter required'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $filename = basename($version);
        $file = SYNC_DIR . '/versions/' . $filename;

        if (!file_exists($file)) {
            http_response_code(404);
            echo json_encode(['error' => 'Version not found'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = json_decode(file_get_contents($file), true);
        $data['file'] = $filename;
        $data['size_formatted'] = formatFileSize($data['size'] ?? filesize($file));

        echo json_encode([
            'success' => true,
            'version' => $data
        ], JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
