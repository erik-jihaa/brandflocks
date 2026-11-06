<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

/** @return array<string, mixed>|null */
function li_scan_find_by_id(PDO $pdo, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM scans WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Recente voltooide scans voor het openingsscherm.
 *
 * @return list<array<string, mixed>>
 */
function li_scan_list_recent(PDO $pdo, int $limit = 40): array
{
    $limit = max(1, min($limit, 100));
    $stmt = $pdo->prepare(
        'SELECT id, company_name, company_slug, company_url, branche_code, posts_count,
                cost_usd, narrative_cost_usd, created_at, report_opened_at
         FROM scans
         WHERE status = :status
           AND metrics IS NOT NULL
         ORDER BY created_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':status', 'completed');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return is_array($rows) ? $rows : [];
}

function li_scan_delete_by_id(PDO $pdo, string $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM scans WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->rowCount() > 0;
}

/** @return array<string, mixed>|null */
function li_scan_find_cached(PDO $pdo, string $companyUrl, int $cacheDays): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM scans
         WHERE company_url = :url
           AND status = :status
           AND raw_data IS NOT NULL
           AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmt->bindValue(':url', $companyUrl);
    $stmt->bindValue(':status', 'completed');
    $stmt->bindValue(':days', $cacheDays, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();

    return $row ?: null;
}

function li_scan_estimate_cost_eur(PDO $pdo, float $usdToEur): ?float
{
    $stmt = $pdo->query(
        "SELECT AVG(cost_usd) AS avg_cost
         FROM scans
         WHERE status = 'completed' AND cost_usd IS NOT NULL AND cost_usd > 0"
    );
    $row = $stmt ? $stmt->fetch() : false;
    $avg = is_array($row) && isset($row['avg_cost']) ? (float) $row['avg_cost'] : null;
    if ($avg === null || $avg <= 0) {
        return 0.10;
    }

    return round($avg * $usdToEur, 2);
}

function li_ensure_cost_log_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS cost_log (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          scan_id CHAR(36) NULL,
          kind VARCHAR(16) NOT NULL,
          usd DECIMAL(12, 6) NOT NULL,
          created_at DATETIME NOT NULL,
          KEY idx_cost_log_created (created_at),
          KEY idx_cost_log_kind_created (kind, created_at),
          KEY idx_cost_log_scan (scan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL
    );
}

function li_cost_log_record(PDO $pdo, ?string $scanId, string $kind, float $usd): void
{
    if (!in_array($kind, ['apify', 'claude'], true)) {
        return;
    }
    if ($kind === 'claude' && $usd <= 0) {
        return;
    }

    li_ensure_cost_log_schema($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO cost_log (scan_id, kind, usd, created_at) VALUES (:scan_id, :kind, :usd, NOW())'
    );
    $stmt->execute([
        'scan_id' => $scanId !== null && trim($scanId) !== '' ? trim($scanId) : null,
        'kind' => $kind,
        'usd' => round(max(0, $usd), 6),
    ]);
}

/** Zet of corrigeer één Apify-regel per scan (school-async: schatting → werkelijk). */
function li_cost_log_set_apify_cost(PDO $pdo, string $scanId, float $usd): void
{
    $scanId = trim($scanId);
    if ($scanId === '') {
        return;
    }

    li_ensure_cost_log_schema($pdo);
    $usd = round(max(0, $usd), 6);
    $update = $pdo->prepare(
        'UPDATE cost_log SET usd = :usd WHERE scan_id = :scan_id AND kind = :kind LIMIT 1'
    );
    $update->execute(['usd' => $usd, 'scan_id' => $scanId, 'kind' => 'apify']);
    if ($update->rowCount() > 0) {
        return;
    }

    li_cost_log_record($pdo, $scanId, 'apify', $usd);
}

/**
 * @param float|null $apifyUsd
 * @param float|null $claudeUsd
 * @param bool $apifyRunFinished Apify-run afgerond — altijd een apify-regel (ook $0) voor de daglimiet
 */
function li_cost_log_scan_costs(
    PDO $pdo,
    string $scanId,
    ?float $apifyUsd,
    ?float $claudeUsd,
    bool $apifyRunFinished = false,
): void {
    $scanId = trim($scanId);
    if ($scanId === '') {
        return;
    }

    if ($apifyRunFinished) {
        li_cost_log_set_apify_cost($pdo, $scanId, max(0, (float) ($apifyUsd ?? 0)));
    }
    if ($claudeUsd !== null && $claudeUsd > 0) {
        li_cost_log_record($pdo, $scanId, 'claude', (float) $claudeUsd);
    }
}

function li_scan_estimate_apify_cost_usd(PDO $pdo): float
{
    $usdToEur = (float) li_config('usd_to_eur', 0.92);
    $eur = li_scan_estimate_cost_eur($pdo, $usdToEur);
    if ($usdToEur <= 0) {
        return 0.10;
    }

    return round(max(0.01, $eur / $usdToEur), 4);
}

/** @return array{timeoutSec: int, maxTotalChargeUsd: float} */
function li_scan_apify_run_query_options(PDO $pdo, int $timeoutSec): array
{
    $dailyCostLimitUsd = (float) li_config('daily_cost_limit_usd', 5.0);
    $limits = li_scan_daily_limits(
        $pdo,
        (int) li_config('daily_scan_limit', 20),
        $dailyCostLimitUsd,
    );
    $remaining = max(0.01, $dailyCostLimitUsd - $limits['cost_today_usd']);

    return [
        'timeoutSec' => max(1, $timeoutSec),
        'maxTotalChargeUsd' => round($remaining, 4),
    ];
}

/** @return array{allowed: bool, reason: string|null, scans_today: int, cost_today_usd: float} */
function li_scan_daily_limits(PDO $pdo, int $dailyScanLimit, float $dailyCostLimitUsd): array
{
    li_ensure_cost_log_schema($pdo);

    $stmt = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM cost_log WHERE kind = 'apify' AND DATE(created_at) = CURDATE()) AS cnt,
            (SELECT COALESCE(SUM(usd), 0) FROM cost_log WHERE DATE(created_at) = CURDATE()) AS cost_sum"
    );
    $row = $stmt ? $stmt->fetch() : false;
    $count = is_array($row) ? (int) ($row['cnt'] ?? 0) : 0;
    $cost = is_array($row) ? (float) ($row['cost_sum'] ?? 0) : 0.0;

    if ($count >= $dailyScanLimit) {
        return [
            'allowed' => false,
            'reason' => 'Daglimiet bereikt (' . $dailyScanLimit . ' scans vandaag). Probeer morgen opnieuw.',
            'scans_today' => $count,
            'cost_today_usd' => $cost,
        ];
    }

    if ($cost >= $dailyCostLimitUsd) {
        return [
            'allowed' => false,
            'reason' => 'Dagbudget bereikt ($' . number_format($dailyCostLimitUsd, 2) . ' vandaag).',
            'scans_today' => $count,
            'cost_today_usd' => $cost,
        ];
    }

    return [
        'allowed' => true,
        'reason' => null,
        'scans_today' => $count,
        'cost_today_usd' => $cost,
    ];
}

