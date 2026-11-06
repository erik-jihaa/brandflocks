<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/sources-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$body = ce_read_json_body();
$fromClientId = trim((string) ($body['fromClientId'] ?? $body['sourceClientId'] ?? ''));
$toClientId = trim((string) ($body['toClientId'] ?? $body['targetClientId'] ?? ''));
$replace = !array_key_exists('replace', $body) || !empty($body['replace']);

try {
    ce_json_response(ce_copy_client_sources(ce_db(), $fromClientId, $toClientId, $replace));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 500);
}
