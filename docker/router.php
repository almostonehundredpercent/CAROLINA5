<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . '/../public' . $uri;

if ($uri !== '/' && is_file($file)) {
    if (preg_match('/\.(?:css|js|jpg|jpeg|png|webp|svg|ico|woff2?)$/i', $file)) {
        header('Cache-Control: public, max-age=31536000, immutable');
    }
    return false;
}

require __DIR__ . '/../public/index.php';
