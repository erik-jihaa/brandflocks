<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/llm-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

@set_time_limit(0);
ignore_user_abort(true);

$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }
}

$clientId = trim((string) ($body['clientId'] ?? $body['client_id'] ?? $_GET['client_id'] ?? ''));
if ($clientId !== '' && !preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('client_id ongeldig', 400);
}

$kindRaw = trim((string) ($body['kind'] ?? $_GET['kind'] ?? 'both'));
$kinds = $kindRaw === 'both' ? ['geo', 'seo'] : [ce_llm_question_kind($kindRaw)];

if (($_GET['probe'] ?? '') === '1') {
    $keys = ce_llm_visibility_api_keys();
    $gemini = ce_llm_visibility_ask_model('gemini', 'Antwoord met alleen: ok', $keys);
    $search = ce_google_custom_search_config();
    ce_json_response([
        'ok' => $gemini['error'] === '',
        'configured' => [
            'chatgpt' => trim((string) ($keys['openai'] ?? '')) !== '',
            'perplexity' => trim((string) ($keys['perplexity'] ?? '')) !== '',
            'claude' => trim((string) ($keys['anthropic'] ?? '')) !== '',
            'gemini' => trim((string) ($keys['gemini'] ?? '')) !== '',
            'googleSearch' => $search['key'] !== '' && $search['cx'] !== '',
        ],
        'gemini' => [
            'ok' => $gemini['error'] === '',
            'error' => $gemini['error'],
            'sample' => mb_substr($gemini['text'], 0, 80),
        ],
    ]);
}

ce_ensure_llm_schema($pdo);

$runs = [];
foreach ($kinds as $kind) {
    $runs[$kind] = ce_run_findability_checks($pdo, $clientId !== '' ? $clientId : null, $kind);
}

$reportKind = in_array('geo', $kinds, true) ? 'geo' : 'seo';
if ($clientId !== '' && count($kinds) === 1) {
    $reportKind = $kinds[0];
}

ce_json_response([
    'ok' => array_reduce($runs, static fn (bool $carry, array $run): bool => $carry || (($run['ok'] ?? false) === true), false),
    'clientId' => $clientId !== '' ? $clientId : null,
    'runs' => $runs,
    'report' => $clientId !== '' ? [
        'geo' => ce_fetch_llm_visibility_report($pdo, $clientId, 'geo'),
        'seo' => ce_fetch_llm_visibility_report($pdo, $clientId, 'seo'),
    ] : null,
    'reportKind' => $reportKind,
]);
