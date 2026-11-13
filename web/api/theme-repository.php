<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/event-log-repository.php';
require_once __DIR__ . '/tone-styles.php';
require_once __DIR__ . '/copy-modus.php';

/** Aantal theme-voorstellen per week op Theme (keuze uit meerdere opties). */
function ce_theme_proposal_count(): int
{
    return 3;
}

function ce_ensure_themes_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'themes')) {
        return;
    }

    if (!ce_table_has_column($pdo, 'themes', 'phase_key')) {
        try {
            $pdo->exec('ALTER TABLE themes ADD COLUMN phase_key VARCHAR(100) NULL AFTER cycle_week');
        } catch (Throwable) {
            // Kolom bestaat al of geen rechten.
        }
    }

    if (!ce_table_has_column($pdo, 'themes', 'editor_suggested')) {
        try {
            $pdo->exec('ALTER TABLE themes ADD COLUMN editor_suggested VARCHAR(100) NULL');
            unset($GLOBALS['ce_table_columns']['themes']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }

    require_once __DIR__ . '/copy-modus.php';
    ce_ensure_theme_copy_modus_columns($pdo);
}

function ce_ensure_research_items_schema(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'research_items')) {
        return;
    }

    $required = ['rss', 'trello', 'mailbox', 'scraper', 'linkedin', 'manual_fastlane', 'theme', 'adhoc_web'];
    $enumValues = ce_column_enum_values($pdo, 'research_items', 'source_type');
    if ($enumValues !== []) {
        $missing = array_diff($required, $enumValues);
        if ($missing !== []) {
            try {
                $pdo->exec("ALTER TABLE research_items MODIFY COLUMN source_type VARCHAR(50) DEFAULT 'rss'");
                unset($GLOBALS['ce_table_columns']['research_items']);
            } catch (Throwable) {
                ce_alter_column_enum($pdo, 'research_items', 'source_type', array_values(array_unique([...$enumValues, ...$required])));
                unset($GLOBALS['ce_table_columns']['research_items']);
            }
        }
    }

    if (!ce_table_has_column($pdo, 'research_items', 'invalshoek')) {
        try {
            $pdo->exec('ALTER TABLE research_items ADD COLUMN invalshoek VARCHAR(20) NULL');
            unset($GLOBALS['ce_table_columns']['research_items']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }

    if (!ce_table_has_column($pdo, 'research_items', 'stance')) {
        try {
            $pdo->exec(
                "ALTER TABLE research_items ADD COLUMN stance ENUM('bevestigt','nuanceert','spreekt_tegen') NULL"
            );
            unset($GLOBALS['ce_table_columns']['research_items']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }

    if (!ce_table_has_column($pdo, 'research_items', 'reliability')) {
        try {
            $pdo->exec(
                "ALTER TABLE research_items ADD COLUMN reliability ENUM('low','medium','high') NULL"
            );
            unset($GLOBALS['ce_table_columns']['research_items']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }
}

/** @return array<string, mixed> */
function ce_research_item_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'snippet' => (string) ($row['snippet'] ?? ''),
        'url' => (string) ($row['url'] ?? ''),
        'itemRationale' => (string) ($row['item_rationale'] ?? ''),
        'invalshoek' => trim((string) ($row['invalshoek'] ?? '')) !== ''
            ? trim((string) ($row['invalshoek'] ?? ''))
            : null,
        'stance' => trim((string) ($row['stance'] ?? '')) !== ''
            ? trim((string) ($row['stance'] ?? ''))
            : null,
        'reliability' => trim((string) ($row['reliability'] ?? '')) !== ''
            ? trim((string) ($row['reliability'] ?? ''))
            : null,
        'isAdhocWeb' => (string) ($row['source_type'] ?? '') === 'adhoc_web',
        'feedback' => (string) ($row['feedback'] ?? 'none'),
        'sourceType' => (string) ($row['source_type'] ?? ''),
    ];
}

/** @return list<array<string, mixed>> */
function ce_fetch_theme_research_rows_for_source_url(PDO $pdo, string $themeId): array
{
    if ($themeId === '' || !ce_table_exists($pdo, 'research_items')) {
        return [];
    }

    $rows = [];
    $stmt = $pdo->prepare(
        'SELECT * FROM research_items WHERE theme_id = :theme_id ORDER BY created_at ASC, title ASC'
    );
    $stmt->execute(['theme_id' => $themeId]);
    foreach ($stmt->fetchAll() as $row) {
        $rows[(string) ($row['id'] ?? '')] = $row;
    }

    if (ce_table_exists($pdo, 'content_slots')) {
        $slotStmt = $pdo->prepare(
            <<<'SQL'
            SELECT ri.*
            FROM research_items ri
            INNER JOIN content_slots s ON s.id = ri.slot_id
            WHERE s.theme_id = :theme_id
            ORDER BY ri.created_at ASC, ri.title ASC
            SQL
        );
        $slotStmt->execute(['theme_id' => $themeId]);
        foreach ($slotStmt->fetchAll() as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $rows[$id] = $row;
            }
        }
    }

    return array_values($rows);
}

/** Bepaal bron-URL en schrijf naar themes.source_article_url wanneer nog leeg. */
function ce_persist_theme_source_article_url(PDO $pdo, string $themeId): ?string
{
    if ($themeId === '' || !ce_table_exists($pdo, 'themes') || !ce_table_has_column($pdo, 'themes', 'source_article_url')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM themes WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $themeId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $stored = trim((string) ($row['source_article_url'] ?? ''));
    if ($stored !== '' && !ce_is_feed_or_search_url($stored) && !ce_is_linkedin_content_url($stored)) {
        return ce_normalize_theme_source_article_url($stored);
    }

    $researchDtos = [];
    foreach (ce_fetch_theme_research_rows_for_source_url($pdo, $themeId) as $itemRow) {
        $researchDtos[] = ce_research_item_dto($itemRow);
    }

    $resolved = ce_resolve_theme_source_article_url(
        '',
        $researchDtos,
        (string) ($row['rationale'] ?? ''),
        (string) ($row['motivation'] ?? ''),
    );
    $normalized = ce_normalize_theme_source_article_url($resolved);
    if ($normalized === null) {
        return null;
    }

    $upd = $pdo->prepare(
        "UPDATE themes SET source_article_url = :url
         WHERE id = :id
           AND (source_article_url IS NULL OR TRIM(source_article_url) = '')"
    );
    $upd->execute(['url' => $normalized, 'id' => $themeId]);

    return $normalized;
}

/** @return array{updated: int, skipped: int, samples: list<array<string, string>>} */
function ce_backfill_theme_source_article_urls(PDO $pdo, ?string $clientId = null, int $sampleLimit = 10): array
{
    if (!ce_table_exists($pdo, 'themes') || !ce_table_has_column($pdo, 'themes', 'source_article_url')) {
        return ['updated' => 0, 'skipped' => 0, 'samples' => []];
    }

    $sql = "SELECT id, title FROM themes
            WHERE (source_article_url IS NULL OR TRIM(source_article_url) = '')
              AND COALESCE(status, 'proposed') NOT IN ('deleted')";
    $params = [];
    if ($clientId !== null && ce_table_has_column($pdo, 'themes', 'client_id')) {
        $sql .= ' AND client_id = :client_id';
        $params['client_id'] = $clientId;
    }
    $sql .= ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $updated = 0;
    $skipped = 0;
    $samples = [];

    foreach ($stmt->fetchAll() as $row) {
        $themeId = (string) ($row['id'] ?? '');
        if ($themeId === '') {
            continue;
        }
        $url = ce_persist_theme_source_article_url($pdo, $themeId);
        if ($url !== null) {
            $updated++;
            if (count($samples) < $sampleLimit) {
                $samples[] = [
                    'themeId' => $themeId,
                    'title' => (string) ($row['title'] ?? ''),
                    'sourceArticleUrl' => $url,
                ];
            }
        } else {
            $skipped++;
        }
    }

    return ['updated' => $updated, 'skipped' => $skipped, 'samples' => $samples];
}

/** @param array<string, mixed> $row */
function ce_ensure_theme_editor_suggested(PDO $pdo, array &$row, ?string $clientId, int $ordinal): void
{
    ce_ensure_themes_schema($pdo);
    if (!ce_table_has_column($pdo, 'themes', 'editor_suggested')) {
        return;
    }

    $current = trim((string) ($row['editor_suggested'] ?? ''));
    if ($current !== '') {
        return;
    }

    if ($clientId === null || $clientId === '') {
        $clientId = trim((string) ($row['client_id'] ?? ''));
    }
    if ($clientId === '') {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null || $clientId === '') {
        return;
    }

    $editors = ce_client_editor_option_labels($pdo, $clientId);
    if ($editors === []) {
        return;
    }

    $suggested = $editors[$ordinal % count($editors)];
    $themeId = trim((string) ($row['id'] ?? ''));
    if ($themeId === '') {
        return;
    }

    $pdo->prepare('UPDATE themes SET editor_suggested = :editor WHERE id = :id')
        ->execute(['editor' => $suggested, 'id' => $themeId]);
    $row['editor_suggested'] = $suggested;
}

function ce_theme_editor_for_slot(PDO $pdo, string $slotId): string
{
    if ($slotId === '' || !ce_table_exists($pdo, 'content_slots') || !ce_table_exists($pdo, 'themes')) {
        return '';
    }
    if (!ce_table_has_column($pdo, 'content_slots', 'theme_id')
        || !ce_table_has_column($pdo, 'themes', 'editor_suggested')) {
        return '';
    }

    $stmt = $pdo->prepare(
        'SELECT t.editor_suggested
         FROM content_slots s
         INNER JOIN themes t ON t.id = s.theme_id
         WHERE s.id = :slot_id
         LIMIT 1'
    );
    $stmt->execute(['slot_id' => $slotId]);

    return trim((string) ($stmt->fetchColumn() ?: ''));
}

/** @param array<string, mixed> $article */
function ce_sync_article_editor_from_theme(PDO $pdo, array $article, string $slotId): array
{
    ce_ensure_article_editor_columns($pdo);
    if ($slotId === '' || !ce_table_has_column($pdo, 'articles', 'editor_suggested')) {
        return $article;
    }

    $editorSuggested = trim((string) ($article['editor_suggested'] ?? ''));
    $editorFinal = trim((string) ($article['editor_final'] ?? ''));
    if ($editorSuggested !== '' || $editorFinal !== '') {
        return $article;
    }

    $fromTheme = ce_theme_editor_for_slot($pdo, $slotId);
    if ($fromTheme === '') {
        return $article;
    }

    $articleId = trim((string) ($article['id'] ?? ''));
    if ($articleId !== '') {
        $pdo->prepare('UPDATE articles SET editor_suggested = :editor WHERE id = :id')
            ->execute(['editor' => $fromTheme, 'id' => $articleId]);
    }

    $article['editor_suggested'] = $fromTheme;

    return $article;
}

/** @return array<string, mixed> */
function ce_theme_dto(PDO $pdo, array $row): array
{
    $themeId = (string) ($row['id'] ?? '');
    $items = [];

    if ($themeId !== '' && ce_table_exists($pdo, 'research_items')) {
        $stmt = $pdo->prepare(
            'SELECT * FROM research_items WHERE theme_id = :theme_id ORDER BY created_at ASC, title ASC'
        );
        $stmt->execute(['theme_id' => $themeId]);
        foreach ($stmt->fetchAll() as $item) {
            if (ce_research_item_excluded_from_theme_ui($item)) {
                continue;
            }
            $items[] = ce_research_item_dto($item);
        }
    }

    $urlResearchItems = [];
    foreach (ce_fetch_theme_research_rows_for_source_url($pdo, $themeId) as $itemRow) {
        $urlResearchItems[] = ce_research_item_dto($itemRow);
    }

    $sourceArticleUrl = ce_resolve_theme_source_article_url(
        isset($row['source_article_url']) ? (string) $row['source_article_url'] : '',
        $urlResearchItems,
        (string) ($row['rationale'] ?? ''),
        (string) ($row['motivation'] ?? ''),
    );

    if ($themeId !== '' && ce_table_has_column($pdo, 'themes', 'source_article_url')) {
        $storedRaw = trim((string) ($row['source_article_url'] ?? ''));
        if ($storedRaw === '' && $sourceArticleUrl !== '') {
            $normalized = ce_normalize_theme_source_article_url($sourceArticleUrl);
            if ($normalized !== null) {
                $pdo->prepare(
                    "UPDATE themes SET source_article_url = :url
                     WHERE id = :id
                       AND (source_article_url IS NULL OR TRIM(source_article_url) = '')"
                )->execute(['url' => $normalized, 'id' => $themeId]);
            }
        }
    }

    $phaseKey = trim((string) ($row['phase_key'] ?? ''));
    $cycleWeek = trim((string) ($row['cycle_week'] ?? ''));

    $weekLabel = ce_theme_backlog_week_label($row);
    $clientId = trim((string) ($row['client_id'] ?? ''));
    $editorSuggested = trim((string) ($row['editor_suggested'] ?? ''));

    $rhythmRole = null;
    $rhythmRoleLabel = null;
    if ($clientId !== '') {
        require_once __DIR__ . '/content-rhythm-repository.php';
        $rhythmRole = ce_content_rhythm_role_for_theme($pdo, $clientId, $row);
        if ($rhythmRole !== null && $rhythmRole !== '') {
            $rhythmRoleLabel = ce_content_rhythm_role_label($rhythmRole);
        }
    }

    return [
        'id' => $themeId,
        'weekLabel' => $weekLabel,
        'phaseKey' => $phaseKey,
        'cycleWeek' => $cycleWeek,
        'title' => (string) ($row['title'] ?? ''),
        'motivation' => ce_strip_theme_story_leadin((string) ($row['motivation'] ?? '')),
        'rationale' => ce_strip_theme_story_leadin((string) ($row['rationale'] ?? '')),
        'sourceArticleUrl' => $sourceArticleUrl,
        'feedback' => (string) ($row['feedback'] ?? 'none'),
        'status' => (string) ($row['status'] ?? 'proposed'),
        'editorSuggested' => $editorSuggested !== '' ? $editorSuggested : null,
        'editorOptions' => $clientId !== ''
            ? ce_client_editor_option_labels($pdo, $clientId)
            : ce_client_editor_option_labels($pdo, ce_default_client_id($pdo) ?? ''),
        'researchItems' => $items,
        'rhythmRole' => $rhythmRole,
        'rhythmRoleLabel' => $rhythmRoleLabel,
        'copyModus' => ce_copy_modus_is_valid((string) ($row['copy_modus'] ?? ''))
            ? (string) $row['copy_modus']
            : null,
        'copyModusOverride' => ((int) ($row['copy_modus_override'] ?? 0)) === 1,
        'copyModusLabel' => ce_copy_modus_is_valid((string) ($row['copy_modus'] ?? ''))
            ? ce_copy_modus_ui_label((string) $row['copy_modus'])
            : null,
    ];
}

/** @return array{0: string, 1: array<string, string>} */
function ce_theme_pending_sql(PDO $pdo, ?string $clientId, string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    $params = [];
    $parts = [
        "COALESCE({$prefix}status, 'proposed') NOT IN ('deleted', 'submitted', 'converted_to_slot')",
        "COALESCE({$prefix}feedback, 'none') NOT IN ('deleted')",
    ];

    if ($clientId !== null && ce_table_has_column($pdo, 'themes', 'client_id')) {
        $parts[] = "{$prefix}client_id = :client_id";
        $params['client_id'] = $clientId;
    }

    return [implode(' AND ', $parts), $params];
}

function ce_theme_backlog_week_key(array $row): string
{
    $cycleWeek = trim((string) ($row['cycle_week'] ?? ''));
    if ($cycleWeek !== '') {
        return $cycleWeek;
    }

    $phaseKey = trim((string) ($row['phase_key'] ?? ''));
    if (preg_match('/^\d{1,2}$/', $phaseKey)) {
        return $phaseKey;
    }

    return $phaseKey;
}

function ce_theme_backlog_week_label(array $row): string
{
    $cycleWeek = trim((string) ($row['cycle_week'] ?? ''));
    if ($cycleWeek !== '') {
        $isoWeek = ce_phase_key_from_date($cycleWeek);
        if ($isoWeek !== null && $isoWeek !== '') {
            return 'Week ' . (int) $isoWeek;
        }
    }

    $phaseKey = trim((string) ($row['phase_key'] ?? ''));
    if (preg_match('/^\d{1,2}$/', $phaseKey)) {
        return 'Week ' . (int) $phaseKey;
    }

    if (preg_match('/(?:^|[_-])week[_-]?(\d+)/i', $phaseKey, $matches)) {
        return 'Week ' . (int) $matches[1];
    }

    return 'Week ' . ce_week_start();
}

function ce_theme_matches_backlog_week(array $theme, string $weekKey): bool
{
    if ($weekKey === '') {
        return false;
    }

    $cycleWeek = trim((string) ($theme['cycleWeek'] ?? ''));
    if ($cycleWeek !== '' && $cycleWeek === $weekKey) {
        return true;
    }

    $phaseKey = trim((string) ($theme['phaseKey'] ?? ''));
    if ($phaseKey !== '' && $phaseKey === $weekKey) {
        return true;
    }

    if (preg_match('/^\d{1,2}$/', $weekKey) && $cycleWeek !== '') {
        $isoWeek = ce_phase_key_from_date($cycleWeek);

        return $isoWeek !== null && (string) (int) $isoWeek === (string) (int) $weekKey;
    }

    return false;
}

/** @deprecated use ce_theme_backlog_week_key */
function ce_theme_row_week_key(array $row): string
{
    return ce_theme_backlog_week_key($row);
}

function ce_count_pending_themes(PDO $pdo, ?string $clientId = null): int
{
    if (!ce_table_exists($pdo, 'themes')) {
        return 0;
    }

    [$where, $params] = ce_theme_pending_sql($pdo, $clientId);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM themes WHERE $where");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/** Themes die op /theme echt beoordeeld moeten worden (zelfde filters als ce_fetch_themes). */
function ce_count_themes_needing_review(PDO $pdo, ?string $clientId = null): int
{
    if (!ce_table_exists($pdo, 'themes')) {
        return 0;
    }

    ce_ensure_themes_schema($pdo);
    if ($clientId !== null) {
        ce_backfill_theme_phase_keys($pdo, $clientId);
        ce_purge_stale_theme_proposals($pdo, $clientId);
    }

    [$where, $params] = ce_theme_pending_sql($pdo, $clientId);
    $stmt = $pdo->prepare("SELECT * FROM themes WHERE $where");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        if (ce_theme_is_planning_placeholder($row)) {
            continue;
        }
        $cycleWeek = trim((string) ($row['cycle_week'] ?? ''));
        if ($cycleWeek !== '' && !ce_theme_cycle_week_in_theme_backlog($cycleWeek)) {
            continue;
        }
        $weekKey = ce_theme_backlog_week_key($row);
        if ($weekKey !== '' && ce_themes_still_required_for_week($pdo, $clientId, $weekKey) <= 0) {
            continue;
        }
        $rows[] = ce_theme_dto($pdo, $row);
    }

    return count(ce_dedupe_theme_dtos_by_title($rows));
}

function ce_count_proposed_themes_for_week(PDO $pdo, ?string $clientId, string $weekKey): int
{
    if ($weekKey === '' || !ce_table_exists($pdo, 'themes')) {
        return 0;
    }

    [$where, $params] = ce_theme_pending_sql($pdo, $clientId);
    $stmt = $pdo->prepare("SELECT cycle_week, phase_key FROM themes WHERE $where");
    $stmt->execute($params);

    $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        $dto = [
            'cycleWeek' => trim((string) ($row['cycle_week'] ?? '')),
            'phaseKey' => trim((string) ($row['phase_key'] ?? '')),
        ];
        if (ce_theme_matches_backlog_week($dto, $weekKey)) {
            $count++;
        }
    }

    return $count;
}

/**
 * Themes die de redacteur op "delete" zette (feedback=deleted) blijven status=proposed.
 * CE-01 telt die wél mee en skipt dan — terwijl Theme ze niet meer toont. Markeer ze
 * als deleted vóór een nieuwe generate-run.
 */
function ce_purge_discarded_proposed_themes_for_week(PDO $pdo, string $clientId, string $weekKey): int
{
    if ($weekKey === '' || $clientId === '' || !ce_table_exists($pdo, 'themes')) {
        return 0;
    }

    $deletedStatus = ce_pick_status_value($pdo, 'themes', ['deleted', 'archived', 'proposed']);
    if ($deletedStatus === 'proposed') {
        // Geen deleted-status beschikbaar: hard delete.
        $deletedStatus = '';
    }

    $stmt = $pdo->prepare(
        "SELECT id, cycle_week, phase_key FROM themes
         WHERE client_id = :client_id
           AND COALESCE(status, 'proposed') = 'proposed'
           AND LOWER(COALESCE(feedback, 'none')) = 'deleted'"
    );
    $stmt->execute(['client_id' => $clientId]);

    $purged = 0;
    foreach ($stmt->fetchAll() as $row) {
        $dto = [
            'cycleWeek' => trim((string) ($row['cycle_week'] ?? '')),
            'phaseKey' => trim((string) ($row['phase_key'] ?? '')),
        ];
        if (!ce_theme_matches_backlog_week($dto, $weekKey)) {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        if ($deletedStatus !== '') {
            $pdo->prepare('UPDATE themes SET status = :status WHERE id = :id AND client_id = :client_id')
                ->execute(['status' => $deletedStatus, 'id' => $id, 'client_id' => $clientId]);
        } else {
            $pdo->prepare('DELETE FROM themes WHERE id = :id AND client_id = :client_id')
                ->execute(['id' => $id, 'client_id' => $clientId]);
        }
        $purged++;
    }

    return $purged;
}

/** @return array{weekKey: string, cycleWeek: string, weekLabel: string} */
function ce_resolve_theme_week_keys(string $weekKey): array
{
    $weekKey = trim($weekKey);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekKey)) {
        return [
            'weekKey' => $weekKey,
            'cycleWeek' => $weekKey,
            'weekLabel' => ce_theme_backlog_week_label(['cycle_week' => $weekKey]),
        ];
    }

    if (preg_match('/^\d{1,2}$/', $weekKey)) {
        $cycleWeek = ce_cycle_monday_for_iso_week((int) $weekKey);

        return [
            'weekKey' => (string) (int) $weekKey,
            'cycleWeek' => $cycleWeek,
            'weekLabel' => ce_theme_backlog_week_label(['cycle_week' => $cycleWeek]),
        ];
    }

    return [
        'weekKey' => $weekKey,
        'cycleWeek' => $weekKey,
        'weekLabel' => ce_theme_backlog_week_label(['cycle_week' => $weekKey, 'phase_key' => $weekKey]),
    ];
}

/** @param list<array<string, mixed>> $weeks */
function ce_theme_weeks_ensure_navigation(
    PDO $pdo,
    ?string $clientId,
    array $weeks,
    string $activeWeekKey,
    int $pendingCount,
): array {
    if ($activeWeekKey === '') {
        return $weeks;
    }

    foreach ($weeks as $entry) {
        $weekKey = (string) ($entry['weekKey'] ?? '');
        $cycleWeek = (string) ($entry['cycleWeek'] ?? '');
        if ($weekKey === $activeWeekKey || $cycleWeek === $activeWeekKey) {
            return $weeks;
        }
        $resolvedActive = ce_resolve_theme_week_keys($activeWeekKey);
        if ($weekKey === $resolvedActive['weekKey'] || $cycleWeek === $resolvedActive['cycleWeek']) {
            return $weeks;
        }
    }

    if (ce_themes_still_required_for_week($pdo, $clientId, $activeWeekKey) <= 0) {
        return $weeks;
    }

    $resolved = ce_resolve_theme_week_keys($activeWeekKey);
    $cycleWeek = $resolved['cycleWeek'] !== '' ? $resolved['cycleWeek'] : $resolved['weekKey'];
    $weeks[] = [
        'weekKey' => $cycleWeek,
        'weekLabel' => $resolved['weekLabel'],
        'cycleWeek' => $cycleWeek,
        'pendingCount' => $pendingCount,
    ];

    usort($weeks, static function (array $a, array $b): int {
        $aCycle = (string) ($a['cycleWeek'] ?? $a['weekKey'] ?? '');
        $bCycle = (string) ($b['cycleWeek'] ?? $b['weekKey'] ?? '');

        return strcmp($aCycle, $bCycle);
    });

    return $weeks;
}

function ce_client_posts_per_week(PDO $pdo, ?string $clientId = null): int
{
    if ($clientId === null) {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null) {
        return 1;
    }

    $row = ce_fetch_client_row($pdo, $clientId);
    if (!$row) {
        return 1;
    }

    return max(1, (int) ($row['posts_per_week'] ?? 1));
}

function ce_client_planning_horizon_weeks(PDO $pdo, ?string $clientId = null): int
{
    if ($clientId === null) {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null) {
        return 4;
    }

    $row = ce_fetch_client_row($pdo, $clientId);
    if (!$row) {
        return 4;
    }

    return max(1, (int) ($row['planning_horizon_weeks'] ?? 4));
}

/** @return list<string> Maandagen (Y-m-d) voor huidige week + komende horizon-1 weken. */
function ce_upcoming_cycle_weeks(int $horizonWeeks): array
{
    $horizonWeeks = max(1, $horizonWeeks);

    try {
        $timezone = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
        $monday = new DateTimeImmutable('now', new DateTimeZone($timezone));
        $monday = $monday->modify('-' . ((int) $monday->format('N') - 1) . ' days');
    } catch (Throwable) {
        return [];
    }

    $mondays = [];
    for ($i = 0; $i < $horizonWeeks; $i++) {
        $mondays[] = $monday->modify('+' . $i . ' weeks')->format('Y-m-d');
    }

    return $mondays;
}

/** @return array{weekStart: string, weekEnd: string}|null */
function ce_cycle_week_date_range(string $cycleWeek): ?array
{
    $cycleWeek = substr(trim($cycleWeek), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cycleWeek)) {
        return null;
    }

    try {
        $timezone = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
        $monday = new DateTimeImmutable($cycleWeek, new DateTimeZone($timezone));

        return [
            'weekStart' => $monday->format('Y-m-d'),
            'weekEnd' => $monday->modify('+6 days')->format('Y-m-d'),
        ];
    } catch (Throwable) {
        return null;
    }
}

