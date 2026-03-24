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

function respond_snapshot(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function coachlab_player_data_dir(): string
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

function safe_segment(string $value, string $fallback): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?: '';
    $value = trim($value, '_-');
    return $value !== '' ? $value : $fallback;
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    respond_snapshot(400, ['error' => 'Missing JSON request body.']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    respond_snapshot(400, ['error' => 'Invalid JSON request body.']);
}

$team = $data['team'] ?? null;
$player = $data['player'] ?? null;
$snapshot = $data['snapshot'] ?? null;
if (!is_array($team) || !is_array($player) || !is_array($snapshot)) {
    respond_snapshot(400, ['error' => 'Request must include team, player, and snapshot objects.']);
}

$baseDir = coachlab_player_data_dir();
$teamId = safe_segment((string) ($team['id'] ?? ''), 'team');
$playerId = safe_segment((string) ($player['id'] ?? ''), 'player');
$snapshotDate = safe_segment((string) ($snapshot['date'] ?? date('Y-m-d')), date('Y-m-d'));

$dir = $baseDir . '/snapshots/' . $teamId . '/' . $playerId;
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    respond_snapshot(500, ['error' => 'Could not create snapshot directory.', 'path' => $dir]);
}

$filename = $snapshotDate . '.json';
$fullPath = $dir . '/' . $filename;

$payload = [
    '_type' => 'coachlab_player_snapshot',
    '_saved_at' => gmdate('c'),
    'team' => $team,
    'player' => $player,
    'snapshot' => $snapshot,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($json)) {
    respond_snapshot(500, ['error' => 'Could not encode snapshot JSON.']);
}

if (file_put_contents($fullPath, $json . PHP_EOL) === false) {
    respond_snapshot(500, ['error' => 'Could not write snapshot file.', 'path' => $fullPath]);
}

respond_snapshot(200, [
    'ok' => true,
    'path' => $fullPath,
    'file' => $filename,
]);
