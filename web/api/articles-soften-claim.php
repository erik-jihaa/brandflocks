<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/article-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$row = ce_fetch_article_by_slot($pdo, $slotId);
if (!$row) {
    ce_json_error('Article not found', 404);
}

$body = ce_read_json_body();
$result = ce_soften_claim_in_article($pdo, $slotId, $row, $body);
if (($result['ok'] ?? false) !== true) {
    ce_json_error((string) ($result['error'] ?? 'Herschrijven mislukt'), 502);
}

$content = (string) ($result['content'] ?? '');
ce_apply_article_patch($pdo, (string) $row['id'], ['content' => $content]);

ce_json_response([
    'ok' => true,
    'content' => $content,
]);
