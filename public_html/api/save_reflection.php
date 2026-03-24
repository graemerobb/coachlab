<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Allow: POST, OPTIONS');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

function respond_reflection(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function coachlab_reflection_data_dir(): string
{
    $candidates = [
        getenv('COACHLAB_PLAYER_DATA_DIR') ?: '',
        __DIR__ . '/player_data',
    ];

    foreach ($candidates as $candidate) {
        $value = rtrim(trim((string) $candidate), '/');
        if ($value === '') {
            continue;
        }
        if (is_dir($value)) {
            return $value;
        }
        $parent = dirname($value);
        if (is_dir($parent) && is_writable($parent)) {
            return $value;
        }
    }

    return __DIR__ . '/player_data';
}

function safe_reflection_segment(string $value, string $fallback): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?: '';
    $value = trim($value, '_-');
    return $value !== '' ? $value : $fallback;
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    respond_reflection(400, ['error' => 'Missing JSON request body.']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    respond_reflection(400, ['error' => 'Invalid JSON request body.']);
}

$playerName = trim((string) ($data['playerName'] ?? ''));
if ($playerName === '') {
    respond_reflection(400, ['error' => 'Reflection must include playerName.']);
}

$baseDir = coachlab_reflection_data_dir();
$playerKey = safe_reflection_segment($playerName, 'player');
$dateKey = safe_reflection_segment((string) ($data['reflectionDate'] ?? date('Y-m-d')), date('Y-m-d'));
$dir = $baseDir . '/reflections/' . $playerKey;

if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    respond_reflection(500, ['error' => 'Could not create reflection directory.', 'path' => $dir]);
}

$payload = $data;
$payload['_saved_at'] = gmdate('c');
$payload['_type'] = $payload['_type'] ?? 'frp_self_reflection';

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($json)) {
    respond_reflection(500, ['error' => 'Could not encode reflection JSON.']);
}

$path = $dir . '/' . $dateKey . '.json';
if (file_put_contents($path, $json . PHP_EOL) === false) {
    respond_reflection(500, ['error' => 'Could not write reflection file.', 'path' => $path]);
}

respond_reflection(200, [
    'ok' => true,
    'path' => $path,
]);