function ce_sql_slot_belongs_to_cycle_week(): string
{
    return <<<'SQL'
(t.cycle_week IS NOT NULL AND DATE(t.cycle_week) = DATE(:cycle_week))
SQL;
}

function ce_count_agenda_slots_for_cycle_week(PDO $pdo, ?string $clientId, string $cycleWeek): int
{
    if ($cycleWeek === '' || !ce_table_exists($pdo, 'content_slots')) {
        return 0;
    }

    if ($clientId === null) {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null) {
        return 0;
    }

    if (ce_cycle_week_date_range($cycleWeek) === null) {
        return 0;
    }

    $sql = <<<'SQL'
SELECT COUNT(DISTINCT s.id)
FROM content_slots s
INNER JOIN themes t ON t.id = s.theme_id
WHERE s.client_id = :client_id
  AND COALESCE(s.status, '') NOT IN ('deleted', 'archived', 'archive')
  AND
SQL;
    $sql .= ce_sql_slot_belongs_to_cycle_week();

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'client_id' => $clientId,
        'cycle_week' => $cycleWeek,
    ]);

    return (int) $stmt->fetchColumn();
}

function ce_count_agenda_slots_for_theme_week(PDO $pdo, ?string $clientId, string $weekKey): int
{
    if ($weekKey === '') {
        return 0;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekKey)) {
        return ce_count_agenda_slots_for_cycle_week($pdo, $clientId, $weekKey);
    }

    if (preg_match('/^\d{1,2}$/', $weekKey)) {
        $cycleWeek = ce_cycle_monday_for_iso_week((int) $weekKey);

        return ce_count_agenda_slots_for_cycle_week($pdo, $clientId, $cycleWeek);
    }

    if (!ce_table_exists($pdo, 'content_slots')) {
        return 0;
    }

    if ($clientId === null) {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null) {
        return 0;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT COUNT(DISTINCT s.id)
FROM content_slots s
WHERE s.client_id = :client_id
  AND s.phase_key = :phase_key
  AND COALESCE(s.status, '') NOT IN ('deleted')
SQL
    );
    $stmt->execute([
        'client_id' => $clientId,
        'phase_key' => $weekKey,
    ]);

    return (int) $stmt->fetchColumn();
}

