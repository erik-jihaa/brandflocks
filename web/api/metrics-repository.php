<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/analytics-oauth-helper.php';
require_once __DIR__ . '/client-context.php';

/** @return array{id: string, propertyId: string}|null */
function ce_analytics_source_for_client(PDO $pdo, ?string $clientId): ?array
{
    return ce_analytics_oauth_source_for_client($pdo, $clientId);
}

/** @return array{propertyId: string, accessToken: string}|null */
function ce_analytics_api_context(PDO $pdo, ?string $clientId): ?array
{
    $source = ce_analytics_source_for_client($pdo, $clientId);
    if ($source === null) {
        return null;
    }

    $refreshToken = ce_analytics_get_refresh_token($pdo, $source['id']);
    if ($refreshToken === null) {
        return null;
    }

    $token = ce_analytics_refresh_access_token($refreshToken);
    if ($token === null || empty($token['access_token'])) {
        return null;
    }

    return [
        'propertyId' => $source['propertyId'],
        'accessToken' => (string) $token['access_token'],
    ];
}

/** @param array<string, mixed> $body
 *  @return array<string, mixed>|null
 */
function ce_analytics_run_report(array $context, array $body): ?array
{
    $payload = json_encode($body, JSON_THROW_ON_ERROR);

    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
        . rawurlencode($context['propertyId'])
        . ':runReport';

    $response = ce_analytics_http_post($url, $payload, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $context['accessToken'],
    ]);

    if ($response['status'] < 200 || $response['status'] >= 300) {
        return null;
    }

    $json = json_decode($response['body'], true);

    return is_array($json) ? $json : null;
}

function ce_analytics_metric_int(array $row, int $index): int
{
    if (!isset($row['metricValues'][$index]['value'])) {
        return 0;
    }

    $value = $row['metricValues'][$index]['value'];

    return is_numeric($value) ? (int) $value : 0;
}

function ce_analytics_dimension_value(array $row, int $index): string
{
    if (!isset($row['dimensionValues'][$index]['value'])) {
        return '';
    }

    return trim((string) $row['dimensionValues'][$index]['value']);
}

/** @return array{activeUsers: int, newUsers: int, sessions: int, screenPageViews: int} */
function ce_analytics_fetch_totals(
    PDO $pdo,
    ?string $clientId,
    string $startDate,
    string $endDate,
): array {
    $empty = [
        'activeUsers' => 0,
        'newUsers' => 0,
        'sessions' => 0,
        'screenPageViews' => 0,
    ];

    $context = ce_analytics_api_context($pdo, $clientId);
    if ($context === null) {
        return $empty;
    }

    $json = ce_analytics_run_report($context, [
        'dateRanges' => [
            ['startDate' => $startDate, 'endDate' => $endDate],
        ],
        'metrics' => [
            ['name' => 'activeUsers'],
            ['name' => 'newUsers'],
            ['name' => 'sessions'],
            ['name' => 'screenPageViews'],
        ],
    ]);

    if ($json === null || !isset($json['rows'][0])) {
        return $empty;
    }

    $row = $json['rows'][0];

    return [
        'activeUsers' => ce_analytics_metric_int($row, 0),
        'newUsers' => ce_analytics_metric_int($row, 1),
        'sessions' => ce_analytics_metric_int($row, 2),
        'screenPageViews' => ce_analytics_metric_int($row, 3),
    ];
}

function ce_analytics_fetch_page_views(
    PDO $pdo,
    ?string $clientId,
    string $startDate,
    string $endDate,
): ?int {
    $oauthConnected = ce_analytics_oauth_connected_for_client($pdo, $clientId);
    $totals = ce_analytics_fetch_totals($pdo, $clientId, $startDate, $endDate);

    if (!$oauthConnected && $totals['screenPageViews'] === 0) {
        $context = ce_analytics_api_context($pdo, $clientId);
        if ($context === null) {
            return null;
        }
    }

    return $totals['screenPageViews'];
}

