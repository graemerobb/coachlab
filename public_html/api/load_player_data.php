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

function normalize_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?: '';
    return trim($value);
}

function merge_reflections(array $teams, string $baseDir): array
{
    $reflectionRoot = $baseDir . '/reflections';
    if (!is_dir($reflectionRoot)) {
        return $teams;
    }

    $latestByPlayer = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($reflectionRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') {
            continue;
        }
        $raw = file_get_contents($file->getPathname());
        if ($raw === false || trim($raw) === '') {
            continue;
        }
        $reflection = json_decode($raw, true);
        if (!is_array($reflection) || ($reflection['_type'] ?? '') !== 'frp_self_reflection') {
            continue;
        }
        $playerKey = normalize_key((string) ($reflection['playerName'] ?? ''));
        if ($playerKey === '') {
            continue;
        }
        $currentDate = (string) ($reflection['reflectionDate'] ?? '');
        $existingDate = (string) (($latestByPlayer[$playerKey]['reflectionDate'] ?? ''));
        if (!isset($latestByPlayer[$playerKey]) || $currentDate >= $existingDate) {
            $latestByPlayer[$playerKey] = $reflection;
        }
    }

    return array_map(function (array $team) use ($latestByPlayer): array {
        $teamKey = normalize_key((string) ($team['name'] ?? ''));
        $team['squad'] = array_map(function (array $player) use ($latestByPlayer, $teamKey): array {
            $playerKey = normalize_key((string) ($player['name'] ?? ''));
            if ($playerKey === '' || !isset($latestByPlayer[$playerKey])) {
                return $player;
            }
            $reflection = $latestByPlayer[$playerKey];
            $reflectionTeamKey = normalize_key((string) ($reflection['teamName'] ?? ''));
            if ($reflectionTeamKey !== '' && $teamKey !== '' && $reflectionTeamKey !== $teamKey) {
                return $player;
            }
            return array_merge($player, [
                'selfRatings' => array_merge($player['selfRatings'] ?? [], $reflection['selfRatings'] ?? []),
                'goals' => array_merge($player['goals'] ?? [], $reflection['goals'] ?? []),
                'wellbeing' => array_merge($player['wellbeing'] ?? [], $reflection['wellbeing'] ?? []),
                'reflectionData' => $reflection,
            ]);
        }, $team['squad'] ?? []);
        return $team;
    }, $teams);
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

$data['teams'] = merge_reflections($data['teams'] ?? [], coachlab_player_data_dir_shared());

respond_player_data(200, $data);
