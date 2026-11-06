<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/api/bootstrap.php';
require_once dirname(__DIR__) . '/web/api/research-quality.php';

$pdo = ce_db();

echo "=== Bieze tenant ===\n";
$clients = $pdo->query(
    "SELECT id, company, name FROM clients WHERE LOWER(company) LIKE '%bieze%' OR LOWER(name) LIKE '%bieze%' ORDER BY company"
)->fetchAll(PDO::FETCH_ASSOC);
if ($clients === []) {
    echo "Geen client met 'bieze' in company/name.\n";
    exit(1);
}
foreach ($clients as $c) {
    echo sprintf("- %s | company=%s | name=%s\n", $c['id'], $c['company'] ?? '', $c['name'] ?? '');
}

$clientId = (string) $clients[0]['id'];

echo "\n=== Recente slots met artikel + self_review (Bieze) ===\n";
$slots = $pdo->prepare(
    <<<'SQL'
    SELECT s.id AS slot_id, s.theme, s.status AS slot_status, a.id AS article_id,
           LEFT(a.self_review, 200) AS self_review_preview, a.updated_at
    FROM content_slots s
    LEFT JOIN articles a ON a.slot_id = s.id
    WHERE s.client_id = :client_id
    ORDER BY a.updated_at DESC NULLS LAST, s.created_at DESC
    LIMIT 15
    SQL
);
// MySQL doesn't support NULLS LAST - fix query
$slots = $pdo->prepare(
    <<<'SQL'
    SELECT s.id AS slot_id, s.theme, s.status AS slot_status, a.id AS article_id,
           LEFT(a.self_review, 220) AS self_review_preview, a.updated_at
    FROM content_slots s
    LEFT JOIN articles a ON a.slot_id = s.id
    WHERE s.client_id = :client_id
    ORDER BY COALESCE(a.updated_at, s.created_at) DESC
    LIMIT 15
    SQL
);
$slots->execute(['client_id' => $clientId]);
$slotRows = $slots->fetchAll(PDO::FETCH_ASSOC);
foreach ($slotRows as $row) {
    $preview = (string) ($row['self_review_preview'] ?? '');
    $flag = str_contains(strtolower($preview), 'onderbouw') || str_contains(strtolower($preview), 'untitled')
        ? ' ***'
        : '';
    echo sprintf(
        "- slot=%s | %s | article=%s%s\n  review: %s\n",
        $row['slot_id'],
        $row['theme'] ?? '',
        $row['article_id'] ?? '(geen)',
        $flag,
        $preview !== '' ? $preview : '(leeg)',
    );
}

// Pick slot with onderbouwing in self_review, else most recent with article
$targetSlotId = null;
foreach ($slotRows as $row) {
    $preview = strtolower((string) ($row['self_review_preview'] ?? ''));
    if ($row['article_id'] && (str_contains($preview, 'onderbouw') || str_contains($preview, 'untitled'))) {
        $targetSlotId = (string) $row['slot_id'];
        break;
    }
}
if ($targetSlotId === null && $slotRows !== []) {
    foreach ($slotRows as $row) {
        if ($row['article_id']) {
            $targetSlotId = (string) $row['slot_id'];
            break;
        }
    }
}

if ($targetSlotId === null) {
    echo "\nGeen slot met artikel gevonden.\n";
    exit(0);
}

echo "\n=== Research items voor slot {$targetSlotId} ===\n";
$items = $pdo->prepare(
    <<<'SQL'
    SELECT ri.id, ri.source_type, ri.title, CHAR_LENGTH(ri.snippet) AS snip,
           LEFT(ri.snippet, 120) AS snippet_preview, ri.url, ri.stance, ri.reliability,
           ri.invalshoek, ri.created_at
    FROM research_items ri
    WHERE ri.slot_id = :slot_id
    ORDER BY ri.created_at
    SQL
);
$items->execute(['slot_id' => $targetSlotId]);
$researchRows = $items->fetchAll(PDO::FETCH_ASSOC);
if ($researchRows === []) {
    echo "Geen research_items voor dit slot — ook theme_id check:\n";
    $themeStmt = $pdo->prepare('SELECT theme_id FROM content_slots WHERE id = :id LIMIT 1');
    $themeStmt->execute(['id' => $targetSlotId]);
    $themeId = trim((string) ($themeStmt->fetchColumn() ?: ''));
    if ($themeId !== '') {
        $items = $pdo->prepare(
            'SELECT id, source_type, title, CHAR_LENGTH(snippet) AS snip, url FROM research_items WHERE theme_id = :theme_id ORDER BY created_at'
        );
        $items->execute(['theme_id' => $themeId]);
        $researchRows = $items->fetchAll(PDO::FETCH_ASSOC);
    }
}

$usable = 0;
foreach ($researchRows as $ri) {
    $title = (string) ($ri['title'] ?? '');
    $snip = (int) ($ri['snip'] ?? 0);
    $ok = ce_research_item_row_is_usable(['title' => $title, 'snippet' => str_repeat('x', $snip)]);
    if ($ok) {
        $usable++;
    }
    echo sprintf(
        "- %s | %s | title=%s | snip=%d | usable=%s | stance=%s | rel=%s\n  url=%s\n  preview=%s\n",
        $ri['id'] ?? '',
        $ri['source_type'] ?? '',
        $title,
        $snip,
        $ok ? 'yes' : 'NO',
        $ri['stance'] ?? 'NULL',
        $ri['reliability'] ?? 'NULL',
        $ri['url'] ?? '',
        $ri['snippet_preview'] ?? '',
    );
}

echo sprintf("\nTotaal: %d items, %d bruikbaar (drempel snippet >= %d)\n", count($researchRows), $usable, CE_RESEARCH_MIN_SNIPPET_LEN);
if ($usable < CE_RESEARCH_MIN_USABLE_ITEMS) {
    echo 'Conclusie: ' . ce_research_insufficient_message() . "\n";
}
