<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/research-quality.php';
require_once __DIR__ . '/fastlane-repository.php';
require_once __DIR__ . '/article-repository.php';

/** Vaste testset (stap 1) — zie docs/testset-artikelen.md */
const CE_TESTSET_THEME_TITLE_PREFIX = '[nulmeting]';

/** SQL AND-fragment: verberg nulmeting-freeze-kopieën in Agenda/Home-lijsten. */
function ce_sql_exclude_testset_freeze_slots(string $slotAlias = 's', ?string $themeAlias = 't'): string
{
    $slotAlias = preg_replace('/[^a-z_]/', '', strtolower($slotAlias)) ?: 's';
    $parts = [
        "({$slotAlias}.input_human IS NULL OR {$slotAlias}.input_human NOT LIKE '%testsetFreeze%')",
    ];
    if ($themeAlias !== null) {
        $themeAlias = preg_replace('/[^a-z_]/', '', strtolower($themeAlias)) ?: 't';
        $parts[] = "({$themeAlias}.title IS NULL OR {$themeAlias}.title NOT LIKE '[nulmeting]%')";
    }

    return ' AND ' . implode(' AND ', $parts);
}

/** Vaste testset (stap 1) — zie docs/testset-artikelen.md */
const CE_TESTSET_SOURCE_SLOT_IDS = [
    'b6a729dc-6a18-420e-89a1-f9a5fbf5693b',
    '51322a62-1a0d-478f-ae9e-ba5bc69fc351',
    '90964f0e-0093-4301-953f-4f2f52823179',
    'e1dcab0b-129f-42c9-8c20-e86a4432a97d',
    '415f356b-9f9f-4313-84b2-1833d7f9a5ef',
    '2a8929ed-bed0-411a-84bb-f9c5f4d5e251',
];

/** @param array<string, mixed> $row */
function ce_testset_insert_row_copy(PDO $pdo, string $table, array $row, array $overrides): string
{
    $cols = ce_table_columns($pdo, $table);
    $newId = ce_uuid_v4();
    $insert = ['id' => $newId];

    foreach ($row as $column => $value) {
        if (!isset($cols[$column]) || $column === 'id') {
            continue;
        }
        if (in_array($column, ['created_at', 'updated_at'], true)) {
            continue;
        }
        $insert[$column] = $value;
    }

    foreach ($overrides as $column => $value) {
        if (isset($cols[$column])) {
            $insert[$column] = $value;
        }
    }

    $names = array_keys($insert);
    $placeholders = array_map(static fn (string $c): string => ':' . $c, $names);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $pdo->prepare($sql)->execute($insert);

    return $newId;
}

