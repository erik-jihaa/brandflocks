<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/integrations-status-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_auth_is_authenticated()) {
    ce_json_error('Login vereist', 401);
}

$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo);
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 404);
}

$result = ce_integrations_status_for_client($pdo, $clientId);

ce_json_response([
    'ok' => true,
    'integrations' => $result['integrations'],
    'summary' => $result['summary'],
    'publishMode' => $result['publishMode'],
]);
