<?php
// Router pour PHP built-in server (dev uniquement)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = __DIR__;

// Assets depuis data/ (photos, pdf-cache, pdf)
$dataRoutes = [
    '/uploads/'   => $base . '/data/uploads/',
    '/pdf-cache/' => $base . '/data/pdf-cache/',
    '/pdf/'       => $base . '/data/pdf/',
];
foreach ($dataRoutes as $prefix => $dir) {
    if (str_starts_with($path, $prefix)) {
        $file = $dir . substr($path, strlen($prefix));
        if (is_file($file)) {
            header('Content-Type: ' . (mime_content_type($file) ?: 'application/octet-stream'));
            readfile($file);
            exit;
        }
        http_response_code(404);
        exit;
    }
}

// Fichiers statiques depuis app/public/ (css, js, images…)
$static = $base . '/app/public' . $path;
if (is_file($static) && !str_ends_with($path, '.php')) {
    $mimes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    $ext = strtolower(pathinfo($static, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mimes[$ext] ?? mime_content_type($static)));
    readfile($static);
    exit;
}

// Fichier PHP dans app/public/ (api, etc.)
$phpFile = $base . '/app/public' . $path;
if (is_file($phpFile) && str_ends_with($path, '.php')) {
    chdir(dirname($phpFile));
    require $phpFile;
    exit;
}

// Application PHP (router principal)
chdir($base . '/app/public');
require $base . '/app/public/index.php';
