<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/campagne-preview-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$campaignId = trim((string) ($_GET['campaign_id'] ?? ''));
if ($campaignId === '') {
    ce_json_error('campaign_id ontbreekt', 400);
}

$body = ce_read_json_body();
$url = trim((string) ($body['productPageUrl'] ?? $body['product_page_url'] ?? $body['url'] ?? ''));
if ($url === '') {
    ce_json_error('productPageUrl is verplicht', 400);
}

$pdo = ce_db();
$clientId = ce_default_client_id($pdo);

$result = ce_campagne_preview_run_product_extract($pdo, $campaignId, $clientId, $url);
if (($result['ok'] ?? false) !== true) {
    ce_json_error((string) ($result['error'] ?? 'Extract mislukt'), 400);
}

$detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
ce_json_response([
    'ok' => true,
    'productExtract' => $result,
    'campaign' => $detail['campaign'] ?? null,
    'slots' => $detail['slots'] ?? [],
]);
