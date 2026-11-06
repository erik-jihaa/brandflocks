<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/slot-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

if (!ce_slot_exists($pdo, $slotId)) {
    ce_json_error('Slot not found', 404);
}

require_once __DIR__ . '/archive-repository.php';

$body = ce_read_json_body();
if ($body !== [] && ce_fetch_article_by_slot($pdo, $slotId)) {
    ce_update_archive_metadata($pdo, $slotId, $body);
}

ce_archive_slot($pdo, $slotId);
ce_json_response(['ok' => true, 'slotId' => $slotId, 'action' => 'archived']);
