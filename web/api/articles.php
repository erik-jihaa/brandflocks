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
    require_once __DIR__ . '/fastlane-repository.php';
    require_once __DIR__ . '/slot-repository.php';
    require_once __DIR__ . '/research-quality.php';
    $row = ce_fetch_article_for_api($pdo, $slotId);
    $resolvedSlotId = ce_resolve_article_route_slot_id($pdo, $slotId) ?? $slotId;
    $slot = ce_fetch_fastlane_slot_row($pdo, $resolvedSlotId);
    $hasBody = ce_article_row_has_real_body($row);

    // Campagnethema zonder artikeltekst: thema-start, geen auto-CE-03 op GET.
    // Recente stub (< 3 min) = schrijven onderweg na onderzoek-indienen.
    if (!$hasBody && $slot !== null && ce_slot_is_campaign_theme($slot)) {
        $recentStub = false;
        if ($row !== null) {
            $updatedAt = strtotime((string) ($row['updated_at'] ?? $row['created_at'] ?? ''));
            $recentStub = $updatedAt !== false && (time() - $updatedAt) < 180;
        }
        if (!$recentStub) {
            ce_json_response(ce_build_theme_start_api_payload($pdo, $slot));
        }
    }

    $preparedWrite = false;

    if (!$hasBody && $slot !== null) {
        $slotUi = ce_status_to_ui((string) ($slot['status'] ?? ''));
        $researchReady = ce_research_slot_ready_for_write($pdo, $resolvedSlotId);
        if (
            in_array($slotUi, ['research_done', 'themed'], true)
            && ($researchReady['ok'] ?? false) === true
        ) {
            ce_prepare_fastlane_write_after_research($pdo, $resolvedSlotId);
            $row = ce_fetch_article_by_slot($pdo, $resolvedSlotId);
            $hasBody = ce_article_row_has_real_body($row);
            $preparedWrite = true;
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
        require_once __DIR__ . '/fastlane-repository.php';
        require_once __DIR__ . '/slot-repository.php';
        $resolvedSlotId = ce_resolve_article_route_slot_id($pdo, $slotId) ?? $slotId;
        $slotRow = ce_fetch_fastlane_slot_row($pdo, $resolvedSlotId);
        if ($slotRow !== null
            && strtolower(trim((string) ($slotRow['origin'] ?? ''))) === 'campagne_preview') {
            $row = ce_ensure_article_stub_for_slot($pdo, $resolvedSlotId);
        }
    }
    if (!$row) {
        ce_json_error('Article not found', 404);
    }

    $schrijfvormChanged = ce_article_patch_changes_schrijfvorm($row, $body);
    ce_apply_article_patch($pdo, (string) $row['id'], $body);
    $updated = ce_fetch_article_for_api($pdo, (string) ($row['id'] ?? $slotId));
    if (!$updated) {
        ce_json_error('Article not found', 404);
    }
    $payload = ce_build_article_api_payload($pdo, $updated);

    if ($schrijfvormChanged) {
        require_once __DIR__ . '/fastlane-repository.php';
        $rewriteSlotId = trim((string) ($updated['slot_id'] ?? ''));
        if ($rewriteSlotId === '') {
            $rewriteSlotId = ce_resolve_article_route_slot_id($pdo, $slotId) ?? $slotId;
        }
        $rewrite = ce_start_article_ce03_rewrite($pdo, $rewriteSlotId, $updated);
        if (($rewrite['ok'] ?? false) === true) {
            $payload['writing'] = true;
            $payload['writingPending'] = true;
        } elseif ((int) ($rewrite['http'] ?? 0) === 503) {
            ce_json_error((string) ($rewrite['error'] ?? 'CE-03 kon niet worden gestart.'), 503);
        }
    }

    ce_json_response($payload);
}

ce_json_error('Method not allowed', 405);
