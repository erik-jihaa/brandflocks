<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/slot-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$uiStatus = strtolower(trim((string) ($body['status'] ?? '')));
$allowed = ['pending', 'feedback', 'approved', 'published', 'draft'];
if (!in_array($uiStatus, $allowed, true)) {
    ce_json_error('Invalid status', 400);
}

$pdo = ce_db();
$row = ce_fetch_article_by_slot($pdo, $slotId);
if (!$row) {
    ce_json_error('Article not found', 404);
}

ce_apply_ui_status($pdo, $slotId, (string) $row['id'], $uiStatus);

$updated = ce_fetch_article_by_slot($pdo, $slotId);
ce_json_response([
    'ok' => true,
    'slotId' => $slotId,
    'status' => ce_resolve_ui_status($updated['status'] ?? null, $updated['slot_status'] ?? null),
    'slotStatus' => ce_status_to_ui($updated['slot_status'] ?? null),
    'articleStatus' => ce_status_to_ui($updated['status'] ?? null),
]);
