<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mailbox-helper.php';

$sourceId = $_GET['source_id'] ?? '';
$itemId = $_GET['newsletter_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    ce_json_error('Invalid source_id', 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET' && $itemId === '') {
    $items = ce_fetch_newsletter_items_for_source($pdo, $sourceId);
    ce_json_response(['items' => $items]);
}

if ($itemId !== '' && !preg_match('/^[0-9a-f-]{36}$/i', $itemId)) {
    ce_json_error('Invalid newsletter_id', 400);
}

if ($method === 'PATCH' && $itemId !== '') {
    $body = ce_read_json_body();
    try {
        $updated = ce_update_newsletter_item($pdo, $sourceId, $itemId, is_array($body) ? $body : []);
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    }
    if ($updated === null) {
        ce_json_error('Nieuwsbrief niet gevonden', 404);
    }
    ce_json_response(['item' => $updated]);
}

if ($method === 'DELETE' && $itemId !== '') {
    if (!ce_delete_newsletter_item($pdo, $sourceId, $itemId)) {
        ce_json_error('Nieuwsbrief niet gevonden', 404);
    }
    ce_json_response(['ok' => true, 'id' => $itemId]);
}

ce_json_error('Method not allowed', 405);