function ce_theme_backlog_this_monday(): string
{
    $timezone = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
        $day = (int) $now->format('N');

        return $now->modify('-' . ($day - 1) . ' days')->format('Y-m-d');
    } catch (Throwable) {
        return date('Y-m-d');
    }
}

/** Eerste week in theme-backlog — huidige maandag als er nog geen planning is. */
function ce_default_active_theme_week(): string
{
    return ce_theme_backlog_this_monday();
}

function ce_theme_cycle_week_in_theme_backlog(string $cycleWeek): bool
{
    $cycleWeek = substr(trim($cycleWeek), 0, 10);
    if ($cycleWeek === '') {
        return false;
    }

    return strcmp($cycleWeek, ce_theme_backlog_this_monday()) >= 0;
}

function ce_themes_still_required_for_week(PDO $pdo, ?string $clientId, string $weekKey): int
{
    $postsPerWeek = ce_client_posts_per_week($pdo, $clientId);
    if ($weekKey === '') {
        return $postsPerWeek;
    }

    $filled = ce_count_agenda_slots_for_theme_week($pdo, $clientId, $weekKey);

    return max(0, $postsPerWeek - $filled);
}

function ce_purge_stale_theme_proposals(PDO $pdo, ?string $clientId = null): int
{
    if (!ce_table_exists($pdo, 'themes') || !ce_table_has_column($pdo, 'themes', 'cycle_week')) {
        return 0;
    }

    try {
        $thisMonday = ce_theme_backlog_this_monday();
    } catch (Throwable) {
        return 0;
    }

    [$where, $params] = ce_theme_pending_sql($pdo, $clientId);
    $params['this_monday'] = $thisMonday;

    $deletedStatus = 'deleted';
    if (in_array($deletedStatus, ce_column_enum_values($pdo, 'themes', 'status'), true)) {
        $sql = "UPDATE themes SET status = :deleted_status WHERE $where AND DATE(cycle_week) < DATE(:this_monday)";
        $params['deleted_status'] = $deletedStatus;
    } else {
        $sql = "DELETE FROM themes WHERE $where AND DATE(cycle_week) < DATE(:this_monday)";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

/** @return list<string> */
function ce_collect_theme_cycle_weeks(PDO $pdo, ?string $clientId): array
{
    if (!ce_table_exists($pdo, 'themes') || !ce_table_has_column($pdo, 'themes', 'cycle_week')) {
        return [];
    }

    $sql = "SELECT DISTINCT cycle_week FROM themes
            WHERE cycle_week IS NOT NULL AND TRIM(cycle_week) != ''
              AND COALESCE(status, 'proposed') NOT IN ('deleted')";
    $params = [];
    if ($clientId !== null && ce_table_has_column($pdo, 'themes', 'client_id')) {
        $sql .= ' AND client_id = :client_id';
        $params['client_id'] = $clientId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $weeks = [];
    foreach ($stmt->fetchAll() as $row) {
        $cycleWeek = trim((string) ($row['cycle_week'] ?? ''));
        if ($cycleWeek !== '') {
            $weeks[$cycleWeek] = true;
        }
    }

    return array_keys($weeks);
}

/** @return list<array{weekKey: string, weekLabel: string, cycleWeek: string, pendingCount: int}> */
function ce_fetch_theme_weeks(PDO $pdo, ?string $clientId = null): array
{
    if (!ce_table_exists($pdo, 'themes')) {
        return [];
    }

    ce_ensure_themes_schema($pdo);
    if ($clientId !== null) {
        ce_backfill_theme_phase_keys($pdo, $clientId);
        ce_purge_stale_theme_proposals($pdo, $clientId);
    }

    $cycleWeekKeys = ce_collect_theme_cycle_weeks($pdo, $clientId);
    foreach (ce_upcoming_cycle_weeks(ce_client_planning_horizon_weeks($pdo, $clientId)) as $horizonMonday) {
        if (!in_array($horizonMonday, $cycleWeekKeys, true)) {
            $cycleWeekKeys[] = $horizonMonday;
        }
    }
    sort($cycleWeekKeys);

    $weeks = [];
    foreach ($cycleWeekKeys as $cycleWeek) {
        if (!ce_theme_cycle_week_in_theme_backlog($cycleWeek)) {
            continue;
        }
        if (ce_themes_still_required_for_week($pdo, $clientId, $cycleWeek) <= 0) {
            continue;
        }

        $weeks[] = [
            'weekKey' => $cycleWeek,
            'weekLabel' => ce_theme_backlog_week_label(['cycle_week' => $cycleWeek]),
            'cycleWeek' => $cycleWeek,
            'pendingCount' => ce_count_proposed_themes_for_week($pdo, $clientId, $cycleWeek),
        ];
    }

    usort($weeks, static function (array $a, array $b): int {
        return strcmp((string) ($a['cycleWeek'] ?? ''), (string) ($b['cycleWeek'] ?? ''));
    });

    return $weeks;
}

function ce_backfill_theme_phase_keys(PDO $pdo, ?string $clientId = null): int
{
    ce_ensure_themes_schema($pdo);

    if (!ce_table_exists($pdo, 'themes') || !ce_table_has_column($pdo, 'themes', 'phase_key')) {
        return 0;
    }

    $sql = "SELECT id, cycle_week, phase_key FROM themes
            WHERE (phase_key IS NULL OR phase_key = '')
              AND cycle_week IS NOT NULL
              AND COALESCE(status, 'proposed') NOT IN ('deleted')";
    $params = [];
    if ($clientId !== null) {
        $sql .= ' AND client_id = :client_id';
        $params['client_id'] = $clientId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $updated = 0;

    $upd = $pdo->prepare('UPDATE themes SET phase_key = :phase_key WHERE id = :id');
    foreach ($stmt->fetchAll() as $row) {
        $phaseKey = ce_phase_key_from_date((string) ($row['cycle_week'] ?? ''));
        if ($phaseKey === null) {
            continue;
        }
        $upd->execute(['phase_key' => $phaseKey, 'id' => $row['id']]);
        $updated++;
    }

    return $updated;
}

/** @return list<array<string, mixed>> */
function ce_dedupe_theme_dtos_by_title(array $themes): array
{
    $seen = [];
    $out = [];

    foreach ($themes as $theme) {
        $titleKey = strtolower(trim((string) ($theme['title'] ?? '')));
        if ($titleKey === '' || isset($seen[$titleKey])) {
            continue;
        }
        $seen[$titleKey] = true;
        $out[] = $theme;
    }

    return $out;
}

/** @return array<string, mixed> */
function ce_fetch_themes(PDO $pdo, ?string $week = null): array
{
    ce_ensure_themes_schema($pdo);

    if (!ce_table_exists($pdo, 'themes')) {
        return ['themes' => [], 'note' => 'themes-tabel ontbreekt nog in DB'];
    }

    $clientId = ce_default_client_id($pdo);
    if ($clientId !== null) {
        ce_backfill_theme_phase_keys($pdo, $clientId);
    }

    $weeks = ce_fetch_theme_weeks($pdo, $clientId);
    $pendingTotal = ce_count_themes_needing_review($pdo, $clientId);

    $initialWeekParam = ($week !== null && $week !== '') ? trim((string) $week) : '';
    $requestedWeek = $initialWeekParam;
    $explicitWeek = $requestedWeek !== '';

    if ($explicitWeek) {
        $resolvedReq = ce_resolve_theme_week_keys($requestedWeek);
        $reqCycle = $resolvedReq['cycleWeek'] !== '' ? $resolvedReq['cycleWeek'] : $requestedWeek;
        $pastOrComplete = !ce_theme_cycle_week_in_theme_backlog($reqCycle)
            || ce_themes_still_required_for_week($pdo, $clientId, $requestedWeek) <= 0;
        if ($pastOrComplete) {
            $explicitWeek = false;
            $requestedWeek = '';
            $week = null;
        }
    } elseif (!$explicitWeek && count($weeks) > 0) {
        $week = (string) ($weeks[0]['weekKey'] ?? '');
        $explicitWeek = $week !== '';
    }

    $sql = 'SELECT * FROM themes WHERE 1=1';
    $params = [];

    if ($clientId !== null) {
        $sql .= ' AND client_id = :client_id';
        $params['client_id'] = $clientId;
    }

    [$pendingWhere, $pendingParams] = ce_theme_pending_sql($pdo, $clientId);
    $sql .= ' AND ' . $pendingWhere;
    $params = array_merge($params, $pendingParams);

    if ($week !== null && $week !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) && ce_table_has_column($pdo, 'themes', 'cycle_week')) {
            $sql .= ' AND cycle_week = :week_key';
            $params['week_key'] = $week;
        } elseif (preg_match('/^\d{1,2}$/', $week) && ce_table_has_column($pdo, 'themes', 'cycle_week')) {
            $sql .= ' AND DATE(cycle_week) = DATE(:week_key)';
            $params['week_key'] = ce_cycle_monday_for_iso_week((int) $week);
        } else {
            if (ce_table_has_column($pdo, 'themes', 'phase_key')) {
                $sql .= ' AND (phase_key = :week_key OR cycle_week = :week_key)';
            } else {
                $sql .= ' AND cycle_week = :week_key';
            }
            $params['week_key'] = $week;
        }
    }
    if (ce_table_has_column($pdo, 'themes', 'cycle_week')) {
        $sql .= ' ORDER BY cycle_week ASC, created_at ASC';
    } elseif (ce_table_has_column($pdo, 'themes', 'phase_key')) {
        $sql .= ' ORDER BY phase_key ASC, created_at ASC';
    } else {
        $sql .= ' ORDER BY created_at ASC';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $themes = [];
    $ordinal = 0;
    foreach ($stmt->fetchAll() as $row) {
        ce_ensure_theme_editor_suggested($pdo, $row, $clientId, $ordinal);
        $ordinal++;
        $themes[] = ce_theme_dto($pdo, $row);
    }

    $themes = array_values(array_filter(
        $themes,
        static fn (array $theme): bool => ce_theme_cycle_week_in_theme_backlog((string) ($theme['cycleWeek'] ?? '')),
    ));

    if ($week !== null && $week !== '' && preg_match('/^\d{1,2}$/', $week)) {
        $themes = array_values(array_filter(
            $themes,
            static fn (array $theme): bool => ce_theme_matches_backlog_week($theme, $week),
        ));
    }

    $themes = ce_dedupe_theme_dtos_by_title($themes);

    $activeWeekKey = '';

    if ($explicitWeek && $week !== null && $week !== '') {
        $activeWeekKey = (string) $week;
    } elseif (count($weeks) > 0) {
        $activeWeekKey = (string) ($weeks[0]['weekKey'] ?? '');
    }

    $resolvedActive = ce_resolve_theme_week_keys($activeWeekKey);
    if ($resolvedActive['cycleWeek'] !== '') {
        $activeWeekKey = $resolvedActive['cycleWeek'];
    }
    if ($activeWeekKey !== '' && !ce_theme_cycle_week_in_theme_backlog($activeWeekKey)) {
        $activeWeekKey = count($weeks) > 0 ? (string) ($weeks[0]['weekKey'] ?? '') : '';
    }

    if (!$explicitWeek && $activeWeekKey !== '' && count($themes) > 0) {
        $themes = array_values(array_filter(
            $themes,
            static fn (array $theme): bool => ce_theme_matches_backlog_week($theme, $activeWeekKey),
        ));
        $themes = array_slice($themes, 0, 3);
    } elseif ($explicitWeek && $week !== null && $week !== '') {
        $themes = array_slice($themes, 0, 3);
    } elseif ($activeWeekKey !== '' && count($themes) > 0) {
        $themes = array_values(array_filter(
            $themes,
            static fn (array $theme): bool => ce_theme_matches_backlog_week($theme, $activeWeekKey),
        ));
        $themes = array_slice($themes, 0, 3);
    }

    if (count($themes) > 0 && !$explicitWeek) {
        $firstCycle = trim((string) ($themes[0]['cycleWeek'] ?? ''));
        if ($firstCycle !== '') {
            $activeWeekKey = $firstCycle;
        }
    }

    if ($activeWeekKey === '') {
        $activeWeekKey = ce_default_active_theme_week();
    }

    $postsPerWeek = ce_client_posts_per_week($pdo, $clientId);
    $slotsFilledForWeek = ce_count_agenda_slots_for_theme_week($pdo, $clientId, $activeWeekKey);
    $themesStillRequired = ce_themes_still_required_for_week($pdo, $clientId, $activeWeekKey);
    $proposedThemeCount = ce_count_proposed_themes_for_week($pdo, $clientId, $activeWeekKey);
    $weekFull = $themesStillRequired <= 0;
    $needsThemeGeneration = $proposedThemeCount < ce_theme_proposal_count() && $themesStillRequired > 0;

    $resolvedWeek = ce_resolve_theme_week_keys($activeWeekKey !== '' ? $activeWeekKey : (string) ($week ?? ''));
    $weeks = ce_theme_weeks_ensure_navigation($pdo, $clientId, $weeks, $activeWeekKey, $proposedThemeCount);

    $weekIndex = 0;
    foreach ($weeks as $index => $entry) {
        $weekKey = (string) ($entry['weekKey'] ?? '');
        $cycleWeek = (string) ($entry['cycleWeek'] ?? '');
        if ($weekKey === $activeWeekKey || ($cycleWeek !== '' && $cycleWeek === $activeWeekKey)) {
            $weekIndex = $index;
            break;
        }
        if ($resolvedWeek['weekKey'] !== '' && ($weekKey === $resolvedWeek['weekKey'] || $cycleWeek === $resolvedWeek['cycleWeek'])) {
            $weekIndex = $index;
            break;
        }
    }

    if ($weekFull && !$needsThemeGeneration) {
        $themes = [];
    }

    $redirectWeekKey = '';
    if ($initialWeekParam !== '' && $activeWeekKey !== '') {
        $initialResolved = ce_resolve_theme_week_keys($initialWeekParam);
        $activeResolved = ce_resolve_theme_week_keys($activeWeekKey);
        $sameWeek = $initialResolved['cycleWeek'] !== '' && $initialResolved['cycleWeek'] === $activeResolved['cycleWeek'];
        if (!$sameWeek && $initialResolved['weekKey'] !== $activeResolved['weekKey']) {
            $redirectWeekKey = $activeWeekKey;
        }
    }

    return [
        'themes' => $themes,
        'meta' => [
            'pendingTotal' => $pendingTotal,
            'weeks' => $weeks,
            'activeWeekKey' => $activeWeekKey,
            'weekIndex' => $weekIndex,
            'weekCount' => count($weeks),
            'postsPerWeek' => $postsPerWeek,
            'slotsFilledForWeek' => $slotsFilledForWeek,
            'themesStillRequired' => $themesStillRequired,
            'weekFull' => $weekFull,
            'proposedThemeCount' => $proposedThemeCount,
            'needsThemeGeneration' => $needsThemeGeneration,
            'themeProposalCount' => ce_theme_proposal_count(),
            'activeCycleWeek' => $resolvedWeek['cycleWeek'],
            'redirectWeekKey' => $redirectWeekKey,
        ],
    ];
}

/** @return array<string, mixed> */
function ce_generate_themes_for_week(PDO $pdo, ?string $weekParam = null): array
{
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen actieve klant gevonden');
    }

    $weekParam = trim((string) ($weekParam ?? ''));
    if ($weekParam === '') {
        $current = ce_fetch_themes($pdo, null);
        $weekParam = (string) ($current['meta']['activeWeekKey'] ?? '');
    }
    if ($weekParam === '') {
        $weekParam = ce_default_active_theme_week();
    }

    $resolved = ce_resolve_theme_week_keys($weekParam);
    $cycleWeek = $resolved['cycleWeek'];
    ce_purge_discarded_proposed_themes_for_week($pdo, $clientId, $weekParam);
    $themesStillRequired = ce_themes_still_required_for_week($pdo, $clientId, $weekParam);
    if ($themesStillRequired <= 0) {
        throw new InvalidArgumentException('Agenda voor deze week is al vol — geen theme-voorstellen nodig.');
    }

    $proposalTarget = ce_theme_proposal_count();
    $proposedThemeCount = ce_count_proposed_themes_for_week($pdo, $clientId, $weekParam);
    if ($proposedThemeCount >= $proposalTarget) {
        throw new InvalidArgumentException(
            'Er staan al genoeg theme-voorstellen voor deze week — beoordeel ze op Theme.',
        );
    }

    // CE-01 antwoordt pas als themes in de DB staan (~30s). Wacht synchroon onder de
    // proxy-limiet (~60s). Fire-and-forget verbreekt responseNode en annuleert de run.
    $ce01 = ce_trigger_ce01_theme_director($clientId, [$cycleWeek], $proposalTarget, false, 50);
    $listed = ce_fetch_themes($pdo, $weekParam);
    $themes = is_array($listed['themes'] ?? null) ? $listed['themes'] : [];
    $ce01Ok = ($ce01['ok'] ?? false) === true;
    $ce01Skipped = is_array($ce01['response'] ?? null) && (($ce01['response']['skipped'] ?? false) === true);
    $hasThemes = $themes !== [];

    if (!$hasThemes && ($ce01Skipped || !$ce01Ok)) {
        $reason = is_array($ce01['response'] ?? null)
            ? trim((string) ($ce01['response']['reason'] ?? $ce01['response']['error'] ?? ''))
            : '';
        $message = is_string($ce01['error'] ?? null) ? (string) $ce01['error'] : '';
        if ($message === '' && $reason !== '') {
            $message = $reason;
        }
        if ($message === '') {
            $message = 'CE-01 leverde geen nieuwe theme-voorstellen op.';
        }

        return [
            'ok' => false,
            'async' => false,
            'week' => $weekParam,
            'cycleWeek' => $cycleWeek,
            'ce01' => $ce01,
            'themes' => $themes,
            'meta' => $listed['meta'] ?? [],
            'error' => $message,
        ];
    }

    return [
        'ok' => $ce01Ok || $hasThemes,
        'async' => false,
        'week' => $weekParam,
        'cycleWeek' => $cycleWeek,
        'ce01' => $ce01,
        'themes' => $themes,
        'meta' => $listed['meta'] ?? [],
    ];
}

/** @param array<string, mixed> $body */
function ce_create_theme(PDO $pdo, array $body): array
{
    if (!ce_table_exists($pdo, 'themes')) {
        throw new RuntimeException('themes-tabel ontbreekt');
    }

    $title = trim((string) ($body['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('title is verplicht');
    }

    $motivation = trim((string) ($body['motivation'] ?? ''));
    $rationale = trim((string) ($body['rationale'] ?? ''));
    $sourceArticleUrl = ce_normalize_theme_source_article_url(
        trim((string) ($body['sourceArticleUrl'] ?? $body['source_article_url'] ?? '')),
    );
    if ($sourceArticleUrl === null) {
        $fromText = ce_extract_article_url_from_text($rationale) ?: ce_extract_article_url_from_text($motivation);
        $sourceArticleUrl = ce_normalize_theme_source_article_url($fromText ?: '');
    }
    $cycleWeek = trim((string) ($body['cycleWeek'] ?? $body['cycle_week'] ?? ''));

    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen actieve klant gevonden');
    }

    if ($cycleWeek === '') {
        $stmt = $pdo->prepare(
            "SELECT cycle_week FROM themes
             WHERE client_id = :client_id
               AND COALESCE(status, 'proposed') = 'proposed'
               AND cycle_week IS NOT NULL
             ORDER BY cycle_week ASC
             LIMIT 1"
        );
        $stmt->execute(['client_id' => $clientId]);
        $row = $stmt->fetch();
        $cycleWeek = $row ? trim((string) ($row['cycle_week'] ?? '')) : '';
    }

    if ($cycleWeek === '') {
        $cycleWeek = ce_default_active_theme_week();
    }

    $phaseKey = ce_phase_key_from_date($cycleWeek) ?? '';
    $themeId = ce_uuid_v4();

    require_once __DIR__ . '/sources-repository.php';

    $cols = ['id', 'client_id', 'title'];
    $vals = [':id', ':client_id', ':title'];
    $params = ['id' => $themeId, 'client_id' => $clientId, 'title' => $title];

    if (ce_table_has_column($pdo, 'themes', 'motivation')) {
        $cols[] = 'motivation';
        $vals[] = ':motivation';
        $params['motivation'] = $motivation;
    }
    if (ce_table_has_column($pdo, 'themes', 'rationale')) {
        $cols[] = 'rationale';
        $vals[] = ':rationale';
        $params['rationale'] = $rationale;
    }
    if (ce_table_has_column($pdo, 'themes', 'source_article_url')) {
        $cols[] = 'source_article_url';
        $vals[] = ':source_article_url';
        $params['source_article_url'] = $sourceArticleUrl;
    }
    if (ce_table_has_column($pdo, 'themes', 'cycle_week')) {
        $cols[] = 'cycle_week';
        $vals[] = ':cycle_week';
        $params['cycle_week'] = $cycleWeek;
    }
    if (ce_table_has_column($pdo, 'themes', 'phase_key')) {
        $cols[] = 'phase_key';
        $vals[] = ':phase_key';
        $params['phase_key'] = $phaseKey !== '' ? $phaseKey : null;
    }
    if (ce_table_has_column($pdo, 'themes', 'status')) {
        $cols[] = 'status';
        $vals[] = ':status';
        $params['status'] = 'proposed';
    }
    if (ce_table_has_column($pdo, 'themes', 'feedback')) {
        $cols[] = 'feedback';
        $vals[] = ':feedback';
        $params['feedback'] = 'none';
    }

    $sql = 'INSERT INTO themes (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $pdo->prepare($sql)->execute($params);

    $stmt = $pdo->prepare('SELECT * FROM themes WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $themeId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Theme aanmaken mislukt');
    }

    return ['theme' => ce_theme_dto($pdo, $row)];
}

/** @param array<string, mixed> $body */
function ce_patch_theme(PDO $pdo, string $themeId, array $body): array
{
    if (!ce_table_exists($pdo, 'themes')) {
        throw new RuntimeException('themes-tabel ontbreekt');
    }

    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'theme', $themeId);

    require_once __DIR__ . '/copy-modus.php';
    ce_ensure_theme_copy_modus_columns($pdo);

    $map = [
        'title' => 'title',
        'motivation' => 'motivation',
        'rationale' => 'rationale',
        'sourceArticleUrl' => 'source_article_url',
        'feedback' => 'feedback',
        'editorSuggested' => 'editor_suggested',
    ];

    $sets = [];
    $params = ['id' => $themeId];

    foreach ($map as $jsonKey => $dbCol) {
        if (!array_key_exists($jsonKey, $body) || !ce_table_has_column($pdo, 'themes', $dbCol)) {
            continue;
        }
        $value = (string) $body[$jsonKey];
        if ($dbCol === 'source_article_url') {
            $value = ce_normalize_theme_source_article_url($value);
        }
        if ($dbCol === 'editor_suggested') {
            $value = trim($value) ?: null;
            if ($value !== null) {
                $clientId = ce_default_client_id($pdo);
                $allowed = $clientId !== null && $clientId !== ''
                    ? ce_client_editor_option_labels($pdo, $clientId)
                    : [];
                if ($allowed !== [] && !in_array($value, $allowed, true)) {
                    throw new InvalidArgumentException('Onbekende auteur: ' . $value);
                }
            }
        }
        $sets[] = "$dbCol = :$dbCol";
        $params[$dbCol] = $value;
    }

    if (array_key_exists('copyModusOverrideActivatie', $body)
        && ce_table_has_column($pdo, 'themes', 'copy_modus')
        && ce_table_has_column($pdo, 'themes', 'copy_modus_override')) {
        if ($body['copyModusOverrideActivatie'] === true || $body['copyModusOverrideActivatie'] === 1
            || $body['copyModusOverrideActivatie'] === '1' || $body['copyModusOverrideActivatie'] === 'true') {
            $sets[] = 'copy_modus = :copy_modus';
            $sets[] = 'copy_modus_override = 1';
            $params['copy_modus'] = CE_COPY_MODUS_SELL;
        } elseif ($body['copyModusOverrideActivatie'] === false || $body['copyModusOverrideActivatie'] === 0
            || $body['copyModusOverrideActivatie'] === '0' || $body['copyModusOverrideActivatie'] === 'false') {
            $sets[] = 'copy_modus_override = 0';
            $modus = CE_COPY_MODUS_MERK;
            if (array_key_exists('copyModus', $body) && ce_copy_modus_is_valid((string) $body['copyModus'])) {
                $modus = (string) $body['copyModus'];
            }
            $sets[] = 'copy_modus = :copy_modus';
            $params['copy_modus'] = $modus;
        }
    } elseif (array_key_exists('copyModus', $body) && ce_copy_modus_is_valid((string) $body['copyModus'])
        && ce_table_has_column($pdo, 'themes', 'copy_modus')) {
        $sets[] = 'copy_modus = :copy_modus';
        $params['copy_modus'] = (string) $body['copyModus'];
    }

    if ($sets === []) {
        throw new InvalidArgumentException('Geen velden om bij te werken');
    }

    $pdo->prepare('UPDATE themes SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

    $stmt = $pdo->prepare('SELECT * FROM themes WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $themeId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Theme niet gevonden');
    }

    ce_persist_theme_source_article_url($pdo, $themeId);
    $stmt->execute(['id' => $themeId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Theme niet gevonden');
    }

    return ['theme' => ce_theme_dto($pdo, $row)];
}

function ce_submit_theme(PDO $pdo, string $themeId): array
{
    if (!ce_table_exists($pdo, 'themes')) {
        throw new RuntimeException('themes-tabel ontbreekt');
    }

    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'theme', $themeId);

    ce_persist_theme_source_article_url($pdo, $themeId);

    $status = ce_pick_status_value($pdo, 'themes', ['submitted', 'converted_to_slot', 'proposed']);
    if (in_array('submitted', ce_column_enum_values($pdo, 'themes', 'status'), true)) {
        $status = 'submitted';
    }

    $pdo->prepare('UPDATE themes SET status = :status WHERE id = :id')
        ->execute(['status' => $status, 'id' => $themeId]);

    $webhook = trim((string) (getenv('N8N_CE01B_WEBHOOK_URL') ?: ''));
    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($webhook === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $webhook = trim((string) ($config['ce01b_webhook_url'] ?? ''));
        }
    }

    $triggered = false;
    $webhookResult = null;
    if ($webhook !== '') {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode(['theme_id' => $themeId], JSON_THROW_ON_ERROR),
                'timeout' => 180,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($webhook, false, $ctx);
        $triggered = true;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $webhookResult = $decoded;
            }
        }
    }

    $stmt = $pdo->prepare('SELECT * FROM themes WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $themeId]);
    $row = $stmt->fetch();

    if ($row) {
        $themeClientId = trim((string) ($row['client_id'] ?? ''));
        if ($themeClientId !== '') {
            require_once __DIR__ . '/tenant-activity-repository.php';
            ce_track_tenant_activity($pdo, $themeClientId, 'themes_reviewed');
        }
    }

    return [
        'theme' => $row ? ce_theme_dto($pdo, $row) : null,
        'webhookTriggered' => $triggered,
        'webhookResult' => $webhookResult,
    ];
}

