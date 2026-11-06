<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sources-repository.php';

$sourceId = $_GET['source_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    ce_json_error('Invalid source_id', 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    $source = ce_fetch_source($pdo, $sourceId);
    if (!$source) {
        ce_json_error('Source not found', 404);
    }
    ce_json_response($source);
}

$body = null;
if ($method === 'PATCH' || $method === 'POST') {
    $body = ce_read_json_body();
}

if ($method === 'PATCH') {
    $updated = ce_update_source($pdo, $sourceId, $body ?? []);
    if (!$updated) {
        ce_json_error('Source not found', 404);
    }
    ce_json_response($updated);
}

$deleteViaPost = $method === 'POST' && is_array($body) && ($body['action'] ?? '') === 'delete';
if ($method === 'DELETE' || $deleteViaPost) {
    if (!ce_delete_source($pdo, $sourceId)) {
        ce_json_error('Source not found', 404);
    }
    ce_json_response(['ok' => true, 'id' => $sourceId]);
}

ce_json_error('Method not allowed', 405);
