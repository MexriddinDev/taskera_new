<?php

$publicPath = __DIR__ . '/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// If the SPA directory or any file within it is requested
if (str_starts_with($uri, '/web_sites') || $uri === '/web_sites') {
    $filePath = $publicPath . $uri;
    // Serve existing files directly (assets, index.html)
    if ($uri !== '/web_sites' && is_file($filePath)) {
        return false;
    }
    // Everything else serves the SPA index.html
    readfile($publicPath . '/web_sites/index.html');
    return true;
}

// Standard Laravel behavior: serve static files or fallback to index.php
if ($uri !== '/' && is_file($publicPath . $uri)) {
    return false;
}

require_once $publicPath . '/index.php';
