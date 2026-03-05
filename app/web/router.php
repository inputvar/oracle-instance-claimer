<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/' || $uri === '/index.html') {
    header('Content-Type: text/html');
    readfile(__DIR__ . '/index.html');
    return true;
}

if ($uri === '/stream') {
    return require __DIR__ . '/stream.php';
}

if ($uri === '/health') {
    header('Content-Type: text/plain');
    echo 'ok';
    return true;
}

// Serve static files
$filePath = __DIR__ . $uri;
if (file_exists($filePath) && is_file($filePath)) {
    return false; // Let PHP built-in server handle it
}

// 404
http_response_code(404);
echo '404 Not Found';
