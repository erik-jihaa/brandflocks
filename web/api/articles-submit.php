<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/feedback-repository.php';
require_once __DIR__ . '/review-repository.php';
require_once __DIR__ . '/review-edits-repository.php';
require_once __DIR__ . '/publish-repository.php';
require_once __DIR__ . '/publish-settings-repository.php';
require_once __DIR__ . '/event-log-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$body = ce_read_json_body();

require_once __DIR__ . '/schema-helper.php';
ce_ensure_slot_pipeline_statuses($pdo);
ce_ensure_article_statuses($pdo);
ce_ensure_review_schema($pdo);

$reviewMode = strtolower(trim((string) ($body['reviewMode'] ?? 'human')));
if (!in_array($reviewMode, ['human', 'ai'], true)) {
    $reviewMode = 'human';
}
unset($body['reviewMode']);

$row = ce_fetch_article_by_slot($pdo, $slotId);
if (!$row) {
    ce_json_error('Article not found', 404);
}

$articleId = (string) $row['id'];

if ($reviewMode === 'ai') {
    require_once __DIR__ . '/review-edits-repository.php';
    ce_abandon_pending_review_edits($pdo, $slotId);
}

ce_apply_article_patch($pdo, $articleId, $body);

require_once __DIR__ . '/hba-v2-repository.php';
$submitText = trim(
    (string) ($body['headline'] ?? $row['headline'] ?? '') . "\n"
    . (string) ($body['content'] ?? $row['content_human'] ?? $row['content_ai'] ?? ''),
);
if (ce_hba_text_has_open_placeholders($submitText)) {
    ce_json_error(ce_hba_open_placeholder_message(), 400);
}

$row = ce_fetch_article_by_slot($pdo, $slotId) ?: $row;
if (ce_hba_article_self_review_blocks_flow($row['self_review'] ?? null)) {
    ce_json_error(ce_hba_self_review_unsupported_assumption_message(), 400);
}

$clientId = isset($row['client_id']) ? (string) $row['client_id'] : ce_client_id_for_slot($pdo, $slotId);
$publishMode = ce_client_publish_mode($pdo, $clientId);

$previousUi = ce_resolve_editorial_status(
    $row['status'] ?? null,
    $row['slot_status'] ?? null,
    ce_count_open_feedback($pdo, $slotId),
);
$wasFeedback = $previousUi === 'feedback';
if (!$wasFeedback && ce_table_exists($pdo, 'article_review_edits')) {
    $wasFeedback = count(ce_fetch_review_edits($pdo, $slotId, 'pending')) > 0;
}

if ($reviewMode === 'ai') {
    $webhook = ce_trigger_ce04_webhook($slotId);
    if (!$webhook['ok']) {
        ce_log_event($pdo, 'error', 'n8n', 'n8n_webhook_failed', (string) ($webhook['error'] ?? 'AI review mislukt'), [
            'clientId' => $clientId,
            'slotId' => $slotId,
            'workflow' => 'ce04',
        ]);
        ce_json_error($webhook['error'] ?? 'AI review mislukt', 502);
    }

    $updated = ce_fetch_article_by_slot($pdo, $slotId);
    ce_reconcile_feedback_with_pending_edits($pdo, $slotId);
    $openFeedbackCount = ce_count_open_feedback($pdo, $slotId);
    $payload = ce_format_article_row($updated ?: [], $openFeedbackCount);
    $payload['feedback'] = ce_fetch_article_feedback($pdo, $slotId);
    $payload['reviewEdits'] = ce_fetch_review_edits($pdo, $slotId);
    $payload['reviewMode'] = 'ai';
    $payload['publishMode'] = $publishMode;
    if ($openFeedbackCount === 0) {
        $payload['aiReviewWarning'] = 'AI review afgerond maar geen open feedback ontvangen — controleer CE-04 of probeer opnieuw.';
    }
    ce_json_response($payload);
}

if ($publishMode === 'manual_without_review') {
    ce_apply_ui_status($pdo, $slotId, $articleId, 'approved');
    $updated = ce_fetch_article_by_slot($pdo, $slotId);
    ce_reconcile_feedback_with_pending_edits($pdo, $slotId);
    $payload = ce_format_article_row($updated ?: [], 0);
    $payload = array_merge($payload, ce_article_review_meta($updated ?: []));
    $payload['feedback'] = ce_fetch_article_feedback($pdo, $slotId);
    $payload['reviewMode'] = 'none';
    $payload['publishMode'] = $publishMode;
    $payload['mailSent'] = false;
    ce_json_response($payload);
}

$reviewToken = ce_apply_submit_status($pdo, $slotId, $articleId);

$mail = ce_trigger_ce04_human_webhook($slotId, $articleId, $reviewToken, [
    'headline' => $body['headline'] ?? null,
    'content' => $body['content'] ?? null,
    'hashtags' => $body['hashtags'] ?? null,
    'resubmitted' => $wasFeedback,
]);
$mailSent = $mail['ok'];
if (!$mailSent) {
    ce_log_event($pdo, 'error', 'n8n', 'n8n_webhook_failed', (string) ($mail['error'] ?? 'Approval-mail niet verstuurd'), [
        'clientId' => $clientId,
        'slotId' => $slotId,
        'workflow' => 'ce04_human',
    ]);
}

$updated = ce_fetch_article_by_slot($pdo, $slotId);
ce_reconcile_feedback_with_pending_edits($pdo, $slotId);
$payload = ce_format_article_row($updated ?: [], 0);
$payload = array_merge($payload, ce_article_review_meta($updated ?: []));
$payload['feedback'] = ce_fetch_article_feedback($pdo, $slotId);
$payload['reviewMode'] = 'human';
$payload['publishMode'] = $publishMode;
$payload['reviewUrl'] = ce_review_page_url($reviewToken);
$payload['reviewerEmail'] = $mail['reviewerEmail'] ?? ce_review_email($pdo, $clientId);
$payload['mailSent'] = $mailSent;
if (!$mailSent) {
    $payload['mailWarning'] = $mail['error'] ?? 'Approval-mail niet verstuurd';
}
ce_json_response($payload);
