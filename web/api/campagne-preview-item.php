<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/campagne-preview-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$campaignId = trim((string) ($_GET['campaign_id'] ?? ''));
if ($campaignId === '') {
    ce_json_error('campaign_id ontbreekt', 400);
}

$pdo = ce_db();
$clientId = ce_default_client_id($pdo);

if ($method === 'GET') {
    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($detail === null) {
        ce_json_error('Campagne niet gevonden', 404);
    }
    ce_json_response($detail);
}

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    try {
        ce_json_response(ce_campagne_preview_patch($pdo, $campaignId, $body, $clientId));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
