<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/archive-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    $detail = ce_fetch_archive_detail($pdo, $slotId);
    if (!$detail) {
        ce_json_error('Article not found', 404);
    }
    ce_json_response($detail);
}

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    $item = ce_update_archive_metadata($pdo, $slotId, $body);

    if (!$item) {
        ce_json_error('Article not found', 404);
    }

    ce_json_response($item);
}

ce_json_error('Method not allowed', 405);
