<?php

declare(strict_types=1);

/**
 * Slaat kernformuleringen op in de huidige brand_narratives-rij.
 * POST { "client_id": "...", "kernformuleringen": ["...", "...", "..."] }
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$clientId = trim((string) ($body['client_id'] ?? $body['clientId'] ?? ''));
if ($clientId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('client_id ontbreekt of is ongeldig', 400);
}

$lines = $body['kernformuleringen'] ?? null;
if (!is_array($lines)) {
    ce_json_error('kernformuleringen moet een array zijn', 400);
}

$kernformuleringen = [];
foreach ($lines as $line) {
    if (!is_string($line)) {
        continue;
    }
    $trimmed = trim($line);
    if ($trimmed !== '') {
        $kernformuleringen[] = $trimmed;
    }
}
$kernformuleringen = array_slice($kernformuleringen, 0, 5);
if ($kernformuleringen === []) {
    ce_json_error('Minstens één kernformulering is verplicht', 400);
}

$pdo = ce_db();
$row = ce_fetch_current_brand_narrative($pdo, $clientId);
if (!$row) {
    ce_json_error('Geen brand narrative gevonden voor deze tenant', 404);
}

$narrative = [
    'archetype' => (string) ($row['archetype'] ?? ''),
    'doel' => (string) ($row['doel'] ?? ''),
    'foe' => (string) ($row['foe'] ?? ''),
    'spanning' => (string) ($row['spanning'] ?? ''),
    'mechanisme' => (string) ($row['mechanisme'] ?? ''),
    'geloofwaardigheidstest' => (string) ($row['geloofwaardigheidstest'] ?? ''),
    'verboden_logica' => ce_hba_decode_json_list($row['verboden_logica'] ?? []),
    'bewijsvelden' => ce_hba_decode_json_list($row['bewijsvelden'] ?? []),
    'tone_of_behaviour' => (string) ($row['tone_of_behaviour'] ?? ''),
    'woorden_die_passen' => ce_hba_decode_json_list($row['woorden_die_passen'] ?? []),
    'woorden_die_niet_passen' => ce_hba_decode_json_list($row['woorden_die_niet_passen'] ?? []),
    'goede_voorbeelden' => ce_hba_decode_json_list($row['goede_voorbeelden'] ?? []),
    'zwakke_voorbeelden' => ce_hba_decode_json_list($row['zwakke_voorbeelden'] ?? []),
    'contextprofiel' => (string) ($row['contextprofiel'] ?? ''),
    'distinctiviteit' => (string) ($row['distinctiviteit'] ?? ''),
    'wat_bewust_niet' => (string) ($row['wat_bewust_niet'] ?? ''),
    'branche_code' => (string) ($row['branche_code'] ?? ''),
    'identity_intro' => (string) ($row['identity_intro'] ?? ''),
    'default_platforms' => ce_hba_decode_json_list($row['default_platforms'] ?? []),
    'target_post_volume_per_month' => isset($row['target_post_volume_per_month'])
        ? (int) $row['target_post_volume_per_month']
        : null,
    'kernformuleringen' => $kernformuleringen,
];

$saved = ce_save_brand_narrative($pdo, $clientId, $narrative, 'admin-kernformuleringen');

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'kernformuleringen' => $saved['kernformuleringen'] ?? $kernformuleringen,
]);
