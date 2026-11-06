<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/hba-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$bundle = is_array($body['bundle'] ?? null) ? $body['bundle'] : $body;
$clientId = trim((string) ($bundle['clientId'] ?? $bundle['client']['id'] ?? ''));
if ($clientId === '' || !is_array($bundle['client'] ?? null)) {
    ce_json_error('bundle met clientId en client is verplicht', 400);
}

/** @param list<array<string, mixed>> $rows */
function ce_bundle_ids(array $rows): array
{
    return array_values(array_filter(array_map(
        static fn ($row) => is_array($row) ? (string) ($row['id'] ?? '') : '',
        $rows,
    )));
}

function ce_import_delete_client_analytics(PDO $pdo, string $clientId): void
{
    if ($clientId === '') {
        return;
    }

    if (ce_table_exists($pdo, 'llm_visibility_checks') && ce_table_has_column($pdo, 'llm_visibility_checks', 'client_id')) {
        $pdo->prepare('DELETE FROM llm_visibility_checks WHERE client_id = :id')->execute(['id' => $clientId]);
    }
    if (ce_table_exists($pdo, 'llm_probleemvragen') && ce_table_has_column($pdo, 'llm_probleemvragen', 'client_id')) {
        $pdo->prepare('DELETE FROM llm_probleemvragen WHERE client_id = :id')->execute(['id' => $clientId]);
    }
    if (ce_table_exists($pdo, 'linkedin_posts') && ce_table_has_column($pdo, 'linkedin_posts', 'client_id')) {
        $pdo->prepare('DELETE FROM linkedin_posts WHERE client_id = :id')->execute(['id' => $clientId]);
    }
}

/** @param array<string, mixed> $bundle */
function ce_import_purge_bundle(PDO $pdo, string $clientId, array $bundle): void
{
    $tables = is_array($bundle['tables'] ?? null) ? $bundle['tables'] : [];
    $slotIds = ce_bundle_ids($tables['content_slots'] ?? []);
    $themeIds = ce_bundle_ids($tables['themes'] ?? []);
    $sourceIds = ce_bundle_ids($tables['sources'] ?? []);
    $articleIds = ce_bundle_ids(is_array($bundle['articles'] ?? null) ? $bundle['articles'] : []);
    $related = is_array($bundle['related'] ?? null) ? $bundle['related'] : [];
    $researchIds = ce_bundle_ids($related['research_items'] ?? []);
    $hbaScoreIds = ce_bundle_ids($related['hba_scores'] ?? []);
    $linkedInPostIds = ce_bundle_ids($related['linkedin_posts'] ?? []);
    $llmQuestionIds = ce_bundle_ids($related['llm_probleemvragen'] ?? []);
    $llmCheckIds = ce_bundle_ids($related['llm_visibility_checks'] ?? []);

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $existing = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
    $existing->execute(['id' => $clientId]);
    if ($existing->fetch()) {
        ce_reset_client_content($pdo, $clientId);
        if (ce_table_exists($pdo, 'brand_narratives')) {
            $pdo->prepare('DELETE FROM brand_narratives WHERE client_id = :id')->execute(['id' => $clientId]);
        }
        ce_import_delete_client_analytics($pdo, $clientId);
        $pdo->prepare('DELETE FROM clients WHERE id = :id')->execute(['id' => $clientId]);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    }

    ce_delete_by_ids($pdo, 'llm_visibility_checks', 'id', $llmCheckIds);
    ce_delete_by_ids($pdo, 'llm_probleemvragen', 'id', $llmQuestionIds);
    ce_delete_by_ids($pdo, 'linkedin_posts', 'id', $linkedInPostIds);
    ce_delete_by_ids($pdo, 'linkedin_posts', 'client_id', [$clientId]);
    ce_delete_by_ids($pdo, 'llm_visibility_checks', 'client_id', [$clientId]);
    ce_delete_by_ids($pdo, 'llm_probleemvragen', 'client_id', [$clientId]);
    ce_delete_by_ids($pdo, 'hba_scores', 'id', $hbaScoreIds);
    ce_delete_by_ids($pdo, 'hba_scores', 'post_id', $articleIds);
    ce_delete_by_ids($pdo, 'article_review_edits', 'article_id', $articleIds);
    ce_delete_by_ids($pdo, 'article_feedback', 'article_id', $articleIds);
    ce_delete_by_ids($pdo, 'articles', 'id', $articleIds);
    ce_delete_by_ids($pdo, 'research_items', 'id', $researchIds);
    ce_delete_by_ids($pdo, 'research_items', 'slot_id', $slotIds);
    ce_delete_by_ids($pdo, 'research_items', 'theme_id', $themeIds);
    ce_delete_by_ids($pdo, 'content_slots', 'id', $slotIds);
    ce_delete_by_ids($pdo, 'themes', 'id', $themeIds);
    ce_delete_by_ids($pdo, 'sources', 'id', $sourceIds);
    ce_delete_by_ids($pdo, 'objectives', 'id', ce_bundle_ids($tables['objectives'] ?? []));
    ce_delete_by_ids($pdo, 'brand_narratives', 'id', ce_bundle_ids($tables['brand_narratives'] ?? []));
    ce_delete_by_ids($pdo, 'client_images', 'id', ce_bundle_ids($tables['client_images'] ?? []));
}

