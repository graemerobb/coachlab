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
    echo json_encode([
        'error' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function openai_api_key(): string
{
    $candidates = [
        getenv('OPENAI_API_KEY') ?: '',
        $_ENV['OPENAI_API_KEY'] ?? '',
        $_SERVER['OPENAI_API_KEY'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string) $candidate);
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function call_openai_structured(mixed $input, array $schema, string $prompt, string $schemaName): array
{
    $payload = [
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-4o',
        'temperature' => 0,
        'messages' => [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => is_string($input) ? $input : json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $schemaName,
                'schema' => $schema,
                'strict' => false,
            ],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . openai_api_key(),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $debug = [
        'openai_request_payload' => $payload,
        'openai_http_status' => $code,
        'openai_curl_error' => $err ?: null,
        'openai_raw_response_text' => is_string($raw) ? $raw : null,
    ];

    if (!is_string($raw) || $raw === '') {
        respond(502, [
            'error' => $err ?: 'Empty OpenAI response',
            'debug' => $debug,
        ]);
    }

    $resp = json_decode($raw, true);
    $debug['openai_raw_response'] = is_array($resp) ? $resp : null;

    if ($code < 200 || $code >= 300) {
        respond(502, [
            'error' => 'OpenAI error',
            'detail' => $resp['error']['message'] ?? $raw,
            'debug' => $debug,
        ]);
    }

    if (!is_array($resp)) {
        respond(502, [
            'error' => 'Invalid OpenAI response',
            'debug' => $debug,
        ]);
    }

    $content = $resp['choices'][0]['message']['content'] ?? '';
    $json = json_decode((string) $content, true);

    $debug['raw_openai_content'] = (string) $content;
    $debug['raw_openai_content_decoded'] = is_array($json) ? $json : null;

    if (!is_array($json)) {
        respond(502, [
            'error' => 'Model did not return valid JSON',
            'debug' => $debug,
        ]);
    }

    return [
        'decoded' => $json,
        'raw_openai_response' => $resp,
        'raw_openai_content' => (string) $content,
        'debug' => $debug,
    ];
}

function build_schema(): array
{
    return [
        'name' => 'elite_player_matches',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'matches'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'matches' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'era', 'position', 'matchScore', 'why', 'comparison', 'shared_traits', 'watch_for'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'era' => ['type' => 'string'],
                            'position' => ['type' => 'string'],
                            'matchScore' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                            'why' => ['type' => 'string'],
                            'comparison' => ['type' => 'string'],
                            'shared_traits' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'watch_for' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    respond(400, ['error' => 'Missing JSON request body.']);
}

$request = json_decode($raw, true);
if (!is_array($request)) {
    respond(400, ['error' => 'Invalid JSON request body.']);
}

$player = $request['player'] ?? null;
$team = $request['team'] ?? null;
if (!is_array($player) || !is_array($team)) {
    respond(400, ['error' => 'Request must include team and player objects.']);
}

$apiKey = openai_api_key();
if ($apiKey === '') {
    respond(500, ['error' => 'OPENAI_API_KEY is not configured on the server.']);
}

$systemPrompt = <<<PROMPT
You are an expert football talent analyst.

Given a player's JSON profile from a coaching tool, identify elite footballers, past and present, whose playing profile best matches the player's current skill set.

Return JSON only.

Guidelines:
- Focus on present ability profile, not maximum potential.
- Prefer technically and tactically meaningful comparisons over famous names.
- Include a mix of current and historical players when appropriate.
- Use concise, coach-friendly language.
- "matchScore" is a 1-100 similarity score.
- "shared_traits" should be short phrases.
- "watch_for" should suggest what film or behaviors a coach should study in that elite player.
- If data is sparse, still return the best-effort matches and say so in the summary.
- Return 3 to 5 matches.
PROMPT;

$input = [
    'team' => $team,
    'player' => $player,
];
$result = call_openai_structured($input, build_schema()['schema'], $systemPrompt, 'elite_player_matches');
$parsed = $result['decoded'];

$parsed['_meta'] = [
    'model' => $result['raw_openai_response']['model'] ?? (getenv('OPENAI_MODEL') ?: 'gpt-4o'),
    'requested_at' => gmdate('c'),
    'player_name' => $player['name'] ?? '',
];

respond(200, $parsed);
