<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/linkedin-oauth-helper.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$body = ce_read_json_body();
$clientId = ce_resolve_client_id($pdo, (string) ($body['clientId'] ?? ''));
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 400);
}

ce_linkedin_oauth_disconnect($pdo, $clientId);

ce_json_response(['ok' => true]);
