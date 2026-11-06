<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/metrics-repository.php';

function ce_ensure_performance_reports_table(PDO $pdo): void
{
    if (ce_table_exists($pdo, 'performance_reports')) {
        ce_ensure_report_article_count_columns($pdo);
        ce_ensure_report_distribution_columns($pdo);
        ce_ensure_report_meta_columns($pdo);
        ce_ensure_report_scope_column($pdo);

        return;
    }

    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS performance_reports (
          id CHAR(36) NOT NULL PRIMARY KEY,
          client_id CHAR(36) NOT NULL,
          period_start DATE NOT NULL,
          period_end DATE NOT NULL,
          scope VARCHAR(20) DEFAULT 'general',
          scope_article_id CHAR(36) NULL,
          pct_on_strategy DECIMAL(6,2) NULL,
          pct_general DECIMAL(6,2) NULL,
          tone_distribution JSON NULL,
          angle_distribution JSON NULL,
          execution_rate DECIMAL(6,2) NULL,
          engagement_on_strategy DECIMAL(12,2) NULL,
          engagement_general DECIMAL(12,2) NULL,
          engagement_diff_pct DECIMAL(8,2) NULL,
          article_count_strategy INT NULL,
          article_count_general INT NULL,
          article_count_total INT NULL,
          median_on_strategy DECIMAL(12,2) NULL,
          median_general DECIMAL(12,2) NULL,
          outlier_posts JSON NULL,
          best_performing_post JSON NULL,
          daily_breakdown JSON NULL,
          narrative TEXT NULL,
          generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY idx_client (client_id),
          KEY idx_period (period_start, period_end)
        )
        SQL
    );
    unset($GLOBALS['ce_table_columns']['performance_reports']);
}

function ce_ensure_report_article_count_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    foreach ([
        'article_count_strategy' => 'INT NULL AFTER engagement_diff_pct',
        'article_count_general' => 'INT NULL AFTER article_count_strategy',
        'article_count_total' => 'INT NULL AFTER article_count_general',
    ] as $column => $definition) {
        if (ce_table_has_column($pdo, 'performance_reports', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE performance_reports ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['performance_reports']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }
}

function ce_ensure_report_distribution_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    foreach ([
        'tone_distribution' => 'JSON NULL',
        'angle_distribution' => 'JSON NULL',
    ] as $column => $definition) {
        if (ce_table_has_column($pdo, 'performance_reports', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE performance_reports ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['performance_reports']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }
}

function ce_ensure_report_meta_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    foreach ([
        'report_title' => 'VARCHAR(255) NULL',
        'findability_snapshot' => 'JSON NULL',
    ] as $column => $definition) {
        if (ce_table_has_column($pdo, 'performance_reports', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE performance_reports ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['performance_reports']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }
}

/** Legacy DB: scope was ENUM('general','article') — findability needs VARCHAR. */
function ce_ensure_report_scope_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'performance_reports') || !ce_table_has_column($pdo, 'performance_reports', 'scope')) {
        return;
    }

    foreach ([
        "ALTER TABLE performance_reports MODIFY scope VARCHAR(20) NOT NULL DEFAULT 'general'",
        "ALTER TABLE performance_reports MODIFY scope VARCHAR(20) DEFAULT 'general'",
    ] as $sql) {
        try {
            $pdo->exec($sql);
            unset($GLOBALS['ce_table_columns']['performance_reports']);

            return;
        } catch (Throwable) {
            // Probeer volgende variant (MySQL/MariaDB verschillen).
        }
    }
}