/** @return list<array<string, mixed>> */
function ce_testset_research_snapshot(PDO $pdo, string $slotId): array
{
    $context = ce_resolve_slot_research_context($pdo, $slotId);
    $rows = ce_fetch_research_raw_rows_for_slot($pdo, $slotId, $context);
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = [
            'id' => (string) ($row['id'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'source_type' => (string) ($row['source_type'] ?? ''),
            'stance' => $row['stance'] ?? null,
            'reliability' => $row['reliability'] ?? null,
            'snippet_len' => mb_strlen(trim((string) ($row['snippet'] ?? ''))),
        ];
    }

    return $out;
}

/**
 * Kopieer slot + thema + research binnen dezelfde tenant (origineel blijft intact).
 *
 * @return array<string, mixed>
 */
function ce_testset_freeze_copy_slot(PDO $pdo, string $sourceSlotId, string $freezeLabel = ''): array
{
    $sourceSlotId = trim($sourceSlotId);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceSlotId)) {
        throw new InvalidArgumentException('Ongeldig sourceSlotId');
    }

    $slotStmt = $pdo->prepare('SELECT * FROM content_slots WHERE id = :id LIMIT 1');
    $slotStmt->execute(['id' => $sourceSlotId]);
    $slotRow = $slotStmt->fetch(PDO::FETCH_ASSOC);
    if (!$slotRow) {
        throw new RuntimeException('Slot niet gevonden: ' . $sourceSlotId);
    }

    $clientId = (string) ($slotRow['client_id'] ?? '');
    $researchReady = ce_research_slot_ready_for_write($pdo, $sourceSlotId);
    if (($researchReady['ok'] ?? false) !== true) {
        throw new RuntimeException(
            'Research onvoldoende op bron-slot: ' . (string) ($researchReady['message'] ?? '')
        );
    }

    $freezeLabel = trim($freezeLabel) !== '' ? trim($freezeLabel) : gmdate('Y-m-d');
    $marker = json_encode([
        'testsetFreeze' => $freezeLabel,
        'sourceSlotId' => $sourceSlotId,
    ], JSON_THROW_ON_ERROR);

    $sourceThemeId = trim((string) ($slotRow['theme_id'] ?? ''));
    $newThemeId = null;
    if ($sourceThemeId !== '' && ce_table_exists($pdo, 'themes')) {
        $themeStmt = $pdo->prepare('SELECT * FROM themes WHERE id = :id LIMIT 1');
        $themeStmt->execute(['id' => $sourceThemeId]);
        $themeRow = $themeStmt->fetch(PDO::FETCH_ASSOC);
        if ($themeRow) {
            $title = trim((string) ($themeRow['title'] ?? ''));
            $newThemeId = ce_testset_insert_row_copy($pdo, 'themes', $themeRow, [
                'client_id' => $clientId,
                'title' => $title !== ''
                    ? CE_TESTSET_THEME_TITLE_PREFIX . ' ' . $title
                    : CE_TESTSET_THEME_TITLE_PREFIX,
                'status' => ce_pick_status_value($pdo, 'themes', ['converted_to_slot', 'submitted', 'proposed']),
            ]);
        }
    }

    $researchDone = ce_db_status_for_ui($pdo, 'content_slots', 'research_done');
    $newSlotId = ce_testset_insert_row_copy($pdo, 'content_slots', $slotRow, [
        'client_id' => $clientId,
        'theme_id' => $newThemeId ?? $sourceThemeId,
        'status' => $researchDone,
        'input_human' => $marker,
        'publish_date' => null,
        'campaign_id' => null,
    ]);

    $context = ce_resolve_slot_research_context($pdo, $sourceSlotId);
    $researchRows = ce_fetch_research_raw_rows_for_slot($pdo, $sourceSlotId, $context);
    $seenResearch = [];
    $copiedResearchIds = [];

    foreach ($researchRows as $item) {
        if (!is_array($item)) {
            continue;
        }
        $rid = trim((string) ($item['id'] ?? ''));
        if ($rid !== '') {
            if (isset($seenResearch[$rid])) {
                continue;
            }
            $seenResearch[$rid] = true;
        }

        $newResearchId = ce_testset_insert_row_copy($pdo, 'research_items', $item, [
            'slot_id' => $newSlotId,
            'theme_id' => $newThemeId ?? $sourceThemeId,
        ]);
        $copiedResearchIds[] = $newResearchId;
    }

    ce_backfill_research_slot_ids($pdo, $newSlotId);

    $pdo->prepare('DELETE FROM articles WHERE slot_id = :slot_id')->execute(['slot_id' => $newSlotId]);
    ce_ensure_article_stub_for_slot($pdo, $newSlotId);

    $clearCols = ['content_ai', 'content_human', 'self_review', 'headline_human', 'write_directive'];
    $sets = [];
    foreach ($clearCols as $col) {
        if (ce_table_has_column($pdo, 'articles', $col)) {
            $sets[] = $col . ' = NULL';
        }
    }
    if (ce_table_has_column($pdo, 'articles', 'status')) {
        $draft = ce_db_status_for_ui($pdo, 'articles', 'draft');
        $sets[] = 'status = ' . $pdo->quote($draft);
    }
    if ($sets !== []) {
        $pdo->exec('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE slot_id = ' . $pdo->quote($newSlotId));
    }

    $themeTitle = trim((string) ($slotRow['theme'] ?? ''));
    if ($themeTitle === '' && $newThemeId !== null) {
        $t = $pdo->prepare('SELECT title FROM themes WHERE id = :id LIMIT 1');
        $t->execute(['id' => $newThemeId]);
        $themeTitle = trim((string) ($t->fetchColumn() ?: ''));
    }

    return [
        'sourceSlotId' => $sourceSlotId,
        'copySlotId' => $newSlotId,
        'clientId' => $clientId,
        'themeId' => $newThemeId ?? $sourceThemeId,
        'themeTitle' => $themeTitle,
        'researchSnapshot' => ce_testset_research_snapshot($pdo, $sourceSlotId),
        'copiedResearchCount' => count($copiedResearchIds),
        'usableResearchOnCopy' => ce_count_usable_research_for_slot($pdo, $newSlotId),
        'freezeLabel' => $freezeLabel,
    ];
}

/** @param list<string> $slotIds @return array<string, mixed> */
function ce_testset_freeze_slots(PDO $pdo, array $slotIds, string $freezeLabel = ''): array
{
    $results = [];
    foreach ($slotIds as $slotId) {
        $results[] = ce_testset_freeze_copy_slot($pdo, $slotId, $freezeLabel);
    }

    return [
        'ok' => true,
        'freezeLabel' => trim($freezeLabel) !== '' ? trim($freezeLabel) : gmdate('Y-m-d'),
        'slots' => $results,
    ];
}
