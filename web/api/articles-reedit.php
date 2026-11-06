<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/review-edits-repository.php';
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
    ce_json_error('Re-edit is alleen mogelijk voor concepten in redactie.', 409);
}

if (ce_fetch_review_edits($pdo, $slotId, 'pending') !== []) {
    ce_json_error('Los eerst open review-wijzigingen op voordat je opnieuw bewerkt.', 409);
}

$body = ce_read_json_body();
ce_apply_article_patch($pdo, (string) $row['id'], $body);

$row = ce_fetch_article_by_slot($pdo, $slotId) ?? $row;
$result = ce_reedit_article_from_notes($pdo, $slotId, $row, $body);
if (($result['ok'] ?? false) !== true) {
    ce_json_error((string) ($result['error'] ?? 'Re-edit mislukt'), 502);
}

ce_apply_article_patch($pdo, (string) $row['id'], [
    'headline' => (string) ($result['headline'] ?? ''),
    'content' => (string) ($result['content'] ?? ''),
]);

if ($status === 'feedback') {
    $pdo->prepare("UPDATE articles SET status = 'draft' WHERE id = :id")->execute(['id' => (string) $row['id']]);
}

ce_json_response([
    'ok' => true,
    'headline' => (string) ($result['headline'] ?? ''),
    'content' => (string) ($result['content'] ?? ''),
]);
