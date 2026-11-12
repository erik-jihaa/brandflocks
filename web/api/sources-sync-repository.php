<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/scrape-repository.php';
require_once __DIR__ . '/metrics-repository.php';

/** Max 1× per uur; min 1× per week. */
function ce_client_source_checks_per_week(PDO $pdo, ?string $clientId): int
{
    ce_ensure_account_columns($pdo);
    if ($clientId === null || !ce_table_exists($pdo, 'clients')) {
        return 1;
    }

    if (!ce_table_has_column($pdo, 'clients', 'source_checks_per_week')) {
        return 1;
    }

    $stmt = $pdo->prepare('SELECT source_checks_per_week FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();

    return max(1, min(168, (int) ($row['source_checks_per_week'] ?? 1)));
}

function ce_client_source_check_interval_seconds(int $checksPerWeek): int
{
    $checksPerWeek = max(1, min(168, $checksPerWeek));

    return (int) floor((7 * 24 * 3600) / $checksPerWeek);
}

function ce_client_active_source_count(PDO $pdo, string $clientId): int
{
    ce_ensure_sources_schema($pdo);
    if (!ce_table_exists($pdo, 'sources')) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM sources WHERE client_id = :client_id AND category <> 'asana'";
    if (ce_table_has_column($pdo, 'sources', 'active')) {
        $sql .= ' AND COALESCE(active, 1) = 1';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $clientId]);

    return (int) $stmt->fetchColumn();
}

function ce_client_last_source_check_at(PDO $pdo, string $clientId): ?DateTimeImmutable
{
    ce_ensure_sources_schema($pdo);
    if (!ce_table_exists($pdo, 'sources')) {
        return null;
    }

    $sql = "SELECT MAX(last_checked_at) AS last_check FROM sources WHERE client_id = :client_id AND category <> 'asana'";
    if (ce_table_has_column($pdo, 'sources', 'active')) {
        $sql .= ' AND COALESCE(active, 1) = 1';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $clientId]);
    $raw = trim((string) ($stmt->fetchColumn() ?: ''));

    if ($raw === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($raw);
    } catch (Throwable) {
        return null;
    }
}

function ce_client_is_due_for_source_sync(PDO $pdo, string $clientId, bool $force = false): bool
{
    if ($force) {
        return ce_client_active_source_count($pdo, $clientId) > 0;
    }

    if (ce_client_active_source_count($pdo, $clientId) === 0) {
        return false;
    }

    $last = ce_client_last_source_check_at($pdo, $clientId);
    if ($last === null) {
        return true;
    }

    $interval = ce_client_source_check_interval_seconds(
        ce_client_source_checks_per_week($pdo, $clientId),
    );
    $elapsed = time() - $last->getTimestamp();

    return $elapsed >= $interval;
}

/** @return array{due: bool, checksPerWeek: int, intervalHours: float, lastCheckAt: string|null, activeSources: int, secondsUntilDue: int|null} */
function ce_client_source_sync_status(PDO $pdo, string $clientId): array
{
    $checksPerWeek = ce_client_source_checks_per_week($pdo, $clientId);
    $intervalSeconds = ce_client_source_check_interval_seconds($checksPerWeek);
    $last = ce_client_last_source_check_at($pdo, $clientId);
    $activeSources = ce_client_active_source_count($pdo, $clientId);
    $due = ce_client_is_due_for_source_sync($pdo, $clientId, false);

    $secondsUntilDue = null;
    if ($last !== null && !$due && $activeSources > 0) {
        $remaining = $intervalSeconds - (time() - $last->getTimestamp());
        $secondsUntilDue = max(0, $remaining);
    }

    return [
        'due' => $due,
        'checksPerWeek' => $checksPerWeek,
        'intervalHours' => round($intervalSeconds / 3600, 2),
        'lastCheckAt' => $last?->format('Y-m-d H:i:s'),
        'activeSources' => $activeSources,
        'secondsUntilDue' => $secondsUntilDue,
    ];
}

function ce_touch_source_checked(PDO $pdo, string $sourceId, string $status): void
{
    ce_ensure_sources_schema($pdo);
    $status = $status === 'reachable' ? 'reachable' : 'unreachable';

    $pdo->prepare(
        'UPDATE sources SET url_status = :status, last_checked_at = NOW() WHERE id = :id'
    )->execute(['status' => $status, 'id' => $sourceId]);
}