/** @param array<string, mixed> $data */
function li_scan_insert(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO scans (
            id, company_url, company_name, company_slug, branche_code, company_description,
            contextprofiel, posts_count, apify_run_id, compute_units, cost_usd, narrative_cost_usd,
            raw_data, metrics, status, error_message
        ) VALUES (
            :id, :company_url, :company_name, :company_slug, :branche_code, :company_description,
            :contextprofiel, :posts_count, :apify_run_id, :compute_units, :cost_usd, :narrative_cost_usd,
            :raw_data, :metrics, :status, :error_message
        )'
    );

    $stmt->execute([
        'id' => $data['id'],
        'company_url' => $data['company_url'],
        'company_name' => $data['company_name'],
        'company_slug' => $data['company_slug'],
        'branche_code' => $data['branche_code'] ?? null,
        'company_description' => $data['company_description'] ?? null,
        'contextprofiel' => $data['contextprofiel'] ?? null,
        'posts_count' => $data['posts_count'],
        'apify_run_id' => $data['apify_run_id'],
        'compute_units' => $data['compute_units'],
        'cost_usd' => $data['cost_usd'],
        'narrative_cost_usd' => $data['narrative_cost_usd'] ?? null,
        'raw_data' => $data['raw_data'],
        'metrics' => $data['metrics'],
        'status' => $data['status'],
        'error_message' => $data['error_message'],
    ]);
}

/** @param array<string, mixed> $data */
function li_scan_update(PDO $pdo, string $id, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE scans SET
            company_name = :company_name,
            posts_count = :posts_count,
            apify_run_id = :apify_run_id,
            compute_units = :compute_units,
            cost_usd = :cost_usd,
            narrative_cost_usd = :narrative_cost_usd,
            raw_data = :raw_data,
            metrics = :metrics,
            status = :status,
            error_message = :error_message
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $id,
        'company_name' => $data['company_name'],
        'posts_count' => $data['posts_count'],
        'apify_run_id' => $data['apify_run_id'],
        'compute_units' => $data['compute_units'],
        'cost_usd' => $data['cost_usd'],
        'narrative_cost_usd' => $data['narrative_cost_usd'] ?? null,
        'raw_data' => $data['raw_data'],
        'metrics' => $data['metrics'],
        'status' => $data['status'],
        'error_message' => $data['error_message'],
    ]);
}

/** @return array{branche_code: ?string, company_description: ?string, contextprofiel: ?string} */
function li_scan_context_from_row(array $row): array
{
    return [
        'branche_code' => li_branche_code_for_scan($row['branche_code'] ?? null),
        'company_description' => li_scan_normalize_company_description($row['company_description'] ?? null),
        'contextprofiel' => li_contextprofiel_code_for_scan($row['contextprofiel'] ?? null),
    ];
}

/** @param array<string, mixed> $metrics @param array<string, mixed> $freshMetrics @return array<string, mixed> */
function li_scan_merge_post_rank_metrics(array $metrics, array $freshMetrics): array
{
    foreach (['top_posts', 'best_post'] as $key) {
        if (array_key_exists($key, $freshMetrics)) {
            $metrics[$key] = $freshMetrics[$key];
        }
    }

    return $metrics;
}

/** @param array<string, mixed> $analysis @return array<string, mixed>|null */
function li_scan_run_narrative(
    array $analysis,
    ?string $brancheCode,
    ?string $companyDescription = null,
    ?string $contextProfiel = null,
    ?PDO $costPdo = null,
    ?string $costScanId = null,
): ?array {
    $topPosts = is_array($analysis['metrics']['top_posts'] ?? null) ? $analysis['metrics']['top_posts'] : [];

    return li_analyze_narrative(
        $analysis['posts'],
        $analysis['company_name'],
        $brancheCode,
        $companyDescription,
        $contextProfiel,
        $topPosts,
        $costPdo,
        $costScanId,
    );
}

