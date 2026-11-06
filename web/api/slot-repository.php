<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

/** Zet article + slot naar dezelfde UI-status (DB-waarden kunnen per tabel verschillen). */
function ce_apply_ui_status(PDO $pdo, string $slotId, ?string $articleId, string $uiStatus): void
{
    $slotDb = ce_db_status_for_ui($pdo, 'content_slots', $uiStatus);
    $pdo->prepare('UPDATE content_slots SET status = :status WHERE id = :id')
        ->execute(['status' => $slotDb, 'id' => $slotId]);

    if ($articleId !== null && $articleId !== '') {
        $articleDb = ce_db_status_for_ui($pdo, 'articles', $uiStatus);
        $pdo->prepare('UPDATE articles SET status = :status WHERE id = :id')
            ->execute(['status' => $articleDb, 'id' => $articleId]);
    }
}

function ce_apply_submit_status(PDO $pdo, string $slotId, string $articleId): string
{
    require_once __DIR__ . '/feedback-repository.php';
    require_once __DIR__ . '/review-repository.php';

    ce_ensure_slot_pipeline_statuses($pdo);
    ce_ensure_article_statuses($pdo);
    ce_ensure_review_schema($pdo);

    ce_close_open_feedback($pdo, $slotId);

    if (ce_table_exists($pdo, 'article_review_edits')) {
        $pdo->prepare(
            "UPDATE article_review_edits SET status = 'accepted' WHERE slot_id = :slot_id AND status = 'pending'"
        )->execute(['slot_id' => $slotId]);
    }

    ce_apply_ui_status($pdo, $slotId, $articleId, 'pending');

    return ce_apply_human_review_token($pdo, $articleId);
}

function ce_archive_status(PDO $pdo, string $table): string
{
    return ce_pick_status_value($pdo, $table, ['archived', 'archive']);
}

function ce_reset_slot_status(PDO $pdo): string
{
    return ce_pick_status_value($pdo, 'content_slots', [
        'planned',
        'open',
        'theme_ready',
        'research_done',
        'new',
        'draft',
    ]);
}

function ce_delete_slot_content(PDO $pdo, string $slotId): void
{
    if (ce_table_exists($pdo, 'article_feedback')) {
        $pdo->prepare('DELETE FROM article_feedback WHERE slot_id = :slot_id')
            ->execute(['slot_id' => $slotId]);
    }

    if (ce_table_exists($pdo, 'article_review_edits')) {
        $pdo->prepare('DELETE FROM article_review_edits WHERE slot_id = :slot_id')
            ->execute(['slot_id' => $slotId]);
    }

    $pdo->prepare('DELETE FROM articles WHERE slot_id = :slot_id')
        ->execute(['slot_id' => $slotId]);

    $resetStatus = ce_reset_slot_status($pdo);
    $pdo->prepare('UPDATE content_slots SET status = :status WHERE id = :id')
        ->execute(['status' => $resetStatus, 'id' => $slotId]);
}

/** Verwijder gekoppelde performance reports vóór articles (FK scope_article_id). */
function ce_delete_slot_report_links(PDO $pdo, string $slotId): void
{
    if (!ce_table_exists($pdo, 'articles')) {
        return;
    }

    $idsStmt = $pdo->prepare('SELECT id FROM articles WHERE slot_id = :slot_id');
    $idsStmt->execute(['slot_id' => $slotId]);
    $articleIds = array_values(array_filter(array_map(
        static fn ($row) => trim((string) ($row['id'] ?? '')),
        $idsStmt->fetchAll() ?: [],
    )));
    if ($articleIds === []) {
        return;
    }

    if (
        ce_table_exists($pdo, 'performance_reports')
        && ce_table_has_column($pdo, 'performance_reports', 'scope_article_id')
    ) {
        $placeholders = implode(', ', array_fill(0, count($articleIds), '?'));
        $pdo->prepare("DELETE FROM performance_reports WHERE scope_article_id IN ($placeholders)")
            ->execute($articleIds);
    }

    if (
        ce_table_exists($pdo, 'linkedin_posts')
        && ce_table_has_column($pdo, 'linkedin_posts', 'article_id')
    ) {
        $placeholders = implode(', ', array_fill(0, count($articleIds), '?'));
        $pdo->prepare("UPDATE linkedin_posts SET article_id = NULL WHERE article_id IN ($placeholders)")
            ->execute($articleIds);
    }
}

