<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/scrape-repository.php';
require_once __DIR__ . '/account-repository.php';

/** @return array<string, float> */
function ce_tenant_cost_default_rates(): array
{
    return [
        'claudeInputPerMtokEur' => 2.75,
        'claudeOutputPerMtokEur' => 13.75,
        'n8nPerExecutionEur' => 0.002,
        'dataTrafficPerGbEur' => 0.09,
        'gbPerArticle' => 0.04,
        'eurUsd' => 0.92,
        /** Default Apify CU-schatting (EUR) — gelijk aan Sources-budget €0.05/CU. */
        'apifyPerComputeUnitEur' => 0.05,
        /** Minimum per scrape-run zonder units/kosten in scrape_runs. */
        'apifyMinRunEur' => 0.05,
    ];
}

/** @return array<string, float> */
function ce_tenant_cost_default_services(): array
{
    return [
        'hostingSharedEur' => 5.0,
        'yoastPremiumEur' => 8.25,
        'mailTransactionalEur' => 0.5,
        'linkedinApiEur' => 0.0,
        'wordpressMaintenanceEur' => 0.0,
    ];
}

/** Per workflow call — geschatte tokens (input / output). */
function ce_tenant_cost_workflow_tokens(): array
{
    return [
        'ce01' => ['in' => 1800, 'out' => 800, 'n8n' => 8],
        'ce02' => ['in' => 2500, 'out' => 1200, 'n8n' => 12],
        'ce01b' => ['in' => 900, 'out' => 300, 'n8n' => 4],
        'ce03' => ['in' => 20000, 'out' => 3500, 'n8n' => 18],
        'ce04' => ['in' => 3500, 'out' => 600, 'n8n' => 6],
        'ce07' => ['in' => 1800, 'out' => 800, 'n8n' => 8],
    ];
}

function ce_tenant_cost_month_bounds(string $month): ?array
{
    $month = trim($month);
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return null;
    }

    try {
        $start = new DateTimeImmutable($month . '-01 00:00:00', new DateTimeZone('Europe/Amsterdam'));
    } catch (Throwable) {
        return null;
    }

    $end = $start->modify('last day of this month')->setTime(23, 59, 59);

    return [
        'month' => $month,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    ];
}

/** Kolom/expressie voor “wanneer is dit artikel geschreven” — articles heeft geen created_at in productie. */
function ce_tenant_cost_article_activity_expr(PDO $pdo): string
{
    if (ce_table_has_column($pdo, 'articles', 'created_at')) {
        return 'a.created_at';
    }
    if (ce_table_has_column($pdo, 'articles', 'review_sent_at')) {
        return 'COALESCE(a.published_at, a.review_sent_at, s.updated_at)';
    }
    if (ce_table_has_column($pdo, 'articles', 'published_at')) {
        return 'COALESCE(a.published_at, s.updated_at)';
    }

    return 's.updated_at';
}

/** Apify usageTotalUsd → EUR (scrape_runs.cost_eur bevat historisch vaak USD). */
function ce_tenant_cost_apify_usd_to_eur(?float $usd): float
{
    if ($usd === null || $usd <= 0) {
        return 0.0;
    }
    $rates = ce_tenant_cost_default_rates();

    return round((float) $usd * (float) ($rates['eurUsd'] ?? 0.92), 4);
}

/**
 * @return array{scraperRuns: int, apifyUnits: float, apifyCostEur: float}
 */