/** @param array<string, mixed> $metrics @return array<string, mixed> */
function li_scan_metrics_with_context(
    array $metrics,
    ?string $brancheCode,
    ?string $companyDescription = null,
    ?string $contextProfiel = null,
): array {
    if ($brancheCode !== null) {
        $metrics['branche_code'] = $brancheCode;
    } else {
        unset($metrics['branche_code']);
    }

    if ($companyDescription !== null) {
        $metrics['company_description'] = $companyDescription;
    } else {
        unset($metrics['company_description']);
    }

    if ($contextProfiel !== null) {
        $metrics['contextprofiel'] = $contextProfiel;
    } else {
        unset($metrics['contextprofiel']);
    }

    return $metrics;
}

/** @param array<string, mixed> $metrics @return array<string, mixed> */
function li_scan_metrics_with_branche(array $metrics, ?string $brancheCode): array
{
    return li_scan_metrics_with_context($metrics, $brancheCode);
}

/**
 * Herbereken narrative uit raw_data na context-wijziging — geen Apify-run.
 *
 * @param array<string, mixed> $row
 *
 * @return array{ok: bool, error?: string, metrics?: array<string, mixed>}
 */
function li_scan_recompute_narrative(
    PDO $pdo,
    array $row,
    ?string $brancheCode,
    ?string $companyDescription = null,
    ?string $contextProfiel = null,
): array {
    $scanId = (string) ($row['id'] ?? '');
    if ($scanId === '' || ($row['status'] ?? '') !== 'completed') {
        return ['ok' => false, 'error' => 'Scan niet voltooid.'];
    }

    $rawJson = (string) ($row['raw_data'] ?? '');
    if ($rawJson === '') {
        return ['ok' => false, 'error' => 'Geen ruwe postdata meer beschikbaar voor deze scan.'];
    }

    $items = json_decode($rawJson, true);
    if (!is_array($items) || $items === []) {
        return ['ok' => false, 'error' => 'Ruwe postdata ongeldig.'];
    }

    if (!(bool) li_config('ai_summary_enabled', false)) {
        return ['ok' => false, 'error' => 'Narratief-analyse staat uit.'];
    }

    $dailyCostLimitUsd = (float) li_config('daily_cost_limit_usd', 5.0);
    $limits = li_scan_daily_limits(
        $pdo,
        (int) li_config('daily_scan_limit', 20),
        $dailyCostLimitUsd,
    );
    if ($limits['cost_today_usd'] >= $dailyCostLimitUsd) {
        return ['ok' => false, 'error' => 'Dagbudget bereikt — narrative kan vandaag niet opnieuw berekend worden.'];
    }

    $slug = (string) ($row['company_slug'] ?? '');
    $url = (string) ($row['company_url'] ?? '');
    if (li_linkedin_page_type_from_url($url) === 'school') {
        $items = li_normalize_school_apify_items($items, $slug);
    }

    $analysis = li_analyze_posts($items, $slug);
    try {
        $narrative = li_scan_run_narrative(
            $analysis,
            $brancheCode,
            $companyDescription,
            $contextProfiel,
            $pdo,
            $scanId,
        );
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Kostenlogboek niet beschikbaar — scan gestopt.'];
    }
    if ($narrative === null) {
        return ['ok' => false, 'error' => 'Narratief-analyse mislukt of te weinig posts.'];
    }

    $metrics = json_decode((string) ($row['metrics'] ?? ''), true);
    if (!is_array($metrics)) {
        $metrics = $analysis['metrics'];
    }
    $metrics['narrative'] = $narrative;
    $metrics = li_scan_merge_post_rank_metrics($metrics, $analysis['metrics']);
    $metrics = li_scan_metrics_with_context($metrics, $brancheCode, $companyDescription, $contextProfiel);

    $narrativeCostUsd = isset($narrative['cost_usd']) ? (float) $narrative['cost_usd'] : null;
    $metricsJson = json_encode($metrics, JSON_UNESCAPED_UNICODE);
    if ($metricsJson === false) {
        return ['ok' => false, 'error' => 'Metrics konden niet worden opgeslagen.'];
    }

    $stmt = $pdo->prepare(
        'UPDATE scans SET branche_code = :branche_code, company_description = :company_description,
                contextprofiel = :contextprofiel, metrics = :metrics,
                narrative_cost_usd = COALESCE(narrative_cost_usd, 0) + COALESCE(:narrative_cost, 0)
         WHERE id = :id'
    );
    $stmt->execute([
        'branche_code' => $brancheCode,
        'company_description' => $companyDescription,
        'contextprofiel' => $contextProfiel,
        'metrics' => $metricsJson,
        'narrative_cost' => $narrativeCostUsd,
        'id' => $scanId,
    ]);

    return ['ok' => true, 'metrics' => $metrics];
}

/**
 * @param array{
 *   ok: bool,
 *   items: array<int, array<string, mixed>>,
 *   apify_run_id: string|null,
 *   units_used: float|null,
 *   cost_usd: float|null,
 *   message: string|null,
 *   status?: string
 * } $apify
 *
 * @return array{ok: bool, scan_id: string, pending?: bool, error?: string, metrics?: array<string, mixed>, company_name?: string}
 */
