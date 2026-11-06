<?php

declare(strict_types=1);

/**
 * Hermet onderzoeks-oordelen voor één slot (staging/debug).
 * POST { "client_id": "...", "slot_id": "...", "run"?: true }
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/hba-v2-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$clientId = trim((string) ($body['client_id'] ?? $body['clientId'] ?? ''));
$slotId = trim((string) ($body['slot_id'] ?? $body['slotId'] ?? ''));
$run = !empty($body['run']);

if ($clientId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('client_id ontbreekt of is ongeldig', 400);
}
if ($slotId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('slot_id ontbreekt of is ongeldig', 400);
}

$pdo = ce_db();
ce_set_active_client_id($clientId);

$result = $run
    ? ce_hba_v2_run_research_verdicts($pdo, $clientId, $slotId)
    : ce_hba_v2_load_research_verdicts($pdo, $clientId, $slotId);

ce_json_response([
    'ok' => ($result['ok'] ?? false) === true,
    'clientId' => $clientId,
    'slotId' => $slotId,
    'mode' => $run ? 'run' : 'load',
    'code' => $result['code'] ?? null,
    'error' => $result['error'] ?? null,
    'watOntbreekt' => $result['watOntbreekt'] ?? null,
    'verdicts' => $result['verdicts'] ?? [],
    'positioningArchetype' => $result['positioningArchetype'] ?? null,
]);
