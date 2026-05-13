<?php
/**
 * API Endpoint — Serve text PDF
 * Serves data/outputs/texte.pdf to the frontend
 */

require '../../config/config.php';

// Check if file exists
if (!file_exists(SOURCE_PDF)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PDF not found']);
    exit;
}

// Set cache headers
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize(SOURCE_PDF));
header('Cache-Control: public, max-age=3600');
header('ETag: "' . md5_file(SOURCE_PDF) . '"');

// Check If-None-Match (conditional request)
if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    $clientETag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
    $serverETag = md5_file(SOURCE_PDF);
    if ($clientETag === $serverETag) {
        http_response_code(304); // Not Modified
        exit;
    }
}

// Stream the file
readfile(SOURCE_PDF);
