<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/sources-sync-repository.php';
require_once __DIR__ . '/client-context.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

if (!ce_reset_token_valid()) {
    ce_require_auth($pdo);
}

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

$clientId = trim((string) ($body['client_id'] ?? $body['clientId'] ?? $_GET['client_id'] ?? ''));
$force = filter_var($body['force'] ?? $_GET['force'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Alleen service-aanroepen en platform-beheerders werken over tenants heen;
// een ingelogde tenant wordt op zijn eigen client vastgezet.
$clientId = ce_restrict_sync_scope_to_session($pdo, $clientId);

if ($method === 'GET') {
    if ($clientId !== '') {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
            ce_json_error('Invalid client_id', 400);
        }

        ce_json_response([
            'ok' => true,
            'clientId' => $clientId,
            'status' => ce_client_source_sync_status($pdo, $clientId),
            'sources' => ce_fetch_active_sources_for_client($pdo, $clientId),
        ]);
    }

    ce_json_response([
        'ok' => true,
        'overview' => ce_sources_sync_overview($pdo),
    ]);
}

if ($clientId !== '') {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        ce_json_error('Invalid client_id', 400);
    }

    $batch = ce_sync_client_sources($pdo, $clientId, $force);
    ce_json_response([
        'ok' => true,
        ...$batch,
    ]);
}

$result = ce_sync_all_due_client_sources($pdo, $force);
ce_json_response([
    'ok' => true,
    ...$result,
]);
