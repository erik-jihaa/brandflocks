<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/publish-settings-repository.php';
require_once __DIR__ . '/event-log-repository.php';
require_once __DIR__ . '/tenant-cost-repository.php';
require_once __DIR__ . '/client-images-repository.php';
require_once __DIR__ . '/linkedin-oauth-helper.php';
require_once __DIR__ . '/tenant-activity-repository.php';
require_once __DIR__ . '/support-service-repository.php';
require_once __DIR__ . '/event-log-suggestions.php';
require_once __DIR__ . '/integrations-status-repository.php';

/** CMS publish target(s) — WordPress, Joomla, statische HTML. */
function ce_platform_cms_diagnostics(PDO $pdo, string $clientId, array $clientRow): array
{
    require_once __DIR__ . '/joomla-publish-repository.php';

    $platforms = [];
    $selectedCms = ce_client_website_cms_from_row($clientRow);

    $wpSiteUrl = (string) ($clientRow['wordpress_site_url'] ?? '');
    $wpConfigured = ce_client_wordpress_configured($pdo, $clientId);
    if ($selectedCms === 'wordpress' || ($selectedCms === '' && ($wpSiteUrl !== '' || $wpConfigured))) {
        $platforms[] = [
            'type' => 'wordpress',
            'label' => 'WordPress',
            'configured' => $wpConfigured,
            'siteUrl' => $wpSiteUrl,
        ];
    }

    $joomlaSiteUrl = (string) ($clientRow['joomla_site_url'] ?? '');
    $joomlaConfigured = ce_client_joomla_configured($pdo, $clientId);
    if ($selectedCms === 'joomla' || ($selectedCms === '' && ($joomlaSiteUrl !== '' || $joomlaConfigured))) {
        $platforms[] = [
            'type' => 'joomla',
            'label' => 'Joomla',
            'configured' => $joomlaConfigured,
            'siteUrl' => $joomlaSiteUrl,
        ];
    }

    $primary = $platforms[0] ?? null;

    return [
        'configured' => $primary !== null && ($primary['configured'] ?? false),
        'siteUrl' => (string) ($primary['siteUrl'] ?? ''),
        'primaryType' => $primary['type'] ?? null,
        'primaryLabel' => $primary['label'] ?? null,
        'platforms' => $platforms,
    ];
}

function ce_platform_cms_configured(PDO $pdo, string $clientId, ?array $clientRow = null): bool
{
    if ($clientRow === null) {
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $clientRow = $stmt->fetch();
        if (!$clientRow) {
            return false;
        }
    }

    return ce_platform_cms_diagnostics($pdo, $clientId, $clientRow)['configured'];
}