/** @return array<string, mixed> */
function ce_patch_research_feedback(PDO $pdo, string $itemId, string $feedback): array
{
    if (!ce_table_exists($pdo, 'research_items')) {
        throw new RuntimeException('research_items-tabel ontbreekt');
    }

    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'research', $itemId);

    $allowed = ce_column_enum_values($pdo, 'research_items', 'feedback');
    if ($allowed !== [] && !in_array($feedback, $allowed, true)) {
        $aliases = ['like' => 'liked', 'forget' => 'forgotten', 'delete' => 'forgotten', 'none' => 'none'];
        $feedback = $aliases[$feedback] ?? $feedback;
        if (!in_array($feedback, $allowed, true)) {
            throw new InvalidArgumentException('Ongeldige feedback-waarde');
        }
    }

    $pdo->prepare('UPDATE research_items SET feedback = :feedback WHERE id = :id')
        ->execute(['feedback' => $feedback, 'id' => $itemId]);

    $stmt = $pdo->prepare('SELECT * FROM research_items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $itemId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Research-item niet gevonden');
    }

    return ['item' => ce_research_item_dto($row)];
}

/** @param list<array<string, mixed>> $rawRows */
function ce_build_research_items_from_raw_rows(
    array $rawRows,
    int $limit = 10,
    ?PDO $pdo = null,
    ?string $clientId = null,
): array {
    $items = [];
    $seen = [];
    foreach ($rawRows as $row) {
        if ($pdo !== null && ce_research_item_is_own_linkedin($pdo, $clientId, $row)) {
            continue;
        }

        $id = (string) ($row['id'] ?? '');
        if ($id === '' || isset($seen['id:' . $id])) {
            continue;
        }

        $url = strtolower(trim((string) ($row['url'] ?? '')));
        $title = preg_replace('/\s+/u', ' ', strtolower(trim((string) ($row['title'] ?? '')))) ?? '';
        $snippet = strtolower(trim(substr((string) ($row['snippet'] ?? ''), 0, 120)));

        $duplicate = false;
        if ($url !== '' && isset($seen['url:' . $url])) {
            $duplicate = true;
        }
        if (!$duplicate && $title !== '' && isset($seen['title:' . $title])) {
            $duplicate = true;
        }
        if (!$duplicate && $snippet !== '' && strlen($snippet) > 20 && isset($seen['snippet:' . $snippet])) {
            $duplicate = true;
        }
        if ($duplicate) {
            continue;
        }

        $seen['id:' . $id] = true;
        if ($url !== '') {
            $seen['url:' . $url] = true;
        }
        if ($title !== '') {
            $seen['title:' . $title] = true;
        }
        if ($snippet !== '') {
            $seen['snippet:' . $snippet] = true;
        }

        $items[] = ce_research_item_dto($row);
        if (count($items) >= $limit) {
            break;
        }
    }

    return $items;
}