function li_scan_finalize_apify_result(
    PDO $pdo,
    string $scanId,
    string $companyUrl,
    string $companySlug,
    string $pageType,
    array $apify,
): array {
    if (($apify['status'] ?? '') === 'running') {
        return ['ok' => true, 'scan_id' => $scanId, 'pending' => true];
    }

    if (!$apify['ok']) {
        li_scan_update($pdo, $scanId, [
            'company_name' => li_slug_to_display_name($companySlug),
            'posts_count' => null,
            'apify_run_id' => $apify['apify_run_id'],
            'compute_units' => $apify['units_used'],
            'cost_usd' => $apify['cost_usd'],
            'narrative_cost_usd' => null,
            'raw_data' => null,
            'metrics' => null,
            'status' => 'failed',
            'error_message' => (string) ($apify['message'] ?? 'Scan mislukt'),
        ]);
        try {
            li_cost_log_scan_costs($pdo, $scanId, $apify['cost_usd'], null, true);
        } catch (Throwable) {
            return [
                'ok' => false,
                'scan_id' => $scanId,
                'error' => 'Kostenlogboek niet beschikbaar — scan gestopt.',
            ];
        }

        return [
            'ok' => false,
            'scan_id' => $scanId,
            'error' => (string) ($apify['message'] ?? 'Scan mislukt'),
        ];
    }

    $rawItems = $pageType === 'school'
        ? li_normalize_school_apify_items($apify['items'], $companySlug)
        : $apify['items'];
    $analysis = li_analyze_posts($rawItems, $companySlug);
    $metrics = $analysis['metrics'];
    $narrativeCostUsd = null;

    $scanRow = li_scan_find_by_id($pdo, $scanId);
    $ctx = is_array($scanRow) ? li_scan_context_from_row($scanRow) : [
        'branche_code' => null,
        'company_description' => null,
        'contextprofiel' => null,
    ];

    if ((bool) li_config('ai_summary_enabled', false)) {
        try {
            $narrative = li_scan_run_narrative(
                $analysis,
                $ctx['branche_code'],
                $ctx['company_description'],
                $ctx['contextprofiel'],
                $pdo,
                $scanId,
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'scan_id' => $scanId,
                'error' => 'Kostenlogboek niet beschikbaar — scan gestopt.',
            ];
        }
        if ($narrative !== null) {
            $metrics['narrative'] = $narrative;
            $metrics = li_scan_metrics_with_context(
                $metrics,
                $ctx['branche_code'],
                $ctx['company_description'],
                $ctx['contextprofiel'],
            );
            $narrativeCostUsd = isset($narrative['cost_usd']) ? (float) $narrative['cost_usd'] : null;
        }
    }

    $rawJson = json_encode($rawItems, JSON_UNESCAPED_UNICODE);

    li_scan_update($pdo, $scanId, [
        'company_name' => $analysis['company_name'],
        'posts_count' => (int) ($metrics['posts_count'] ?? 0),
        'apify_run_id' => $apify['apify_run_id'],
        'compute_units' => $apify['units_used'],
        'cost_usd' => $apify['cost_usd'],
        'narrative_cost_usd' => $narrativeCostUsd,
        'raw_data' => $rawJson !== false ? $rawJson : null,
        'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
        'status' => 'completed',
        'error_message' => null,
    ]);
    try {
        li_cost_log_scan_costs($pdo, $scanId, $apify['cost_usd'], null, true);
    } catch (Throwable) {
        return [
            'ok' => false,
            'scan_id' => $scanId,
            'error' => 'Kostenlogboek niet beschikbaar — scan gestopt.',
        ];
    }

    return [
        'ok' => true,
        'scan_id' => $scanId,
        'metrics' => $metrics,
        'company_name' => $analysis['company_name'],
    ];
}

/** @return array<string, mixed> */
function li_scan_poll_running(PDO $pdo, array $row): array
{
    $scanId = (string) ($row['id'] ?? '');
    $companyUrl = (string) ($row['company_url'] ?? '');
    $companySlug = (string) ($row['company_slug'] ?? '');
    $runId = trim((string) ($row['apify_run_id'] ?? ''));
    $pageType = li_linkedin_page_type_from_url($companyUrl);
    $token = (string) li_config('apify_api_token', '');
    $pollWait = (int) li_config('school_poll_wait_sec', 20);

    if ($scanId === '' || $runId === '') {
        return ['ok' => false, 'error' => 'Scan mist Apify run-id.'];
    }

    $apify = li_apify_collect_page_scan($token, $runId, $pollWait);

    return li_scan_finalize_apify_result($pdo, $scanId, $companyUrl, $companySlug, $pageType, $apify);
}

/** @return array<string, mixed> */
function li_scan_start_async(
    PDO $pdo,
    string $companyUrl,
    string $companySlug,
    string $pageType,
    int $maxPosts,
    ?string $brancheCode = null,
    ?string $companyDescription = null,
    ?string $contextProfiel = null,
): array {
    $token = (string) li_config('apify_api_token', '');
    $timeout = (int) li_config('apify_timeout_sec', 120);
    $runOpts = li_scan_apify_run_query_options($pdo, $timeout);
    $start = li_apify_start_page_scan(
        $token,
        $pageType,
        $companySlug,
        $maxPosts,
        $runOpts['timeoutSec'],
        $runOpts['maxTotalChargeUsd'],
    );
    $scanId = li_uuid();

    if (!$start['ok']) {
        li_scan_insert($pdo, [
            'id' => $scanId,
            'company_url' => $companyUrl,
            'company_name' => li_slug_to_display_name($companySlug),
            'company_slug' => $companySlug,
            'branche_code' => $brancheCode,
            'company_description' => $companyDescription,
            'contextprofiel' => $contextProfiel,
            'posts_count' => null,
            'apify_run_id' => null,
            'compute_units' => null,
            'cost_usd' => null,
            'narrative_cost_usd' => null,
            'raw_data' => null,
            'metrics' => null,
            'status' => 'failed',
            'error_message' => (string) ($start['message'] ?? 'Scan start mislukt'),
        ]);

        return [
            'ok' => false,
            'scan_id' => $scanId,
            'error' => (string) ($start['message'] ?? 'Scan start mislukt'),
        ];
    }

    li_scan_insert($pdo, [
        'id' => $scanId,
        'company_url' => $companyUrl,
        'company_name' => li_slug_to_display_name($companySlug),
        'company_slug' => $companySlug,
        'branche_code' => $brancheCode,
        'company_description' => $companyDescription,
        'contextprofiel' => $contextProfiel,
        'posts_count' => null,
        'apify_run_id' => $start['apify_run_id'],
        'compute_units' => null,
        'cost_usd' => null,
        'narrative_cost_usd' => null,
        'raw_data' => null,
        'metrics' => null,
        'status' => 'running',
        'error_message' => null,
    ]);

    try {
        li_cost_log_set_apify_cost($pdo, $scanId, li_scan_estimate_apify_cost_usd($pdo));
    } catch (Throwable) {
        li_scan_update($pdo, $scanId, [
            'company_name' => li_slug_to_display_name($companySlug),
            'posts_count' => null,
            'apify_run_id' => $start['apify_run_id'],
            'compute_units' => null,
            'cost_usd' => null,
            'narrative_cost_usd' => null,
            'raw_data' => null,
            'metrics' => null,
            'status' => 'failed',
            'error_message' => 'Kostenlogboek niet beschikbaar — scan gestopt.',
        ]);

        return [
            'ok' => false,
            'scan_id' => $scanId,
            'error' => 'Kostenlogboek niet beschikbaar — scan gestopt.',
        ];
    }

    return [
        'ok' => true,
        'scan_id' => $scanId,
        'pending' => true,
    ];
}