/** @return list<array<string, mixed>> */
function ce_fetch_active_sources_for_client(PDO $pdo, string $clientId): array
{
    ce_ensure_sources_schema($pdo);

    $sql = "SELECT id, title, category FROM sources WHERE client_id = :client_id AND category <> 'asana'";
    if (ce_table_has_column($pdo, 'sources', 'active')) {
        $sql .= ' AND COALESCE(active, 1) = 1';
    }
    $sql .= ' ORDER BY category ASC, title ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $clientId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed> */
function ce_sync_single_source(PDO $pdo, string $sourceId): array
{
    $stmt = $pdo->prepare('SELECT id, title, category, client_id FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [
            'sourceId' => $sourceId,
            'ok' => false,
            'error' => 'Bron niet gevonden',
        ];
    }

    $category = strtolower(trim((string) ($row['category'] ?? '')));
    $title = (string) ($row['title'] ?? '');

    if (in_array($category, ['scraper', 'linkedin'], true)) {
        $run = ce_run_scraper_source($pdo, $sourceId, null, null, null);
        $ok = (bool) ($run['ok'] ?? false);
        $status = $run['run']['status'] ?? 'error';
        $reachable = $ok && !in_array($status, ['error', 'skipped_budget', 'skipped_config'], true);
        ce_touch_source_checked($pdo, $sourceId, $reachable ? 'reachable' : 'unreachable');

        return [
            'sourceId' => $sourceId,
            'title' => $title,
            'category' => $category,
            'ok' => $ok,
            'mode' => 'scraper',
            'status' => $status,
            'message' => $run['run']['message'] ?? null,
            'linkedinIngested' => (int) ($run['run']['linkedin_ingested'] ?? 0),
        ];
    }

    $checked = ce_check_source($pdo, $sourceId, null);
    if ($checked === null) {
        return [
            'sourceId' => $sourceId,
            'title' => $title,
            'category' => $category,
            'ok' => false,
            'mode' => 'check',
            'error' => 'Check mislukt',
        ];
    }

    $result = [
        'sourceId' => $sourceId,
        'title' => $title,
        'category' => $category,
        'ok' => ($checked['checkStatus'] ?? '') === 'green',
        'mode' => 'check',
        'checkStatus' => $checked['checkStatus'] ?? null,
        'checkMessage' => $checked['checkMessage'] ?? null,
    ];

    if ($category === 'analytics' && $result['ok']) {
        $clientId = trim((string) ($row['client_id'] ?? ''));
        try {
            $result['ga4Sync'] = ce_analytics_sync_article_views($pdo, $clientId !== '' ? $clientId : null);
        } catch (Throwable) {
            $result['ga4Sync'] = ['matched' => 0, 'checked' => 0];
        }
    }

    return $result;
}

/** @return array<string, mixed> */
function ce_sync_client_sources(PDO $pdo, string $clientId, bool $force = false): array
{
    if (!ce_client_is_due_for_source_sync($pdo, $clientId, $force)) {
        return [
            'clientId' => $clientId,
            'skipped' => true,
            'reason' => 'not_due',
            'status' => ce_client_source_sync_status($pdo, $clientId),
            'sources' => [],
        ];
    }

    $sources = ce_fetch_active_sources_for_client($pdo, $clientId);
    $results = [];
    $checked = 0;
    $failed = 0;

    foreach ($sources as $row) {
        $sourceId = (string) ($row['id'] ?? '');
        if ($sourceId === '') {
            continue;
        }

        try {
            $result = ce_sync_single_source($pdo, $sourceId);
        } catch (Throwable $e) {
            $result = [
                'sourceId' => $sourceId,
                'title' => (string) ($row['title'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }

        $results[] = $result;
        if ($result['ok'] ?? false) {
            $checked++;
        } else {
            $failed++;
        }
    }

    return [
        'clientId' => $clientId,
        'skipped' => false,
        'status' => ce_client_source_sync_status($pdo, $clientId),
        'sourcesChecked' => $checked,
        'sourcesFailed' => $failed,
        'sources' => $results,
    ];
}

/** @return list<string> */
function ce_fetch_client_ids_with_sources(PDO $pdo): array
{
    ce_ensure_sources_schema($pdo);
    if (!ce_table_exists($pdo, 'sources') || !ce_table_exists($pdo, 'clients')) {
        return [];
    }

    $sql = <<<'SQL'
SELECT DISTINCT s.client_id
FROM sources s
INNER JOIN clients c ON c.id = s.client_id
SQL;
    if (ce_table_has_column($pdo, 'sources', 'active')) {
        $sql .= ' WHERE COALESCE(s.active, 1) = 1';
    }
    if (ce_table_has_column($pdo, 'clients', 'active')) {
        $sql .= ce_table_has_column($pdo, 'sources', 'active') ? ' AND COALESCE(c.active, 1) = 1' : ' WHERE COALESCE(c.active, 1) = 1';
    }
    $sql .= ' ORDER BY s.client_id ASC';

    $stmt = $pdo->query($sql);
    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = trim((string) ($row['client_id'] ?? ''));
        if ($id !== '') {
            $ids[] = $id;
        }
    }

    return $ids;
}

/** @return array{clients: int, synced: int, skipped: int, failed: int, batches: list<array<string, mixed>>} */
function ce_sync_all_due_client_sources(PDO $pdo, bool $force = false): array
{
    $totals = [
        'clients' => 0,
        'synced' => 0,
        'skipped' => 0,
        'failed' => 0,
        'batches' => [],
    ];

    foreach (ce_fetch_client_ids_with_sources($pdo) as $clientId) {
        $batch = ce_sync_client_sources($pdo, $clientId, $force);
        $totals['batches'][] = $batch;

        if ($batch['skipped'] ?? false) {
            $totals['skipped']++;
            continue;
        }

        $totals['clients']++;
        $totals['synced'] += (int) ($batch['sourcesChecked'] ?? 0);
        $totals['failed'] += (int) ($batch['sourcesFailed'] ?? 0);
    }

    return $totals;
}

/** @return list<array<string, mixed>> */
function ce_sources_sync_overview(PDO $pdo): array
{
    $overview = [];
    foreach (ce_fetch_client_ids_with_sources($pdo) as $clientId) {
        $name = $clientId;
        if (ce_table_exists($pdo, 'clients')) {
            $stmt = $pdo->prepare('SELECT name FROM clients WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $clientId]);
            $name = trim((string) ($stmt->fetchColumn() ?: $clientId));
        }

        $overview[] = [
            'clientId' => $clientId,
            'clientName' => $name,
            ...ce_client_source_sync_status($pdo, $clientId),
        ];
    }

    return $overview;
}
