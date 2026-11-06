<?php

declare(strict_types=1);

require_once __DIR__ . '/review-repository.php';

/** @return list<array<string, mixed>> */
function ce_fetch_review_edits(PDO $pdo, string $slotId, ?string $status = null): array
{
    ce_ensure_review_schema($pdo);

    if (!ce_table_exists($pdo, 'article_review_edits')) {
        return [];
    }

    if (!ce_table_has_column($pdo, 'article_review_edits', 'slot_id')) {
        return [];
    }

    $sql = 'SELECT * FROM article_review_edits WHERE slot_id = :slot_id';
    $params = ['slot_id' => $slotId];

    if ($status !== null) {
        $sql .= ' AND status = :status';
        $params['status'] = $status;
    }

    $sql .= ' ORDER BY created_at ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map('ce_format_review_edit_row', $stmt->fetchAll() ?: []);
}

/** @param array<string, mixed> $row */
function ce_format_review_edit_row(array $row): array
{
    $progress = null;
    $raw = $row['progress_json'] ?? null;
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $progress = $decoded;
        }
    }

    return [
        'id' => $row['id'],
        'articleId' => $row['article_id'],
        'slotId' => $row['slot_id'],
        'originalText' => $row['original_text'],
        'suggestedText' => $row['suggested_text'],
        'status' => $row['status'],
        'progress' => $progress,
    ];
}

/** @param array<string, mixed> $progress */
function ce_save_review_edit_progress(PDO $pdo, string $editId, array $progress, ?string $boundSlotId = null): ?array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'review_edit', $editId, $boundSlotId);

    ce_ensure_review_schema($pdo);

    if (!ce_table_has_column($pdo, 'article_review_edits', 'progress_json')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, status FROM article_review_edits WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();
    if (!$row || ($row['status'] ?? '') !== 'pending') {
        return null;
    }

    $json = json_encode($progress, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }

    $pdo->prepare('UPDATE article_review_edits SET progress_json = :progress WHERE id = :id')
        ->execute(['progress' => $json, 'id' => $editId]);

    return ce_fetch_review_edit($pdo, $editId);
}

/** Verwijder pending edits zodat een nieuwe AI-reviewronde schoon start. */
function ce_abandon_pending_review_edits(PDO $pdo, string $slotId): void
{
    ce_ensure_review_schema($pdo);

    if (!ce_table_exists($pdo, 'article_review_edits')) {
        return;
    }

    $pdo->prepare("DELETE FROM article_review_edits WHERE slot_id = :slot_id AND status = 'pending'")
        ->execute(['slot_id' => $slotId]);
}

/** @return array<string, mixed>|null */
function ce_fetch_review_edit(PDO $pdo, string $editId): ?array
{
    ce_ensure_review_schema($pdo);

    if (!ce_table_exists($pdo, 'article_review_edits')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM article_review_edits WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();

    return $row ? ce_format_review_edit_row($row) : null;
}

/** @return array{edit: array<string, mixed>, content: string}|null */
function ce_resolve_review_edit(PDO $pdo, string $editId, string $decision, ?string $resolvedContent = null, ?string $boundSlotId = null): ?array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'review_edit', $editId, $boundSlotId);

    if (!in_array($decision, ['accepted', 'rejected'], true)) {
        return null;
    }

    ce_ensure_review_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM article_review_edits WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editRow = $stmt->fetch();
    if (!$editRow) {
        return null;
    }

    $articleId = (string) $editRow['article_id'];
    $slotId = (string) ($editRow['slot_id'] ?? '');
    $original = (string) $editRow['original_text'];
    $suggested = (string) $editRow['suggested_text'];

    $articleStmt = $pdo->prepare('SELECT content_human, content_ai FROM articles WHERE id = :id LIMIT 1');
    $articleStmt->execute(['id' => $articleId]);
    $article = $articleStmt->fetch();
    if (!$article) {
        return null;
    }

    $content = trim((string) ($article['content_human'] ?? ''));
    if ($content === '') {
        $content = (string) ($article['content_ai'] ?? '');
    }

    if ($decision === 'accepted') {
        if ($resolvedContent !== null && trim($resolvedContent) !== '') {
            $content = $resolvedContent;
        } else {
            $content = $suggested !== '' ? $suggested : $content;
        }
    } else {
        // rejected: terug naar baseline van deze edit
        $content = $original !== '' ? $original : $content;
    }

    if (ce_table_has_column($pdo, 'articles', 'content_human')) {
        $pdo->prepare('UPDATE articles SET content_human = :content WHERE id = :id')
            ->execute(['content' => $content, 'id' => $articleId]);
    }

    $pdo->prepare('UPDATE article_review_edits SET status = :status WHERE id = :id')
        ->execute(['status' => $decision, 'id' => $editId]);

    if (ce_table_has_column($pdo, 'article_review_edits', 'progress_json')) {
        $pdo->prepare('UPDATE article_review_edits SET progress_json = NULL WHERE id = :id')
            ->execute(['id' => $editId]);
    }

    if ($decision === 'rejected' && $slotId !== '') {
        require_once __DIR__ . '/feedback-repository.php';
        ce_close_open_feedback($pdo, $slotId);
    }

    $updated = ce_fetch_review_edit($pdo, $editId);

    return $updated !== null ? ['edit' => $updated, 'content' => $content] : null;
}
