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

$pdo = ce_db();
$clientId = ce_default_client_id($pdo);
$body = ce_read_json_body();
$stage = strtolower(trim((string) ($body['stage'] ?? '')));
if ($stage !== '' && !in_array($stage, ['check', 'posts'], true)) {
    ce_json_error('stage moet check of posts zijn', 400);
}
$result = ce_campagne_preview_run_plan_check($pdo, $campaignId, $clientId, $stage === '' ? null : $stage);
if (($result['ok'] ?? false) !== true) {
    ce_json_error((string) ($result['error'] ?? 'Plan-check mislukt'), 400);
}

$detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
ce_json_response([
    'ok' => true,
    'planCheck' => $result,
    'campaign' => $detail['campaign'] ?? null,
    'slots' => $detail['slots'] ?? [],
]);
