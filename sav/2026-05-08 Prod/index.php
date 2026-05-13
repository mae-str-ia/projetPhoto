<?php
// Simple password protection
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'roger' || $_SERVER['PHP_AUTH_PW'] !== 'livre2026') {
    header('WWW-Authenticate: Basic realm="Accès protégé - projetPhoto"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Accès refusé';
    exit;
}

// Serve from app/public/ directory
chdir(__DIR__ . '/app/public');
require __DIR__ . '/app/public/index.php';
