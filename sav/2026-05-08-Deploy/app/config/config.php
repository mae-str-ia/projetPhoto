<?php
/**
 * projetPhoto Configuration
 * Constants, autoloader, helper functions
 */

// Directories
define('ROOT_DIR', dirname(dirname(__FILE__)));       // app/
define('DATA_ROOT', dirname(ROOT_DIR) . '/data');     // data/
define('CONFIG_DIR', ROOT_DIR . '/config');
define('SRC_DIR', ROOT_DIR . '/src');
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('TEMPLATE_DIR', ROOT_DIR . '/templates');
define('DATA_DIR', DATA_ROOT);
define('UPLOAD_DIR', DATA_ROOT . '/uploads/photos');
define('PDF_CACHE_DIR', DATA_ROOT . '/cache/pdf-pages');
define('SCREENSHOTS_DIR', DATA_ROOT . '/cache/screenshots');
define('OUTPUTS_DIR', DATA_ROOT . '/outputs');
define('SYNC_DIR', DATA_ROOT . '/SYNC');
define('LOCAL_ONLY_DIR', DATA_ROOT . '/LOCAL_ONLY');
define('MARKDOWN_DIR', DATA_ROOT . '/markdown');
define('MARKDOWN_BUILD_DIR', MARKDOWN_DIR . '/build');

// Application mode
define('APP_MODE', getenv('APP_MODE') ?: (PHP_OS_FAMILY === 'Windows' ? 'local' : 'server'));
define('IS_LOCAL', APP_MODE === 'local');
define('IS_SERVER', APP_MODE === 'server');

// Application
define('APP_NAME', 'Photo Livre');
define('BOOK_JSON', SYNC_DIR . '/book.json');
define('SOURCE_PDF', OUTPUTS_DIR . '/texte.pdf');

// Web
$scheme = 'http';
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')) {
    $scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8081';
$baseUrl = $scheme . '://' . $host;

// Server runs from app/public/ directory, so we don't add /app/public prefix
// (In production, the web server root IS app/public/, so paths are direct)

define('BASE_URL', $baseUrl);

// Upload settings
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Sync API token (for data export to local PDF generation program)
define('SYNC_TOKEN', getenv('SYNC_TOKEN') ?: 'changeme');

// Page dimensions (cm)
define('PAGE_WIDTH', 240);
define('PAGE_HEIGHT', 160);
define('TOTAL_PAGES', 364);

// PHP error reporting
error_reporting(E_ALL);
ini_set('display_errors', IS_LOCAL ? 1 : 0);
ini_set('log_errors', IS_SERVER ? 1 : 0);

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $file = SRC_DIR . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Helper functions

/**
 * HTML escape
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Format bytes to human readable
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Format date
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Generate unique ID
 */
function generateId($prefix = '') {
    return $prefix . uniqid('', true);
}

/**
 * JSON encode with indentation
 */
function jsonEncode($data) {
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Safe JSON decode
 */
function jsonDecode($json, $assoc = true) {
    return json_decode($json, $assoc);
}

/**
 * Write JSON file atomically
 */
function writeJsonFile($filepath, $data) {
    $json = jsonEncode($data);
    $tmpfile = $filepath . '.tmp';
    if (file_put_contents($tmpfile, $json) === false) {
        throw new Exception("Failed to write to $tmpfile");
    }
    if (!rename($tmpfile, $filepath)) {
        unlink($tmpfile);
        throw new Exception("Failed to move $tmpfile to $filepath");
    }
}

/**
 * Read JSON file safely
 */
function readJsonFile($filepath) {
    if (!file_exists($filepath)) {
        return null;
    }
    $content = file_get_contents($filepath);
    if ($content === false) {
        throw new Exception("Failed to read $filepath");
    }
    return jsonDecode($content);
}

/**
 * Ensure directories exist
 */
function ensureDir($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            throw new Exception("Failed to create directory: $path");
        }
    }
}
