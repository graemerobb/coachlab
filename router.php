<?php

declare(strict_types=1);

$docroot = __DIR__ . '/public_html';
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uriPath === '/' || $uriPath === '') {
    $uriPath = '/coachlab.html';
}

$directFile = realpath($docroot . $uriPath);
if ($directFile !== false && str_starts_with($directFile, realpath($docroot)) && is_file($directFile)) {
    return false;
}

if (str_starts_with($uriPath, '/coachlab/')) {
    $mapped = substr($uriPath, strlen('/coachlab'));
    if ($mapped === '' || $mapped === '/') {
        $mapped = '/coachlab.html';
    }
    $target = realpath($docroot . $mapped);
    if ($target !== false && str_starts_with($target, realpath($docroot)) && is_file($target)) {
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            $_SERVER['SCRIPT_NAME'] = $mapped;
            $_SERVER['PHP_SELF'] = $mapped;
            $_SERVER['SCRIPT_FILENAME'] = $target;
            require $target;
            return true;
        }

        $mimeMap = [
            'html' => 'text/html; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'csv' => 'text/csv; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        readfile($target);
        return true;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found";
