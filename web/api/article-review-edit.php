<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/review-edits-repository.php';

$slotId = $_GET['slot_id'] ?? '';
$editId = $_GET['edit_id'] ?? '';

if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId) || !preg_match('/^[0-9a-f-]{36}$/i', $editId)) {
    ce_json_error('Invalid id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'PATCH') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();

// Gedeeltelijke voortgang (okay/delete per change) — edit blijft pending.
if (array_key_exists('progress', $body)) {
    $progress = $body['progress'];
    if (!is_array($progress)) {
        ce_json_error('Invalid progress', 400);
    }

    $pdo = ce_db();
    $row = ce_fetch_article_by_slot($pdo, $slotId);
    if (!$row) {
        ce_json_error('Article not found', 404);
    }

    $updated = ce_save_review_edit_progress($pdo, $editId, $progress, $slotId);
    if (!$updated) {
        ce_json_error('Review edit not found', 404);
    }

    if (($updated['slotId'] ?? '') !== $slotId) {
        ce_json_error('Review edit not found', 404);
    }

    ce_json_response([
        'edit' => $updated,
        'reviewEdits' => ce_fetch_review_edits($pdo, $slotId),
    ]);
}

$decision = strtolower(trim((string) ($body['status'] ?? '')));
if (!in_array($decision, ['accepted', 'rejected'], true)) {
    ce_json_error('Invalid status', 400);
}

$resolvedContent = trim((string) ($body['content'] ?? ''));
$contentOverride = $resolvedContent !== '' ? $resolvedContent : null;

$pdo = ce_db();
$row = ce_fetch_article_by_slot($pdo, $slotId);
if (!$row) {
    ce_json_error('Article not found', 404);
}

$result = ce_resolve_review_edit($pdo, $editId, $decision, $contentOverride, $slotId);
if (!$result) {
    ce_json_error('Review edit not found', 404);
}

if (($result['edit']['slotId'] ?? '') !== $slotId) {
    ce_json_error('Review edit not found', 404);
}

ce_json_response([
    'edit' => $result['edit'],
    'content' => $result['content'],
    'reviewEdits' => ce_fetch_review_edits($pdo, $slotId),
]);