function ce_research_feedback_is_open(?string $feedback): bool
{
    $value = strtolower(trim((string) $feedback));

    return $value === '' || $value === 'none';
}

function ce_resolve_slot_id_for_research_item(PDO $pdo, array $row): string
{
    $slotId = trim((string) ($row['slot_id'] ?? ''));
    if ($slotId !== '') {
        return $slotId;
    }

    $themeId = trim((string) ($row['theme_id'] ?? ''));
    if ($themeId === '' || !ce_table_exists($pdo, 'content_slots')) {
        return '';
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT id FROM content_slots
WHERE theme_id = :theme_id
  AND COALESCE(status, '') NOT IN ('archived', 'archive', 'deleted')
ORDER BY
  CASE COALESCE(status, '')
    WHEN 'themed' THEN 0
    WHEN 'research_done' THEN 1
    WHEN 'open' THEN 2
    WHEN 'planned' THEN 3
    ELSE 4
  END,
  created_at ASC
LIMIT 1
SQL
    );
    $stmt->execute(['theme_id' => $themeId]);

    return trim((string) ($stmt->fetchColumn() ?: ''));
}

/** @return array<string, mixed> */
function ce_pending_research_item_enriched(PDO $pdo, array $row): array
{
    $dto = ce_research_item_dto($row);
    $themeId = trim((string) ($row['theme_id'] ?? ''));
    $slotId = ce_resolve_slot_id_for_research_item($pdo, $row);

    $themeTitle = '';
    $themeRationale = '';
    $phaseKey = '';
    $weekLabel = '';

    if ($themeId !== '' && ce_table_exists($pdo, 'themes')) {
        $cols = ['title'];
        if (ce_table_has_column($pdo, 'themes', 'rationale')) {
            $cols[] = 'rationale';
        }
        if (ce_table_has_column($pdo, 'themes', 'phase_key')) {
            $cols[] = 'phase_key';
        }
        if (ce_table_has_column($pdo, 'themes', 'cycle_week')) {
            $cols[] = 'cycle_week';
        }
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM themes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $themeId]);
        $themeRow = $stmt->fetch() ?: null;
        if ($themeRow) {
            $themeTitle = trim((string) ($themeRow['title'] ?? ''));
            $themeRationale = trim((string) ($themeRow['rationale'] ?? ''));
            $phaseKey = trim((string) ($themeRow['phase_key'] ?? ''));
            $cycleWeek = trim((string) ($themeRow['cycle_week'] ?? ''));
            if ($phaseKey === '' && $cycleWeek !== '') {
                $phaseKey = ce_phase_key_from_date($cycleWeek) ?? $cycleWeek;
            }
        }
    }

    if ($slotId !== '' && ce_table_exists($pdo, 'content_slots')) {
        require_once __DIR__ . '/slot-repository.php';
        $slotRow = ce_fetch_fastlane_slot_row($pdo, $slotId);
        if ($slotRow) {
            if ($themeTitle === '') {
                $themeTitle = trim((string) ($slotRow['theme'] ?? ''));
            }
            if ($phaseKey === '') {
                $phaseKey = trim((string) ($slotRow['phase_key'] ?? ''));
            }
        }
    }

    if ($themeTitle === '') {
        $themeTitle = 'Theme';
    }

    $weekNumber = null;
    if ($phaseKey !== '' && ctype_digit($phaseKey)) {
        $weekNumber = (int) $phaseKey;
    }
    $weekLabel = ce_week_label($phaseKey !== '' ? $phaseKey : null, $weekNumber);

    return array_merge($dto, [
        'slotId' => $slotId,
        'themeId' => $themeId,
        'themeTitle' => $themeTitle,
        'themeRationale' => $themeRationale,
        'weekLabel' => $weekLabel,
        'phaseKey' => $phaseKey,
    ]);
}

