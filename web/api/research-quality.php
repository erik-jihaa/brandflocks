<?php

declare(strict_types=1);

/** Minimale snippet-lengte voor bruikbare research (briefing aandachtspunten §1). */
const CE_RESEARCH_MIN_SNIPPET_LEN = 80;

/** Minimaal aantal bruikbare items vóór CE-03 (agenda / weekritme). */
const CE_RESEARCH_MIN_USABLE_ITEMS = 3;

/** Snel artikel (fastlane): één bruikbare bron of snippet is genoeg. */
const CE_RESEARCH_MIN_USABLE_ITEMS_FASTLANE = 1;

function ce_research_insufficient_message(): string
{
    return 'Te weinig research om een artikel op te baseren — vul je bronnen aan of kies een ander thema.';
}

function ce_research_title_is_usable(?string $title): bool
{
    $title = trim((string) $title);
    if ($title === '') {
        return false;
    }

    return strcasecmp($title, 'untitled') !== 0;
}

function ce_research_snippet_is_usable(?string $snippet): bool
{
    return mb_strlen(trim((string) $snippet)) >= CE_RESEARCH_MIN_SNIPPET_LEN;
}

/** @param array<string, mixed> $row */
function ce_research_item_row_is_usable(array $row): bool
{
    return ce_research_title_is_usable($row['title'] ?? null)
        && ce_research_snippet_is_usable($row['snippet'] ?? null);
}

function ce_count_usable_research_for_slot(PDO $pdo, string $slotId): int
{
    if ($slotId === '' || !ce_table_exists($pdo, 'research_items')) {
        return 0;
    }

    require_once __DIR__ . '/theme-repository.php';
    $context = ce_resolve_slot_research_context($pdo, $slotId);
    $rows = ce_fetch_research_raw_rows_for_slot($pdo, $slotId, $context);

    $seen = [];
    $count = 0;
    foreach ($rows as $row) {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '') {
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
        }
        if (ce_research_item_row_is_usable($row)) {
            $count++;
        }
    }

    return $count;
}

function ce_research_min_usable_items_for_slot(PDO $pdo, string $slotId): int
{
    require_once __DIR__ . '/slot-repository.php';

    $slot = ce_fetch_fastlane_slot_row($pdo, $slotId);
    if ($slot !== null && ce_is_fastlane_research_slot($pdo, $slot)) {
        return CE_RESEARCH_MIN_USABLE_ITEMS_FASTLANE;
    }

    return CE_RESEARCH_MIN_USABLE_ITEMS;
}

/** @return array{ok: bool, usableCount: int, message?: string} */
function ce_research_slot_ready_for_write(PDO $pdo, string $slotId): array
{
    $usable = ce_count_usable_research_for_slot($pdo, $slotId);
    $minRequired = ce_research_min_usable_items_for_slot($pdo, $slotId);
    if ($usable >= $minRequired) {
        return ['ok' => true, 'usableCount' => $usable];
    }

    return [
        'ok' => false,
        'usableCount' => $usable,
        'message' => ce_research_insufficient_message(),
    ];
}
