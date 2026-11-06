<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/feedback-repository.php';
require_once __DIR__ . '/review-edits-repository.php';
require_once __DIR__ . '/review-repository.php';
require_once __DIR__ . '/editor-questions.php';
require_once __DIR__ . '/tone-styles.php';
require_once __DIR__ . '/objectives-repository.php';
require_once __DIR__ . '/hba-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

/** Volledige article-API payload (GET + PATCH). */
function ce_build_article_api_payload(PDO $pdo, array $row): array
{
    $slotId = (string) ($row['slot_id'] ?? '');
    $openFeedbackCount = $slotId !== '' ? ce_count_open_feedback($pdo, $slotId) : 0;
    $payload = ce_format_article_row($row, $openFeedbackCount);
    $payload = array_merge($payload, ce_article_review_meta($row));
    $clientId = (string) ($row['client_id'] ?? '');
    $payload['toneOptions'] = ce_client_tone_option_labels($pdo, $clientId);
    $payload['tone'] = ce_resolve_client_tone_ui($pdo, $clientId, $payload['tone'] ?? null);
    $payload['toneSuggested'] = ce_resolve_client_tone_ui($pdo, $clientId, $payload['toneSuggested'] ?? null);
    $payload['editorOptions'] = ce_client_editor_option_labels($pdo, $clientId);
    $payload = array_merge($payload, ce_article_objective_payload($pdo, $row));
    if ($slotId !== '') {
        ce_reconcile_feedback_with_pending_edits($pdo, $slotId);
        $payload['feedback'] = ce_fetch_article_feedback($pdo, $slotId);
        $payload['reviewEdits'] = ce_fetch_review_edits($pdo, $slotId);
        ce_apply_pending_review_content($payload);
    }

    if ($clientId !== '') {
        require_once __DIR__ . '/publish-repository.php';
        $payload['publishMode'] = ce_client_publish_mode($pdo, $clientId);
    }

    $payload = array_merge($payload, ce_hba_article_api_extras($pdo, $row));

    require_once __DIR__ . '/publications-repository.php';
    $articleId = trim((string) ($payload['id'] ?? $row['id'] ?? ''));
    $publications = $articleId !== '' ? ce_fetch_publications_for_article($pdo, $articleId) : [];
    if ($publications === [] && $articleId !== '') {
        $wpId = trim((string) ($payload['wordpressPostId'] ?? ''));
        if ($wpId !== '') {
            $publications[] = [
                'id' => '',
                'articleId' => $articleId,
                'platform' => 'wordpress',
                'status' => 'gepubliceerd',
                'scheduledAt' => null,
                'publishedAt' => null,
                'externalId' => $wpId,
                'externalUrl' => null,
                'errorMessage' => null,
                'attempts' => 0,
            ];
        }
    }
    $payload['publications'] = $publications;

    return $payload;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    $row = ce_fetch_article_for_api($pdo, $slotId);
    if (!$row) {
        require_once __DIR__ . '/fastlane-repository.php';
        require_once __DIR__ . '/slot-repository.php';
        require_once __DIR__ . '/research-quality.php';
        $resolvedSlotId = ce_resolve_article_route_slot_id($pdo, $slotId) ?? $slotId;
        $slot = ce_fetch_fastlane_slot_row($pdo, $resolvedSlotId);
        if ($slot !== null) {
            $slotUi = ce_status_to_ui((string) ($slot['status'] ?? ''));
            $researchReady = ce_research_slot_ready_for_write($pdo, $resolvedSlotId);
            if (
                in_array($slotUi, ['research_done', 'themed'], true)
                && ($researchReady['ok'] ?? false) === true
            ) {
                ce_prepare_fastlane_write_after_research($pdo, $resolvedSlotId);
                $row = ce_fetch_article_by_slot($pdo, $resolvedSlotId);
            } elseif (
                in_array($slotUi, ['research_done', 'themed'], true)
                && ($researchReady['ok'] ?? false) !== true
            ) {
                ce_json_error(
                    (string) ($researchReady['message'] ?? ce_research_insufficient_message()),
                    409,
                );
            }
        }
    }
    if (!$row) {
        ce_json_error('Article not found', 404);
    }
    require_once __DIR__ . '/fastlane-repository.php';
    $resume = ce_maybe_resume_article_write($pdo, $slotId, $row);
    $payload = ce_build_article_api_payload($pdo, $row);
    if ($resume !== null) {
        $payload['writingPending'] = true;
    }
    ce_json_response($payload);
}

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    $row = ce_fetch_article_for_api($pdo, $slotId);
    if (!$row) {
        ce_json_error('Article not found', 404);
    }

    ce_apply_article_patch($pdo, (string) $row['id'], $body);
    $updated = ce_fetch_article_for_api($pdo, (string) ($row['id'] ?? $slotId));
    if (!$updated) {
        ce_json_error('Article not found', 404);
    }
    ce_json_response(ce_build_article_api_payload($pdo, $updated));
}

ce_json_error('Method not allowed', 405);
