<?php

declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if (str_starts_with($path, '/api/')) {
    require __DIR__ . '/backend/public/index.php';
    return true;
}

$publicRoot = realpath(__DIR__ . '/public');
$requestedFile = realpath(__DIR__ . '/public' . $path);

if ($publicRoot !== false && $requestedFile !== false && str_starts_with($requestedFile, $publicRoot) && is_file($requestedFile)) {
    $extensions = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];
    $extension = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($extensions[$extension] ?? 'application/octet-stream'));
    readfile($requestedFile);
    return true;
}

header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/public/index.html');
return true;
