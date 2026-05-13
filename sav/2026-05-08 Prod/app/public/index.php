<?php
require '../config/config.php';

// Initialize book if needed, or extend if PDF has more pages
if (!file_exists(BOOK_JSON)) {
    BookManager::initBook();
} else {
    BookManager::extendIfNeeded();
}

// Determine page
$page = isset($_GET['page']) ? $_GET['page'] : 'gallery';

// Sanitize page name (alphanumeric + underscore)
if (!preg_match('/^[a-z_-]+$/', $page)) {
    $page = 'gallery';
}

// Template mapping
$pages = [
    'gallery' => 'gallery.php',
    'spread'  => 'spread.php',
    'editor'  => 'editor.php',
    'media'   => 'media.php',
];

if (!isset($pages[$page])) {
    $page = 'gallery';
}

$templateFile = TEMPLATE_DIR . '/' . $pages[$page];

if (!file_exists($templateFile)) {
    http_response_code(404);
    echo "Template not found: $templateFile";
    exit;
}

// Set page title and template for layout
$pageTitle = ucfirst(str_replace('-', ' ', $page));
$template = $templateFile;

// Include layout which includes the template
include TEMPLATE_DIR . '/layout.php';
