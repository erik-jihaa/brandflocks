<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/publish-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/mappers.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$result = ce_publish_article_destinations($pdo, $slotId);

if (!$result['ok']) {
    ce_json_error($result['error'] ?? 'Publish mislukt', 502);
}

$row = ce_fetch_article_by_slot($pdo, $slotId);
ce_json_response([
    'ok' => true,
    'slotId' => $slotId,
    'postId' => $result['postId'] ?? null,
    'postUrl' => $result['postUrl'] ?? null,
    'destinations' => $result['destinations'] ?? [],
    'status' => ce_status_to_ui($row['status'] ?? null),
]);
