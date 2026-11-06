<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/publications-repository.php';

$pdo = ce_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$articleId = trim((string) ($_GET['article_id'] ?? ''));
if ($articleId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $articleId)) {
    ce_json_error('article_id ontbreekt of is ongeldig', 400);
}

if ($method === 'GET') {
    ce_json_response([
        'ok' => true,
        'articleId' => $articleId,
        'publications' => ce_fetch_publications_for_article($pdo, $articleId),
    ]);
}

if ($method !== 'POST' && $method !== 'PATCH') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$platform = strtolower(trim((string) ($body['platform'] ?? 'manual')));
$action = trim((string) ($body['action'] ?? ''));

if ($action === 'geplaatst' || ($body['status'] ?? '') === 'gepubliceerd') {
    $saved = ce_upsert_publication($pdo, $articleId, $platform !== '' ? $platform : 'manual', [
        'status' => 'gepubliceerd',
        'published_at' => date('Y-m-d H:i:s'),
        'external_url' => $body['externalUrl'] ?? $body['external_url'] ?? null,
        'error_message' => null,
    ]);
    ce_json_response(['ok' => true, 'publication' => $saved]);
}

if ($action === 'mislukt' || ($body['status'] ?? '') === 'mislukt') {
    $saved = ce_upsert_publication($pdo, $articleId, $platform !== '' ? $platform : 'manual', [
        'status' => 'mislukt',
        'error_message' => trim((string) ($body['errorMessage'] ?? $body['error_message'] ?? 'Publicatie mislukt')),
        'attempts' => (int) ($body['attempts'] ?? 1),
    ]);
    ce_json_response(['ok' => true, 'publication' => $saved]);
}

$saved = ce_upsert_publication($pdo, $articleId, $platform !== '' ? $platform : 'manual', [
    'status' => trim((string) ($body['status'] ?? 'gepland')),
    'scheduled_at' => $body['scheduledAt'] ?? $body['scheduled_at'] ?? null,
    'external_url' => $body['externalUrl'] ?? $body['external_url'] ?? null,
    'external_id' => $body['externalId'] ?? $body['external_id'] ?? null,
    'error_message' => $body['errorMessage'] ?? $body['error_message'] ?? null,
]);
ce_json_response(['ok' => true, 'publication' => $saved]);