/** @return array<string, mixed> */
function li_scan_run_and_store(
    PDO $pdo,
    string $companyUrl,
    string $companySlug,
    string $pageType,
    bool $forceRefresh,
    int $cacheDays,
    ?string $brancheCode = null,
    ?string $companyDescription = null,
    ?string $contextProfiel = null,
): array {
    @set_time_limit(300);

    $usdToEur = (float) li_config('usd_to_eur', 0.92);
    $maxPosts = (int) li_config('max_posts', 30);
    $timeout = (int) li_config('apify_timeout_sec', 120);
    $token = (string) li_config('apify_api_token', '');

    if (!$forceRefresh) {
        $cached = li_scan_find_cached($pdo, $companyUrl, $cacheDays);
        if ($cached !== null) {
            $metrics = li_scan_metrics_from_row($pdo, $cached);

            return [
                'ok' => true,
                'scan_id' => (string) $cached['id'],
                'cached' => true,
                'cached_at' => (string) ($cached['created_at'] ?? ''),
                'metrics' => is_array($metrics) ? $metrics : [],
                'company_name' => (string) ($cached['company_name'] ?? ''),
                'company_url' => (string) ($cached['company_url'] ?? $companyUrl),
                'cost_usd' => $cached['cost_usd'] !== null ? (float) $cached['cost_usd'] : null,
                'cost_eur' => $cached['cost_usd'] !== null ? round((float) $cached['cost_usd'] * $usdToEur, 4) : null,
            ];
        }
    }

    $limits = li_scan_daily_limits(
        $pdo,
        (int) li_config('daily_scan_limit', 20),
        (float) li_config('daily_cost_limit_usd', 5.0),
    );
    if (!$limits['allowed']) {
        return [
            'ok' => false,
            'error' => (string) $limits['reason'],
        ];
    }

    if ($pageType === 'school') {
        return li_scan_start_async(
            $pdo,
            $companyUrl,
            $companySlug,
            $pageType,
            $maxPosts,
            $brancheCode,
            $companyDescription,
            $contextProfiel,
        );
    }

    $runOpts = li_scan_apify_run_query_options($pdo, $timeout);
    $scanId = li_uuid();
    $apify = li_apify_run_page_scan(
        $token,
        $pageType,
        $companySlug,
        $maxPosts,
        $runOpts['timeoutSec'],
        $runOpts['maxTotalChargeUsd'],
    );

    if (!$apify['ok']) {
        li_scan_insert($pdo, [
            'id' => $scanId,
            'company_url' => $companyUrl,
            'company_name' => li_slug_to_display_name($companySlug),
            'company_slug' => $companySlug,
            'branche_code' => $brancheCode,
            'company_description' => $companyDescription,
            'contextprofiel' => $contextProfiel,
            'posts_count' => null,
            'apify_run_id' => $apify['apify_run_id'],
            'compute_units' => $apify['units_used'],
            'cost_usd' => $apify['cost_usd'],
            'narrative_cost_usd' => null,
            'raw_data' => null,
            'metrics' => null,
            'status' => 'failed',
            'error_message' => (string) ($apify['message'] ?? 'Scan mislukt'),
        ]);
        try {
            li_cost_log_scan_costs($pdo, $scanId, $apify['cost_usd'], null, true);
        } catch (Throwable) {
            return [
                'ok' => false,
                'scan_id' => $scanId,
                'error' => 'Kostenlogboek niet beschikbaar — scan gestopt.',
            ];
        }

        return [
            'ok' => false,
            'scan_id' => $scanId,
            'error' => (string) ($apify['message'] ?? 'Scan mislukt'),
        ];
    }

    try {
        li_cost_log_set_apify_cost($pdo, $scanId, max(0, (float) ($apify['cost_usd'] ?? 0)));
    } catch (Throwable) {
        return [
            'ok' => false,
            'scan_id' => $scanId,
            'error' => 'Kostenlogboek niet beschikbaar — scan gestopt.',
        ];
    }

    $rawItems = $apify['items'];
    $analysis = li_analyze_posts($rawItems, $companySlug);
    $metrics = $analysis['metrics'];
    $narrativeCostUsd = null;

    if ((bool) li_config('ai_summary_enabled', false)) {
        try {
            $narrative = li_scan_run_narrative(
                $analysis,
                $brancheCode,
                $companyDescription,
                $contextProfiel,
                $pdo,
                $scanId,
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'scan_id' => $scanId,
                'error' => 'Kostenlogboek niet beschikbaar — scan gestopt.',
            ];
        }
        if ($narrative !== null) {
            $metrics['narrative'] = $narrative;
            $metrics = li_scan_metrics_with_context($metrics, $brancheCode, $companyDescription, $contextProfiel);
            $narrativeCostUsd = isset($narrative['cost_usd']) ? (float) $narrative['cost_usd'] : null;
        }
    }

    $rawJson = json_encode($rawItems, JSON_UNESCAPED_UNICODE);

    li_scan_insert($pdo, [
        'id' => $scanId,
        'company_url' => $companyUrl,
        'company_name' => $analysis['company_name'],
        'company_slug' => $companySlug,
        'branche_code' => $brancheCode,
        'company_description' => $companyDescription,
        'contextprofiel' => $contextProfiel,
        'posts_count' => (int) ($metrics['posts_count'] ?? 0),
        'apify_run_id' => $apify['apify_run_id'],
        'compute_units' => $apify['units_used'],
        'cost_usd' => $apify['cost_usd'],
        'narrative_cost_usd' => $narrativeCostUsd,
        'raw_data' => $rawJson !== false ? $rawJson : null,
        'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
        'status' => 'completed',
        'error_message' => null,
    ]);

    return [
        'ok' => true,
        'scan_id' => $scanId,
        'cached' => false,
        'metrics' => $metrics,
        'company_name' => $analysis['company_name'],
        'company_url' => $companyUrl,
        'raw_count' => $analysis['raw_count'],
        'cost_usd' => $apify['cost_usd'],
        'cost_eur' => $apify['cost_usd'] !== null ? round((float) $apify['cost_usd'] * $usdToEur, 4) : null,
    ];
}