/** @return array<string, mixed> */
function ce_fetch_pending_research_queue(PDO $pdo, ?string $clientId = null): array
{
    if (!ce_table_exists($pdo, 'research_items')) {
        return ['items' => [], 'total' => 0];
    }

    require_once __DIR__ . '/sources-repository.php';
    $clientId = $clientId ?? ce_default_client_id($pdo);

    $rows = [];
    if ($clientId !== null && ce_table_has_column($pdo, 'research_items', 'client_id')) {
        $stmt = $pdo->prepare(
            <<<'SQL'
SELECT * FROM research_items
WHERE client_id = :client_id
  AND (feedback IS NULL OR feedback = '' OR feedback = 'none')
ORDER BY created_at ASC, title ASC
LIMIT 200
SQL
        );
        $stmt->execute(['client_id' => $clientId]);
        $rows = $stmt->fetchAll() ?: [];
    } elseif ($clientId !== null && ce_table_exists($pdo, 'content_slots')) {
        $stmt = $pdo->prepare(
            <<<'SQL'
SELECT ri.*
FROM research_items ri
LEFT JOIN content_slots s ON s.id = ri.slot_id
LEFT JOIN themes t ON t.id = ri.theme_id
WHERE (ri.feedback IS NULL OR ri.feedback = '' OR ri.feedback = 'none')
  AND (s.client_id = :client_id OR t.client_id = :client_id)
ORDER BY ri.created_at ASC, ri.title ASC
LIMIT 200
SQL
        );
        $stmt->execute(['client_id' => $clientId]);
        $rows = $stmt->fetchAll() ?: [];
    } else {
        $rows = $pdo->query(
            <<<'SQL'
SELECT * FROM research_items
WHERE feedback IS NULL OR feedback = '' OR feedback = 'none'
ORDER BY created_at ASC, title ASC
LIMIT 200
SQL
        )->fetchAll() ?: [];
    }

    $items = [];
    foreach ($rows as $row) {
        if (!ce_research_feedback_is_open((string) ($row['feedback'] ?? ''))) {
            continue;
        }
        if (ce_research_item_is_own_linkedin($pdo, $clientId, $row)) {
            continue;
        }
        $items[] = ce_pending_research_item_enriched($pdo, $row);
    }

    usort($items, static function (array $a, array $b): int {
        $weekCmp = strcmp((string) ($a['weekLabel'] ?? ''), (string) ($b['weekLabel'] ?? ''));
        if ($weekCmp !== 0) {
            return $weekCmp;
        }
        $themeCmp = strcasecmp((string) ($a['themeTitle'] ?? ''), (string) ($b['themeTitle'] ?? ''));
        if ($themeCmp !== 0) {
            return $themeCmp;
        }

        return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });

    return [
        'items' => $items,
        'total' => count($items),
    ];
}

/** @return array{slotTheme: string, themeId: string, themeRationale: string, slotRow: ?array, isFastlaneSlot: bool, phaseKey: string} */
function ce_resolve_slot_research_context(PDO $pdo, string $slotId): array
{
    require_once __DIR__ . '/slot-repository.php';

    $slotTheme = '';
    $themeId = '';
    $themeRationale = '';
    $phaseKey = '';
    $slotRow = null;

    if (ce_table_exists($pdo, 'content_slots')) {
        $slotRow = ce_fetch_fastlane_slot_row($pdo, $slotId);
        if ($slotRow) {
            $slotTheme = (string) ($slotRow['theme'] ?? '');
            $themeId = (string) ($slotRow['theme_id'] ?? '');
            $phaseKey = trim((string) ($slotRow['phase_key'] ?? ''));
        }
    }

    if ($slotRow !== null) {
        require_once __DIR__ . '/mappers.php';
        if (ce_slot_is_campaign_theme($slotRow)) {
            require_once __DIR__ . '/campagne-preview-repository.php';
            ce_campagne_repair_slot_campaign_columns($pdo, $slotId);
            $slotRow = ce_fetch_fastlane_slot_row($pdo, $slotId) ?? $slotRow;
            $campaignTitle = ce_campagne_resolve_slot_title($pdo, $slotRow);
            if ($campaignTitle !== '') {
                $slotTheme = $campaignTitle;
            }
        }
    }

    if ($themeId !== '' && ce_table_exists($pdo, 'themes')) {
        $cols = ['title'];
        if (ce_table_has_column($pdo, 'themes', 'rationale')) {
            $cols[] = 'rationale';
        }
        $t = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM themes WHERE id = :id LIMIT 1');
        $t->execute(['id' => $themeId]);
        $themeRow = $t->fetch();
        if ($themeRow) {
            $themeTitle = trim((string) ($themeRow['title'] ?? ''));
            if (
                $themeTitle !== ''
                && ($slotRow === null || !ce_slot_is_campaign_theme($slotRow))
            ) {
                $slotTheme = $themeTitle;
            }
            $themeRationale = trim((string) ($themeRow['rationale'] ?? ''));
        }
    }

    return [
        'slotTheme' => $slotTheme,
        'themeId' => $themeId,
        'themeRationale' => $themeRationale,
        'slotRow' => $slotRow,
        'isFastlaneSlot' => $slotRow !== null && ce_is_fastlane_research_slot($pdo, $slotRow),
        'phaseKey' => $phaseKey,
    ];
}

