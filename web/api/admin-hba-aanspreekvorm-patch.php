<?php

declare(strict_types=1);

/**
 * Zet aanspreekvorm (je/u) op de huidige brand_narratives-rij.
 * POST { "client_id": "...", "aanspreekvorm": "je" | "u" | "" }
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/hba-hint-verificatie.php';

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

$vorm = ce_hba_normalize_aanspreekvorm(
    (string) ($body['aanspreekvorm'] ?? $body['aanspreekVorm'] ?? '')
);

$pdo = ce_db();
$row = ce_fetch_current_brand_narrative($pdo, $clientId);
if (!$row) {
    ce_json_error('Geen brand narrative gevonden voor deze tenant', 404);
}

ce_hba_persist_aanspreekvorm($pdo, (string) ($row['id'] ?? ''), $vorm);
$updated = ce_fetch_current_brand_narrative($pdo, $clientId);

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'narrativeId' => (string) ($updated['id'] ?? ''),
    'aanspreekvorm' => ce_hba_normalize_aanspreekvorm((string) ($updated['aanspreekvorm'] ?? '')),
]);
