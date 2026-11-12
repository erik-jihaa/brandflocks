<?php

declare(strict_types=1);

/**
 * Verwijdert alle bronnen met category = asana (tenant-wide of één client).
 * POST JSON: { "clientId": "uuid-optioneel" }
 * Vereist CE_RESET_TOKEN; op productie ook confirmEnvironment (zie content-reset).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/sources-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

ce_require_destructive_confirmation('delete-asana-sources');

$body = ce_read_json_body();
$clientIdRaw = trim((string) ($body['clientId'] ?? $body['client_id'] ?? ''));

try {
    $result = ce_delete_all_asana_sources(ce_db(), $clientIdRaw !== '' ? $clientIdRaw : null);
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
}

ce_json_response([
    'ok' => true,
    'deleted' => $result['deleted'],
    'failedIds' => $result['failed'],
    'affectedClientIds' => $result['clientIds'],
]);
