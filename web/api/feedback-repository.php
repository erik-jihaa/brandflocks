<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

function ce_feedback_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function ce_ensure_article_feedback_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'article_feedback')) {
        return;
    }

    if (!ce_table_has_column($pdo, 'article_feedback', 'slot_id')) {
        try {
            $pdo->exec('ALTER TABLE article_feedback ADD COLUMN slot_id CHAR(36) NULL AFTER id');
            unset($GLOBALS['ce_table_columns']['article_feedback']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }
}

/** @return list<array<string, mixed>> */
function ce_fetch_article_feedback(PDO $pdo, string $slotId): array
{
    ce_ensure_article_feedback_schema($pdo);

    if (!ce_table_exists($pdo, 'article_feedback')) {
        return [];
    }

    if (!ce_table_has_column($pdo, 'article_feedback', 'slot_id')) {
        return [];
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
        SELECT
            id,
            slot_id,
            article_id,
            reviewer_name,
            feedback_type,
            comment,
            action_label,
            excerpt,
            status
        FROM article_feedback
        WHERE slot_id = :slot_id
          AND status != 'deleted'
        ORDER BY created_at ASC, id ASC
        SQL
    );
    $stmt->execute(['slot_id' => $slotId]);
    $rows = $stmt->fetchAll();

    return array_map(static function (array $row): array {
        $type = (string) ($row['feedback_type'] ?? 'text');

        return [
            'id' => $row['id'],
            'type' => $type,
            'name' => $row['reviewer_name'] ?? 'Review',
            'comment' => $row['comment'] ?? '',
            'actionLabel' => ce_feedback_action_label_nl($row['action_label'] ?? null, $type),
            'excerpt' => $row['excerpt'] ?? null,
            'status' => $row['status'] ?? 'open',
        ];
    }, $rows);
}

/** §6 — Engelse CE-04-knoppen normaliseren voor klant-UI. */
function ce_feedback_action_label_nl(mixed $label, string $type = 'text'): ?string
{
    $raw = trim((string) ($label ?? ''));
    if ($raw === '' || $raw === 'Remove') {
        return $type === 'image' ? 'Nieuwe afbeelding' : 'Verwijderen';
    }
    if ($raw === 'New image') {
        return 'Nieuwe afbeelding';
    }

    return $raw;
}

function ce_count_open_feedback(PDO $pdo, string $slotId): int
{
    ce_ensure_article_feedback_schema($pdo);

    if (!ce_table_exists($pdo, 'article_feedback')) {
        return 0;
    }

    if (!ce_table_has_column($pdo, 'article_feedback', 'slot_id')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM article_feedback WHERE slot_id = :slot_id AND status = 'open'"
    );
    $stmt->execute(['slot_id' => $slotId]);

    return (int) $stmt->fetchColumn();
}

function ce_close_open_feedback(PDO $pdo, string $slotId): void
{
    if (!ce_table_exists($pdo, 'article_feedback')) {
        return;
    }

    $pdo->prepare(
        "UPDATE article_feedback SET status = 'okay' WHERE slot_id = :slot_id AND status = 'open'"
    )->execute(['slot_id' => $slotId]);
}

/** Alleen tekstkaarten sluiten — image-feedback blijft los van het tekst-reviewrond. */
function ce_close_open_text_feedback(PDO $pdo, string $slotId): void
{
    if (!ce_table_exists($pdo, 'article_feedback')) {
        return;
    }

    $pdo->prepare(
        "UPDATE article_feedback SET status = 'okay' WHERE slot_id = :slot_id AND status = 'open' AND feedback_type = 'text'"
    )->execute(['slot_id' => $slotId]);
}

/**
 * Eén actief commentaarsysteem per tekst-reviewronde (Word-model): zodra er een
 * pending review-edit is, is dat de canonieke bron van tekstlabels. Oudere losse
 * AI-tekstkaarten (uit een eerdere ai-review-ronde) worden dan gesloten — anders
 * verspringen labels doordat twee onafhankelijke systemen dezelfde regels claimen.
 */
function ce_reconcile_feedback_with_pending_edits(PDO $pdo, string $slotId): void
{
    require_once __DIR__ . '/review-edits-repository.php';
    if (ce_fetch_review_edits($pdo, $slotId, 'pending') !== []) {
        ce_close_open_text_feedback($pdo, $slotId);
    }
}

/** @return array<string, mixed>|null */
function ce_update_feedback_status(
    PDO $pdo,
    string $feedbackId,
    string $status,
    ?string $expectedSlotId = null,
): ?array {
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'feedback', $feedbackId, $expectedSlotId);

    if (!ce_table_exists($pdo, 'article_feedback')) {
        return null;
    }

    if (!in_array($status, ['open', 'okay', 'deleted'], true)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'UPDATE article_feedback SET status = :status WHERE id = :id'
    );
    $stmt->execute(['status' => $status, 'id' => $feedbackId]);

    if ($stmt->rowCount() === 0) {
        return null;
    }

    $fetch = $pdo->prepare(
        'SELECT id, feedback_type, reviewer_name, comment, action_label, excerpt, status
         FROM article_feedback WHERE id = :id'
    );
    $fetch->execute(['id' => $feedbackId]);
    $row = $fetch->fetch();
    if (!$row) {
        return null;
    }

    $type = (string) ($row['feedback_type'] ?? 'text');

    return [
        'id' => $row['id'],
        'type' => $type,
        'name' => $row['reviewer_name'] ?? 'Review',
        'comment' => $row['comment'] ?? '',
        'actionLabel' => ce_feedback_action_label_nl($row['action_label'] ?? null, $type),
        'excerpt' => $row['excerpt'] ?? null,
        'status' => $row['status'] ?? 'open',
    ];
}

