<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond_player_data(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function coachlab_player_data_dir_shared(): string
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

$path = coachlab_player_data_dir_shared() . '/coachlab_players.json';
if (!is_file($path)) {
    respond_player_data(200, [
        'ok' => true,
        'teams' => [],
        'activeTeamId' => null,
        '_meta' => ['source' => 'default'],
    ]);
}

$raw = file_get_contents($path);
if ($raw === false || trim($raw) === '') {
    respond_player_data(200, [
        'ok' => true,
        'teams' => [],
        'activeTeamId' => null,
        '_meta' => ['source' => 'empty_file'],
    ]);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    respond_player_data(500, ['error' => 'Player data file is not valid JSON.', 'path' => $path]);
}

respond_player_data(200, $data);
