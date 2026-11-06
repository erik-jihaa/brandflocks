<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sources-repository.php';

$sourceId = $_GET['source_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    ce_json_error('Invalid source_id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = null;
$raw = file_get_contents('php://input');
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$pdo = ce_db();
require_once __DIR__ . '/client-context.php';
ce_require_resource_access($pdo, 'source', $sourceId);
$updated = ce_check_source($pdo, $sourceId, $body);
if (!$updated) {
    ce_json_error('Source not found', 404);
}

ce_json_response($updated);
