<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/scrape-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$sourceId = $_GET['source_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    ce_json_error('Invalid source_id', 400);
}

$body = [];
$raw = file_get_contents('php://input');
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$themeId = trim((string) ($body['theme_id'] ?? $body['themeId'] ?? ''));
$slotId = trim((string) ($body['slot_id'] ?? $body['slotId'] ?? ''));
$searchLabel = trim((string) ($body['search_label'] ?? $body['searchLabel'] ?? ''));

$pdo = ce_db();
$result = ce_run_scraper_source(
    $pdo,
    $sourceId,
    $themeId !== '' ? $themeId : null,
    $slotId !== '' ? $slotId : null,
    $searchLabel !== '' ? $searchLabel : null,
);

ce_json_response($result);
