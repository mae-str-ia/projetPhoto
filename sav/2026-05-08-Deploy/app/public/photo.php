<?php
// Simple photo server - serves images from data/uploads/photos/
$file = $_GET['file'] ?? '';

// Debug log
error_log("photo.php: file=$file, cwd=" . getcwd());

if (empty($file) || !preg_match('/^photo_[a-z0-9.]+(\.jpg|\.jpeg|\.png)$/i', $file) || strpos($file, '..') !== false) {
    http_response_code(400);
    error_log("photo.php: Invalid file pattern");
    exit('Invalid file');
}

$path = realpath('../../data/uploads/photos/' . $file);
$baseDir = realpath('../../data/uploads/photos');

// Security: ensure file is in the right directory
if (!$path || strpos($path, $baseDir) !== 0) {
    http_response_code(404);
    exit('File not found');
}

if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found');
}

// Serve the file with correct content type
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$contentType = $ext === 'png' ? 'image/png' : 'image/jpeg';
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