/** @return list<array<string, mixed>> */
function ce_platform_fetch_tenants_overview(PDO $pdo): array
{
    if (!ce_table_exists($pdo, 'clients')) {
        return [];
    }

    $rows = $pdo->query('SELECT * FROM clients ORDER BY name ASC')->fetchAll() ?: [];
    $monthStart = (new DateTimeImmutable('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
    $errorsSince = (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
    $activitySummaries = ce_tenant_activity_summaries($pdo);

    $result = [];
    foreach ($rows as $row) {
        $clientId = (string) ($row['id'] ?? '');
        if ($clientId === '') {
            continue;
        }

        $errors7d = 0;
        if (ce_table_exists($pdo, 'event_log')) {
            $eStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM event_log
                 WHERE client_id = :client_id AND severity = 'error' AND created_at >= :since"
            );
            $eStmt->execute(['client_id' => $clientId, 'since' => $errorsSince]);
            $errors7d = (int) $eStmt->fetchColumn();
        }

        $articlesMonth = 0;
        if (ce_table_exists($pdo, 'articles') && ce_table_exists($pdo, 'content_slots')) {
            $activityExpr = ce_tenant_cost_article_activity_expr($pdo);
            $aStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM articles a
                 INNER JOIN content_slots s ON s.id = a.slot_id
                 WHERE s.client_id = :client_id AND {$activityExpr} >= :since"
            );
            $aStmt->execute(['client_id' => $clientId, 'since' => $monthStart]);
            $articlesMonth = (int) $aStmt->fetchColumn();
        }

        $galleryCount = count(ce_fetch_client_images($pdo, $clientId));
        $cms = ce_platform_cms_diagnostics($pdo, $clientId, $row);
        $cmsConfigured = (bool) ($cms['configured'] ?? false);
        $publishMode = ce_normalize_publish_mode($row['publish_mode'] ?? null);
        $signals = ce_platform_pipeline_signals($pdo, $clientId);
        $ticketCounts = ce_support_open_ticket_counts($pdo, $clientId);

        $postsPerWeek = max(1, (int) ($row['posts_per_week'] ?? 1));
        $weeksInMonth = max(1, (int) date('j') / 7);
        $expectedArticles = (int) ceil($postsPerWeek * $weeksInMonth);

        $status = 'green';
        if ($errors7d > 0 || $signals !== []) {
            $status = 'red';
        } elseif ((string) ($cms['siteUrl'] ?? '') !== '' && !$cmsConfigured) {
            $status = 'orange';
        }

        $activity = $activitySummaries[$clientId] ?? [
            'lastLogin' => null,
            'lastPublish' => null,
            'daysSinceLastLogin' => null,
            'daysSinceLastPublish' => null,
        ];
        $daysSinceLogin = $activity['daysSinceLastLogin'];
        if ($status === 'green' && $daysSinceLogin !== null && $daysSinceLogin > 14) {
            $status = 'orange';
        }

        $costEur = null;
        $n8nExecutionsThisMonth = null;
        try {
            $month = date('Y-m');
            $costReport = ce_tenant_cost_report($pdo, $clientId, $month);
            $costEur = ce_tenant_cost_report_grand_eur($costReport);
            $n8nExecutionsThisMonth = (int) ($costReport['calculation']['n8nExecutions'] ?? 0);
        } catch (Throwable) {
            $costEur = null;
            $n8nExecutionsThisMonth = null;
        }

        $latestError = $errors7d > 0
            ? ce_platform_latest_tenant_error($pdo, $clientId, $errorsSince)
            : null;

        $integrationIssues = ce_platform_integration_issues_light($pdo, $clientId, $row);
        $mailboxSourcesOk = ce_platform_mailbox_sources_ok($pdo, $clientId);

        $result[] = [
            'clientId' => $clientId,
            'company' => (string) ($row['name'] ?? ''),
            'contactEmail' => (string) ($row['contact_email'] ?? ''),
            'publishMode' => $publishMode,
            'active' => !empty($row['active']),
            'status' => $status,
            'errors7d' => $errors7d,
            'latestError' => $latestError,
            'articlesThisMonth' => $articlesMonth,
            'expectedArticlesMonth' => $expectedArticles,
            'galleryImageCount' => $galleryCount,
            'cmsConfigured' => $cmsConfigured,
            'cmsSiteUrl' => (string) ($cms['siteUrl'] ?? ''),
            'cmsPrimaryType' => $cms['primaryType'],
            'cmsPrimaryLabel' => $cms['primaryLabel'],
            'wordpressConfigured' => $cmsConfigured,
            'wordpressSiteUrl' => (string) ($cms['siteUrl'] ?? ''),
            'costEurThisMonth' => $costEur,
            'n8nExecutionsThisMonth' => $n8nExecutionsThisMonth,
            'pipelineSignals' => $signals,
            'openTicketCount' => $ticketCounts['open'],
            'supportEmailTicketCount' => $ticketCounts['supportEmail'],
            'lastLogin' => $activity['lastLogin'],
            'lastPublish' => $activity['lastPublish'],
            'daysSinceLastLogin' => $activity['daysSinceLastLogin'],
            'daysSinceLastPublish' => $activity['daysSinceLastPublish'],
            'integrationIssues' => $integrationIssues,
            'mailboxSourcesOk' => $mailboxSourcesOk,
        ];
    }

    return $result;
}

/** @return array{event: string, message: string, suggestion: string, createdAt: string}|null */
function ce_platform_latest_tenant_error(PDO $pdo, string $clientId, string $since): ?array
{
    if (!ce_table_exists($pdo, 'event_log')) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, client_id, severity, category, event, message, context, created_at
         FROM event_log
         WHERE client_id = :client_id
           AND severity = 'error'
           AND created_at >= :since
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $stmt->execute(['client_id' => $clientId, 'since' => $since]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $context = $row['context'] ?? null;
    $row['context'] = is_string($context) ? json_decode($context, true) : null;
    $enriched = ce_event_log_enrich([$row])[0] ?? $row;

    return [
        'event' => (string) ($enriched['event'] ?? ''),
        'message' => (string) ($enriched['message'] ?? ''),
        'suggestion' => (string) ($enriched['suggestion'] ?? ''),
        'createdAt' => (string) ($enriched['created_at'] ?? ''),
    ];
}

/** @return list<string> */
function ce_platform_pipeline_signals(PDO $pdo, string $clientId): array
{
    $signals = [];

    if (!ce_table_exists($pdo, 'content_slots') || !ce_table_exists($pdo, 'articles')) {
        return $signals;
    }

    $stuckStmt = $pdo->prepare(
        <<<'SQL'
        SELECT COUNT(*) FROM content_slots s
        LEFT JOIN articles a ON a.slot_id = s.id
        WHERE s.client_id = :client_id
          AND s.status IN ('draft', 'themed', 'research')
          AND s.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
          AND (a.id IS NULL OR TRIM(COALESCE(a.content_ai, a.content_human, '')) = '')
        SQL
    );
    $stuckStmt->execute(['client_id' => $clientId]);
    if ((int) $stuckStmt->fetchColumn() > 0) {
        $signals[] = 'stuck_draft_slots';
    }

    if (ce_table_has_column($pdo, 'articles', 'review_sent_at')) {
        $pendingStmt = $pdo->prepare(
            <<<'SQL'
            SELECT COUNT(*) FROM articles a
            INNER JOIN content_slots s ON s.id = a.slot_id
            WHERE s.client_id = :client_id
              AND a.status = 'pending_review'
              AND (a.review_sent_at IS NULL OR TRIM(a.review_sent_at) = '')
            SQL
        );
        $pendingStmt->execute(['client_id' => $clientId]);
        if ((int) $pendingStmt->fetchColumn() > 0) {
            $signals[] = 'pending_zonder_review_mail';
        }
    }

    return $signals;
}

/** @return array<string, mixed> */
function ce_platform_fetch_tenant_detail(PDO $pdo, string $clientId): array
{
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Tenant niet gevonden');
    }

    require_once __DIR__ . '/account-repository.php';
    $overview = null;
    foreach (ce_platform_fetch_tenants_overview($pdo) as $tenant) {
        if (($tenant['clientId'] ?? '') === $clientId) {
            $overview = $tenant;
            break;
        }
    }

    $sourcesCount = 0;
    if (ce_table_exists($pdo, 'sources')) {
        $s = $pdo->prepare('SELECT COUNT(*) FROM sources WHERE client_id = :id AND COALESCE(active, 1) = 1');
        $s->execute(['id' => $clientId]);
        $sourcesCount = (int) $s->fetchColumn();
    }

    $linkedin = ce_linkedin_oauth_status_for_client($pdo, $clientId);
    $linkedinTokenValid = $linkedin['connected']
        ? ce_linkedin_oauth_valid_access_token($pdo, $clientId) !== null
        : false;

    $cms = ce_platform_cms_diagnostics($pdo, $clientId, $row);

    $objectivesCount = 0;
    if (ce_table_exists($pdo, 'objectives')) {
        $oStmt = $pdo->prepare('SELECT COUNT(*) FROM objectives WHERE client_id = :id');
        $oStmt->execute(['id' => $clientId]);
        $objectivesCount = (int) $oStmt->fetchColumn();
    }

    $events = ce_event_log_enrich(ce_fetch_events($pdo, [
        'clientId' => $clientId,
        'days' => 30,
        'limit' => 100,
    ]));

    return [
        'ok' => true,
        'tenant' => $overview,
        'account' => [
            'clientId' => $clientId,
            'company' => (string) ($row['name'] ?? ''),
            'contactEmail' => (string) ($row['contact_email'] ?? ''),
            'publishMode' => ce_normalize_publish_mode($row['publish_mode'] ?? null),
            'postsPerWeek' => (int) ($row['posts_per_week'] ?? 0),
            'planningHorizonWeeks' => (int) ($row['planning_horizon_weeks'] ?? 0),
            'active' => !empty($row['active']),
        ],
        'diagnostics' => [
            'cms' => $cms,
            'cmsConfigured' => (bool) ($cms['configured'] ?? false),
            'cmsSiteUrl' => (string) ($cms['siteUrl'] ?? ''),
            'cmsPrimaryType' => $cms['primaryType'],
            'cmsPrimaryLabel' => $cms['primaryLabel'],
            'wordpressConfigured' => (bool) ($cms['configured'] ?? false),
            'wordpressSiteUrl' => (string) ($cms['siteUrl'] ?? ''),
            'hasWordpressAppPassword' => trim((string) ($row['wordpress_user'] ?? '')) !== ''
                && trim((string) ($row['wordpress_app_password'] ?? '')) !== '',
            'linkedinConnected' => $linkedin['connected'] ?? false,
            'linkedinConfigured' => ce_linkedin_oauth_configured(),
            'linkedinTokenValid' => $linkedinTokenValid,
            'linkedinMemberName' => $linkedin['memberName'] ?? null,
            'activeSourcesCount' => $sourcesCount,
            'galleryImageCount' => count(ce_fetch_client_images($pdo, $clientId)),
            'styleGuideReady' => ce_client_styleguide_ready($row, $pdo),
            'objectivesCount' => $objectivesCount,
        ],
        'recentEvents' => $events,
        'service' => ce_support_service_summary($pdo, $clientId),
    ];
}

/** @return array<string, mixed> */
function ce_platform_fetch_status(PDO $pdo): array
{
    $health = ['ok' => false, 'environment' => ce_env()];
    try {
        $pdo->query('SELECT 1');
        $health['ok'] = true;
        $health['db'] = 'connected';
    } catch (Throwable $e) {
        $health['db'] = 'error';
        $health['message'] = $e->getMessage();
    }

    $migration = null;
    try {
        $migration = $pdo->query(
            'SELECT filename, applied_at FROM schema_migrations ORDER BY applied_at DESC, filename DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable) {
        $migration = null;
    }

    $errors24h = 0;
    if (ce_table_exists($pdo, 'event_log')) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM event_log WHERE severity = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        $stmt->execute();
        $errors24h = (int) $stmt->fetchColumn();
    }

    $tenantCount = 0;
    if (ce_table_exists($pdo, 'clients')) {
        $tenantCount = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    }

    return [
        'ok' => true,
        'environment' => ce_env(),
        'health' => $health,
        'lastMigration' => $migration,
        'errors24h' => $errors24h,
        'tenantCount' => $tenantCount,
        'releaseTag' => trim((string) (getenv('CE_RELEASE_TAG') ?: '')) ?: null,
    ];
}

/** @return array<string, mixed> */
function ce_platform_fetch_costs(PDO $pdo, string $month): array
{
    $month = ce_platform_clamp_month($month);
    $bounds = ce_tenant_cost_month_bounds($month);
    if ($bounds === null) {
        throw new InvalidArgumentException('month moet YYYY-MM zijn');
    }

    if (!ce_table_exists($pdo, 'clients')) {
        return [
            'ok' => true,
            'month' => $month,
            'previousMonth' => null,
            'previousGrandTotalEur' => 0,
            'grandTotalEur' => 0,
            'breakdown' => [],
            'tenants' => [],
        ];
    }

    $prevBounds = ce_tenant_cost_month_bounds(
        (new DateTimeImmutable($bounds['month'] . '-01'))->modify('-1 month')->format('Y-m'),
    );
    $prevMonth = $prevBounds['month'] ?? null;

    $rows = $pdo->query('SELECT id, name FROM clients ORDER BY name ASC')->fetchAll() ?: [];
    $tenants = [];
    $grandTotal = 0.0;
    $prevGrandTotal = 0.0;
    $totalN8nExecutions = 0;
    /** @var array<string, array{id: string, label: string, eur: float}> $aggregateLines */
    $aggregateLines = [];

    foreach ($rows as $row) {
        $clientId = (string) ($row['id'] ?? '');
        if ($clientId === '') {
            continue;
        }
        try {
            $report = ce_tenant_cost_report($pdo, $clientId, $month);
            $total = ce_tenant_cost_report_grand_eur($report);
            $grandTotal += $total;

            $tenantLines = [];
            foreach ($report['calculation']['lines'] ?? [] as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $id = (string) ($line['id'] ?? '');
                $eur = (float) ($line['eur'] ?? 0);
                if ($id === '' || $eur <= 0) {
                    continue;
                }
                if (!isset($aggregateLines[$id])) {
                    $aggregateLines[$id] = [
                        'id' => $id,
                        'label' => (string) ($line['label'] ?? $id),
                        'eur' => 0.0,
                    ];
                }
                $aggregateLines[$id]['eur'] += $eur;
                $tenantLines[] = [
                    'id' => $id,
                    'label' => (string) ($line['label'] ?? $id),
                    'eur' => round($eur, 2),
                ];
            }

            $actual = is_array($report['usage']['actual'] ?? null) ? $report['usage']['actual'] : [];
            $n8nExecutions = (int) ($report['calculation']['n8nExecutions'] ?? 0);
            $totalN8nExecutions += $n8nExecutions;

            if ($prevMonth !== null) {
                try {
                    $prevReport = ce_tenant_cost_report($pdo, $clientId, $prevMonth);
                    $prevGrandTotal += ce_tenant_cost_report_grand_eur($prevReport);
                } catch (Throwable) {
                    // ignore per-tenant prev failures
                }
            }

            $tenants[] = [
                'clientId' => $clientId,
                'company' => (string) ($row['name'] ?? ''),
                'totalEur' => round($total, 2),
                'lines' => $tenantLines,
                'usage' => [
                    'articlesWritten' => (int) ($actual['articlesWritten'] ?? 0),
                    'articlesPublished' => (int) ($actual['articlesPublished'] ?? 0),
                    'scraperRuns' => (int) ($actual['scraperRuns'] ?? 0),
                    'apifyUnits' => round((float) ($actual['apifyUnits'] ?? 0), 2),
                    'apifyCostEur' => round((float) ($actual['apifyCostEur'] ?? 0), 2),
                    'n8nExecutions' => $n8nExecutions,
                ],
            ];
        } catch (Throwable) {
            $tenants[] = [
                'clientId' => $clientId,
                'company' => (string) ($row['name'] ?? ''),
                'totalEur' => null,
                'lines' => [],
                'usage' => null,
            ];
        }
    }

    usort($tenants, static fn ($a, $b) => ((float) ($b['totalEur'] ?? 0)) <=> ((float) ($a['totalEur'] ?? 0)));

    $breakdown = array_values(array_map(static function (array $line): array {
        return [
            'id' => $line['id'],
            'label' => $line['label'],
            'eur' => round((float) $line['eur'], 2),
        ];
    }, $aggregateLines));
    usort($breakdown, static fn ($a, $b) => ($b['eur'] <=> $a['eur']));

    return [
        'ok' => true,
        'month' => $month,
        'previousMonth' => $prevMonth,
        'previousGrandTotalEur' => round($prevGrandTotal, 2),
        'grandTotalEur' => round($grandTotal, 2),
        'totalN8nExecutions' => $totalN8nExecutions,
        'breakdown' => $breakdown,
        'tenants' => $tenants,
        'trend' => ce_platform_costs_trend($pdo, $month),
    ];
}

function ce_platform_current_month(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('Y-m');
}

function ce_platform_clamp_month(string $month): string
{
    $month = trim($month);
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return ce_platform_current_month();
    }

    $current = ce_platform_current_month();
    if ($month > $current) {
        return $current;
    }

    return $month;
}

