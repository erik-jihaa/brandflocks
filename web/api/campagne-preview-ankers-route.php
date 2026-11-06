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
$ids = $body['geoQuestionIds'] ?? $body['geo_question_ids'] ?? null;
if (!is_array($ids)) {
    ce_json_error('geoQuestionIds array is verplicht (1 tot 3 ids, of leeg om te wissen)', 400);
}

$pdo = ce_db();
$clientId = ce_default_client_id($pdo);

try {
    ce_campagne_preview_set_geo_ankers($pdo, $campaignId, $clientId, $ids);
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}

$detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
ce_json_response([
    'ok' => true,
    'campaign' => $detail['campaign'] ?? null,
    'slots' => $detail['slots'] ?? [],
    'availableGeoQuestions' => $detail['availableGeoQuestions'] ?? [],
]);
