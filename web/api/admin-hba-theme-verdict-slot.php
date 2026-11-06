<?php

declare(strict_types=1);

/**
 * Hermet thema-oordeel voor één theme_id of fastlane-slot (staging/debug).
 * POST { "client_id": "...", "theme_id"?: "...", "slot_id"?: "..." }
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
$themeId = trim((string) ($body['theme_id'] ?? $body['themeId'] ?? ''));
$slotId = trim((string) ($body['slot_id'] ?? $body['slotId'] ?? ''));

if ($clientId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('client_id ontbreekt of is ongeldig', 400);
}

$filter = $themeId !== '' ? [$themeId] : ($slotId !== '' ? [$slotId] : []);
if ($filter === []) {
    ce_json_error('theme_id of slot_id verplicht', 400);
}

$pdo = ce_db();
ce_set_active_client_id($clientId);
$debug = !empty($body['debug']);
$resolvedThemes = ce_hba_v2_theme_rows_by_ids($pdo, $filter);
$result = ce_hba_v2_run_theme_verdicts($pdo, $clientId, null, $filter);

$response = [
    'ok' => ($result['ok'] ?? false) === true,
    'clientId' => $clientId,
    'filter' => $filter,
    'verdicts' => $result['verdicts'] ?? [],
    'error' => $result['error'] ?? null,
    'code' => $result['code'] ?? null,
];
if ($debug) {
    require_once __DIR__ . '/theme-repository.php';
    require_once __DIR__ . '/slot-repository.php';
    $slotId = $slotId !== '' ? $slotId : ($filter[0] ?? '');
    $response['debug'] = [
        'resolvedThemeCount' => count($resolvedThemes),
        'resolvedThemes' => $resolvedThemes,
        'slotContext' => $slotId !== '' ? ce_resolve_slot_research_context($pdo, $slotId) : null,
        'fastlaneRow' => $slotId !== '' ? ce_fetch_fastlane_slot_row($pdo, $slotId) : null,
        'gate' => ce_hba_v2_guardrail_gate($pdo, $clientId),
    ];
}
ce_json_response($response);
