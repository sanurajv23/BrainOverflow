<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$requestPath = str_replace('\\', '/', $requestPath);
$pathSegments = array_values(array_filter(explode('/', trim($requestPath, '/')), 'strlen'));
$blockedDirectories = ['config', 'includes'];
$blockedExtensions = ['env', 'ini', 'conf', 'sql', 'log', 'bak', 'old', 'dist', 'example'];
$isBlocked = false;

foreach ($pathSegments as $pathSegment) {
    if (str_starts_with($pathSegment, '.') || in_array(strtolower($pathSegment), $blockedDirectories, true)) {
        $isBlocked = true;
        break;
    }
}

$requestedExtension = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));

if ($requestedExtension !== '' && in_array($requestedExtension, $blockedExtensions, true)) {
    $isBlocked = true;
}

if ($isBlocked) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    exit;
}

if ($requestPath === '/') {
    require __DIR__ . '/index.php';
    exit;
}

$requestedFile = __DIR__ . $requestPath;

if (is_file($requestedFile)) {
    return false;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Not found.';
