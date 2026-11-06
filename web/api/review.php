<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/review-repository.php';

$token = $_GET['review_token'] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    ce_json_error('Invalid review token', 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

$row = ce_fetch_article_by_review_token($pdo, $token);
if (!$row) {
    ce_json_error('Review not found', 404);
}

if ($method === 'GET') {
    ce_json_response(ce_format_review_payload_with_edits($pdo, $row));
}

if ($method !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_is_pending_review_status($row['status'] ?? null)) {
    ce_json_error('Review is al afgehandeld', 409);
}

$body = ce_read_json_body();
$decision = strtolower(trim((string) ($body['decision'] ?? '')));
if (!in_array($decision, ['approved', 'feedback'], true)) {
    ce_json_error('Invalid decision', 400);
}

$contentAi = (string) ($row['content_ai'] ?? '');
$baseline = trim((string) ($row['content_human'] ?? ''));
if ($baseline === '') {
    $baseline = $contentAi;
}

$editedText = (string) ($body['content'] ?? $baseline);
$headline = trim((string) ($body['headline'] ?? ce_article_headline($row)));

$articleId = (string) $row['id'];
$slotId = (string) $row['slot_id'];

ce_store_review_edits($pdo, $articleId, $slotId, $baseline, $editedText);

// content_human pas bijwerken na accept op de feedback-pagina (PATCH review-edits).
// Tot die tijd toont de API de suggested-tekst via pending review-edits.
// Eén commentaarsysteem (Word-model): de tracked review-edit is de enige bron van
// tekstlabels voor deze ronde — géén losse article_feedback-rijen meer aanmaken
// (dat gaf dubbele/verspringende labels, o.a. op de hashtag-regel).

if (ce_table_has_column($pdo, 'articles', 'headline_human') && $headline !== '') {
    $pdo->prepare('UPDATE articles SET headline_human = :headline WHERE id = :id')
        ->execute(['headline' => $headline, 'id' => $articleId]);
}

require_once __DIR__ . '/feedback-repository.php';

if ($decision === 'feedback') {
    ce_reconcile_feedback_with_pending_edits($pdo, $slotId);
}

$uiStatus = $decision === 'approved' ? 'approved' : 'feedback';
ce_apply_ui_status($pdo, $slotId, $articleId, $uiStatus);

$updated = ce_fetch_article_by_review_token($pdo, $token);
$payload = ce_format_review_payload_with_edits($pdo, $updated ?: $row);
$payload['decision'] = $decision;
$payload['editCount'] = ce_build_review_edit_fragments($baseline, $editedText) !== [] ? 1 : 0;

ce_json_response($payload);