/** @param array<string, mixed> $row */
function ce_import_replace(PDO $pdo, string $table, array $row): void
{
    if (!ce_table_exists($pdo, $table) || $row === []) {
        return;
    }
    $allowed = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
        $name = (string) ($col['Field'] ?? '');
        if ($name !== '') {
            $allowed[$name] = true;
        }
    }
    $filtered = [];
    foreach ($row as $col => $value) {
        if (isset($allowed[$col])) {
            $filtered[$col] = $value;
        }
    }
    if ($filtered === []) {
        return;
    }
    $cols = array_keys($filtered);
    $colSql = implode(', ', array_map(static fn ($c) => '`' . str_replace('`', '``', $c) . '`', $cols));
    $ph = implode(', ', array_map(static fn ($c) => ':' . $c, $cols));
    $stmt = $pdo->prepare("REPLACE INTO `{$table}` ({$colSql}) VALUES ({$ph})");
    $params = [];
    foreach ($cols as $col) {
        $params[$col] = $filtered[$col];
    }
    $stmt->execute($params);
}

$pdo = ce_db();
ce_ensure_hba_schema($pdo);

try {
    ce_import_purge_bundle($pdo, $clientId, $bundle);

    $clientRow = $bundle['client'];
    ce_import_replace($pdo, 'clients', $clientRow);

    $tables = is_array($bundle['tables'] ?? null) ? $bundle['tables'] : [];
    foreach (['brand_narratives', 'objectives', 'sources', 'themes', 'campaigns', 'content_slots', 'client_images'] as $table) {
        foreach ($tables[$table] ?? [] as $row) {
            if (is_array($row)) {
                ce_import_replace($pdo, $table, $row);
            }
        }
    }

    foreach ($bundle['articles'] ?? [] as $row) {
        if (is_array($row)) {
            ce_import_replace($pdo, 'articles', $row);
        }
    }

    $related = is_array($bundle['related'] ?? null) ? $bundle['related'] : [];
    foreach ($related['linkedin_posts'] ?? [] as $row) {
        if (is_array($row)) {
            ce_import_replace($pdo, 'linkedin_posts', $row);
        }
    }
    foreach ($related['llm_probleemvragen'] ?? [] as $row) {
        if (is_array($row)) {
            ce_import_replace($pdo, 'llm_probleemvragen', $row);
        }
    }
    foreach ($related['llm_visibility_checks'] ?? [] as $row) {
        if (is_array($row)) {
            ce_import_replace($pdo, 'llm_visibility_checks', $row);
        }
    }

    $seenResearch = [];
    foreach ($related['research_items'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (string) ($row['id'] ?? '');
        if ($id === '' || isset($seenResearch[$id])) {
            continue;
        }
        $seenResearch[$id] = true;
        ce_import_replace($pdo, 'research_items', $row);
    }
    foreach ($related['hba_scores'] ?? [] as $row) {
        if (is_array($row)) {
            ce_import_replace($pdo, 'hba_scores', $row);
        }
    }
    foreach ($related['publications'] ?? [] as $row) {
        if (is_array($row)) {
            ce_import_replace($pdo, 'publications', $row);
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
} catch (Throwable $e) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable) {
    }
    ce_json_error('Import mislukt: ' . $e->getMessage(), 500);
}

$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $clientId]);
$row = $stmt->fetch();

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'company' => (string) ($row['name'] ?? ''),
    'contactEmail' => (string) ($row['contact_email'] ?? ''),
    'strategy' => ce_strategy_dto($pdo, is_array($row) ? $row : []),
]);