/**
 * @param bool $persist Metrics/narrative terugschrijven naar DB bij enrich
 * @param bool $allowNarrative Zo nee: nooit LLM (publieke report.php)
 *
 * @return array<string, mixed>|null
 */
function li_scan_metrics_from_row(
    PDO $pdo,
    array $row,
    bool $persist = true,
    bool $allowNarrative = true,
): ?array {
    $metrics = json_decode((string) ($row['metrics'] ?? ''), true);
    if (!is_array($metrics)) {
        return null;
    }

    $ctx = li_scan_context_from_row($row);
    $metrics = li_scan_metrics_with_context(
        $metrics,
        $ctx['branche_code'],
        $ctx['company_description'],
        $ctx['contextprofiel'],
    );

    if (!$allowNarrative) {
        return $metrics;
    }

    return li_scan_enrich_narrative_if_missing($pdo, $row, $metrics, $persist);
}

/**
 * Oudere scans (vóór narrative-laag) missen metrics['narrative']. Hergebruik raw_data
 * zonder nieuwe Apify-run — alleen Claude Haiku (~€0,002).
 *
 * @param array<string, mixed> $row
 * @param array<string, mixed> $metrics
 *
 * @return array<string, mixed>
 */
function li_scan_enrich_narrative_if_missing(PDO $pdo, array $row, array $metrics, bool $persist = true): array
{
    $ctx = li_scan_context_from_row($row);
    $brancheCode = $ctx['branche_code'];
    $companyDescription = $ctx['company_description'];
    $contextProfiel = $ctx['contextprofiel'];
    $metrics = li_scan_metrics_with_context($metrics, $brancheCode, $companyDescription, $contextProfiel);

    $narrative = is_array($metrics['narrative'] ?? null) ? $metrics['narrative'] : null;
    $needsDistinctiveness = $brancheCode !== null
        && ($narrative === null || !isset($narrative['distinctiveness_label']));
    $needsDescriptionAlignment = $companyDescription !== null
        && ($narrative === null || !isset($narrative['description_alignment_label']));
    $topPosts = is_array($metrics['top_posts'] ?? null) ? $metrics['top_posts'] : [];
    $needsRepeatability = $topPosts !== []
        && ($narrative === null
            || !isset($narrative['repeatable_best_post_index'])
            || !isset($narrative['repeatable_best_post_reinforces_positioning']));

    if ($narrative !== null && !$needsDistinctiveness && !$needsDescriptionAlignment && !$needsRepeatability) {
        return $metrics;
    }

    if (!(bool) li_config('ai_summary_enabled', false)) {
        return $metrics;
    }

    $rawJson = (string) ($row['raw_data'] ?? '');
    if ($rawJson === '') {
        return $metrics;
    }

    $items = json_decode($rawJson, true);
    if (!is_array($items) || $items === []) {
        return $metrics;
    }

    $dailyCostLimitUsd = (float) li_config('daily_cost_limit_usd', 5.0);
    $limits = li_scan_daily_limits(
        $pdo,
        (int) li_config('daily_scan_limit', 20),
        $dailyCostLimitUsd,
    );
    if ($limits['cost_today_usd'] >= $dailyCostLimitUsd) {
        return $metrics;
    }

    $slug = (string) ($row['company_slug'] ?? '');
    $url = (string) ($row['company_url'] ?? '');
    if (li_linkedin_page_type_from_url($url) === 'school') {
        $items = li_normalize_school_apify_items($items, $slug);
    }
    try {
        $analysis = li_analyze_posts($items, $slug);
        $narrative = li_scan_run_narrative(
            $analysis,
            $brancheCode,
            $companyDescription,
            $contextProfiel,
            $pdo,
            (string) ($row['id'] ?? ''),
        );
    } catch (Throwable) {
        return $metrics;
    }
    if ($narrative === null) {
        return $metrics;
    }

    $metrics['narrative'] = $narrative;
    $metrics = li_scan_merge_post_rank_metrics($metrics, $analysis['metrics']);
    $metrics = li_scan_metrics_with_context($metrics, $brancheCode, $companyDescription, $contextProfiel);

    if (!$persist) {
        return $metrics;
    }

    $scanId = (string) ($row['id'] ?? '');
    if ($scanId === '') {
        return $metrics;
    }

    $narrativeCostUsd = isset($narrative['cost_usd']) ? (float) $narrative['cost_usd'] : null;
    $metricsJson = json_encode($metrics, JSON_UNESCAPED_UNICODE);
    if ($metricsJson === false) {
        return $metrics;
    }

    $stmt = $pdo->prepare(
        'UPDATE scans SET metrics = :metrics, narrative_cost_usd = COALESCE(narrative_cost_usd, 0) + COALESCE(:narrative_cost, 0)
         WHERE id = :id'
    );
    $stmt->execute([
        'metrics' => $metricsJson,
        'narrative_cost' => $narrativeCostUsd,
        'id' => $scanId,
    ]);

    return $metrics;
}