function ce_report_display_title(array $row): string
{
    $custom = trim((string) ($row['report_title'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    return sprintf(
        'Report %s – %s',
        (string) ($row['period_start'] ?? ''),
        (string) ($row['period_end'] ?? ''),
    );
}

/** @param array<string, mixed> $dto */
function ce_update_report_title(PDO $pdo, string $reportId, string $title): void
{
    ce_ensure_report_meta_columns($pdo);
    $title = trim($title);
    if ($title === '') {
        return;
    }

    $stmt = $pdo->prepare('UPDATE performance_reports SET report_title = :title WHERE id = :id');
    $stmt->execute(['title' => $title, 'id' => $reportId]);
}

/**
 * Posts voor merkrapport — zelfde scope als CE-07 (inclusief HBA-testposts in archief).
 *
 * @return list<array<string, mixed>>
 */
function ce_report_posts_for_period(
    PDO $pdo,
    string $clientId,
    string $periodStart,
    string $periodEnd,
    string $scope = 'general',
    ?string $scopeArticleId = null,
): array {
    if ($clientId === '' || !ce_table_exists($pdo, 'articles') || !ce_table_exists($pdo, 'content_slots')) {
        return [];
    }

    $sql = <<<'SQL'
SELECT a.id,
  COALESCE(a.headline_human, a.headline) AS headline,
  COALESCE(a.angle_final, a.angle_suggested, 'general') AS angle,
  COALESCE(a.tone_final, a.tone_suggested, 'b2b') AS tone,
  COALESCE(a.interaction_views, 0) AS views,
  COALESCE(a.interaction_likes, 0) AS likes,
  COALESCE(a.interaction_comments, 0) AS comments,
  DATE(a.published_at) AS publish_date,
  hs.contentrol,
  hs.brand_contribution_score,
  hs.architecture_risk_flags AS hba_flags,
  hs.raw_llm_response
FROM articles a
INNER JOIN content_slots s ON s.id = a.slot_id
LEFT JOIN hba_scores hs ON hs.id = (
  SELECT hs2.id FROM hba_scores hs2
  WHERE hs2.post_id = a.id
  ORDER BY hs2.scored_at DESC
  LIMIT 1
)
WHERE s.client_id = :client_id
  AND COALESCE(a.status, '') IN ('published', 'archived', 'approved', 'draft', 'written')
SQL;

    $params = ['client_id' => $clientId];
    if ($scope === 'article' && $scopeArticleId !== null && $scopeArticleId !== '') {
        $sql .= ' AND a.id = :scope_article_id';
        $params['scope_article_id'] = $scopeArticleId;
    } else {
        $sql .= ' AND (
            a.published_at IS NULL
            OR DATE(a.published_at) BETWEEN :period_start AND :period_end
        )';
        $params['period_start'] = $periodStart;
        $params['period_end'] = $periodEnd;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @param list<array<string, mixed>> $rows */
function ce_report_metrics_from_posts(array $rows): array
{
    require_once __DIR__ . '/hba-prompt.php';

    $posts = [];
    foreach ($rows as $row) {
        $views = (int) ($row['views'] ?? 0);
        $likes = (int) ($row['likes'] ?? 0);
        $comments = (int) ($row['comments'] ?? 0);
        $engagement = $views + $likes + $comments;
        $hoofdMeta = ce_hba_hoofdoordeel_from_raw($row['raw_llm_response'] ?? null);
        $hoofd = trim((string) ($hoofdMeta['hoofdoordeel'] ?? ''));
        $onStrategy = $hoofd !== '' ? $hoofd === 'op_merk' : (
            isset($row['brand_contribution_score'])
            && is_numeric($row['brand_contribution_score'])
            && (float) $row['brand_contribution_score'] >= 6.0
        );
        $posts[] = [
            'id' => (string) ($row['id'] ?? ''),
            'headline' => (string) ($row['headline'] ?? 'Untitled'),
            'angle' => (string) ($row['angle'] ?? 'general'),
            'engagement' => $engagement,
            'publish_date' => (string) ($row['publish_date'] ?? ''),
            'hoofdoordeel' => $hoofd !== '' ? $hoofd : null,
            'on_strategy' => $onStrategy,
        ];
    }

    $strategyPosts = array_values(array_filter($posts, static fn (array $p): bool => $p['on_strategy']));
    $generalPosts = array_values(array_filter($posts, static fn (array $p): bool => !$p['on_strategy']));
    $total = count($posts);

    $pctOnStrategy = $total > 0 ? round(count($strategyPosts) / $total * 1000) / 10 : 0.0;
    $pctGeneral = $total > 0 ? round(count($generalPosts) / $total * 1000) / 10 : 0.0;

    $best = null;
    $rank = static function (array $p): int {
        return match ((string) ($p['hoofdoordeel'] ?? '')) {
            'op_merk' => 3,
            'neutraal' => 2,
            'categorietaal' => 0,
            default => 1,
        };
    };
    foreach ($posts as $p) {
        $hoofd = (string) ($p['hoofdoordeel'] ?? '');
        if ($hoofd === 'regelbreuk' || $hoofd === 'tegenkracht') {
            continue;
        }
        if ($best === null) {
            $best = $p;
            continue;
        }
        if ($p['engagement'] > $best['engagement']) {
            $best = $p;
            continue;
        }
        if ($p['engagement'] === $best['engagement'] && $rank($p) > $rank($best)) {
            $best = $p;
        }
    }

    return [
        'articleCountTotal' => $total,
        'articleCountStrategy' => count($strategyPosts),
        'articleCountGeneral' => count($generalPosts),
        'pctOnStrategy' => $pctOnStrategy,
        'pctGeneral' => $pctGeneral,
        'bestPerformingPost' => $best !== null ? [
            'article_id' => $best['id'],
            'headline' => $best['headline'],
            'angle_final' => $best['angle'],
            'engagement' => $best['engagement'],
            'publish_date' => $best['publish_date'],
            'hoofdoordeel' => $best['hoofdoordeel'],
        ] : null,
        'hbaPosts' => array_map(static fn (array $p): array => [
            'articleId' => $p['id'],
            'headline' => $p['headline'],
            'hoofdoordeel' => $p['hoofdoordeel'],
            'onStrategy' => $p['on_strategy'],
            'engagement' => $p['engagement'],
        ], $posts),
    ];
}

/** @param array<string, mixed> $dto */
function ce_report_apply_live_article_metrics(PDO $pdo, array $dto): array
{
    $clientId = trim((string) ($dto['clientId'] ?? ''));
    if ($clientId === '') {
        $clientId = ce_default_client_id($pdo) ?? '';
    }
    if ($clientId === '') {
        return $dto;
    }

    $scope = trim((string) ($dto['scope'] ?? 'general'));
    if ($scope === 'findability') {
        return $dto;
    }

    $periodStart = trim((string) ($dto['periodStart'] ?? ''));
    $periodEnd = trim((string) ($dto['periodEnd'] ?? ''));
    if ($periodStart === '' || $periodEnd === '') {
        return $dto;
    }

    $scopeArticleId = trim((string) ($dto['scopeArticleId'] ?? ''));
    $rows = ce_report_posts_for_period(
        $pdo,
        $clientId,
        $periodStart,
        $periodEnd,
        $scope,
        $scopeArticleId !== '' ? $scopeArticleId : null,
    );
    $metrics = ce_report_metrics_from_posts($rows);

    $dto['articleCountTotal'] = $metrics['articleCountTotal'];
    $dto['articleCountStrategy'] = $metrics['articleCountStrategy'];
    $dto['articleCountGeneral'] = $metrics['articleCountGeneral'];
    $dto['pctOnStrategy'] = $metrics['pctOnStrategy'];
    $dto['pctGeneral'] = $metrics['pctGeneral'];
    $dto['bestPerformingPost'] = $metrics['bestPerformingPost'];
    $dto['reportHbaPosts'] = $metrics['hbaPosts'];

    return $dto;
}

/** @param array<string, mixed> $dto */
function ce_enrich_report_dto(PDO $pdo, array $dto): array
{
    $clientId = trim((string) ($dto['clientId'] ?? ''));
    if ($clientId === '') {
        $clientId = ce_default_client_id($pdo) ?? '';
    }

    $dto = ce_report_apply_live_article_metrics($pdo, $dto);

    $dto['weeklyViews'] = ce_metrics_weekly_views(
        $pdo,
        $clientId !== '' ? $clientId : null,
        (string) ($dto['periodStart'] ?? ''),
        (string) ($dto['periodEnd'] ?? ''),
    );
    $dto['websiteAnalytics'] = ce_analytics_report_snapshot(
        $pdo,
        $clientId !== '' ? $clientId : null,
        (string) ($dto['periodStart'] ?? ''),
        (string) ($dto['periodEnd'] ?? ''),
    );
    $dto['hasAnalytics'] = (bool) ($dto['websiteAnalytics']['hasAnalytics'] ?? false);

    ce_ensure_strategy_columns($pdo);
    $clientRow = $clientId !== '' ? ce_fetch_client_row($pdo, $clientId) : null;
    $dto['brandGoalCategory'] = $clientRow
        ? ce_strategy_read_field($clientRow, ce_strategy_db_columns($pdo, 'brandGoalCategory'))
        : '';

    require_once __DIR__ . '/linkedin-engagement-repository.php';
    $clientRef = $clientId !== '' ? $clientId : null;
    $dto['linkedInEngagement'] = [
        'hasSource' => ce_linkedin_has_source_for_client($pdo, $clientRef),
        'totals' => ce_linkedin_engagement_aggregate(
            $pdo,
            $clientRef,
            (string) ($dto['periodStart'] ?? ''),
            (string) ($dto['periodEnd'] ?? ''),
        ),
        'weekly' => ce_linkedin_weekly_engagement(
            $pdo,
            $clientRef,
            (string) ($dto['periodStart'] ?? ''),
            (string) ($dto['periodEnd'] ?? ''),
        ),
    ];

    return $dto;
}

/** @return array<string, mixed> */
function ce_report_dto(array $row): array
{
    $decode = static function (?string $json): mixed {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    };

    return [
        'id' => (string) ($row['id'] ?? ''),
        'clientId' => (string) ($row['client_id'] ?? ''),
        'periodStart' => (string) ($row['period_start'] ?? ''),
        'periodEnd' => (string) ($row['period_end'] ?? ''),
        'scope' => (string) ($row['scope'] ?? 'general'),
        'scopeArticleId' => $row['scope_article_id'] ?? null,
        'pctOnStrategy' => isset($row['pct_on_strategy']) ? (float) $row['pct_on_strategy'] : null,
        'pctGeneral' => isset($row['pct_general']) ? (float) $row['pct_general'] : null,
        'toneDistribution' => (static function (?string $json): ?array {
            if ($json === null || $json === '') {
                return null;
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return null;
            }
            $angle = $decoded['_angle'] ?? null;
            unset($decoded['_angle']);

            return $decoded;
        })(isset($row['tone_distribution']) ? (string) $row['tone_distribution'] : null),
        'angleDistribution' => (static function (?string $json): ?array {
            if ($json === null || $json === '') {
                return null;
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return null;
            }

            return isset($decoded['_angle']) && is_array($decoded['_angle']) ? $decoded['_angle'] : null;
        })(isset($row['tone_distribution']) ? (string) $row['tone_distribution'] : null),
        'executionRate' => isset($row['execution_rate']) ? (float) $row['execution_rate'] : null,
        'engagementOnStrategy' => isset($row['engagement_on_strategy']) ? (float) $row['engagement_on_strategy'] : null,
        'engagementGeneral' => isset($row['engagement_general']) ? (float) $row['engagement_general'] : null,
        'engagementDiffPct' => isset($row['engagement_diff_pct']) ? (float) $row['engagement_diff_pct'] : null,
        'articleCountStrategy' => isset($row['article_count_strategy']) ? (int) $row['article_count_strategy'] : null,
        'articleCountGeneral' => isset($row['article_count_general']) ? (int) $row['article_count_general'] : null,
        'articleCountTotal' => isset($row['article_count_total']) ? (int) $row['article_count_total'] : null,
        'medianOnStrategy' => isset($row['median_on_strategy']) ? (float) $row['median_on_strategy'] : null,
        'medianGeneral' => isset($row['median_general']) ? (float) $row['median_general'] : null,
        'outlierPosts' => $decode(isset($row['outlier_posts']) ? (string) $row['outlier_posts'] : null) ?? [],
        'bestPerformingPost' => $decode(isset($row['best_performing_post']) ? (string) $row['best_performing_post'] : null),
        'dailyBreakdown' => $decode(isset($row['daily_breakdown']) ? (string) $row['daily_breakdown'] : null) ?? [],
        'narrative' => (string) ($row['narrative'] ?? ''),
        'generatedAt' => (string) ($row['generated_at'] ?? ''),
        'title' => ce_report_display_title($row),
        'findabilitySnapshot' => (static function (?string $json): ?array {
            if ($json === null || $json === '') {
                return null;
            }
            $decoded = json_decode($json, true);

            return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
        })(isset($row['findability_snapshot']) ? (string) $row['findability_snapshot'] : null),
    ];
}

/** @return array<string, mixed> */
function ce_fetch_reports(PDO $pdo, int $limit = 20): array
{
    ce_ensure_performance_reports_table($pdo);
    $clientId = ce_default_client_id($pdo);
    $sql = 'SELECT * FROM performance_reports WHERE 1=1';
    $params = [];
    if ($clientId !== null) {
        $sql .= ' AND client_id = :client_id';
        $params['client_id'] = $clientId;
    }
    $sql .= ' ORDER BY generated_at DESC LIMIT ' . max(1, min($limit, 50));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $reports[] = ce_enrich_report_dto($pdo, ce_report_dto($row));
    }

    return ['reports' => $reports, 'trend' => ce_fetch_report_trend($pdo, $clientId)];
}

/**
 * Trendlijn engagement_diff_pct over de laatste 6 rapportperiodes (§2a).
 *
 * @return list<array{reportId: string, monthLabel: string, periodStart: string, engagementDiffPct: float|null}>
 */
function ce_fetch_report_trend(PDO $pdo, ?string $clientId, int $limit = 6): array
{
    ce_ensure_performance_reports_table($pdo);

    $sql = <<<'SQL'
SELECT id, period_start, engagement_diff_pct
FROM performance_reports
WHERE 1=1
SQL;
    $params = [];
    if ($clientId !== null) {
        $sql .= ' AND client_id = :client_id';
        $params['client_id'] = $clientId;
    }
    $sql .= ' ORDER BY period_start DESC LIMIT ' . max(1, min($limit, 24));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $months = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    $trend = [];
    foreach (array_reverse($rows) as $row) {
        $periodStart = (string) ($row['period_start'] ?? '');
        $monthIndex = $periodStart !== '' ? ((int) substr($periodStart, 5, 2)) - 1 : -1;
        $trend[] = [
            'reportId' => (string) ($row['id'] ?? ''),
            'monthLabel' => $monthIndex >= 0 && $monthIndex < 12 ? $months[$monthIndex] : '',
            'periodStart' => $periodStart,
            'engagementDiffPct' => isset($row['engagement_diff_pct']) ? (float) $row['engagement_diff_pct'] : null,
        ];
    }

    return $trend;
}

/** @return array<string, mixed> */
function ce_fetch_report(PDO $pdo, string $reportId): array
{
    ce_ensure_performance_reports_table($pdo);
    $clientId = ce_default_client_id($pdo);
    $sql = 'SELECT * FROM performance_reports WHERE id = :id';
    $params = ['id' => $reportId];
    if ($clientId !== null) {
        $sql .= ' AND client_id = :client_id';
        $params['client_id'] = $clientId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Report niet gevonden');
    }

    return ['report' => ce_enrich_report_dto($pdo, ce_report_dto($row))];
}

/** @param array<string, mixed> $body */
function ce_generate_findability_report(PDO $pdo, array $body): array
{
    $periodStart = trim((string) ($body['periodStart'] ?? $body['period_start'] ?? ''));
    $periodEnd = trim((string) ($body['periodEnd'] ?? $body['period_end'] ?? ''));
    $title = trim((string) ($body['title'] ?? $body['reportTitle'] ?? $body['report_title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('title is verplicht');
    }

    $clientId = ce_default_client_id($pdo) ?? '';
    if ($clientId === '') {
        throw new RuntimeException('Geen actieve klant');
    }

    require_once __DIR__ . '/llm-repository.php';
    $geo = ce_fetch_llm_visibility_report($pdo, $clientId, 'geo');
    $seo = ce_fetch_llm_visibility_report($pdo, $clientId, 'seo');
    $snapshot = ['geo' => $geo, 'seo' => $seo];

    $geoLatest = is_array($geo['latest'] ?? null) ? $geo['latest'] : null;
    $seoLatest = is_array($seo['latest'] ?? null) ? $seo['latest'] : null;
    $narrative = sprintf(
        'Findability snapshot: GEO %d/%d zichtbaar, SEO %d/%d zichtbaar.',
        (int) ($geoLatest['mentionedCount'] ?? 0),
        (int) ($geoLatest['totalCount'] ?? 0),
        (int) ($seoLatest['mentionedCount'] ?? 0),
        (int) ($seoLatest['totalCount'] ?? 0),
    );

    ce_ensure_performance_reports_table($pdo);
    ce_ensure_report_meta_columns($pdo);
    ce_ensure_report_scope_column($pdo);

    $reportId = ce_uuid_v4();
    $stmt = $pdo->prepare(
        <<<'SQL'
INSERT INTO performance_reports (
  id, client_id, period_start, period_end, scope, report_title, findability_snapshot, narrative
) VALUES (
  :id, :client_id, :period_start, :period_end, 'findability', :report_title, :findability_snapshot, :narrative
)
SQL
    );
    $stmt->execute([
        'id' => $reportId,
        'client_id' => $clientId,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'report_title' => $title,
        'findability_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
        'narrative' => $narrative,
    ]);

    return ce_fetch_report($pdo, $reportId);
}

/** @param array<string, mixed> $body */
function ce_generate_report(PDO $pdo, array $body): array
{
    $periodStart = trim((string) ($body['periodStart'] ?? $body['period_start'] ?? ''));
    $periodEnd = trim((string) ($body['periodEnd'] ?? $body['period_end'] ?? ''));
    if ($periodStart === '' || $periodEnd === '') {
        throw new InvalidArgumentException('periodStart en periodEnd zijn verplicht');
    }

    $title = trim((string) ($body['title'] ?? $body['reportTitle'] ?? $body['report_title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('title is verplicht');
    }

    $scope = trim((string) ($body['scope'] ?? 'general'));
    if ($scope === 'brand') {
        $scope = 'general';
    }
    if (!in_array($scope, ['general', 'article', 'findability'], true)) {
        throw new InvalidArgumentException('scope moet brand, article of findability zijn');
    }

    if ($scope === 'findability') {
        return ce_generate_findability_report($pdo, array_merge($body, ['title' => $title]));
    }

    $scopeArticleId = trim((string) ($body['scopeArticleId'] ?? $body['scope_article_id'] ?? ''));
    if ($scope === 'article' && $scopeArticleId === '') {
        throw new InvalidArgumentException('scopeArticleId is verplicht bij scope=article');
    }

    $clientId = ce_default_client_id($pdo) ?? '';

    // Ververs echte GA4-paginaweergaven vóór CE-07 rekent — zelfde bron als Overview
    // (articles.interaction_views), zodat Report en Overview nooit uit elkaar lopen.
    if ($clientId !== '') {
        try {
            ce_analytics_sync_article_views($pdo, $clientId);
        } catch (Throwable) {
            // GA4 niet gekoppeld of tijdelijk onbereikbaar — report gaat door met bestaande data.
        }
    }

    $payload = [
        'client_id' => $clientId,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'scope' => $scope,
        'scope_article_id' => $scope === 'article' ? $scopeArticleId : '',
    ];

    $webhook = trim((string) (getenv('N8N_CE07_WEBHOOK_URL') ?: ''));
    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($webhook === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $webhook = trim((string) ($config['ce07_webhook_url'] ?? ''));
        }
    }

    if ($webhook === '') {
        throw new RuntimeException('N8N_CE07_WEBHOOK_URL is niet geconfigureerd');
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            'timeout' => 180,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($webhook, false, $ctx);
    $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true || empty($decoded['report_id'])) {
        $message = is_array($decoded) && isset($decoded['error']) ? (string) $decoded['error'] : 'CE-07 webhook mislukt';
        throw new RuntimeException($message);
    }

    $reportId = (string) $decoded['report_id'];
    ce_update_report_title($pdo, $reportId, $title);

    return ce_fetch_report($pdo, $reportId);
}

/** @return list<array<string, mixed>> */
function ce_fetch_report_article_options(PDO $pdo): array
{
    $clientId = ce_default_client_id($pdo);
    $sql = <<<'SQL'
        SELECT a.id AS article_id, COALESCE(a.headline_human, a.headline, s.theme) AS title
        FROM articles a
        JOIN content_slots s ON s.id = a.slot_id
        WHERE 1=1
        SQL;
    $params = [];
    if ($clientId !== null) {
        $sql .= ' AND s.client_id = :client_id';
        $params['client_id'] = $clientId;
    }
    $sql .= ' ORDER BY ' . ce_article_order_sql($pdo, 'a') . ' LIMIT 50';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map(static fn (array $row): array => [
        'articleId' => (string) ($row['article_id'] ?? ''),
        'title' => (string) ($row['title'] ?? 'Untitled'),
    ], $stmt->fetchAll() ?: []);
}