function ce_tenant_cost_apify_usage(PDO $pdo, string $clientId, string $start, string $end): array
{
    $empty = ['scraperRuns' => 0, 'apifyUnits' => 0.0, 'apifyCostEur' => 0.0];
    ce_ensure_scrape_runs_schema($pdo);
    if (!ce_table_exists($pdo, 'scrape_runs') || !ce_table_has_column($pdo, 'scrape_runs', 'client_id')) {
        return $empty;
    }

    $rates = ce_tenant_cost_default_rates();
    $perUnitEur = (float) ($rates['apifyPerComputeUnitEur'] ?? 0.05);
    $minRunEur = (float) ($rates['apifyMinRunEur'] ?? 0.05);

    $stmt = $pdo->prepare(
        'SELECT units_used, cost_eur
         FROM scrape_runs
         WHERE client_id = :client_id
           AND created_at >= :start AND created_at <= :end'
    );
    $stmt->execute(['client_id' => $clientId, 'start' => $start, 'end' => $end]);

    $runs = 0;
    $units = 0.0;
    $costEur = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $runs++;
        $runUnits = (float) ($row['units_used'] ?? 0);
        if ($runUnits > 0) {
            $units += $runUnits;
        }

        $stored = $row['cost_eur'];
        if ($stored !== null && (float) $stored > 0) {
            $costEur += ce_tenant_cost_apify_usd_to_eur((float) $stored);
            continue;
        }
        if ($runUnits > 0) {
            $costEur += round($runUnits * $perUnitEur, 4);
            continue;
        }
        $costEur += $minRunEur;
    }

    return [
        'scraperRuns' => $runs,
        'apifyUnits' => round($units, 4),
        'apifyCostEur' => round($costEur, 4),
    ];
}

/** @return array<string, int|float> */
function ce_tenant_cost_usage(PDO $pdo, string $clientId, string $start, string $end): array
{
    $usage = [
        'articlesWritten' => 0,
        'articlesPublished' => 0,
        'themesGenerated' => 0,
        'themesSubmitted' => 0,
        'researchRuns' => 0,
        'reportsGenerated' => 0,
        'scraperRuns' => 0,
        'apifyUnits' => 0.0,
        'apifyCostEur' => 0.0,
        'postsPerWeek' => 2,
    ];

    if ($clientId === '') {
        return $usage;
    }

    $account = ce_fetch_account($pdo, $clientId);
    if (($account['postsPerWeek'] ?? null) !== null) {
        $usage['postsPerWeek'] = max(1, (int) $account['postsPerWeek']);
    }

    if (ce_table_exists($pdo, 'articles') && ce_table_exists($pdo, 'content_slots')) {
        $activityAt = ce_tenant_cost_article_activity_expr($pdo);
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM articles a
             INNER JOIN content_slots s ON s.id = a.slot_id
             WHERE s.client_id = :client_id
               AND {$activityAt} >= :start AND {$activityAt} <= :end"
        );
        $stmt->execute(['client_id' => $clientId, 'start' => $start, 'end' => $end]);
        $usage['articlesWritten'] = (int) $stmt->fetchColumn();

        if (ce_table_has_column($pdo, 'articles', 'published_at')) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM articles a
                 INNER JOIN content_slots s ON s.id = a.slot_id
                 WHERE s.client_id = :client_id
                   AND a.published_at IS NOT NULL
                   AND a.published_at >= :start AND a.published_at <= :end'
            );
            $stmt->execute(['client_id' => $clientId, 'start' => $start, 'end' => $end]);
            $usage['articlesPublished'] = (int) $stmt->fetchColumn();
        }
    }

    if (ce_table_exists($pdo, 'themes') && ce_table_has_column($pdo, 'themes', 'client_id')) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM themes
             WHERE client_id = :client_id
               AND created_at >= :start AND created_at <= :end'
        );
        $stmt->execute(['client_id' => $clientId, 'start' => $start, 'end' => $end]);
        $usage['themesGenerated'] = (int) $stmt->fetchColumn();

        if (ce_table_has_column($pdo, 'themes', 'submitted_at')) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM themes
                 WHERE client_id = :client_id
                   AND submitted_at IS NOT NULL
                   AND submitted_at >= :start AND submitted_at <= :end'
            );
            $stmt->execute(['client_id' => $clientId, 'start' => $start, 'end' => $end]);
            $usage['themesSubmitted'] = (int) $stmt->fetchColumn();
        }
    }

    if (ce_table_exists($pdo, 'research_items') && ce_table_has_column($pdo, 'research_items', 'client_id')) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT theme_id) FROM research_items
             WHERE client_id = :client_id
               AND theme_id IS NOT NULL
               AND created_at >= :start AND created_at <= :end'
        );
        $stmt->execute(['client_id' => $clientId, 'start' => $start, 'end' => $end]);
        $usage['researchRuns'] = (int) $stmt->fetchColumn();
    }

    if (ce_table_exists($pdo, 'performance_reports') && ce_table_has_column($pdo, 'performance_reports', 'client_id')) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM performance_reports
             WHERE client_id = :client_id
               AND generated_at >= :start AND generated_at <= :end'
        );
        $stmt->execute(['client_id' => $clientId, 'start' => $start, 'end' => $end]);
        $usage['reportsGenerated'] = (int) $stmt->fetchColumn();
    }

    ce_ensure_scrape_runs_schema($pdo);
    $apify = ce_tenant_cost_apify_usage($pdo, $clientId, $start, $end);
    $usage['scraperRuns'] = $apify['scraperRuns'];
    $usage['apifyUnits'] = $apify['apifyUnits'];
    $usage['apifyCostEur'] = $apify['apifyCostEur'];

    return $usage;
}