const LI_SCAN_METRICS_FRAGMENT_RETENTION_DAYS = 180;

function li_scan_metrics_retention_days(): int
{
    return (int) li_config('metrics_fragment_retention_days', LI_SCAN_METRICS_FRAGMENT_RETENTION_DAYS);
}

/** @param array<string, mixed> $row */
function li_scan_report_link_expired(array $row): bool
{
    $createdAt = substr(trim((string) ($row['created_at'] ?? '')), 0, 10);
    if ($createdAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdAt)) {
        return false;
    }

    $days = li_scan_metrics_retention_days();
    if ($days < 1) {
        return false;
    }

    try {
        $created = new DateTimeImmutable($createdAt);
        $cutoff = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $days . 'D'));

        return $created < $cutoff;
    } catch (Throwable) {
        return false;
    }
}

function li_scan_ensure_metrics_redacted_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $exists = $pdo->query("SHOW COLUMNS FROM scans LIKE 'metrics_redacted_at'")->fetch();
        if (!$exists) {
            $pdo->exec('ALTER TABLE scans ADD COLUMN metrics_redacted_at DATETIME NULL AFTER report_opened_at');
        }
    } catch (Throwable) {
    }
}

function li_scan_ensure_maintenance_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS app_maintenance (
          maintenance_key VARCHAR(64) NOT NULL PRIMARY KEY,
          last_run_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL
    );
}

function li_scan_maintenance_last_run(PDO $pdo, string $key): ?string
{
    li_scan_ensure_maintenance_schema($pdo);
    $stmt = $pdo->prepare('SELECT last_run_at FROM app_maintenance WHERE maintenance_key = :k LIMIT 1');
    $stmt->execute(['k' => $key]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    return trim((string) ($row['last_run_at'] ?? '')) ?: null;
}

function li_scan_maintenance_mark_run(PDO $pdo, string $key): void
{
    li_scan_ensure_maintenance_schema($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO app_maintenance (maintenance_key, last_run_at) VALUES (:k, NOW())
         ON DUPLICATE KEY UPDATE last_run_at = NOW()'
    );
    $stmt->execute(['k' => $key]);
}

function li_scan_purge_login_attempts(PDO $pdo, int $retentionDays = 30): int
{
    if ($retentionDays < 1) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $stmt->bindValue(':days', $retentionDays, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    } catch (Throwable) {
        return 0;
    }
}

/** Opruimen hooguit één keer per dag (scan.php of cleanup.php). */
function li_scan_run_scheduled_maintenance(PDO $pdo, int $rawRetentionDays): void
{
    $last = li_scan_maintenance_last_run($pdo, 'purge');
    if ($last !== null) {
        try {
            $lastRun = new DateTimeImmutable($last);
            $today = new DateTimeImmutable('today');
            if ($lastRun >= $today) {
                return;
            }
        } catch (Throwable) {
        }
    }

    li_scan_purge_old_raw_data($pdo, $rawRetentionDays);
    li_scan_purge_login_attempts($pdo, 30);
    li_scan_maintenance_mark_run($pdo, 'purge');
}

/** @param array<string, mixed> $metrics @return array<string, mixed> */
function li_scan_redact_metrics_post_fragments(array $metrics): array
{
    $redacted = '[verwijderd]';

    foreach (['top_posts', 'weakest_posts'] as $listKey) {
        if (!is_array($metrics[$listKey] ?? null)) {
            continue;
        }
        foreach ($metrics[$listKey] as $idx => $post) {
            if (!is_array($post)) {
                continue;
            }
            if (array_key_exists('text', $post)) {
                $metrics[$listKey][$idx]['text'] = $redacted;
            }
        }
    }

    if (is_array($metrics['best_post'] ?? null) && array_key_exists('text', $metrics['best_post'])) {
        $metrics['best_post']['text'] = $redacted;
    }

    $narrative = is_array($metrics['narrative'] ?? null) ? $metrics['narrative'] : null;
    if ($narrative !== null) {
        if (isset($narrative['inconsistency_examples']) && is_array($narrative['inconsistency_examples'])) {
            $narrative['inconsistency_examples'] = array_map(
                static fn (): string => $redacted,
                $narrative['inconsistency_examples'],
            );
        }
        foreach (
            [
                'core_message',
                'consistency_reasoning',
                'repeatable_best_post_reasoning',
                'repeatable_best_post_excluded_reason',
                'intro_line',
                'case_line',
                'distinctiveness_reasoning',
                'description_alignment_reasoning',
            ] as $textKey
        ) {
            if (isset($narrative[$textKey]) && is_string($narrative[$textKey]) && trim($narrative[$textKey]) !== '') {
                $narrative[$textKey] = $redacted;
            }
        }
        $metrics['narrative'] = $narrative;
    }

    unset($metrics['company_description']);

    return $metrics;
}

function li_scan_purge_old_metrics_fragments(PDO $pdo, int $retentionDays): int
{
    if ($retentionDays < 1) {
        return 0;
    }

    li_scan_ensure_metrics_redacted_column($pdo);

    $select = $pdo->prepare(
        'SELECT id, metrics FROM scans
         WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
           AND metrics_redacted_at IS NULL
           AND (
             report_token IS NOT NULL
             OR metrics IS NOT NULL
           )
         LIMIT 200'
    );
    $select->bindValue(':days', $retentionDays, PDO::PARAM_INT);
    $select->execute();
    $updated = 0;
    while ($row = $select->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $scanId = (string) ($row['id'] ?? '');
        $metrics = json_decode((string) ($row['metrics'] ?? ''), true);
        if (!is_array($metrics)) {
            $metrics = [];
        }
        $metrics = li_scan_redact_metrics_post_fragments($metrics);
        $metricsJson = json_encode($metrics, JSON_UNESCAPED_UNICODE);
        if ($metricsJson === false) {
            continue;
        }

        $update = $pdo->prepare(
            'UPDATE scans SET metrics = :metrics, report_token = NULL, metrics_redacted_at = NOW() WHERE id = :id'
        );
        $update->execute(['metrics' => $metricsJson, 'id' => $scanId]);
        $updated += $update->rowCount();
    }

    return $updated;
}

function li_scan_purge_old_raw_data(PDO $pdo, int $retentionDays): int
{
    $stmt = $pdo->prepare(
        'UPDATE scans SET raw_data = NULL
         WHERE raw_data IS NOT NULL
           AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
    );
    $stmt->bindValue(':days', $retentionDays, PDO::PARAM_INT);
    $stmt->execute();

    $count = $stmt->rowCount();
    $fragmentDays = (int) li_config('metrics_fragment_retention_days', LI_SCAN_METRICS_FRAGMENT_RETENTION_DAYS);
    $count += li_scan_purge_old_metrics_fragments($pdo, $fragmentDays);

    return $count;
}

function li_scan_ensure_report_token_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $exists = $pdo->query("SHOW COLUMNS FROM scans LIKE 'report_token'")->fetch();
        if (!$exists) {
            $pdo->exec('ALTER TABLE scans ADD COLUMN report_token CHAR(32) NULL AFTER error_message');
        }
    } catch (Throwable) {
        // Kolom bestaat al of geen rechten.
    }
}

