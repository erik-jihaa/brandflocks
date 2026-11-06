<?php

declare(strict_types=1);

/**
 * Vul woorden_die_passen / woorden_die_niet_passen zonder andere narrative-velden te wijzigen.
 * POST { "client_id": "...", "woorden_die_passen": ["..."], "woorden_die_niet_passen": ["..."] }
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

/** @return list<string> */
function ce_admin_parse_word_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $items = [];
    foreach ($value as $item) {
        if (!is_string($item)) {
            continue;
        }
        $trimmed = trim($item);
        if ($trimmed !== '') {
            $items[] = $trimmed;
        }
    }

    return array_slice($items, 0, 20);
}

$pdo = ce_db();
$row = ce_fetch_current_brand_narrative($pdo, $clientId);
if (!$row) {
    ce_json_error('Geen brand narrative gevonden voor deze tenant', 404);
}

$passen = ce_admin_parse_word_list($body['woorden_die_passen'] ?? $body['woordenDiePassen'] ?? null);
$nietPassen = ce_admin_parse_word_list(
    $body['woorden_die_niet_passen'] ?? $body['woordenDieNietPassen'] ?? null,
);
if (count($passen) < 3 || count($nietPassen) < 3) {
    ce_json_error('woorden_die_passen en woorden_die_niet_passen vereisen elk minstens 3 items', 400);
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
    'woorden_die_passen' => $passen,
    'woorden_die_niet_passen' => $nietPassen,
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
    'kernformuleringen' => ce_hba_decode_json_list($row['kernformuleringen'] ?? []),
];

$saved = ce_save_brand_narrative($pdo, $clientId, $narrative, 'admin-woorden-patch');
$row = ce_fetch_current_brand_narrative($pdo, $clientId);
$missing = $row ? ce_brand_narrative_missing_fields($row) : CE_HBA_NARRATIVE_FIELDS;

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'woordenDiePassen' => ce_hba_decode_json_list($row['woorden_die_passen'] ?? []),
    'woordenDieNietPassen' => ce_hba_decode_json_list($row['woorden_die_niet_passen'] ?? []),
    'completeness' => ce_brand_narrative_completeness($pdo, $clientId),
    'missingOrInconsistent' => $missing,
]);
