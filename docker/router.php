<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__.'/../public'.$uri;

if ($uri !== '/' && is_file($file)) {
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2'];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: '.($types[$extension] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($file);
    exit;
}

require __DIR__.'/../public/index.php';