function ce_platform_month_label(string $month): string
{
    $names = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)) {
        return $month;
    }

    $idx = max(0, min(11, (int) $matches[2] - 1));

    return $names[$idx] . ' ' . $matches[1];
}

function ce_platform_grand_total_for_month(PDO $pdo, string $month): float
{
    if (!ce_table_exists($pdo, 'clients')) {
        return 0.0;
    }

    $total = 0.0;
    $rows = $pdo->query('SELECT id FROM clients')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        $clientId = (string) ($row['id'] ?? '');
        if ($clientId === '') {
            continue;
        }
        try {
            $report = ce_tenant_cost_report($pdo, $clientId, $month);
            $total += ce_tenant_cost_report_grand_eur($report);
        } catch (Throwable) {
            // ignore per-tenant failures
        }
    }

    return round($total, 2);
}

/** @return array<string, mixed> */
function ce_platform_costs_trend(PDO $pdo, string $endMonth, int $monthsSpan = 8): array
{
    $monthsSpan = max(2, min(12, $monthsSpan));
    $endMonth = ce_platform_clamp_month($endMonth);

    try {
        $end = new DateTimeImmutable($endMonth . '-01', new DateTimeZone('Europe/Amsterdam'));
    } catch (Throwable) {
        $end = new DateTimeImmutable('first day of this month', new DateTimeZone('Europe/Amsterdam'));
    }

    $monthlyTotals = [];
    $monthLabels = [];
    for ($i = $monthsSpan - 1; $i >= 0; $i--) {
        $month = $end->modify("-{$i} months")->format('Y-m');
        $monthLabels[] = ce_platform_month_label($month);
        $monthlyTotals[] = ce_platform_grand_total_for_month($pdo, $month);
    }

    $cumulative = [];
    $running = 0.0;
    foreach ($monthlyTotals as $value) {
        $running += (float) $value;
        $cumulative[] = round($running, 2);
    }

    $nonZeroMonths = count(array_filter($monthlyTotals, static fn (float $v): bool => $v > 0));

    return [
        'monthsSpan' => $monthsSpan,
        'monthlyData' => $cumulative,
        'monthLabels' => $monthLabels,
        'monthlyTotals' => $monthlyTotals,
        'totalCost' => $cumulative !== [] ? (float) end($cumulative) : 0.0,
        'hasEnoughData' => $nonZeroMonths >= 2,
        'lastMonthTotal' => $monthlyTotals !== [] ? (float) end($monthlyTotals) : 0.0,
    ];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_platform_create_tenant(PDO $pdo, array $body, ?string $platformUserId): array
{
    require_once __DIR__ . '/account-repository.php';

    $result = ce_create_client($pdo, $body);
    $clientId = trim((string) ($result['client']['clientId'] ?? ''));
    $company = trim((string) ($result['client']['company'] ?? ''));

    ce_log_event($pdo, 'info', 'platform', 'tenant_created', 'Tenant aangemaakt via platform', [
        'clientId' => $clientId,
        'platformUserId' => $platformUserId ?? '',
        'company' => $company,
    ]);

    return $result;
}

/**
 * Platform tenant-update. De HBA-laag staat voor iedere tenant aan; er zijn momenteel
 * geen via het platform bewerkbare velden meer — geeft het actuele tenant-detail terug.
 *
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function ce_platform_update_tenant(PDO $pdo, string $clientId, array $body, ?string $platformUserId): array
{
    return ce_platform_fetch_tenant_detail($pdo, $clientId);
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_platform_delete_tenant(PDO $pdo, string $clientId, array $body, ?string $platformUserId): array
{
    require_once __DIR__ . '/content-reset.php';

    $confirmCompanyName = trim((string) ($body['confirmCompanyName'] ?? $body['company'] ?? ''));
    $result = ce_delete_client($pdo, $clientId, $confirmCompanyName);

    ce_log_event($pdo, 'warn', 'platform', 'tenant_deleted', 'Tenant verwijderd via platform', [
        'clientId' => $clientId,
        'platformUserId' => $platformUserId ?? '',
        'company' => (string) ($result['company'] ?? ''),
    ]);

    return $result;
}
