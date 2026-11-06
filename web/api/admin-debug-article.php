<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/mappers.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$slotId = trim((string) ($_GET['slot_id'] ?? ''));
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

$pdo = ce_db();

require_once __DIR__ . '/article-repository.php';
ce_consolidate_slot_articles($pdo, $slotId);

if (($_GET['restore_draft'] ?? '') === '1') {
    require_once __DIR__ . '/slot-repository.php';
    require_once __DIR__ . '/article-repository.php';
    ce_ensure_slot_pipeline_statuses($pdo);
    ce_ensure_article_statuses($pdo);

    $slotDraft = ce_db_status_for_ui($pdo, 'content_slots', 'research_done');
    $articleDraft = ce_db_status_for_ui($pdo, 'articles', 'draft');

    $pdo->prepare('UPDATE content_slots SET status = :status WHERE id = :id')
        ->execute(['status' => $slotDraft, 'id' => $slotId]);

    $articleUpdate = $pdo->prepare('UPDATE articles SET status = :status WHERE slot_id = :slot_id');
    $articleUpdate->execute(['status' => $articleDraft, 'slot_id' => $slotId]);
}

$cols = ['id', 'slot_id', 'status', 'tone_final', 'tone_suggested', 'angle_final', 'angle_suggested',
    'platform_final', 'platform_suggested', 'hashtags_suggested', 'hashtags_final', 'write_directive',
    'content_ai', 'content_human', 'headline', 'headline_human', 'self_review', 'created_at', 'updated_at'];
$existing = array_values(array_filter($cols, fn ($c) => ce_table_has_column($pdo, 'articles', $c)));

$stmt = $pdo->prepare('SELECT ' . implode(', ', $existing) . ' FROM articles WHERE slot_id = :slot_id ORDER BY id DESC');
$stmt->execute(['slot_id' => $slotId]);
$rows = $stmt->fetchAll();

$full = ($_GET['full'] ?? '') === '1';
$metricsOnly = ($_GET['metrics'] ?? '') === '1';
foreach ($rows as &$row) {
    $content = trim((string) ($row['content_ai'] ?? ''));
    $row['content_word_count'] = $content !== '' ? str_word_count($content) : 0;
    $row['content_hash'] = $content !== '' ? md5($content) : null;
    if ($metricsOnly) {
        $row = [
            'id' => (string) ($row['id'] ?? ''),
            'slot_id' => (string) ($row['slot_id'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'platform_final' => (string) ($row['platform_final'] ?? ''),
            'content_word_count' => $row['content_word_count'],
            'content_hash' => $row['content_hash'],
        ];
        continue;
    }
    if ($full) {
        $row['selfReview'] = ce_parse_self_review($row['self_review'] ?? null);
        continue;
    }
    foreach (['content_ai', 'content_human', 'write_directive'] as $c) {
        if (isset($row[$c]) && is_string($row[$c]) && strlen($row[$c]) > 600) {
            $row[$c] = substr($row[$c], 0, 600) . '…[' . strlen($row[$c]) . ' chars]';
        }
    }
}
unset($row);

$slotStmt = $pdo->prepare('SELECT id, status, client_id, phase_key, theme FROM content_slots WHERE id = :id LIMIT 1');
$slotStmt->execute(['id' => $slotId]);
$slot = $slotStmt->fetch();

$hbaHint = null;
$articleId = (string) (($rows[0]['id'] ?? ''));
$publishMeta = null;
if ($articleId !== '') {
    require_once __DIR__ . '/publish-repository.php';
    require_once __DIR__ . '/publications-repository.php';
    $pubCols = ['id'];
    foreach (['social_post_id', 'social_post_url', 'publish_urls', 'published_at'] as $col) {
        if (ce_table_has_column($pdo, 'articles', $col)) {
            $pubCols[] = $col;
        }
    }
    $pubStmt = $pdo->prepare('SELECT ' . implode(', ', $pubCols) . ' FROM articles WHERE id = :id LIMIT 1');
    $pubStmt->execute(['id' => $articleId]);
    $pubRow = $pubStmt->fetch() ?: null;
    if (is_array($pubRow)) {
        $publishMeta = [
            'socialPostId' => $pubRow['social_post_id'] ?? null,
            'socialPostUrl' => $pubRow['social_post_url'] ?? null,
            'publishUrls' => ce_publish_urls_from_row($pubRow),
            'publishedAt' => $pubRow['published_at'] ?? null,
            'publications' => ce_fetch_publications_for_article($pdo, $articleId),
        ];
    }
}
$feedback = [];
if ($slotId !== '' && ce_table_exists($pdo, 'article_feedback')) {
    require_once __DIR__ . '/feedback-repository.php';
    $feedback = ce_fetch_article_feedback($pdo, $slotId);
}

if ($articleId !== '' && ce_table_exists($pdo, 'hba_article_hints')) {
    $hintStmt = $pdo->prepare(
        'SELECT id, aanwijzing, toegepast, judged_at FROM hba_article_hints WHERE article_id = :id ORDER BY judged_at DESC LIMIT 1'
    );
    $hintStmt->execute(['id' => $articleId]);
    $hbaHint = $hintStmt->fetch() ?: null;
}

ce_json_response([
    'ok' => true,
    'slotId' => $slotId,
    'slot' => $slot,
    'hbaHint' => $hbaHint,
    'publishMeta' => $publishMeta,
    'articleRowCount' => count($rows),
    'articles' => $rows,
    'feedback' => $feedback,
    'hasWriteDirectiveColumn' => ce_table_has_column($pdo, 'articles', 'write_directive'),
    'hasToneFinalColumn' => ce_table_has_column($pdo, 'articles', 'tone_final'),
]);