/** @param array<string, int|float> $usage */
function ce_tenant_cost_projected_usage(array $usage, int $postsPerWeek): array
{
    $articles = max(0, (int) round($postsPerWeek * 4.33));
    $rates = ce_tenant_cost_default_rates();
    $scraperRuns = $articles;
    $projectedApify = round($scraperRuns * (float) ($rates['apifyMinRunEur'] ?? 0.05), 4);

    return [
        'articlesWritten' => $articles,
        'articlesPublished' => $articles,
        'themesGenerated' => max($articles, (int) round($postsPerWeek * 4)),
        'themesSubmitted' => $articles,
        'researchRuns' => $articles,
        'reportsGenerated' => 1,
        'scraperRuns' => $scraperRuns,
        'apifyUnits' => 0.0,
        'apifyCostEur' => $projectedApify,
        'postsPerWeek' => $postsPerWeek,
    ];
}

/**
 * @param array<string, int|float> $usage
 * @param array<string, float> $rates
 * @param array<string, float> $servicesEnabled — key => monthly EUR when enabled
 * @return array<string, mixed>
 */
function ce_tenant_cost_calculate(array $usage, array $rates, array $servicesEnabled, bool $includeAiReview = false): array
{
    $tokens = ce_tenant_cost_workflow_tokens();
    $articles = max(0, (int) ($usage['articlesWritten'] ?? 0));
    $themesSubmitted = max($articles, (int) ($usage['themesSubmitted'] ?? $articles));
    $researchRuns = max($articles, (int) ($usage['researchRuns'] ?? $articles));
    $themesGenerated = max((int) ($usage['themesGenerated'] ?? 0), (int) round(($usage['postsPerWeek'] ?? 2) * 4));
    $reports = max(0, (int) ($usage['reportsGenerated'] ?? 0));

    $ce01Runs = max(1, (int) round($themesGenerated / max(1, (int) ($usage['postsPerWeek'] ?? 2))));
    $ce02Runs = $researchRuns > 0 ? $researchRuns : $articles;
    $ce01bRuns = $themesSubmitted > 0 ? $themesSubmitted : $articles;
    $ce03Runs = $articles;
    $ce04Runs = $includeAiReview ? $articles : 0;
    $ce07Runs = $reports > 0 ? $reports : 0;

    $claudeIn = 0;
    $claudeOut = 0;
    $n8nExecutions = 0;

    foreach ([
        [$ce01Runs, 'ce01'],
        [$ce02Runs, 'ce02'],
        [$ce01bRuns, 'ce01b'],
        [$ce03Runs, 'ce03'],
        [$ce04Runs, 'ce04'],
        [$ce07Runs, 'ce07'],
    ] as [$runs, $key]) {
        $runCount = max(0, (int) $runs);
        $claudeIn += $runCount * (int) $tokens[$key]['in'];
        $claudeOut += $runCount * (int) $tokens[$key]['out'];
        $n8nExecutions += $runCount * (int) $tokens[$key]['n8n'];
    }

    $claudeInCost = ($claudeIn / 1_000_000) * (float) ($rates['claudeInputPerMtokEur'] ?? 2.75);
    $claudeOutCost = ($claudeOut / 1_000_000) * (float) ($rates['claudeOutputPerMtokEur'] ?? 13.75);
    $claudeTotal = round($claudeInCost + $claudeOutCost, 4);

    $n8nTotal = round($n8nExecutions * (float) ($rates['n8nPerExecutionEur'] ?? 0.002), 4);

    $gb = $articles * (float) ($rates['gbPerArticle'] ?? 0.04);
    $dataTrafficTotal = round($gb * (float) ($rates['dataTrafficPerGbEur'] ?? 0.09), 4);

    $apifyTotal = round((float) ($usage['apifyCostEur'] ?? 0), 4);

    $otherLines = [];
    $otherTotal = 0.0;
    foreach ($servicesEnabled as $label => $amount) {
        $amount = round((float) $amount, 4);
        if ($amount <= 0) {
            continue;
        }
        $otherLines[] = ['id' => $label, 'label' => ce_tenant_cost_service_label($label), 'eur' => $amount];
        $otherTotal += $amount;
    }
    $otherTotal = round($otherTotal, 4);

    $grandTotal = round($claudeTotal + $n8nTotal + $dataTrafficTotal + $apifyTotal + $otherTotal, 4);

    return [
        'workflowRuns' => [
            'ce01' => $ce01Runs,
            'ce02' => $ce02Runs,
            'ce01b' => $ce01bRuns,
            'ce03' => $ce03Runs,
            'ce04' => $ce04Runs,
            'ce07' => $ce07Runs,
        ],
        'tokens' => [
            'input' => $claudeIn,
            'output' => $claudeOut,
        ],
        'n8nExecutions' => $n8nExecutions,
        'dataTrafficGb' => round($gb, 3),
        'lines' => [
            [
                'id' => 'claude',
                'label' => 'Claude (Anthropic)',
                'eur' => $claudeTotal,
                'detail' => sprintf(
                    '%s in / %s out tokens',
                    number_format($claudeIn, 0, ',', '.'),
                    number_format($claudeOut, 0, ',', '.')
                ),
                'source' => 'estimate',
            ],
            [
                'id' => 'n8n',
                'label' => 'n8n (executions)',
                'eur' => $n8nTotal,
                'detail' => number_format($n8nExecutions, 0, ',', '.') . ' executions',
                'source' => 'estimate',
            ],
            [
                'id' => 'data',
                'label' => 'Dataverkeer (hosting/CDN/API)',
                'eur' => $dataTrafficTotal,
                'detail' => number_format($gb, 2, ',', '.') . ' GB geschat',
                'source' => 'estimate',
            ],
            [
                'id' => 'apify',
                'label' => 'Apify (scraper)',
                'eur' => $apifyTotal,
                'detail' => (int) ($usage['scraperRuns'] ?? 0) . ' runs'
                    . ((float) ($usage['apifyUnits'] ?? 0) > 0
                        ? ' · ' . number_format((float) $usage['apifyUnits'], 2, ',', '.') . ' CU'
                        : ''),
                'source' => (int) ($usage['scraperRuns'] ?? 0) > 0 ? 'actual' : 'estimate',
            ],
            ...$otherLines,
        ],
        'totals' => [
            'claudeEur' => $claudeTotal,
            'n8nEur' => $n8nTotal,
            'dataTrafficEur' => $dataTrafficTotal,
            'apifyEur' => $apifyTotal,
            'otherEur' => $otherTotal,
            'grandEur' => $grandTotal,
            'perArticleEur' => $articles > 0 ? round($grandTotal / $articles, 4) : null,
        ],
    ];
}

