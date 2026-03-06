<?php
/**
 * 🛠️ WAZIO ROBUST ROUTER
 * Fixes assets loading and clean URLs for local development.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Identify the relative path (stripping /wazio/ if present)
$relativePath = $uri;
if (strpos($uri, '/wazio/') === 0) {
    $relativePath = substr($uri, 7);
}

// 2. Resolve the actual file path on disk
$baseDir = __DIR__;
$filePath = $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

// 3. Serve static assets if they exist and aren't PHP files
if ($relativePath !== '' && file_exists($filePath) && !is_dir($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);

    if ($ext !== 'php') {
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'json' => 'application/json',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'ico' => 'image/x-icon',
        ];

        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }

        readfile($filePath);
        return true;
    }
}

// 4. For everything else (or if it's a PHP file), delegate to index.php
// We normalize SCRIPT_NAME to help common routing logic
$_SERVER['SCRIPT_NAME'] = '/wazio/index.php';

require_once $baseDir . '/index.php';
return true;