function li_scan_new_report_token(): string
{
    return bin2hex(random_bytes(16));
}

/** Publieke link-token voor een voltooide scan (idempotent). */
function li_scan_ensure_report_token(PDO $pdo, string $scanId): string
{
    li_scan_ensure_report_token_column($pdo);
    $scanId = trim($scanId);
    if ($scanId === '') {
        return '';
    }

    $stmt = $pdo->prepare('SELECT report_token, status, created_at FROM scans WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $scanId]);
    $row = $stmt->fetch();
    if (!$row || ($row['status'] ?? '') !== 'completed') {
        return '';
    }

    if (li_scan_report_link_expired(is_array($row) ? $row : [])) {
        return '';
    }

    $existing = trim((string) ($row['report_token'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = li_scan_new_report_token();
        try {
            $update = $pdo->prepare(
                'UPDATE scans SET report_token = :token WHERE id = :id AND (report_token IS NULL OR report_token = \'\')'
            );
            $update->execute(['token' => $token, 'id' => $scanId]);
            if ($update->rowCount() > 0) {
                return $token;
            }

            $stmt->execute(['id' => $scanId]);
            $row = $stmt->fetch();
            $existing = trim((string) ($row['report_token'] ?? ''));

            return $existing !== '' ? $existing : $token;
        } catch (Throwable) {
            continue;
        }
    }

    return '';
}

function li_scan_ensure_report_opened_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $exists = $pdo->query("SHOW COLUMNS FROM scans LIKE 'report_opened_at'")->fetch();
        if (!$exists) {
            $pdo->exec('ALTER TABLE scans ADD COLUMN report_opened_at DATETIME NULL AFTER report_token');
        }
    } catch (Throwable) {
    }
}

/** Eerste keer dat een prospect het publieke rapport opent (Y/N-tracker). */
function li_scan_record_report_open(PDO $pdo, string $scanId): void
{
    li_scan_ensure_report_opened_column($pdo);
    $scanId = trim($scanId);
    if ($scanId === '') {
        return;
    }

    $pdo->prepare(
        'UPDATE scans SET report_opened_at = COALESCE(report_opened_at, NOW()) WHERE id = :id'
    )->execute(['id' => $scanId]);
}

function li_scan_report_was_opened(array $row): bool
{
    return trim((string) ($row['report_opened_at'] ?? '')) !== '';
}

/** @return array<string, mixed>|null */
function li_scan_find_by_report_token(PDO $pdo, string $token): ?array
{
    li_scan_ensure_report_token_column($pdo);
    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM scans WHERE report_token = :token AND status = :status LIMIT 1'
    );
    $stmt->execute(['token' => $token, 'status' => 'completed']);
    $row = $stmt->fetch();
    if (!$row || !is_array($row)) {
        return null;
    }

    if (li_scan_report_link_expired($row)) {
        return null;
    }

    return $row;
}
