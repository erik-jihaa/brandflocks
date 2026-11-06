<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

ce_require_destructive_confirmation('delete-client');

$body = ce_read_json_body();
$clientId = trim((string) ($body['clientId'] ?? ''));
$confirmCompanyName = trim((string) ($body['confirmCompanyName'] ?? $body['company'] ?? ''));

try {
    ce_json_response(ce_delete_client(ce_db(), $clientId, $confirmCompanyName));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