/** @param array{slotTheme: string, themeId: string, themeRationale: string, slotRow: ?array, isFastlaneSlot: bool, phaseKey: string} $context */
function ce_fetch_research_raw_rows_for_slot(PDO $pdo, string $slotId, array $context): array
{
    if (!ce_table_exists($pdo, 'research_items')) {
        return [];
    }

    $themeId = (string) ($context['themeId'] ?? '');
    $isFastlaneSlot = (bool) ($context['isFastlaneSlot'] ?? false);
    $rawRows = [];

    if ($isFastlaneSlot) {
        $stmt = $pdo->prepare(
            'SELECT * FROM research_items WHERE slot_id = :slot_id ORDER BY created_at ASC, title ASC LIMIT 20'
        );
        $stmt->execute(['slot_id' => $slotId]);
        $rawRows = $stmt->fetchAll() ?: [];

        if ($themeId !== '') {
            $stmt = $pdo->prepare(
                <<<'SQL'
                SELECT * FROM research_items
                WHERE theme_id = :theme_id
                  AND (slot_id IS NULL OR slot_id = '' OR slot_id <> :slot_id)
                ORDER BY created_at ASC, title ASC
                LIMIT 20
                SQL
            );
            $stmt->execute(['theme_id' => $themeId, 'slot_id' => $slotId]);
            $rawRows = array_merge($rawRows, $stmt->fetchAll() ?: []);
        }
    } elseif ($themeId !== '') {
        $stmt = $pdo->prepare(
            'SELECT * FROM research_items
             WHERE slot_id = :slot_id
                OR (theme_id = :theme_id AND (slot_id IS NULL OR slot_id = \'\'))
             ORDER BY created_at ASC, title ASC
             LIMIT 20'
        );
        $stmt->execute(['slot_id' => $slotId, 'theme_id' => $themeId]);
        $rawRows = $stmt->fetchAll() ?: [];
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM research_items WHERE slot_id = :slot_id ORDER BY created_at ASC, title ASC LIMIT 20'
        );
        $stmt->execute(['slot_id' => $slotId]);
        $rawRows = $stmt->fetchAll() ?: [];
    }

    return $rawRows;
}

/** @return array<string, mixed> */
function ce_fetch_research_theme_group(PDO $pdo, string $slotId, int $itemLimit = 10): array
{
    $context = ce_resolve_slot_research_context($pdo, $slotId);
    $clientId = trim((string) ($context['slotRow']['client_id'] ?? ''));
    if ($clientId === '') {
        require_once __DIR__ . '/sources-repository.php';
        $clientId = (string) (ce_default_client_id($pdo) ?? '');
    }
    $items = ce_build_research_items_from_raw_rows(
        ce_fetch_research_raw_rows_for_slot($pdo, $slotId, $context),
        $itemLimit,
        $pdo,
        $clientId !== '' ? $clientId : null,
    );

    return [
        'slotId' => $slotId,
        'themeId' => $context['themeId'],
        'themeTitle' => $context['slotTheme'],
        'themeRationale' => $context['themeRationale'],
        'expectsArticleWrite' => $context['isFastlaneSlot'],
        'items' => $items,
    ];
}

function ce_resolve_cycle_week_for_research(PDO $pdo, string $phaseKey, string $anchorSlotId): string
{
    if ($anchorSlotId !== '' && ce_table_exists($pdo, 'content_slots')) {
        $stmt = $pdo->prepare(
            <<<'SQL'
SELECT t.cycle_week
FROM content_slots s
LEFT JOIN themes t ON t.id = s.theme_id
WHERE s.id = :slot_id
LIMIT 1
SQL
        );
        $stmt->execute(['slot_id' => $anchorSlotId]);
        $cycleWeek = substr(trim((string) ($stmt->fetchColumn() ?: '')), 0, 10);
        if ($cycleWeek !== '') {
            return $cycleWeek;
        }
    }

    $phaseKey = trim($phaseKey);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $phaseKey)) {
        return $phaseKey;
    }
    if (preg_match('/^\d{1,2}$/', $phaseKey)) {
        return ce_cycle_monday_for_iso_week((int) $phaseKey);
    }

    return '';
}

/** @return list<string> */
function ce_fetch_week_research_slot_ids(PDO $pdo, ?string $clientId, string $phaseKey, string $anchorSlotId): array
{
    if ($phaseKey === '' || !ce_table_exists($pdo, 'content_slots')) {
        return $anchorSlotId !== '' ? [$anchorSlotId] : [];
    }

    $cycleWeek = ce_resolve_cycle_week_for_research($pdo, $phaseKey, $anchorSlotId);
    if ($cycleWeek === '' || ce_cycle_week_date_range($cycleWeek) === null) {
        return $anchorSlotId !== '' ? [$anchorSlotId] : [];
    }

    require_once __DIR__ . '/article-repository.php';
    $articleJoin = ce_sql_latest_article_join('s');
    $sql = <<<SQL
SELECT s.id
FROM content_slots s
INNER JOIN themes t ON t.id = s.theme_id
{$articleJoin}
WHERE COALESCE(s.status, '') NOT IN ('archived', 'archive', 'deleted')
  AND (
    a.id IS NULL
    OR COALESCE(a.status, '') NOT IN ('archived', 'archive', 'published')
  )
  AND
SQL;
    $sql .= ce_sql_slot_belongs_to_cycle_week();
    $params = [
        'cycle_week' => $cycleWeek,
    ];
    $scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId);
    $sql .= $scope['sql'] . "\nORDER BY s.theme ASC, s.created_at ASC";
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $slotIds = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '') {
            $slotIds[] = $id;
        }
    }

    if ($anchorSlotId !== '' && !in_array($anchorSlotId, $slotIds, true)) {
        array_unshift($slotIds, $anchorSlotId);
    }

    return array_values(array_unique($slotIds));
}

/** @return array<string, mixed> */
function ce_fetch_slot_research(PDO $pdo, string $slotId): array
{
    if (!ce_table_exists($pdo, 'research_items')) {
        return ['slotId' => $slotId, 'items' => [], 'themeGroups' => [], 'note' => 'research_items-tabel ontbreekt'];
    }

    require_once __DIR__ . '/slot-repository.php';
    require_once __DIR__ . '/publish-settings-repository.php';
    if (!ce_slot_exists($pdo, $slotId)) {
        throw new RuntimeException('Slot niet gevonden');
    }

    $clientId = ce_client_id_for_slot($pdo, $slotId);
    if ($clientId === null || $clientId === '') {
        throw new RuntimeException('Slot niet gevonden');
    }
    ce_assert_slot_client_access($pdo, $slotId, $clientId);

    $slotRow = ce_fetch_fastlane_slot_row($pdo, $slotId);
    $slotStatus = $slotRow ? ce_slot_status_to_ui((string) ($slotRow['status'] ?? '')) : '';
    $anchorContext = ce_resolve_slot_research_context($pdo, $slotId);
    $phaseKey = (string) ($anchorContext['phaseKey'] ?? '');
    $postsPerWeek = ce_client_posts_per_week($pdo, $clientId);
    $slotIds = ce_fetch_week_research_slot_ids($pdo, $clientId, $phaseKey, $slotId);
    if ($slotIds === []) {
        $slotIds = [$slotId];
    }
    $slotIds = array_slice($slotIds, 0, max(1, $postsPerWeek));

    $themeGroups = [];
    $seenThemeTitles = [];
    foreach ($slotIds as $weekSlotId) {
        $group = ce_fetch_research_theme_group($pdo, $weekSlotId);
        $themeTitleKey = preg_replace('/\s+/u', ' ', strtolower(trim((string) ($group['themeTitle'] ?? '')))) ?? '';
        if ($themeTitleKey !== '' && isset($seenThemeTitles[$themeTitleKey])) {
            $existingIndex = $seenThemeTitles[$themeTitleKey];
            $existing = $themeGroups[$existingIndex];
            $existingItems = is_array($existing['items'] ?? null) ? $existing['items'] : [];
            $newItems = is_array($group['items'] ?? null) ? $group['items'] : [];
            $seenItemIds = [];
            foreach ($existingItems as $item) {
                $seenItemIds[(string) ($item['id'] ?? '')] = true;
            }
            foreach ($newItems as $item) {
                $itemId = (string) ($item['id'] ?? '');
                if ($itemId === '' || isset($seenItemIds[$itemId])) {
                    continue;
                }
                $existingItems[] = $item;
                $seenItemIds[$itemId] = true;
            }
            $themeGroups[$existingIndex]['items'] = array_slice($existingItems, 0, 10);
            if ($weekSlotId === $slotId) {
                $themeGroups[$existingIndex]['slotId'] = $slotId;
            }
            continue;
        }
        if ($themeTitleKey !== '') {
            $seenThemeTitles[$themeTitleKey] = count($themeGroups);
        }
        $themeGroups[] = $group;
    }

    if ($themeGroups === []) {
        $themeGroups[] = ce_fetch_research_theme_group($pdo, $slotId);
    }

    $anchorInGroups = false;
    foreach ($themeGroups as $group) {
        if (($group['slotId'] ?? '') === $slotId) {
            $anchorInGroups = true;
            break;
        }
    }
    if (!$anchorInGroups) {
        $themeGroups[] = ce_fetch_research_theme_group($pdo, $slotId);
    }

    $anchorGroup = $themeGroups[0];
    foreach ($themeGroups as $group) {
        if (($group['slotId'] ?? '') === $slotId) {
            $anchorGroup = $group;
            break;
        }
    }

    $weekNumber = null;
    if ($phaseKey !== '' && ctype_digit($phaseKey)) {
        $weekNumber = (int) $phaseKey;
    }

    $items = is_array($anchorGroup['items'] ?? null) ? $anchorGroup['items'] : [];
    $researchNotice = null;
    if (
        $slotStatus === 'research_done'
        && $items === []
        && $slotRow !== null
        && ce_is_fastlane_research_slot($pdo, $slotRow)
    ) {
        $researchNotice =
            'We konden deze pagina\'s niet lezen. Probeer andere links of plak de tekst bij Extra gedachten.';
    }

    return [
        'slotId' => $slotId,
        'slotStatus' => $slotStatus,
        'weekLabel' => ce_week_label($phaseKey !== '' ? $phaseKey : null, $weekNumber),
        'weekNumber' => $weekNumber,
        'phaseKey' => $phaseKey,
        'slotTheme' => (string) ($anchorGroup['themeTitle'] ?? ''),
        'themeRationale' => (string) ($anchorGroup['themeRationale'] ?? ''),
        'expectsArticleWrite' => (bool) ($anchorGroup['expectsArticleWrite'] ?? false),
        'items' => $items,
        'themeGroups' => $themeGroups,
        'researchNotice' => $researchNotice,
    ];
}

function ce_theme_is_planning_placeholder(array $row): bool
{
    $title = strtolower(trim((string) ($row['title'] ?? '')));
    $motivation = strtolower(trim((string) ($row['motivation'] ?? '')));
    $rationale = strtolower(trim((string) ($row['rationale'] ?? '')));

    return str_contains($title, 'planning reservering')
        || str_contains($title, 'planning-reservering')
        || str_contains($title, 'theme rationale')
        || str_contains($motivation, 'interne planning')
        || str_contains($rationale, 'placeholder');
}

function ce_cycle_monday_for_iso_week(int $weekNum, ?int $isoYear = null): string
{
    require_once __DIR__ . '/mappers.php';
    $range = ce_iso_week_range($weekNum, $isoYear);

    return $range['start']->format('Y-m-d');
}

