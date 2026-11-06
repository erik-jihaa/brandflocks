<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/hba-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$clientId = trim((string) ($_GET['clientId'] ?? $_GET['client_id'] ?? ''));
if ($clientId === '') {
    ce_json_error('clientId is verplicht', 400);
}

$pdo = ce_db();
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $clientId]);
$clientRow = $stmt->fetch();
if (!$clientRow) {
    ce_json_error('Client niet gevonden', 404);
}

/** @param list<string> $tables */
function ce_export_rows_for_client(PDO $pdo, string $table, string $clientId, array &$out): void
{
    if (!ce_table_exists($pdo, $table) || !ce_table_has_column($pdo, $table, 'client_id')) {
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE client_id = :client_id");
    $stmt->execute(['client_id' => $clientId]);
    $out[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$tables = [];
ce_export_rows_for_client($pdo, 'brand_narratives', $clientId, $tables);
ce_export_rows_for_client($pdo, 'objectives', $clientId, $tables);
ce_export_rows_for_client($pdo, 'sources', $clientId, $tables);
ce_export_rows_for_client($pdo, 'themes', $clientId, $tables);
ce_export_rows_for_client($pdo, 'campaigns', $clientId, $tables);
ce_export_rows_for_client($pdo, 'content_slots', $clientId, $tables);

$campaignIds = array_values(array_unique(array_filter(array_map(
    static fn ($r) => (string) ($r['campaign_id'] ?? ''),
    $tables['content_slots'] ?? [],
))));
if ($campaignIds !== [] && ce_table_exists($pdo, 'campaigns')) {
    $existingCampaignIds = array_values(array_filter(array_map(
        static fn ($r) => is_array($r) ? (string) ($r['id'] ?? '') : '',
        $tables['campaigns'] ?? [],
    )));
    $missing = array_values(array_diff($campaignIds, $existingCampaignIds));
    if ($missing !== []) {
        $ph = implode(', ', array_fill(0, count($missing), '?'));
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id IN ({$ph})");
        $stmt->execute($missing);
        $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $tables['campaigns'] = array_merge($tables['campaigns'] ?? [], $fetched);
    }
}
ce_export_rows_for_client($pdo, 'client_images', $clientId, $tables);

$slotIds = array_values(array_filter(array_map(
    static fn ($r) => (string) ($r['id'] ?? ''),
    $tables['content_slots'] ?? [],
)));

$articles = [];
$articleIds = [];
if ($slotIds !== [] && ce_table_exists($pdo, 'articles')) {
    $ph = implode(', ', array_fill(0, count($slotIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE slot_id IN ({$ph})");
    $stmt->execute($slotIds);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $articleIds = array_values(array_filter(array_map(
        static fn ($r) => (string) ($r['id'] ?? ''),
        $articles,
    )));
}

$related = [];
if (ce_table_exists($pdo, 'research_items') && ce_table_has_column($pdo, 'research_items', 'client_id')) {
    $stmt = $pdo->prepare('SELECT * FROM research_items WHERE client_id = :v');
    $stmt->execute(['v' => $clientId]);
    $related['research_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($slotIds !== [] && ce_table_exists($pdo, 'research_items')) {
    $ph = implode(', ', array_fill(0, count($slotIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM research_items WHERE slot_id IN ({$ph})");
    $stmt->execute($slotIds);
    $bySlot = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $merged = array_merge($related['research_items'] ?? [], $bySlot);
    $seen = [];
    $related['research_items'] = [];
    foreach ($merged as $researchRow) {
        $id = (string) ($researchRow['id'] ?? '');
        if ($id !== '' && !isset($seen[$id])) {
            $seen[$id] = true;
            $related['research_items'][] = $researchRow;
        }
    }
}

if ($articleIds !== [] && ce_table_exists($pdo, 'hba_scores')) {
    $ph = implode(', ', array_fill(0, count($articleIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM hba_scores WHERE post_id IN ({$ph})");
    $stmt->execute($articleIds);
    $related['hba_scores'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($articleIds !== [] && ce_table_exists($pdo, 'publications')) {
    $ph = implode(', ', array_fill(0, count($articleIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM publications WHERE article_id IN ({$ph})");
    $stmt->execute($articleIds);
    $related['publications'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

foreach (['hba_theme_verdicts', 'hba_research_verdicts', 'hba_guardrail_objects'] as $table) {
    if (!ce_table_exists($pdo, $table) || !ce_table_has_column($pdo, $table, 'client_id')) {
        continue;
    }
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE client_id = :client_id");
    $stmt->execute(['client_id' => $clientId]);
    $related[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

foreach (['linkedin_posts', 'llm_probleemvragen', 'llm_visibility_checks'] as $table) {
    if (!ce_table_exists($pdo, $table) || !ce_table_has_column($pdo, $table, 'client_id')) {
        continue;
    }
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE client_id = :client_id");
    $stmt->execute(['client_id' => $clientId]);
    $related[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Research gekoppeld via theme_id (kolom client_id ontbreekt vaak op research_items).
if (ce_table_exists($pdo, 'research_items') && ce_table_has_column($pdo, 'research_items', 'theme_id')) {
    $themeIds = array_values(array_filter(array_map(
        static fn ($r) => (string) ($r['id'] ?? ''),
        $tables['themes'] ?? [],
    )));
    if ($themeIds !== []) {
        $ph = implode(', ', array_fill(0, count($themeIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM research_items WHERE theme_id IN ({$ph})");
        $stmt->execute($themeIds);
        $byTheme = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $merged = array_merge($related['research_items'] ?? [], $byTheme);
        $seen = [];
        $related['research_items'] = [];
        foreach ($merged as $researchRow) {
            $id = (string) ($researchRow['id'] ?? '');
            if ($id !== '' && !isset($seen[$id])) {
                $seen[$id] = true;
                $related['research_items'][] = $researchRow;
            }
        }
    }
}

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'exportedAt' => gmdate('c'),
    'client' => $clientRow,
    'strategy' => ce_strategy_dto($pdo, $clientRow),
    'tables' => $tables,
    'articles' => $articles,
    'related' => $related,
]);
