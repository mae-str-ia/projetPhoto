#!/usr/bin/env php
<?php
/**
 * projetPhoto Setup Verification Script
 * Run: php test-setup.php
 */

echo "=== projetPhoto Setup Verification ===\n\n";

require 'config/config.php';

$errors = [];
$warnings = [];
$success = [];

// 1. Check PHP version
if (version_compare(PHP_VERSION, '8.0', '<')) {
    $errors[] = "PHP 8.0+ required (current: " . PHP_VERSION . ")";
} else {
    $success[] = "PHP version OK (" . PHP_VERSION . ")";
}

// 2. Check extensions
$required_extensions = ['gd', 'fileinfo', 'json'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "PHP extension '$ext' not loaded";
    } else {
        $success[] = "PHP extension '$ext' loaded";
    }
}

// 3. Check directories
$dirs = [
    DATA_DIR,
    UPLOAD_DIR,
    PDF_CACHE_DIR,
    PDF_DIR,
    TEMPLATE_DIR,
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $errors[] = "Cannot create directory: $dir";
        } else {
            $success[] = "Created directory: $dir";
        }
    } else {
        if (!is_writable($dir)) {
            $warnings[] = "Directory not writable: $dir";
        } else {
            $success[] = "Directory writable: $dir";
        }
    }
}

// 4. Check Ghostscript
$gs_check = false;
$gs_found = null;
$candidates = ['gswin64c', 'gswin32c', 'gs'];
foreach ($candidates as $cmd) {
    $output = null;
    $return = null;
    @exec('where ' . escapeshellarg($cmd) . ' 2>nul', $output, $return);
    if ($return === 0 && !empty($output[0])) {
        $gs_found = trim($output[0]);
        $gs_check = true;
        break;
    }
}

if (!$gs_check) {
    $warnings[] = "Ghostscript not found in PATH (PDF conversion may not work)";
} else {
    $success[] = "Ghostscript found: $gs_found";
}

// 5. Check ImageMagick fallback
$im_check = false;
@exec('where convert 2>nul', $output, $return);
if ($return === 0 && !empty($output[0])) {
    $im_check = true;
    $success[] = "ImageMagick found (convert): " . trim($output[0]);
} else {
    if (!$gs_check) {
        $warnings[] = "ImageMagick also not found (install Ghostscript OR ImageMagick)";
    }
}

// 6. Check PDF file
if (!file_exists(SOURCE_PDF)) {
    $warnings[] = "PDF file not found: " . SOURCE_PDF . " (place your PDF here)";
} else {
    $filesize = filesize(SOURCE_PDF);
    $success[] = "PDF file found: " . SOURCE_PDF . " (" . formatFileSize($filesize) . ")";
}

// 7. Check Composer
if (!file_exists('vendor/autoload.php')) {
    $warnings[] = "vendor/autoload.php not found (run: composer install)";
} else {
    $success[] = "Composer dependencies installed (vendor/autoload.php exists)";
}

// 8. Check/Create book.json
if (!file_exists(BOOK_JSON)) {
    try {
        BookManager::initBook();
        $success[] = "Initialized book.json with 160 pages";
    } catch (Exception $e) {
        $errors[] = "Failed to create book.json: " . $e->getMessage();
    }
} else {
    $book = BookManager::load();
    $photoPages = count(array_filter($book['pages'], fn($p) => $p['type'] === 'photo'));
    $success[] = "book.json exists with $photoPages photo pages";
}

// Print results
echo "ERRORS (" . count($errors) . "):\n";
if (count($errors) === 0) {
    echo "  None\n";
} else {
    foreach ($errors as $error) {
        echo "  ❌ $error\n";
    }
}

echo "\nWARNINGS (" . count($warnings) . "):\n";
if (count($warnings) === 0) {
    echo "  None\n";
} else {
    foreach ($warnings as $warning) {
        echo "  ⚠️  $warning\n";
    }
}

echo "\nSUCCESS (" . count($success) . "):\n";
foreach ($success as $msg) {
    echo "  ✅ $msg\n";
}

echo "\n";
if (count($errors) > 0) {
    echo "❌ Setup has errors. Fix them before running the app.\n";
    exit(1);
} elseif (count($warnings) > 0) {
    echo "⚠️  Setup has warnings. The app may have limited functionality.\n";
    echo "   (Especially if Ghostscript/ImageMagick is missing)\n";
    exit(0);
} else {
    echo "✅ Setup looks good! Ready to run.\n\n";
    echo "Start the app with:\n";
    echo "  cd public\n";
    echo '  "C:/Devs/php85/php.exe" -S localhost:8081' . "\n\n";
    echo "Then open: http://localhost:8081\n";
    exit(0);
}