/** @return array<string, mixed> */
function ce_trigger_ce01_theme_director(
    string $clientId,
    array $refillCycleWeeks = [],
    ?int $proposalCount = null,
    bool $fireAndForget = false,
    int $timeoutSeconds = 180,
): array {
    $clientId = trim($clientId);
    if ($clientId === '') {
        throw new RuntimeException('client_id is verplicht');
    }

    $webhook = trim((string) (getenv('N8N_CE01_WEBHOOK_URL') ?: ''));
    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($webhook === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $webhook = trim((string) ($config['ce01_webhook_url'] ?? ''));
        }
    }

    if ($webhook === '') {
        return ['ok' => false, 'error' => 'N8N_CE01_WEBHOOK_URL is niet geconfigureerd'];
    }

    $payload = ['client_id' => $clientId];
    $weeks = array_values(array_unique(array_filter(array_map(
        static fn ($w) => substr(trim((string) $w), 0, 10),
        $refillCycleWeeks,
    ))));
    if ($weeks !== []) {
        $payload['refill_cycle_weeks'] = $weeks;
        $payload['proposal_count'] = max(1, $proposalCount ?? ce_theme_proposal_count());
    }

    require_once __DIR__ . '/content-rhythm-repository.php';
    $pdo = ce_db();
    $payload['content_rhythm'] = ce_content_rhythm_ce01_payload($pdo, $clientId);

    $timeoutSeconds = $fireAndForget ? min(4, max(1, $timeoutSeconds)) : max(5, $timeoutSeconds);

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            // CE-01 Respond OK komt pas ná Claude + insert (~20–40s). Te korte timeout
            // verbreekt de n8n-run (responseNode) en levert 0 themes op.
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($webhook, false, $ctx);
    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches)) {
                $httpCode = (int) $matches[1];
                break;
            }
        }
    }

    $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    $started = is_array($decoded) && (
        ($decoded['ok'] ?? false) === true
        || (int) ($decoded['themesCreated'] ?? 0) > 0
        || ($decoded['message'] ?? '') === 'Workflow was started'
    );
    if (!$started && $httpCode >= 200 && $httpCode < 300) {
        $started = true;
    }

    $webhookResponseMissing = false;
    if (!$started && $raw === false && $httpCode === 0) {
        $started = true;
        $webhookResponseMissing = true;
    }

    $responseMeta = is_array($decoded)
        ? $decoded
        : ['raw' => $raw === false ? null : $raw, 'httpCode' => $httpCode];
    if ($webhookResponseMissing) {
        $responseMeta['webhookResponseMissing'] = true;
    }

    if ($httpCode === 404) {
        ce_log_event(null, 'error', 'n8n', 'n8n_webhook_failed', 'CE-01 workflow niet actief in n8n', [
            'clientId' => $clientId,
            'workflow' => 'ce01',
            'httpStatus' => $httpCode,
        ]);

        return [
            'ok' => false,
            'webhookTriggered' => false,
            'error' => 'CE-01 workflow is niet actief in n8n — run push-ce01-n8n.sh',
            'response' => is_array($decoded) ? $decoded : ['raw' => $raw, 'httpCode' => $httpCode],
        ];
    }

    if (!$started && $httpCode >= 400) {
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error'] ?? '') : '';
        ce_log_event(null, 'error', 'n8n', 'n8n_webhook_failed', $message !== '' ? $message : 'CE-01 webhook mislukt', [
            'clientId' => $clientId,
            'workflow' => 'ce01',
            'httpStatus' => $httpCode,
        ]);

        return [
            'ok' => false,
            'webhookTriggered' => false,
            'error' => $message !== '' ? 'CE-01 webhook: ' . $message : 'CE-01 webhook mislukt (HTTP ' . $httpCode . ')',
            'response' => $responseMeta,
        ];
    }

    if (!$started) {
        return [
            'ok' => false,
            'webhookTriggered' => false,
            'error' => 'CE-01 webhook bereikte n8n niet — controleer N8N_CE01_WEBHOOK_URL',
            'response' => $responseMeta,
        ];
    }

    if ($fireAndForget) {
        return [
            'ok' => true,
            'webhookTriggered' => true,
            'async' => true,
            'response' => $responseMeta,
        ];
    }

    return [
        'ok' => true,
        'webhookTriggered' => true,
        'response' => $responseMeta,
    ];
}

function ce_ce02_count_research_items(PDO $pdo, ?string $themeId, ?string $slotId): int
{
    if ($slotId !== null && $slotId !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM research_items WHERE slot_id = :id');
        $stmt->execute(['id' => $slotId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    if ($themeId !== null && $themeId !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM research_items WHERE theme_id = :id');
        $stmt->execute(['id' => $themeId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    return 0;
}

/** @return array{ok: bool, items_created?: int, error?: string} */
function ce_wait_for_ce02_research(PDO $pdo, ?string $themeId, ?string $slotId, int $maxWaitSeconds = 120): array
{
    $maxWaitSeconds = max(5, $maxWaitSeconds);
    $deadline = time() + $maxWaitSeconds;

    while (time() < $deadline) {
        $count = ce_ce02_count_research_items($pdo, $themeId, $slotId);
        if ($count > 0) {
            return ['ok' => true, 'items_created' => $count];
        }

        sleep(2);
    }

    return [
        'ok' => false,
        'error' => 'CE-02: geen research items binnen ' . $maxWaitSeconds . ' seconden',
    ];
}

/** @return array<string, mixed> */
function ce_trigger_ce02_research(?string $themeId = null, ?string $slotId = null, bool $fireAndForget = false): array
{
    $themeId = trim((string) ($themeId ?? ''));
    $slotId = trim((string) ($slotId ?? ''));
    if ($themeId === '' && $slotId === '') {
        throw new RuntimeException('theme_id of slot_id is verplicht');
    }

    require_once __DIR__ . '/client-context.php';
    $scopePdo = ce_db();
    if ($themeId !== '') {
        ce_require_resource_access($scopePdo, 'theme', $themeId);
    }
    if ($slotId !== '') {
        ce_require_resource_access($scopePdo, 'slot', $slotId);
    }

    $webhook = trim((string) (getenv('N8N_CE02_WEBHOOK_URL') ?: ''));
    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($webhook === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $webhook = trim((string) ($config['ce02_webhook_url'] ?? ''));
        }
    }

    if ($webhook === '') {
        return ['ok' => false, 'error' => 'N8N_CE02_WEBHOOK_URL is niet geconfigureerd'];
    }

    require_once __DIR__ . '/mailbox-helper.php';
    require_once __DIR__ . '/client-context.php';
    $pdo = ce_db();
    ce_ensure_research_items_schema($pdo);
    $researchClientId = ce_resolve_client_id($pdo, null);
    if ($researchClientId !== null) {
        ce_mailbox_sync_client_sources($pdo, $researchClientId);
    }

    $payload = [];
    if ($themeId !== '') {
        $payload['theme_id'] = $themeId;
    }
    if ($slotId !== '') {
        $payload['slot_id'] = $slotId;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            // onReceived-webhooks antwoorden normaal binnen ~1s; korte timeout voorkomt
            // lange blokkades als n8n de HTTP-ack uitstelt (queue op n8n cloud).
            'timeout' => $fireAndForget ? 4 : 12,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($webhook, false, $ctx);
    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches)) {
                $httpCode = (int) $matches[1];
                break;
            }
        }
    }

    $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    $started = is_array($decoded) && (
        ($decoded['ok'] ?? false) === true
        || ($decoded['message'] ?? '') === 'Workflow was started'
    );
    if (!$started && $httpCode >= 200 && $httpCode < 300) {
        $started = true;
    }

    // n8n cloud: verzoek kan in de queue belanden zonder HTTP-antwoord binnen timeout
    // (executions status 'new'). Behandel dat als trigger, niet als mislukte URL.
    $webhookResponseMissing = false;
    if (!$started && $raw === false && $httpCode === 0) {
        $started = true;
        $webhookResponseMissing = true;
    }

    $responseMeta = is_array($decoded)
        ? $decoded
        : ['raw' => $raw === false ? null : $raw, 'httpCode' => $httpCode];
    if ($webhookResponseMissing) {
        $responseMeta['webhookResponseMissing'] = true;
    }

    if ($httpCode === 404) {
        return [
            'ok' => false,
            'webhookTriggered' => false,
            'error' => 'CE-02 workflow is niet actief in n8n — generator staat in CE-Cursor (Brandflocks ronde A)',
            'response' => $responseMeta,
        ];
    }

    if (!$started && $httpCode >= 400) {
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error'] ?? '') : '';
        if ($message === '' && is_string($raw) && $raw !== '') {
            $message = trim($raw);
        }

        return [
            'ok' => false,
            'webhookTriggered' => false,
            'error' => $message !== '' ? 'CE-02 webhook: ' . $message : 'CE-02 webhook mislukt (HTTP ' . $httpCode . ')',
            'response' => $responseMeta,
        ];
    }

    if (!$started) {
        return [
            'ok' => false,
            'webhookTriggered' => false,
            'error' => 'CE-02 webhook bereikte n8n niet — controleer N8N_CE02_WEBHOOK_URL',
            'response' => $responseMeta,
        ];
    }

    if ($fireAndForget) {
        return [
            'ok' => true,
            'webhookTriggered' => true,
            'async' => true,
            'response' => $responseMeta,
        ];
    }

    $wait = ce_wait_for_ce02_research($pdo, $themeId !== '' ? $themeId : null, $slotId !== '' ? $slotId : null, 120);
    if (empty($wait['ok'])) {
        $waitError = (string) ($wait['error'] ?? 'CE-02 research timeout');
        if ($webhookResponseMissing) {
            $waitError .= ' (n8n antwoordde niet op de webhook — controleer executions in n8n)';
        }

        return [
            'ok' => false,
            'webhookTriggered' => true,
            'error' => $waitError,
            'response' => $responseMeta,
        ];
    }

    return [
        'ok' => true,
        'webhookTriggered' => true,
        'items_created' => (int) ($wait['items_created'] ?? 0),
        'response' => $responseMeta,
    ];
}

/** Koppel theme-research aan dit slot vóór CE-03-telling / schrijven. */
function ce_backfill_research_slot_ids(PDO $pdo, string $slotId): void
{
    if ($slotId === '' || !ce_table_exists($pdo, 'research_items')) {
        return;
    }

    $context = ce_resolve_slot_research_context($pdo, $slotId);
    $themeId = trim((string) ($context['themeId'] ?? ''));
    if ($themeId === '') {
        return;
    }

    $pdo->prepare(
        'UPDATE research_items
         SET slot_id = :slot_id
         WHERE theme_id = :theme_id
           AND (slot_id IS NULL OR slot_id = \'\')'
    )->execute(['slot_id' => $slotId, 'theme_id' => $themeId]);
}

/** @return array<string, mixed> */
function ce_submit_slot_research(PDO $pdo, string $slotId): array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'slot', $slotId);

    if (!ce_slot_exists($pdo, $slotId)) {
        throw new RuntimeException('Slot niet gevonden');
    }

    ce_backfill_research_slot_ids($pdo, $slotId);

    $slotRow = ce_fetch_fastlane_slot_row($pdo, $slotId);
    require_once __DIR__ . '/fastlane-repository.php';
    require_once __DIR__ . '/research-quality.php';
    $expectsWrite = $slotRow !== null && ce_slot_eligible_for_write_after_research($pdo, $slotRow);
    $researchReady = ce_research_slot_ready_for_write($pdo, $slotId);

    if ($expectsWrite && ($researchReady['ok'] ?? false) !== true) {
        return array_merge(
            [
                'ok' => true,
                'slotId' => $slotId,
                'status' => $slotRow !== null ? ce_status_to_ui((string) ($slotRow['status'] ?? '')) : '',
                'expectsArticleWrite' => true,
                'articleWriteTriggered' => false,
                'reason' => 'insufficient_research',
                'message' => (string) ($researchReady['message'] ?? ce_research_insufficient_message()),
                'usableResearchCount' => (int) ($researchReady['usableCount'] ?? 0),
            ],
            ce_fetch_slot_research($pdo, $slotId),
        );
    }

    $writeResult = ce_prepare_fastlane_write_after_research($pdo, $slotId);
    $writeTriggered = ($writeResult['articleWriteTriggered'] ?? false) === true;

    $status = ce_pick_status_value($pdo, 'content_slots', ['research_done', 'planned', 'open']);
    $pdo->prepare('UPDATE content_slots SET status = :status WHERE id = :id')
        ->execute(['status' => $status, 'id' => $slotId]);

    if ($expectsWrite && !$writeTriggered) {
        return array_merge(
            [
                'ok' => true,
                'slotId' => $slotId,
                'status' => $status,
                'expectsArticleWrite' => true,
            ],
            ce_fetch_slot_research($pdo, $slotId),
            $writeResult,
        );
    }

    return array_merge(
        [
            'ok' => true,
            'slotId' => $slotId,
            'status' => $status,
            'expectsArticleWrite' => $expectsWrite || $writeTriggered,
        ],
        ce_fetch_slot_research($pdo, $slotId),
        $writeResult,
    );
}