/** Verwijder fastlane-slot inclusief theme en research (agenda-rij verdwijnt). */
function ce_delete_slot_fully(PDO $pdo, string $slotId, ?string $themeId = null): void
{
    if (ce_table_exists($pdo, 'article_feedback')) {
        $pdo->prepare('DELETE FROM article_feedback WHERE slot_id = :slot_id')
            ->execute(['slot_id' => $slotId]);
    }

    if (ce_table_exists($pdo, 'article_review_edits')) {
        $pdo->prepare('DELETE FROM article_review_edits WHERE slot_id = :slot_id')
            ->execute(['slot_id' => $slotId]);
    }

    ce_delete_slot_report_links($pdo, $slotId);

    if (ce_table_exists($pdo, 'articles')) {
        $pdo->prepare('DELETE FROM articles WHERE slot_id = :slot_id')
            ->execute(['slot_id' => $slotId]);
    }

    if (ce_table_exists($pdo, 'research_items')) {
        $pdo->prepare('DELETE FROM research_items WHERE slot_id = :slot_id')
            ->execute(['slot_id' => $slotId]);
        if ($themeId !== null && $themeId !== '') {
            $pdo->prepare('DELETE FROM research_items WHERE theme_id = :theme_id')
                ->execute(['theme_id' => $themeId]);
        }
    }

    $pdo->prepare('DELETE FROM content_slots WHERE id = :id')
        ->execute(['id' => $slotId]);

    if ($themeId !== null && $themeId !== '' && ce_table_exists($pdo, 'themes')) {
        $check = $pdo->prepare('SELECT id FROM content_slots WHERE theme_id = :theme_id LIMIT 1');
        $check->execute(['theme_id' => $themeId]);
        if (!$check->fetch()) {
            $pdo->prepare('DELETE FROM themes WHERE id = :id')
                ->execute(['id' => $themeId]);
        }
    }
}

/** @return array{ok: true, slotId: string, action: string, fullDelete: bool} */
function ce_delete_agenda_slot(PDO $pdo, string $slotId): array
{
    $cols = ['id', 'theme_id'];
    if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
        $cols[] = 'origin';
    }
    $stmt = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM content_slots WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $slotId]);
    $slot = $stmt->fetch();
    if (!$slot) {
        throw new RuntimeException('Slot not found');
    }

    $themeId = trim((string) ($slot['theme_id'] ?? ''));
    ce_delete_slot_fully($pdo, $slotId, $themeId !== '' ? $themeId : null);

    return [
        'ok' => true,
        'slotId' => $slotId,
        'action' => 'deleted_fully',
        'fullDelete' => true,
    ];
}

function ce_archive_slot(PDO $pdo, string $slotId): void
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'slot', $slotId);

    $slotStatus = ce_archive_status($pdo, 'content_slots');
    $articleStatus = ce_archive_status($pdo, 'articles');

    $pdo->prepare('UPDATE content_slots SET status = :status WHERE id = :id')
        ->execute(['status' => $slotStatus, 'id' => $slotId]);

    $pdo->prepare('UPDATE articles SET status = :status WHERE slot_id = :slot_id')
        ->execute(['status' => $articleStatus, 'slot_id' => $slotId]);
}

function ce_slot_exists(PDO $pdo, string $slotId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM content_slots WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $slotId]);

    return (bool) $stmt->fetch();
}

/** @return list<string> */
function ce_fastlane_slot_select_columns(PDO $pdo): array
{
    $cols = ['id', 'theme'];
    if (ce_table_has_column($pdo, 'content_slots', 'theme_id')) {
        $cols[] = 'theme_id';
    }
    if (ce_table_has_column($pdo, 'content_slots', 'phase_key')) {
        $cols[] = 'phase_key';
    }
    if (ce_table_has_column($pdo, 'content_slots', 'status')) {
        $cols[] = 'status';
    }
    if (ce_table_has_column($pdo, 'content_slots', 'client_id')) {
        $cols[] = 'client_id';
    }
    if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
        $cols[] = 'origin';
    }

    return $cols;
}