function ce_tenant_cost_service_label(string $id): string
{
    return match ($id) {
        'hostingSharedEur' => 'Hosting (gedeeld)',
        'yoastPremiumEur' => 'Yoast SEO Premium',
        'mailTransactionalEur' => 'Transactionele mail',
        'linkedinApiEur' => 'LinkedIn API',
        'wordpressMaintenanceEur' => 'WordPress onderhoud',
        default => $id,
    };
}

/** @param array<string, mixed> $report */
function ce_tenant_cost_report_grand_eur(array $report): float
{
    return (float) ($report['calculation']['totals']['grandEur'] ?? 0);
}

/** @return array<string, mixed> */
function ce_tenant_cost_report(PDO $pdo, ?string $clientId, string $month, ?int $postsPerWeekOverride = null, bool $useProjected = false): array
{
    $bounds = ce_tenant_cost_month_bounds($month);
    if ($bounds === null) {
        $bounds = ce_tenant_cost_month_bounds((new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('Y-m'));
    }

    $clientId = ce_resolve_client_id($pdo, $clientId);
    if ($clientId === null) {
        return ['error' => 'Geen tenant geselecteerd'];
    }

    $account = ce_fetch_account($pdo, $clientId);
    $actualUsage = ce_tenant_cost_usage($pdo, $clientId, $bounds['start'], $bounds['end']);

    $postsPerWeek = $postsPerWeekOverride ?? (int) ($actualUsage['postsPerWeek'] ?? 2);
    $postsPerWeek = max(1, min(20, $postsPerWeek));

    $projectedUsage = ce_tenant_cost_projected_usage($actualUsage, $postsPerWeek);
    $usage = $useProjected || (int) $actualUsage['articlesWritten'] === 0
        ? array_merge($actualUsage, $projectedUsage, ['postsPerWeek' => $postsPerWeek])
        : $actualUsage;

    $rates = ce_tenant_cost_default_rates();
    $services = ce_tenant_cost_default_services();
    $calculation = ce_tenant_cost_calculate($usage, $rates, [
        'hostingSharedEur' => $services['hostingSharedEur'],
        'mailTransactionalEur' => $services['mailTransactionalEur'],
    ], false);

    return [
        'clientId' => $clientId,
        'company' => (string) ($account['company'] ?? ''),
        'month' => $bounds['month'],
        'mode' => $useProjected || (int) $actualUsage['articlesWritten'] === 0 ? 'projected' : 'actual',
        'postsPerWeek' => $postsPerWeek,
        'usage' => [
            'actual' => $actualUsage,
            'effective' => $usage,
            'projected' => $projectedUsage,
        ],
        'rates' => $rates,
        'services' => ce_tenant_cost_service_catalog(),
        'calculation' => $calculation,
    ];
}

/** @return list<array{id: string, label: string, defaultEur: float, description: string}> */
function ce_tenant_cost_service_catalog(): array
{
    $defaults = ce_tenant_cost_default_services();

    return [
        [
            'id' => 'hostingSharedEur',
            'label' => 'Hosting (gedeeld)',
            'defaultEur' => $defaults['hostingSharedEur'],
            'description' => 'Amortisatie server/FTP — per tenant',
        ],
        [
            'id' => 'yoastPremiumEur',
            'label' => 'Yoast SEO Premium',
            'defaultEur' => $defaults['yoastPremiumEur'],
            'description' => 'WordPress-plugin (~€99/jaar)',
        ],
        [
            'id' => 'mailTransactionalEur',
            'label' => 'Transactionele mail',
            'defaultEur' => $defaults['mailTransactionalEur'],
            'description' => 'Human review-mails (CE-04.1bis)',
        ],
        [
            'id' => 'linkedinApiEur',
            'label' => 'LinkedIn API',
            'defaultEur' => $defaults['linkedinApiEur'],
            'description' => 'Community Management — meestal €0 bij normaal gebruik',
        ],
        [
            'id' => 'wordpressMaintenanceEur',
            'label' => 'WordPress onderhoud',
            'defaultEur' => $defaults['wordpressMaintenanceEur'],
            'description' => 'Updates, monitoring, backups',
        ],
    ];
}