function ce_analytics_normalize_page_path(string $path): ?string
{
    $path = trim($path);
    if ($path === '') {
        return null;
    }
    $stripped = strtok($path, '?');
    $path = $stripped === false ? $path : $stripped;
    $path = '/' . ltrim($path, '/');
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

/** Genormaliseerd pad uit een volledige URL (host genegeerd) — voor GA4 pagePath-matching. */
function ce_url_page_path(string $url): ?string
{
    $parts = parse_url(trim($url));
    if (!is_array($parts) || !isset($parts['path']) || trim((string) $parts['path']) === '') {
        return null;
    }

    return ce_analytics_normalize_page_path((string) $parts['path']);
}

/**
 * Eén GA4-call voor alle pagePaths in de periode — schaalt beter dan per-artikel calls.
 *
 * @return array<string, int> genormaliseerd pad => screenPageViews
 */
function ce_analytics_fetch_page_views_by_path(
    PDO $pdo,
    ?string $clientId,
    string $startDate,
    string $endDate,
): array {
    $context = ce_analytics_api_context($pdo, $clientId);
    if ($context === null) {
        return [];
    }

    $json = ce_analytics_run_report($context, [
        'dateRanges' => [
            ['startDate' => $startDate, 'endDate' => $endDate],
        ],
        'dimensions' => [
            ['name' => 'pagePath'],
        ],
        'metrics' => [
            ['name' => 'screenPageViews'],
        ],
        'limit' => 100000,
    ]);

    if ($json === null) {
        return [];
    }

    $map = [];
    foreach ($json['rows'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $path = ce_analytics_normalize_page_path(ce_analytics_dimension_value($row, 0));
        if ($path === null) {
            continue;
        }
        $map[$path] = ($map[$path] ?? 0) + ce_analytics_metric_int($row, 0);
    }

    return $map;
}

/**
 * Koppelt echte GA4-paginaweergaven aan gepubliceerde artikelen (pagePath-matching op de
 * opgeslagen blog-URL) en schrijft ze naar `articles.interaction_views`. Dit is sinds
 * aug 2026 de enige bron voor die kolom — LinkedIn-sync schrijft er niet meer naartoe
 * (zie `ce_linkedin_apply_post_to_article`). Gedeelde bron voor Overview-engagementtrend
 * én CE-07 Report, om dubbeltelling met LinkedIn-engagement te voorkomen.
 *
 * @return array{matched: int, checked: int, debug?: list<array<string, mixed>>}
 */
function ce_analytics_sync_article_views(PDO $pdo, ?string $clientId, int $lookbackDays = 180, bool $debug = false): array
{
    $empty = ['matched' => 0, 'checked' => 0];

    if (
        $clientId === null
        || $clientId === ''
        || !ce_table_exists($pdo, 'articles')
        || !ce_table_exists($pdo, 'content_slots')
        || !ce_table_has_column($pdo, 'articles', 'interaction_views')
    ) {
        return $empty + ($debug ? ['debug' => [['reason' => 'schema_or_client_missing']]] : []);
    }

    if (!ce_analytics_oauth_connected_for_client($pdo, $clientId)) {
        return $empty + ($debug ? ['debug' => [['reason' => 'ga4_not_connected']]] : []);
    }

    $timezone = ce_ce_timezone();
    $endDate = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
    $startDate = (new DateTimeImmutable('now', $timezone))->modify("-{$lookbackDays} days")->format('Y-m-d');

    $viewsByPath = ce_analytics_fetch_page_views_by_path($pdo, $clientId, $startDate, $endDate);
    if ($viewsByPath === []) {
        return $empty + ($debug ? ['debug' => [['reason' => 'ga4_no_pageviews_in_period']]] : []);
    }

    $hasPublishUrls = ce_table_has_column($pdo, 'articles', 'publish_urls');
    $extra = $hasPublishUrls ? ', a.publish_urls' : '';

    $scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId);
    $publishedStatus = ce_db_status_for_ui($pdo, 'articles', 'published');
    $sql = <<<SQL
SELECT a.id, a.social_post_url$extra
FROM articles a
INNER JOIN content_slots s ON s.id = a.slot_id
WHERE a.status = :published
  AND a.published_at IS NOT NULL
  AND a.published_at >= DATE_SUB(CURDATE(), INTERVAL $lookbackDays DAY)
SQL;
    $sql .= $scope['sql'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(['published' => $publishedStatus], $scope['params']));

    $update = $pdo->prepare('UPDATE articles SET interaction_views = :views WHERE id = :id');
    $checked = 0;
    $matched = 0;
    $debugRows = [];

    foreach ($stmt->fetchAll() ?: [] as $row) {
        $checked++;

        $blogUrl = '';
        if ($hasPublishUrls) {
            $rawUrls = $row['publish_urls'] ?? null;
            $decoded = is_string($rawUrls) ? json_decode($rawUrls, true) : null;
            $blogUrl = is_array($decoded) ? trim((string) ($decoded['blog'] ?? '')) : '';
        }
        if ($blogUrl === '') {
            $blogUrl = trim((string) ($row['social_post_url'] ?? ''));
        }

        if ($blogUrl === '' || !preg_match('#^https?://#i', $blogUrl)) {
            if ($debug) {
                $debugRows[] = ['id' => (string) $row['id'], 'reason' => 'no_blog_url'];
            }
            continue;
        }

        $path = ce_url_page_path($blogUrl);
        if ($path === null || !isset($viewsByPath[$path])) {
            if ($debug) {
                $debugRows[] = ['id' => (string) $row['id'], 'reason' => 'path_not_in_ga4', 'blogUrl' => $blogUrl, 'path' => $path];
            }
            continue;
        }

        $update->execute(['views' => $viewsByPath[$path], 'id' => (string) $row['id']]);
        $matched++;
        if ($debug) {
            $debugRows[] = ['id' => (string) $row['id'], 'reason' => 'matched', 'path' => $path, 'views' => $viewsByPath[$path]];
        }
    }

    $result = ['matched' => $matched, 'checked' => $checked];
    if ($debug) {
        $result['debug'] = $debugRows;
    }

    return $result;
}

/** @return list<array{pageTitle: string, views: int}> */
function ce_analytics_fetch_top_pages(
    PDO $pdo,
    ?string $clientId,
    string $startDate,
    string $endDate,
    int $limit = 10,
): array {
    $context = ce_analytics_api_context($pdo, $clientId);
    if ($context === null) {
        return [];
    }

    $json = ce_analytics_run_report($context, [
        'dateRanges' => [
            ['startDate' => $startDate, 'endDate' => $endDate],
        ],
        'dimensions' => [
            ['name' => 'pageTitle'],
        ],
        'metrics' => [
            ['name' => 'screenPageViews'],
        ],
        'orderBys' => [
            [
                'metric' => ['metricName' => 'screenPageViews'],
                'desc' => true,
            ],
        ],
        'limit' => max(1, min($limit, 25)),
    ]);

    if ($json === null) {
        return [];
    }

    $rows = [];
    foreach ($json['rows'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = ce_analytics_dimension_value($row, 0);
        if ($title === '') {
            continue;
        }
        $rows[] = [
            'pageTitle' => mb_substr($title, 0, 120),
            'views' => ce_analytics_metric_int($row, 0),
        ];
    }

    return $rows;
}

/** @return list<array{source: string, medium: string, activeUsers: int, label: string}> */
function ce_analytics_fetch_source_medium(
    PDO $pdo,
    ?string $clientId,
    string $startDate,
    string $endDate,
    int $limit = 10,
): array {
    $context = ce_analytics_api_context($pdo, $clientId);
    if ($context === null) {
        return [];
    }

    $json = ce_analytics_run_report($context, [
        'dateRanges' => [
            ['startDate' => $startDate, 'endDate' => $endDate],
        ],
        'dimensions' => [
            ['name' => 'firstUserSource'],
            ['name' => 'firstUserMedium'],
        ],
        'metrics' => [
            ['name' => 'activeUsers'],
        ],
        'orderBys' => [
            [
                'metric' => ['metricName' => 'activeUsers'],
                'desc' => true,
            ],
        ],
        'limit' => max(1, min($limit, 25)),
    ]);

    if ($json === null) {
        return [];
    }

    $rows = [];
    foreach ($json['rows'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $source = ce_analytics_dimension_value($row, 0);
        $medium = ce_analytics_dimension_value($row, 1);
        $sourceLabel = $source !== '' ? $source : '(direct)';
        $mediumLabel = $medium !== '' ? $medium : '(none)';

        $rows[] = [
            'source' => $sourceLabel,
            'medium' => $mediumLabel,
            'activeUsers' => ce_analytics_metric_int($row, 0),
            'label' => $sourceLabel . ' / ' . $mediumLabel,
        ];
    }

    return $rows;
}

/**
 * @return list<array{weekNumber: int, isoYear: int, weekLabel: string, startDate: string, endDate: string}>
 */
function ce_iso_weeks_in_range(string $periodStart, string $periodEnd): array
{
    $tz = ce_ce_timezone();
    try {
        $start = new DateTimeImmutable($periodStart, $tz);
        $end = new DateTimeImmutable($periodEnd, $tz);
    } catch (Throwable) {
        return [];
    }

    if ($end < $start) {
        return [];
    }

    $weeks = [];
    $seen = [];
    $cursor = $start;

    while ($cursor <= $end) {
        $weekNumber = (int) $cursor->format('W');
        $isoYear = (int) $cursor->format('o');
        $key = $isoYear . '-' . $weekNumber;

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $range = ce_iso_week_range($weekNumber, $isoYear);
            $weekStart = $range['start'] > $start ? $range['start'] : $start;
            $weekEnd = $range['end'] < $end ? $range['end'] : $end;

            $weeks[] = [
                'weekNumber' => $weekNumber,
                'isoYear' => $isoYear,
                'weekLabel' => 'Week ' . $weekNumber,
                'startDate' => $weekStart->format('Y-m-d'),
                'endDate' => $weekEnd->format('Y-m-d'),
            ];
        }

        $cursor = $cursor->modify('+1 day');
    }

    return $weeks;
}

/**
 * @return list<array<string, mixed>>
 */
function ce_metrics_weekly_views(
    PDO $pdo,
    ?string $clientId,
    string $periodStart,
    string $periodEnd,
): array {
    $oauthConnected = ce_analytics_oauth_connected_for_client($pdo, $clientId);
    $rows = [];
    foreach (ce_iso_weeks_in_range($periodStart, $periodEnd) as $week) {
        $totals = ce_analytics_fetch_totals(
            $pdo,
            $clientId,
            $week['startDate'],
            $week['endDate'],
        );

        if ($oauthConnected) {
            $websiteViews = $totals['screenPageViews'];
            $websiteSource = 'ga4';
        } else {
            $websiteViews = $totals['screenPageViews'] > 0 ? $totals['screenPageViews'] : null;
            $websiteSource = $websiteViews !== null ? 'ga4' : null;
        }

        $rows[] = [
            'weekLabel' => $week['weekLabel'],
            'weekNumber' => $week['weekNumber'],
            'isoYear' => $week['isoYear'],
            'startDate' => $week['startDate'],
            'endDate' => $week['endDate'],
            'websiteViews' => $websiteViews,
            'sessions' => $oauthConnected ? $totals['sessions'] : null,
            'activeUsers' => $oauthConnected ? $totals['activeUsers'] : null,
            'websiteSource' => $websiteSource,
        ];
    }

    return $rows;
}

/** @return array<string, mixed> */
function ce_analytics_report_snapshot(
    PDO $pdo,
    ?string $clientId,
    string $periodStart,
    string $periodEnd,
): array {
    $hasAnalytics = ce_analytics_oauth_connected_for_client($pdo, $clientId);
    $totals = ce_analytics_fetch_totals($pdo, $clientId, $periodStart, $periodEnd);

    return [
        'hasAnalytics' => $hasAnalytics,
        'totals' => $totals,
        'topPages' => ce_analytics_fetch_top_pages($pdo, $clientId, $periodStart, $periodEnd),
        'sourceMedium' => ce_analytics_fetch_source_medium($pdo, $clientId, $periodStart, $periodEnd),
        'weekly' => ce_metrics_weekly_views($pdo, $clientId, $periodStart, $periodEnd),
    ];
}

/** @return array<string, mixed> */
function ce_metrics_current_week_snapshot(PDO $pdo, ?string $clientId): array
{
    $weekNumber = ce_week_start();
    $isoYear = ce_resolve_iso_week_year($weekNumber);
    $range = ce_iso_week_range($weekNumber, $isoYear);
    $startDate = $range['start']->format('Y-m-d');
    $endDate = $range['end']->format('Y-m-d');

    $oauthConnected = ce_analytics_oauth_connected_for_client($pdo, $clientId);
    $totals = ce_analytics_fetch_totals($pdo, $clientId, $startDate, $endDate);
    $fetched = ce_analytics_api_context($pdo, $clientId) !== null;

    $websiteViews = null;
    $websiteSource = null;
    if ($oauthConnected) {
        $websiteViews = $totals['screenPageViews'];
        $websiteSource = 'ga4';
    } elseif ($fetched) {
        $websiteViews = $totals['screenPageViews'];
        $websiteSource = 'ga4';
    }

    return [
        'weekLabel' => 'Week ' . $weekNumber,
        'weekNumber' => $weekNumber,
        'isoYear' => $isoYear,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'websiteViewsThisWeek' => $websiteViews,
        'websiteSource' => $websiteSource,
        'hasAnalytics' => $oauthConnected,
        'analyticsPropertyMissing' => $oauthConnected && !$fetched,
        'websiteAnalytics' => [
            'activeUsers' => $oauthConnected ? $totals['activeUsers'] : null,
            'newUsers' => $oauthConnected ? $totals['newUsers'] : null,
            'sessions' => $oauthConnected ? $totals['sessions'] : null,
            'screenPageViews' => $websiteViews,
        ],
    ];
}