/** @return array<string, mixed>|null */
function ce_fetch_fastlane_slot_row(PDO $pdo, string $slotId): ?array
{
    if (!ce_table_exists($pdo, 'content_slots')) {
        return null;
    }

    $cols = ce_fastlane_slot_select_columns($pdo);
    $stmt = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM content_slots WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $slotId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @return array{tone?: string, angle?: string, imageRatio?: string, platform?: string, rationale?: string, tags?: list<string>|string}|null */
function ce_fetch_fastlane_pending_meta(PDO $pdo, string $themeId): ?array
{
    if ($themeId === '' || !ce_table_exists($pdo, 'themes') || !ce_table_has_column($pdo, 'themes', 'motivation')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT motivation FROM themes WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $themeId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $raw = trim((string) ($row['motivation'] ?? ''));
    if ($raw === '') {
        return null;
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return null;
    }

    if (!is_array($decoded) || !isset($decoded['ceFastlaneMeta']) || !is_array($decoded['ceFastlaneMeta'])) {
        return null;
    }

    return $decoded['ceFastlaneMeta'];
}

function ce_slot_has_manual_fastlane_research(PDO $pdo, string $slotId, string $themeId): bool
{
    if (!ce_table_exists($pdo, 'research_items') || !ce_table_has_column($pdo, 'research_items', 'source_type')) {
        return false;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
        SELECT 1 FROM research_items
        WHERE source_type = 'manual_fastlane'
          AND (slot_id = :slot_id OR theme_id = :theme_id)
        LIMIT 1
        SQL
    );
    $stmt->execute(['slot_id' => $slotId, 'theme_id' => $themeId]);

    return (bool) $stmt->fetch();
}

/** Fastlane research-submit → CE-03 (niet voor geplande theme-slots). */
function ce_is_fastlane_research_slot(PDO $pdo, array $slot): bool
{
    if (strtolower(trim((string) ($slot['origin'] ?? ''))) === 'fastlane') {
        return true;
    }

    $themeId = trim((string) ($slot['theme_id'] ?? ''));
    $slotId = trim((string) ($slot['id'] ?? ''));

    if ($themeId !== '' && ce_fetch_fastlane_pending_meta($pdo, $themeId) !== null) {
        return true;
    }

    if ($slotId !== '' && $themeId !== '' && ce_slot_has_manual_fastlane_research($pdo, $slotId, $themeId)) {
        return true;
    }

    return false;
}

/** Maak content_slot uit theme (fallback als CE-01B-webhook traag/faalt). */
function ce_create_slot_from_theme(PDO $pdo, string $themeId, string $clientId): ?string
{
    if (!ce_table_exists($pdo, 'themes') || !ce_table_exists($pdo, 'content_slots')) {
        return null;
    }

    require_once __DIR__ . '/fastlane-repository.php';
    require_once __DIR__ . '/mappers.php';

    $stmt = $pdo->prepare('SELECT * FROM themes WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $themeId]);
    $theme = $stmt->fetch();
    if (!$theme) {
        return null;
    }

    $themeClientId = trim((string) ($theme['client_id'] ?? ''));
    if ($themeClientId !== '' && $themeClientId !== $clientId) {
        return null;
    }

    $existing = $pdo->prepare(
        "SELECT id FROM content_slots WHERE theme_id = :theme_id AND client_id = :client_id
         AND COALESCE(status, '') NOT IN ('archived', 'archive', 'deleted') LIMIT 1"
    );
    $existing->execute(['theme_id' => $themeId, 'client_id' => $clientId]);
    $row = $existing->fetch();
    if ($row) {
        return (string) ($row['id'] ?? '');
    }

    $title = trim((string) ($theme['title'] ?? ''));
    if ($title === '') {
        return null;
    }

    $cycleWeek = trim((string) ($theme['cycle_week'] ?? ''));
    $phaseKey = ce_phase_key_from_date($cycleWeek !== '' ? $cycleWeek : null) ?? '';

    $rhythmRole = null;
    if ($cycleWeek !== '') {
        require_once __DIR__ . '/content-rhythm-repository.php';
        $rhythm = ce_content_rhythm_active($pdo, $clientId);
        if ($rhythm !== null) {
            $countStmt = $pdo->prepare(
                <<<'SQL'
SELECT COUNT(DISTINCT s.id)
FROM content_slots s
INNER JOIN themes t ON t.id = s.theme_id
WHERE s.client_id = :client_id
  AND COALESCE(s.status, '') NOT IN ('deleted', 'archived', 'archive')
  AND t.cycle_week IS NOT NULL
  AND DATE(t.cycle_week) = DATE(:cycle_week)
SQL
            );
            $countStmt->execute(['client_id' => $clientId, 'cycle_week' => $cycleWeek]);
            $index = (int) $countStmt->fetchColumn();
            $rhythmRole = ce_content_rhythm_role_for_index($rhythm, $index);
        }
    }

    $slotId = ce_uuid();
    ce_insert_fastlane_slot(
        $pdo,
        $slotId,
        $clientId,
        $themeId,
        $title,
        '',
        $phaseKey,
        'normal',
        ['themed', 'planned', 'open', 'draft'],
        null,
        $rhythmRole,
    );
    ce_mark_theme_converted($pdo, $themeId);

    if ($cycleWeek !== '') {
        require_once __DIR__ . '/content-rhythm-repository.php';
        ce_content_rhythm_assign_roles_for_week($pdo, $clientId, $cycleWeek);
    }

    return $slotId;
}
