<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/campagne-preview-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$body = ce_read_json_body();
$campaignId = trim((string) ($body['campaignId'] ?? $body['campaign_id'] ?? $_GET['campaign_id'] ?? ''));
$clientId = trim((string) ($body['clientId'] ?? $body['client_id'] ?? ''));

if ($campaignId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $campaignId)) {
    ce_json_error('campaignId ontbreekt of ongeldig', 400);
}

try {
    $result = ce_campagne_soft_delete($pdo, $campaignId, $clientId !== '' ? $clientId : null);
    ce_json_response($result);
} catch (Throwable $e) {
    ce_json_error($e->getMessage(), 400);
}
