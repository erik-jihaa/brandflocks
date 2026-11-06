<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/linkedin-engagement-repository.php';
require_once __DIR__ . '/client-context.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

if (!ce_reset_token_valid()) {
    ce_require_auth($pdo);
}

$body = [];
$raw = file_get_contents('php://input');
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$clientId = trim((string) ($body['client_id'] ?? $body['clientId'] ?? ''));

// Alleen service-aanroepen en platform-beheerders synchroniseren alle tenants.
$clientId = ce_restrict_sync_scope_to_session($pdo, $clientId);

if ($clientId !== '') {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        ce_json_error('Invalid client_id', 400);
    }

    $batch = ce_linkedin_sync_client_sources($pdo, $clientId);
    $result = [
        'clients' => 1,
        'sources' => (int) ($batch['sources'] ?? 0),
        'ingested' => (int) ($batch['ingested'] ?? 0),
        'syncedArticles' => (int) ($batch['syncedArticles'] ?? 0),
    ];
    $snapshotClientId = $clientId;
} else {
    $result = ce_linkedin_sync_all_clients($pdo);
    $snapshotClientId = ce_resolve_client_id($pdo);
}

$result['snapshot'] = ce_linkedin_engagement_snapshot($pdo, $snapshotClientId);

ce_json_response(['ok' => true, ...$result]);
