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

function respond_player_data_save(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function coachlab_player_data_dir_shared_save(): string
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

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    respond_player_data_save(400, ['error' => 'Missing JSON request body.']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    respond_player_data_save(400, ['error' => 'Invalid JSON request body.']);
}

$teams = $data['teams'] ?? null;
if (!is_array($teams)) {
    respond_player_data_save(400, ['error' => 'Request must include a teams array.']);
}

$dir = coachlab_player_data_dir_shared_save();
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    respond_player_data_save(500, ['error' => 'Could not create player data directory.', 'path' => $dir]);
}

$payload = [
    'ok' => true,
    '_type' => 'coachlab_player_data',
    '_saved_at' => gmdate('c'),
    'activeTeamId' => $data['activeTeamId'] ?? null,
    'teams' => $teams,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($json)) {
    respond_player_data_save(500, ['error' => 'Could not encode player data JSON.']);
}

$path = $dir . '/coachlab_players.json';
if (file_put_contents($path, $json . PHP_EOL) === false) {
    respond_player_data_save(500, ['error' => 'Could not write player data file.', 'path' => $path]);
}

respond_player_data_save(200, [
    'ok' => true,
    'path' => $path,
]);
