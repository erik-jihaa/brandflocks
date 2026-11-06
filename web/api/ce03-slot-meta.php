<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/tone-styles.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$slotId = trim((string) ($body['slot_id'] ?? $body['slotId'] ?? ''));
if ($slotId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('slot_id is verplicht (uuid)', 400);
}

$pdo = ce_db();
$slotStmt = $pdo->prepare('SELECT id FROM content_slots WHERE id = :id LIMIT 1');
$slotStmt->execute(['id' => $slotId]);
if (!$slotStmt->fetch()) {
    ce_json_error('Slot niet gevonden', 404);
}

$meta = ce_ce03_webhook_meta_from_slot($pdo, $slotId);

ce_json_response([
    'ok' => true,
    'slotId' => $slotId,
    'meta' => $meta,
    'grenzenHasBlock' => trim((string) ($meta['grenzen_block'] ?? '')) !== '',
    'writeDirectiveChars' => strlen((string) ($meta['write_directive'] ?? '')),
    'nlWritingStyleChars' => strlen((string) ($meta['nl_writing_style_block'] ?? '')),
    'generatedAt' => gmdate('Y-m-d H:i:s') . ' UTC',
]);
