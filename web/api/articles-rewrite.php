<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/review-edits-repository.php';
require_once __DIR__ . '/fastlane-repository.php';
require_once __DIR__ . '/tone-styles.php';

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

$status = strtolower(trim((string) ($row['status'] ?? 'draft')));
if (!in_array($status, ['draft', 'feedback'], true)) {
    ce_json_error('Rewrite is alleen mogelijk voor concepten in redactie.', 409);
}

if (ce_fetch_review_edits($pdo, $slotId, 'pending') !== []) {
    ce_json_error('Los eerst open review-wijzigingen op voordat je herschrijft.', 409);
}

$body = ce_read_json_body();
ce_apply_article_patch($pdo, (string) $row['id'], $body);

// CE-03 leest draft-meta; zorg dat status + directive kloppen vóór webhook.
if ($status === 'feedback') {
    $pdo->prepare("UPDATE articles SET status = 'draft' WHERE id = :id")->execute(['id' => (string) $row['id']]);
}

$ce03Meta = ce_ce03_webhook_meta_from_slot($pdo, $slotId);

$trigger = ce_trigger_ce03_write($slotId, true, $ce03Meta);
if (($trigger['ok'] ?? false) !== true) {
    $message = (string) ($trigger['error'] ?? 'CE-03 kon niet worden gestart.');
    ce_json_error($message, 503);
}

ce_json_response([
    'ok' => true,
    'writing' => true,
    'slotId' => $slotId,
    'webhookTriggered' => (bool) ($trigger['webhookTriggered'] ?? true),
]);
