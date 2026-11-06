<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/client-context.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$body = ce_read_json_body();
$clientId = ce_resolve_client_id($pdo, trim((string) ($body['clientId'] ?? $body['client_id'] ?? '')));

$result = ce_backfill_theme_source_article_urls($pdo, $clientId);

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    ...$result,
]);
