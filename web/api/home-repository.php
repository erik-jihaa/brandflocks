<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/publish-repository.php';
require_once __DIR__ . '/report-repository.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/metrics-repository.php';

/** Zelfde drempel als `HBA_SCORED_POSTS_FOR_OVERVIEW` in de frontend. */
const CE_HBA_SCORED_POSTS_FOR_OVERVIEW = 10;

const CE_POSITIONERING_TARGET_PCT = 30;

function ce_home_pipeline_status(bool $ready, bool $attention): string
{
    if ($attention) {
        return 'attention';
    }

    return $ready ? 'ready' : 'empty';
}

/** Herken podcast-RSS aan titel/URL (tab URL/RSS — geen apart brontype). */
function ce_source_looks_like_podcast(array $row): bool
{
    $haystack = mb_strtolower(implode(' ', array_filter([
        (string) ($row['title'] ?? ''),
        (string) ($row['url'] ?? ''),
        (string) ($row['description'] ?? ''),
    ])));

    if ($haystack === '') {
        return false;
    }

    if (str_contains($haystack, 'podcast') || str_contains($haystack, 'podcastfeed')) {
        return true;
    }

    foreach ([
        'feeds.simplecast.com',
        'anchor.fm',
        'podbean.com',
        'buzzsprout.com',
        'libsyn.com',
        'omny.fm',
        'megaphone.fm',
        'transistor.fm',
        'castos.com',
        'podigee.io',
        'acast.com',
        'feed.pod.co',
        'rss.com/podcasts',
        'spotify.com/show',
        'pca.st',
        'captivate.fm',
    ] as $needle) {
        if (str_contains($haystack, $needle)) {
            return true;
        }
    }

    return false;
}

function ce_client_has_podcast_source(PDO $pdo, ?string $clientId): bool
{
    if ($clientId === null || $clientId === '' || !ce_table_exists($pdo, 'sources')) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT title, url, description FROM sources WHERE client_id = :id AND category = 'url_rss'"
    );
    $stmt->execute(['id' => $clientId]);
    foreach ($stmt->fetchAll() ?: [] as $row) {
        if (ce_source_looks_like_podcast($row)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{id: string, label: string, reason: string, path: string, blocking: bool}
 */
function ce_home_todo_item(string $id, string $label, string $reason, string $path, bool $blocking): array
{
    return [
        'id' => $id,
        'label' => $label,
        'reason' => $reason,
        'path' => $path,
        'blocking' => $blocking,
    ];
}

/**
 * @param array<string, mixed> $ctx
 * @return list<array{id: string, label: string, reason: string, path: string, blocking: bool}>
 */
function ce_build_home_todos(array $ctx): array
{
    $todos = [];

    if (empty($ctx['strategyReady'])) {
        $todos[] = ce_home_todo_item(
            'strategy',
            'Merkarchitectuur invullen',
            'Merkidentiteit en verhaaldriehoek vastleggen',
            '/brandarchitecture',
            true,
        );
    }

    if ((int) ($ctx['sourcesCount'] ?? 0) === 0) {
        $todos[] = ce_home_todo_item(
            'sources',
            'Bronnen toevoegen',
            'Nog geen bronnen — start met je eigen website',
            '/sources',
            true,
        );
    } elseif (empty($ctx['hasCoreSource'])) {
        $todos[] = ce_home_todo_item(
            'core-source',
            'Voeg een kern-bron toe',
            'Zonder eigen website of documenten mist de tool jullie perspectief',
            '/sources/new/url-rss',
            false,
        );
    } elseif ((int) ($ctx['themesProposed'] ?? 0) === 0 && (int) ($ctx['agendaSlots'] ?? 0) === 0) {
        $todos[] = ce_home_todo_item(
            'theme-start',
            'Thema\'s starten',
            'Genereer je eerste thema-voorstellen',
            '/theme',
            true,
        );
    }

    if ((int) ($ctx['themesProposed'] ?? 0) > 0) {
        $count = (int) $ctx['themesProposed'];
        $todos[] = ce_home_todo_item(
            'theme',
            'Thema\'s beoordelen',
            "$count thema(s) wachten op je beoordeling",
            '/theme',
            true,
        );
    }

    $researchSubmitSlotId = trim((string) ($ctx['researchSubmitSlotId'] ?? ''));
    $researchSubmitTitle = trim((string) ($ctx['researchSubmitTitle'] ?? ''));
    if ($researchSubmitSlotId !== '') {
        $title = $researchSubmitTitle !== '' ? $researchSubmitTitle : 'Artikel';
        $todos[] = ce_home_todo_item(
            'research-submit',
            "Research indienen — $title",
            'Indienen start het schrijven',
            '/research/' . $researchSubmitSlotId,
            true,
        );
    }

    if ((int) ($ctx['researchOpen'] ?? 0) > 0) {
        $count = (int) $ctx['researchOpen'];
        $todos[] = ce_home_todo_item(
            'research-browse',
            "Research doorkijken — $count items",
            'kan later',
            '/research',
            false,
        );
    }

    if ((int) ($ctx['feedbackCount'] ?? 0) > 0 && !empty($ctx['nextFeedbackSlotId'])) {
        $todos[] = ce_home_todo_item(
            'feedback',
            'Feedback verwerken',
            (string) ($ctx['nextFeedbackTitle'] ?? 'Artikel'),
            '/article/' . (string) $ctx['nextFeedbackSlotId'],
            true,
        );
    }

    if ((int) ($ctx['draftCount'] ?? 0) > 0 && !empty($ctx['nextDraftSlotId'])) {
        $todos[] = ce_home_todo_item(
            'draft',
            'Artikel afmaken',
            (string) ($ctx['nextDraftTitle'] ?? 'Artikel'),
            '/article/' . (string) $ctx['nextDraftSlotId'],
            true,
        );
    }

    if ((int) ($ctx['pendingCount'] ?? 0) > 0) {
        $count = (int) $ctx['pendingCount'];
        $todos[] = ce_home_todo_item(
            'pending',
            'Goedkeuringen afhandelen',
            "$count artikel(s) wachten op goedkeuring",
            '/agenda',
            true,
        );
    }

    if ((int) ($ctx['approvedCount'] ?? 0) > 0) {
        $count = (int) $ctx['approvedCount'];
        $todos[] = ce_home_todo_item(
            'publish',
            'Artikelen plaatsen',
            "$count goedgekeurd — klaar om te publiceren",
            '/agenda',
            true,
        );
    }

    usort($todos, static function (array $a, array $b): int {
        $blockCmp = ($b['blocking'] <=> $a['blocking']);
        if ($blockCmp !== 0) {
            return $blockCmp;
        }

        return strcmp($a['label'], $b['label']);
    });

    return $todos;
}

/**
 * Bronnenmix + tips: welke brontypes ontbreken of leveren nog niets op.
 *
 * @return array{
 *   total: int,
 *   byCategory: array<string, int>,
 *   review: string,
 *   likedResearch: int,
 *   expandTips: list<array{id: string, label: string, reason: string}>
 * }
 */
function ce_fetch_home_sources_insight(PDO $pdo, ?string $clientId): array
{
    $byCategory = [];
    if ($clientId !== null && ce_table_exists($pdo, 'sources')) {
        $stmt = $pdo->prepare(
            'SELECT category, COUNT(*) AS n FROM sources WHERE client_id = :id GROUP BY category'
        );
        $stmt->execute(['id' => $clientId]);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $category = strtolower(trim((string) ($row['category'] ?? '')));
            if ($category !== '') {
                $byCategory[$category] = (int) ($row['n'] ?? 0);
            }
        }
    }

    $total = array_sum($byCategory);
    $likedResearch = ce_count_liked_research($pdo, $clientId);

    return [
        'total' => $total,
        'byCategory' => $byCategory,
        'review' => ce_format_sources_review_summary($total, $byCategory, $likedResearch),
        'likedResearch' => $likedResearch,
        'expandTips' => ce_build_sources_expand_tips($byCategory, $total),
    ];
}

function ce_format_sources_review_summary(int $total, array $byCategory, int $likedResearch): string
{
    if ($total <= 0) {
        return 'Nog geen bronnen gekoppeld.';
    }

    $parts = [];
    foreach ($byCategory as $category => $count) {
        $label = ce_db_category_to_ui((string) $category);
        $parts[] = $count > 1 ? $label . ' (' . $count . ')' : $label;
    }

    $summary = $total . ' bron' . ($total === 1 ? '' : 'nen') . ' — ' . implode(', ', $parts);
    if ($likedResearch > 0) {
        $summary .= '. ' . $likedResearch . ' research-item' . ($likedResearch === 1 ? '' : 's') . ' geliked';
    }

    return $summary . '.';
}

/**
 * @param array<string, int> $byCategory
 * @return list<array{id: string, label: string, reason: string}>
 */
function ce_build_sources_expand_tips(array $byCategory, int $total): array
{
    $tips = [];

    if ($total <= 0) {
        return [
            [
                'id' => 'expand-start-rss',
                'label' => 'Start met RSS',
                'reason' => 'Voeg sectorfeeds toe als eerste research-input',
            ],
            [
                'id' => 'expand-start-analytics',
                'label' => 'Koppel GA4',
                'reason' => 'Website views en traffic verschijnen in Report',
            ],
            [
                'id' => 'expand-start-linkedin',
                'label' => 'LinkedIn-scraper',
                'reason' => 'Haalt posts en trends uit jullie markt binnen',
            ],
        ];
    }

    if (!isset($byCategory['url_rss'])) {
        $tips[] = [
            'id' => 'expand-rss',
            'label' => 'Voeg RSS-feed toe',
            'reason' => 'Sectornieuws en blogs vullen research aan',
        ];
    } elseif ((int) ($byCategory['url_rss'] ?? 0) < 2) {
        $tips[] = [
            'id' => 'expand-rss-more',
            'label' => 'Extra RSS-feed',
            'reason' => 'Meer bronnen = meer thema\'s om te beoordelen',
        ];
    }

    if (!isset($byCategory['blogs'])) {
        $tips[] = [
            'id' => 'expand-blogs',
            'label' => 'Voeg blogoverzicht toe',
            'reason' => 'Onderliggende blogposts vullen research aan',
        ];
    }

    if (!isset($byCategory['trello'])) {
        $tips[] = [
            'id' => 'expand-trello',
            'label' => 'Koppel Trello',
            'reason' => 'Eigen PR-concepten wegen mee in research',
        ];
    }

    if (!isset($byCategory['mailbox'])) {
        $tips[] = [
            'id' => 'expand-mailbox',
            'label' => 'Voeg mailbox toe',
            'reason' => 'Nieuwsbrieven worden inspiration in research',
        ];
    }

    if (!isset($byCategory['linkedin']) && !isset($byCategory['scraper'])) {
        $tips[] = [
            'id' => 'expand-linkedin',
            'label' => 'LinkedIn-scraper toevoegen',
            'reason' => 'Markttrends en posts uit jullie sector',
        ];
    }

    if (!isset($byCategory['analytics'])) {
        $tips[] = [
            'id' => 'expand-analytics',
            'label' => 'Koppel GA4',
            'reason' => 'Active users, top pages en traffic in Report',
        ];
    }

    if (isset($byCategory['analytics']) && !isset($byCategory['linkedin']) && !isset($byCategory['scraper'])) {
        $tips[] = [
            'id' => 'expand-linkedin-after-analytics',
            'label' => 'LinkedIn-scraper toevoegen',
            'reason' => 'Vergelijk website-traffic met social engagement',
        ];
    }

    return array_slice($tips, 0, 3);
}

/** Aantal gelikete research-items — laat zien wat de bronnen opleveren. */
function ce_count_liked_research(PDO $pdo, ?string $clientId): int
{
    if (!ce_table_exists($pdo, 'research_items')) {
        return 0;
    }

    if ($clientId !== null && ce_table_has_column($pdo, 'research_items', 'client_id')) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM research_items
             WHERE client_id = :id AND feedback IN ('like', 'liked')"
        );
        $stmt->execute(['id' => $clientId]);

        return (int) $stmt->fetchColumn();
    }

    if ($clientId !== null && ce_table_exists($pdo, 'content_slots')) {
        $stmt = $pdo->prepare(
            <<<'SQL'
SELECT COUNT(DISTINCT ri.id)
FROM research_items ri
LEFT JOIN content_slots s ON s.id = ri.slot_id
LEFT JOIN themes t ON t.id = ri.theme_id
WHERE ri.feedback IN ('like', 'liked')
  AND (s.client_id = :id OR t.client_id = :id)
SQL
        );
        $stmt->execute(['id' => $clientId]);

        return (int) $stmt->fetchColumn();
    }

    return (int) $pdo->query(
        "SELECT COUNT(*) FROM research_items WHERE feedback IN ('like', 'liked')"
    )->fetchColumn();
}

/** @return array<string, int>|null */
function ce_contentrol_mix_from_counts(array $counts): ?array
{
    $total = 0;
    foreach ($counts as $value) {
        $total += (int) $value;
    }
    if ($total <= 0) {
        return null;
    }

    $mix = [];
    foreach ($counts as $key => $value) {
        $count = (int) $value;
        if ($count <= 0) {
            continue;
        }
        $mix[(string) $key] = (int) round(($count / $total) * 100);
    }

    return $mix !== [] ? $mix : null;
}

/** @return array<string, int>|null */
function ce_contentrol_mix_from_report_json(?string $json): ?array
{
    if ($json === null || trim($json) === '') {
        return null;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }

    $contentrol = $decoded['_contentrol'] ?? $decoded;
    if (!is_array($contentrol)) {
        return null;
    }

    require_once __DIR__ . '/hba-repository.php';
    $counts = array_fill_keys(ce_hba_contentrol_keys(), 0);
    foreach ($contentrol as $key => $value) {
        if (!is_numeric($value)) {
            continue;
        }
        $normalized = strtolower(trim((string) $key));
        if (isset($counts[$normalized])) {
            $counts[$normalized] += (int) $value;
        }
    }

    return ce_contentrol_mix_from_counts($counts);
}

/**
 * 8-weken sparkline "Hoe gaat het" — % op-strategie per week, gebaseerd op
 * gepubliceerde artikelen (angle_final, fallback angle_suggested).
 *
 * @return array{currentPct: int|null, trendDirection: string, weeklyData: list<int>, hasEnoughData: bool}
 */
function ce_fetch_home_strategy_trend(PDO $pdo, ?string $clientId): array
{
    $weeksSpan = 8;
    $minWeeksRequired = 3;
    $empty = [
        'currentPct' => null,
        'trendDirection' => 'stable',
        'weeklyData' => [],
        'hasEnoughData' => false,
        'weeksWithData' => 0,
        'publishedPostsCount' => 0,
        'minWeeksRequired' => $minWeeksRequired,
        'metricKind' => 'brand_contribution',
    ];

    if (!ce_table_exists($pdo, 'articles') || !ce_table_exists($pdo, 'content_slots')) {
        return $empty;
    }

    $publishedStatus = ce_db_status_for_ui($pdo, 'articles', 'published');
    $scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId);

    if (ce_table_exists($pdo, 'hba_scores')) {
        require_once __DIR__ . '/hba-repository.php';
        $threshold = ce_hba_on_strategy_bc_threshold();

        $sql = <<<SQL
SELECT
  YEARWEEK(a.published_at, 3) AS yweek,
  COUNT(*) AS total,
  SUM(CASE WHEN hs.brand_contribution_score >= :threshold THEN 1 ELSE 0 END) AS on_strategy
FROM articles a
INNER JOIN content_slots s ON s.id = a.slot_id
LEFT JOIN hba_scores hs ON hs.id = (
  SELECT hs2.id FROM hba_scores hs2
  WHERE hs2.post_id = a.id
  ORDER BY hs2.scored_at DESC
  LIMIT 1
)
WHERE a.status = :published
  AND a.published_at IS NOT NULL
  AND a.published_at >= DATE_SUB(CURDATE(), INTERVAL $weeksSpan WEEK)
SQL;
        $sql .= $scope['sql'];
        $sql .= ' GROUP BY yweek ORDER BY yweek ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(
            ['published' => $publishedStatus, 'threshold' => $threshold],
            $scope['params'],
        ));
    } else {
        $hasAngleFinal = ce_table_has_column($pdo, 'articles', 'angle_final');
        $hasAngleSuggested = ce_table_has_column($pdo, 'articles', 'angle_suggested');
        if (!$hasAngleFinal && !$hasAngleSuggested) {
            return $empty;
        }

        $angleExpr = $hasAngleFinal && $hasAngleSuggested
            ? "COALESCE(NULLIF(a.angle_final, ''), a.angle_suggested)"
            : ($hasAngleFinal ? 'a.angle_final' : 'a.angle_suggested');

        $sql = <<<SQL
SELECT
  YEARWEEK(a.published_at, 3) AS yweek,
  COUNT(*) AS total,
  SUM(CASE WHEN $angleExpr IN ('archetype', 'adversary') THEN 1 ELSE 0 END) AS on_strategy
FROM articles a
INNER JOIN content_slots s ON s.id = a.slot_id
WHERE a.status = :published
  AND a.published_at IS NOT NULL
  AND a.published_at >= DATE_SUB(CURDATE(), INTERVAL $weeksSpan WEEK)
SQL;
        $sql .= $scope['sql'];
        $sql .= ' GROUP BY yweek ORDER BY yweek ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(['published' => $publishedStatus], $scope['params']));
    }

    $byYearWeek = [];
    $publishedPostsCount = 0;
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $yw = trim((string) ($row['yweek'] ?? ''));
        if ($yw === '') {
            continue;
        }
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            continue;
        }
        $publishedPostsCount += $total;
        $onStrategy = (int) ($row['on_strategy'] ?? 0);
        $byYearWeek[$yw] = (int) round(($onStrategy / $total) * 100);
    }

    $timezone = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
    } catch (Throwable) {
        $now = new DateTimeImmutable('now');
    }

    $weeklyData = [];
    for ($i = $weeksSpan - 1; $i >= 0; $i--) {
        $weekDate = $now->modify("-{$i} weeks");
        $yw = sprintf('%d%02d', (int) $weekDate->format('o'), (int) $weekDate->format('W'));
        if (isset($byYearWeek[$yw])) {
            $weeklyData[] = $byYearWeek[$yw];
        }
    }

    $pointCount = count($weeklyData);
    if ($pointCount < $minWeeksRequired) {
        return [
            'currentPct' => $pointCount > 0 ? $weeklyData[$pointCount - 1] : null,
            'trendDirection' => 'stable',
            'weeklyData' => $weeklyData,
            'hasEnoughData' => false,
            'weeksWithData' => $pointCount,
            'publishedPostsCount' => $publishedPostsCount,
            'minWeeksRequired' => $minWeeksRequired,
            'metricKind' => 'brand_contribution',
        ];
    }

    $recentWindow = array_slice($weeklyData, -4);
    $currentPct = (int) round(array_sum($recentWindow) / count($recentWindow));

    $firstHalf = array_slice($weeklyData, 0, (int) ceil($pointCount / 2));
    $secondHalf = array_slice($weeklyData, -(int) ceil($pointCount / 2));
    $firstAvg = array_sum($firstHalf) / count($firstHalf);
    $secondAvg = array_sum($secondHalf) / count($secondHalf);
    $delta = $secondAvg - $firstAvg;

    if ($delta > 3) {
        $trendDirection = 'up';
    } elseif ($delta < -3) {
        $trendDirection = 'down';
    } else {
        $trendDirection = 'stable';
    }

    return [
        'currentPct' => $currentPct,
        'trendDirection' => $trendDirection,
        'weeklyData' => $weeklyData,
        'hasEnoughData' => true,
        'weeksWithData' => $pointCount,
        'publishedPostsCount' => $publishedPostsCount,
        'minWeeksRequired' => $minWeeksRequired,
        'metricKind' => 'brand_contribution',
    ];
}

/**
 * Cumulatieve engagement-trend over de laatste N weken — echte GA4-paginaweergaven
 * (gebucket op published_at) plus LinkedIn-engagement (reacties+likes+comments,
 * gebucket op posted_at, rechtstreeks uit `linkedin_posts`). Loopt per definitie
 * nooit terug.
 *
 * Sinds aug 2026: `articles.interaction_views` bevat uitsluitend echte GA4-weergaven
 * (`ce_analytics_sync_article_views()`); LinkedIn-likes/comments op `articles` worden
 * hier bewust NIET meegeteld — die zitten al in de losstaande LinkedIn-som hierboven en
 * zouden anders dubbel geteld worden.
 *
 * @return array{totalEngagement: int, weeklyData: list<int>, hasEnoughData: bool}
 */
function ce_fetch_home_engagement_trend(PDO $pdo, ?string $clientId, int $weeksSpan = 8): array
{
    $empty = [
        'totalEngagement' => 0,
        'weeklyData' => [],
        'hasEnoughData' => false,
        'weeksSpan' => $weeksSpan,
        'breakdown' => [
            'articles' => ['views' => 0],
            'linkedIn' => ['reactions' => 0, 'likes' => 0, 'comments' => 0],
        ],
        'lastWeekTotal' => 0,
    ];

    $timezone = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
    } catch (Throwable) {
        $now = new DateTimeImmutable('now');
    }

    $periodStart = $now->modify('-' . $weeksSpan . ' weeks')->format('Y-m-d');
    $periodEnd = $now->format('Y-m-d');

    $byYearWeek = [];
    $articleTotals = ['views' => 0];
    $linkedInTotals = ['reactions' => 0, 'likes' => 0, 'comments' => 0];

    if (
        ce_table_exists($pdo, 'articles')
        && ce_table_exists($pdo, 'content_slots')
        && ce_table_has_column($pdo, 'articles', 'published_at')
        && ce_table_has_column($pdo, 'articles', 'interaction_views')
    ) {
        $publishedStatus = ce_db_status_for_ui($pdo, 'articles', 'published');
        $scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId);

        $sql = <<<SQL
SELECT
  YEARWEEK(a.published_at, 3) AS yweek,
  COALESCE(SUM(a.interaction_views), 0) AS views
FROM articles a
INNER JOIN content_slots s ON s.id = a.slot_id
WHERE a.status = :published
  AND a.published_at IS NOT NULL
  AND a.published_at >= DATE_SUB(CURDATE(), INTERVAL $weeksSpan WEEK)
SQL;
        $sql .= $scope['sql'];
        $sql .= ' GROUP BY yweek';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(['published' => $publishedStatus], $scope['params']));

        foreach ($stmt->fetchAll() ?: [] as $row) {
            $yw = trim((string) ($row['yweek'] ?? ''));
            if ($yw === '') {
                continue;
            }
            $views = (int) ($row['views'] ?? 0);
            $articleTotals['views'] += $views;
            $byYearWeek[$yw] = ($byYearWeek[$yw] ?? 0) + $views;
        }
    }

    if ($clientId !== null && ce_table_exists($pdo, 'linkedin_posts')) {
        require_once __DIR__ . '/linkedin-engagement-repository.php';
        foreach (ce_linkedin_weekly_engagement($pdo, $clientId, $periodStart, $periodEnd) as $week) {
            try {
                $weekDate = new DateTimeImmutable((string) $week['startDate'], new DateTimeZone($timezone));
            } catch (Throwable) {
                continue;
            }
            $yw = sprintf('%d%02d', (int) $weekDate->format('o'), (int) $weekDate->format('W'));
            $reactions = (int) ($week['reactions'] ?? 0);
            $likes = (int) ($week['likes'] ?? 0);
            $comments = (int) ($week['comments'] ?? 0);
            $linkedInTotals['reactions'] += $reactions;
            $linkedInTotals['likes'] += $likes;
            $linkedInTotals['comments'] += $comments;
            $sum = $reactions + $likes + $comments;
            $byYearWeek[$yw] = ($byYearWeek[$yw] ?? 0) + $sum;
        }
    }

    if ($byYearWeek === []) {
        return $empty;
    }

    $weeklyTotals = [];
    for ($i = $weeksSpan - 1; $i >= 0; $i--) {
        $weekDate = $now->modify("-{$i} weeks");
        $yw = sprintf('%d%02d', (int) $weekDate->format('o'), (int) $weekDate->format('W'));
        $weeklyTotals[] = (int) ($byYearWeek[$yw] ?? 0);
    }

    $running = 0;
    $weeklyData = [];
    foreach ($weeklyTotals as $weekTotal) {
        $running += $weekTotal;
        $weeklyData[] = $running;
    }

    $hasEnoughData = $running > 0 && count(array_filter($weeklyTotals)) >= 2;
    $lastWeekTotal = $weeklyTotals !== [] ? (int) $weeklyTotals[count($weeklyTotals) - 1] : 0;

    return [
        'totalEngagement' => $running,
        'weeklyData' => $weeklyData,
        'hasEnoughData' => $hasEnoughData,
        'weeksSpan' => $weeksSpan,
        'breakdown' => [
            'articles' => $articleTotals,
            'linkedIn' => $linkedInTotals,
        ],
        'lastWeekTotal' => $lastWeekTotal,
    ];
}

/** HBA-nulmeting/testposts horen niet in de ritmemeter (D4). */
function ce_home_slot_excluded_from_ritme_meter(array $row): bool
{
    $origin = strtolower(trim((string) ($row['slot_origin'] ?? $row['origin'] ?? '')));
    if ($origin === 'baseline') {
        return true;
    }
    $theme = trim((string) ($row['theme'] ?? ''));
    if ($theme !== '' && str_starts_with($theme, 'HBA-test:')) {
        return true;
    }

    return false;
}

function ce_home_parse_published_at(mixed $value, DateTimeZone $tz): ?DateTimeImmutable
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return null;
    }

    try {
        return new DateTimeImmutable($raw, $tz);
    } catch (Throwable) {
        return null;
    }
}

/** @return array<string, mixed> */
function ce_fetch_home_result(PDO $pdo, ?string $clientId): array
{
    $postsPerWeek = ce_client_posts_per_week($pdo, $clientId);
    $currentWeek = (string) ce_week_start();

    $publishedThisWeek = 0;
    $publishedThisMonth = 0;
    $totalViews = 0;
    $totalLikes = 0;
    $totalComments = 0;
    $postsWithEngagement = 0;
    $postsMissingEngagement = 0;
    require_once __DIR__ . '/hba-repository.php';
    $contentrolCounts = array_fill_keys(ce_hba_contentrol_keys(), 0);

    if (ce_table_exists($pdo, 'content_slots') && ce_table_exists($pdo, 'articles')) {
        ce_ensure_publish_columns($pdo);

        $publishedStatus = ce_db_status_for_ui($pdo, 'articles', 'published');
        $archivedStatus = ce_archive_status($pdo, 'articles');
        $scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId);

        $extra = '';
        if (ce_table_has_column($pdo, 'articles', 'published_at')) {
            $extra .= ', a.published_at';
        }
        if (ce_table_has_column($pdo, 'articles', 'interaction_views')) {
            $extra .= ', a.interaction_views, a.interaction_likes, a.interaction_comments';
        }
        if (ce_table_exists($pdo, 'hba_scores')) {
            $extra .= ', hs.contentrol';
        }

        $hbaJoin = '';
        if (ce_table_exists($pdo, 'hba_scores')) {
            $hbaJoin = <<<'SQL'

LEFT JOIN hba_scores hs ON hs.id = (
  SELECT hs2.id FROM hba_scores hs2
  WHERE hs2.post_id = a.id
  ORDER BY hs2.scored_at DESC
  LIMIT 1
)
SQL;
        }

        $slotMeta = ', s.theme';
        if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
            $slotMeta .= ', s.origin AS slot_origin';
        }

        $sql = <<<SQL
SELECT s.phase_key, a.status AS article_status$slotMeta$extra
FROM content_slots s
INNER JOIN articles a ON a.slot_id = s.id
$hbaJoin
WHERE a.status IN (:published, :archived)
SQL;
        $sql .= $scope['sql'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(
            ['published' => $publishedStatus, 'archived' => $archivedStatus],
            $scope['params'],
        ));

        $timezone = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
        try {
            $tz = new DateTimeZone($timezone);
            $now = new DateTimeImmutable('now', $tz);
            $monthStart = new DateTimeImmutable('first day of this month 00:00:00', $tz);
        } catch (Throwable) {
            $now = new DateTimeImmutable('now');
            $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
        }
        $currentWeekNum = (int) $currentWeek;
        $currentIsoYear = (int) $now->format('o');
        $weekStartOverride = (int) (getenv('CE_WEEK_START') ?: 0) > 0;

        foreach ($stmt->fetchAll() ?: [] as $row) {
            $phaseKey = trim((string) ($row['phase_key'] ?? ''));
            $publishedDt = array_key_exists('published_at', $row)
                ? ce_home_parse_published_at($row['published_at'] ?? null, $now->getTimezone())
                : null;

            $inCurrentWeek = false;
            if ($publishedDt instanceof DateTimeImmutable) {
                $inCurrentWeek = (int) $publishedDt->format('W') === $currentWeekNum
                    && ($weekStartOverride || (int) $publishedDt->format('o') === $currentIsoYear);
            } elseif ($phaseKey === $currentWeek) {
                $inCurrentWeek = true;
            }
            if ($inCurrentWeek && !ce_home_slot_excluded_from_ritme_meter($row)) {
                $publishedThisWeek++;
            }

            $inCurrentMonth = false;
            if ($publishedDt instanceof DateTimeImmutable) {
                $inCurrentMonth = $publishedDt >= $monthStart;
            } elseif ($phaseKey !== '' && ctype_digit($phaseKey)) {
                $inCurrentMonth = (int) $phaseKey >= $currentWeekNum - 4;
            }

            if ($inCurrentMonth) {
                $publishedThisMonth++;
                $contentrol = strtolower(trim((string) ($row['contentrol'] ?? '')));
                if ($contentrol !== '' && isset($contentrolCounts[$contentrol])) {
                    $contentrolCounts[$contentrol]++;
                }
            }

            if (!ce_table_has_column($pdo, 'articles', 'interaction_views')) {
                continue;
            }

            $views = array_key_exists('interaction_views', $row) ? $row['interaction_views'] : null;
            $likes = array_key_exists('interaction_likes', $row) ? $row['interaction_likes'] : null;
            $comments = array_key_exists('interaction_comments', $row) ? $row['interaction_comments'] : null;

            $hasEngagement = ($views !== null && (int) $views > 0)
                || ($likes !== null && (int) $likes > 0)
                || ($comments !== null && (int) $comments > 0);

            if ($hasEngagement) {
                $postsWithEngagement++;
                $viewCount = max(0, (int) ($views ?? 0));
                $totalViews += $viewCount;
                $totalLikes += max(0, (int) ($likes ?? 0));
                $totalComments += max(0, (int) ($comments ?? 0));
            } elseif ($views === null && $likes === null && $comments === null) {
                $postsMissingEngagement++;
            } elseif ((int) ($views ?? 0) === 0 && (int) ($likes ?? 0) === 0 && (int) ($comments ?? 0) === 0) {
                $postsMissingEngagement++;
            }
        }
    }

    $contentrolMix = ce_contentrol_mix_from_counts($contentrolCounts);

    $latestReport = null;
    if (ce_table_exists($pdo, 'performance_reports')) {
        ce_ensure_performance_reports_table($pdo);

        $reportCols = [
            'pct_on_strategy',
            'execution_rate',
            'engagement_diff_pct',
            'narrative',
            'period_start',
            'period_end',
        ];
        if (ce_table_has_column($pdo, 'performance_reports', 'tone_distribution')) {
            $reportCols[] = 'tone_distribution';
        }
        if (ce_table_has_column($pdo, 'performance_reports', 'angle_distribution')) {
            $reportCols[] = 'angle_distribution';
        }

        $reportSql = 'SELECT ' . implode(', ', $reportCols) . ' FROM performance_reports WHERE 1=1';
        $reportParams = [];
        if ($clientId !== null) {
            $reportSql .= ' AND client_id = :client_id';
            $reportParams['client_id'] = $clientId;
        }
        $reportSql .= ' ORDER BY generated_at DESC LIMIT 1';
        $reportStmt = $pdo->prepare($reportSql);
        $reportStmt->execute($reportParams);
        $reportRow = $reportStmt->fetch();
        if ($reportRow) {
            $latestReport = [
                'periodStart' => (string) ($reportRow['period_start'] ?? ''),
                'periodEnd' => (string) ($reportRow['period_end'] ?? ''),
                'pctOnStrategy' => isset($reportRow['pct_on_strategy']) ? (float) $reportRow['pct_on_strategy'] : null,
                'executionRate' => isset($reportRow['execution_rate']) ? (float) $reportRow['execution_rate'] : null,
                'engagementDiffPct' => isset($reportRow['engagement_diff_pct']) ? (float) $reportRow['engagement_diff_pct'] : null,
                'narrativePreview' => mb_substr(trim((string) ($reportRow['narrative'] ?? '')), 0, 200),
            ];
            if ($contentrolMix === null) {
                $contentrolMix = ce_contentrol_mix_from_report_json(
                    isset($reportRow['angle_distribution']) ? (string) $reportRow['angle_distribution'] : null,
                );
            }
        }
    }

    $weekMetrics = ce_metrics_current_week_snapshot($pdo, $clientId);

    require_once __DIR__ . '/linkedin-engagement-repository.php';
    $linkedInEngagement = ce_linkedin_engagement_snapshot($pdo, $clientId);
    $sourcesInsight = ce_fetch_home_sources_insight($pdo, $clientId);
    $strategyTrend = ce_fetch_home_strategy_trend($pdo, $clientId);
    $engagementTrend = ce_fetch_home_engagement_trend($pdo, $clientId);

    require_once __DIR__ . '/llm-repository.php';
    $findability = ce_fetch_home_findability($pdo, $clientId);

    if (ce_request_is_tenant_scoped($pdo)) {
        $contentrolMix = null;
    }

    return [
        'strategyTrend' => $strategyTrend,
        'engagementTrend' => $engagementTrend,
        'findability' => $findability,
        'postsPerWeek' => $postsPerWeek,
        'publishedThisWeek' => $publishedThisWeek,
        'publishedThisMonth' => $publishedThisMonth,
        'angleMix' => null,
        'contentrolMix' => $contentrolMix,
        'weekLabel' => $weekMetrics['weekLabel'],
        'websiteViewsThisWeek' => $weekMetrics['websiteViewsThisWeek'],
        'websiteSource' => $weekMetrics['websiteSource'],
        'hasAnalytics' => $weekMetrics['hasAnalytics'],
        'linkedInEngagement' => $linkedInEngagement,
        'sourcesReview' => [
            'summary' => (string) ($sourcesInsight['review'] ?? ''),
            'likedResearch' => (int) ($sourcesInsight['likedResearch'] ?? 0),
            'total' => (int) ($sourcesInsight['total'] ?? 0),
            'expandTips' => is_array($sourcesInsight['expandTips'] ?? null) ? $sourcesInsight['expandTips'] : [],
        ],
        'engagement' => [
            'views' => $totalViews,
            'likes' => $totalLikes,
            'comments' => $totalComments,
            'postsTracked' => $postsWithEngagement,
            'postsMissing' => $postsMissingEngagement,
        ],
        'latestReport' => $latestReport,
    ];
}

/** Zelfde PBS-trend als de meter Merkcijfer (`/hba/progress`). */
function ce_home_return_summary_pbs_phrase(PDO $pdo, ?string $clientId): ?string
{
    if ($clientId === null || trim($clientId) === '') {
        return null;
    }

    require_once __DIR__ . '/hba-v2-repository.php';
    ce_ensure_hba_v2_schema($pdo);
    $scoredPosts = ce_hba_v2_count_published_scored_posts($pdo, $clientId);
    if ($scoredPosts < CE_HBA_SCORED_POSTS_FOR_OVERVIEW) {
        return null;
    }

    $phase = 'A';
    if ($scoredPosts >= 20) {
        $phase = 'C';
    } elseif ($scoredPosts >= 10) {
        $phase = 'B';
    }

    $pbs = ce_hba_v2_compute_pbs_metrics($pdo, $clientId, $phase);
    $trend = (string) ($pbs['pbsTrend'] ?? '');

    return match ($trend) {
        'up' => 'je merkcijfer liep licht op',
        'down' => 'je merkcijfer zakte iets',
        'flat' => 'je merkcijfer bleef stabiel',
        default => null,
    };
}

/**
 * Terugkeerregel (notitie 15 §1.1) — alleen na >7 dagen zonder login.
 *
 * @param list<array{id: string, label: string, reason: string, path: string, blocking: bool}> $todos
 * @param array<string, mixed> $result
 * @return array{message: string, daysAway: int, sinceDate: string, reportPath: string}|null
 */
function ce_build_home_return_summary(PDO $pdo, ?string $clientId, array $todos, array $result): ?array
{
    require_once __DIR__ . '/tenant-activity-repository.php';

    $sinceDate = ce_tenant_previous_login_date($pdo, $clientId);
    if ($sinceDate === null) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    $daysAway = ce_tenant_days_since($sinceDate, $today);
    if ($daysAway === null || $daysAway <= 7) {
        return null;
    }

    $todayIso = $today->format('Y-m-d');
    $published = 0;
    if (
        $clientId !== null
        && ce_table_exists($pdo, 'articles')
        && ce_table_exists($pdo, 'content_slots')
        && ce_table_has_column($pdo, 'articles', 'published_at')
    ) {
        $publishedStatus = ce_db_status_for_ui($pdo, 'articles', 'published');
        $scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId);
        $sql = <<<SQL
SELECT COUNT(*)
FROM articles a
INNER JOIN content_slots s ON s.id = a.slot_id
WHERE a.status = :published
  AND a.published_at IS NOT NULL
  AND DATE(a.published_at) > :since_date
  AND DATE(a.published_at) <= :today
{$scope['sql']}
SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($scope['params'], [
            'published' => $publishedStatus,
            'since_date' => $sinceDate,
            'today' => $todayIso,
        ]));
        $published = (int) $stmt->fetchColumn();
    }

    require_once __DIR__ . '/linkedin-engagement-repository.php';
    $periodStart = (new DateTimeImmutable($sinceDate))->modify('+1 day')->format('Y-m-d');
    if ($periodStart > $todayIso) {
        $periodStart = $todayIso;
    }
    $engagement = ce_linkedin_engagement_aggregate($pdo, $clientId, $periodStart, $todayIso);
    $reactions = (int) ($engagement['reactions'] ?? 0);
    $likes = (int) ($engagement['likes'] ?? 0);
    $comments = (int) ($engagement['comments'] ?? 0);
    $engagementTotal = max($reactions, $likes + $comments);

    $parts = [];
    if ($published === 1) {
        $parts[] = 'Er is 1 artikel gepubliceerd';
    } elseif ($published > 1) {
        $parts[] = "Er zijn $published artikelen gepubliceerd";
    } else {
        $parts[] = 'Er zijn geen nieuwe artikelen gepubliceerd';
    }

    if ($engagementTotal > 0 && $published > 0) {
        $parts[0] .= ", samen goed voor $engagementTotal reacties";
    }
    $parts[0] .= '.';

    $brandParts = [];
    $pbsPhrase = ce_home_return_summary_pbs_phrase($pdo, $clientId);
    if ($pbsPhrase !== null) {
        $brandParts[] = $pbsPhrase;
    }

    $mix = is_array($result['contentrolMix'] ?? null) ? $result['contentrolMix'] : [];
    $positioning = (int) ($mix['positionering'] ?? $mix['herpositionering'] ?? 0);
    if ($positioning < CE_POSITIONERING_TARGET_PCT) {
        $brandParts[] = 'positionering blijft achter';
    }

    $waiting = count(array_filter(
        $todos,
        static fn (array $todo): bool => !empty($todo['blocking']),
    ));
    if ($waiting === 1) {
        $brandParts[] = 'er wacht 1 ding op jou';
    } elseif ($waiting > 1) {
        $brandParts[] = "er wachten $waiting dingen op jou";
    }

    if ($brandParts !== []) {
        $sentence = implode(', ', $brandParts);
        $parts[] = mb_strtoupper(mb_substr($sentence, 0, 1)) . mb_substr($sentence, 1) . '.';
    }

    return [
        'message' => implode(' ', $parts),
        'daysAway' => $daysAway,
        'sinceDate' => $sinceDate,
        'reportPath' => '/report',
    ];
}

/**
 * §1.6 — optionele koppelingen die nog niet gelegd zijn (podcast + mailbox + Trello).
 *
 * @return list<array{id: string, label: string, path: string}>
 */
function ce_build_home_pending_connections(PDO $pdo, ?string $clientId): array
{
    if ($clientId === null || trim($clientId) === '') {
        return [];
    }

    try {
        require_once __DIR__ . '/integrations-status-repository.php';
        $status = ce_integrations_status_for_client($pdo, $clientId);
    } catch (Throwable) {
        return [];
    }
    $integrations = is_array($status['integrations'] ?? null) ? $status['integrations'] : [];

    $items = [];

    if (!ce_client_has_podcast_source($pdo, $clientId)) {
        $items[] = [
            'id' => 'podcast',
            'label' => 'podcast',
            'path' => '/sources/new/url-rss',
        ];
    }

    foreach (
        [
            'mailbox' => ['label' => 'mailbox', 'path' => '/sources/new/mailbox'],
            'trello' => ['label' => 'Trello', 'path' => '/sources/new/trello'],
        ] as $key => $meta
    ) {
        $entry = $integrations[$key] ?? null;
        if (!is_array($entry)) {
            continue;
        }
        $integrationStatus = (string) ($entry['status'] ?? 'not_set');
        if ($integrationStatus === 'unavailable') {
            continue;
        }
        if ($integrationStatus === 'connected') {
            continue;
        }
        $items[] = [
            'id' => $key,
            'label' => $meta['label'],
            'path' => $meta['path'],
        ];
    }

    return $items;
}

/**
 * §1.2 — maximaal één signaal op Overzicht.
 *
 * Voorrang: portfoliosignaal (zelfde rangorde als Agenda) wint; PBS-erosion alleen als fallback
 * wanneer geen portfoliosignaal actief is. Zo blijven Home en Agenda consistent.
 *
 * @return array{source: string, message: string, actionLabel: string, actionPath: string}|null
 */
function ce_build_home_overview_signal(PDO $pdo, ?string $clientId): ?array
{
    if ($clientId === null || trim($clientId) === '') {
        return null;
    }

    require_once __DIR__ . '/agenda-portfolio-signals-repository.php';
    $portfolio = ce_agenda_portfolio_heaviest_signal($pdo, $clientId);
    if ($portfolio !== null) {
        return [
            'source' => 'portfolio',
            'message' => (string) ($portfolio['message'] ?? ''),
            'actionLabel' => (string) ($portfolio['actionLabel'] ?? ''),
            'actionPath' => (string) ($portfolio['actionHref'] ?? '/report'),
        ];
    }

    require_once __DIR__ . '/hba-v2-repository.php';
    ce_ensure_hba_v2_schema($pdo);
    $scoredPosts = ce_hba_v2_count_published_scored_posts($pdo, $clientId);
    $phase = 'A';
    if ($scoredPosts >= 20) {
        $phase = 'C';
    } elseif ($scoredPosts >= 10) {
        $phase = 'B';
    }

    $pbs = ce_hba_v2_compute_pbs_metrics($pdo, $clientId, $phase);
    $erosion = is_array($pbs['erosionAlert'] ?? null) ? $pbs['erosionAlert'] : null;
    $erosionMessage = trim((string) ($erosion['message'] ?? ''));
    if ($erosionMessage !== '') {
        return [
            'source' => 'erosion',
            'message' => $erosionMessage,
            'actionLabel' => 'Bekijk thema\'s',
            'actionPath' => (string) ($erosion['linkPath'] ?? '/theme'),
        ];
    }

    return null;
}

/** @return array<string, mixed> */
function ce_fetch_home_dashboard(PDO $pdo): array
{
    $client = ce_fetch_client_row($pdo);
    $clientId = $client ? (string) ($client['id'] ?? '') : ce_resolve_client_id($pdo);
    $companyName = $client ? trim((string) ($client['name'] ?? '')) : '';

    require_once __DIR__ . '/account-repository.php';
    $strategyReady = ce_client_styleguide_ready($client ?: null, $pdo);

    $sourcesCount = 0;
    if ($clientId !== null && ce_table_exists($pdo, 'sources')) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM sources WHERE client_id = :id');
        $stmt->execute(['id' => $clientId]);
        $sourcesCount = (int) $stmt->fetchColumn();
    }

    $themesProposed = ce_count_themes_needing_review($pdo, $clientId);
    $themesTotal = 0;
    if (ce_table_exists($pdo, 'themes')) {
        $sql = 'SELECT COUNT(*) FROM themes WHERE 1=1';
        $params = [];
        if ($clientId !== null && ce_table_has_column($pdo, 'themes', 'client_id')) {
            $sql .= ' AND client_id = :id';
            $params['id'] = $clientId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $themesTotal = (int) $stmt->fetchColumn();
    }

    $agendaTotal = 0;
    $pendingCount = 0;
    $feedbackCount = 0;
    $draftCount = 0;
    $approvedCount = 0;
    $nextFeedbackSlotId = null;
    $nextFeedbackTitle = null;
    $nextDraftSlotId = null;
    $nextDraftTitle = null;
    $researchSubmitSlotId = null;
    $researchSubmitTitle = null;

    if (ce_table_exists($pdo, 'content_slots')) {
        $feedbackJoin = '';
        $feedbackSelect = '0 AS open_feedback_count';
        if (ce_table_exists($pdo, 'article_feedback')) {
            $feedbackJoin = <<<'SQL'
LEFT JOIN (
    SELECT slot_id, COUNT(*) AS open_feedback_count
    FROM article_feedback
    WHERE status = 'open'
    GROUP BY slot_id
) fb ON fb.slot_id = s.id
SQL;
            $feedbackSelect = 'COALESCE(fb.open_feedback_count, 0) AS open_feedback_count';
        }

        $articleJoin = ce_sql_latest_article_join('s');
        $sql = <<<SQL
SELECT
    s.id AS slot_id,
    s.theme,
    a.headline,
    a.headline_human,
    a.status AS article_status,
    s.status AS slot_status,
    $feedbackSelect
FROM content_slots s
{$articleJoin}
$feedbackJoin
WHERE COALESCE(s.status, '') NOT IN ('archived', 'archive')
  AND COALESCE(a.status, '') NOT IN ('archived', 'archive')
SQL;

        $scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId);
        $sql .= $scope['sql'] . "\nORDER BY s.phase_key ASC, s.theme ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($scope['params']);
        $rows = $stmt->fetchAll() ?: [];
        $agendaTotal = count($rows);

        foreach ($rows as $row) {
            $openFeedback = (int) ($row['open_feedback_count'] ?? 0);
            $slotUi = ce_status_to_ui($row['slot_status'] ?? null);
            $uiStatus = ce_resolve_editorial_status(
                $row['article_status'] ?? null,
                $row['slot_status'] ?? null,
                $openFeedback,
            );

            if ($slotUi === 'themed' && $researchSubmitSlotId === null) {
                $researchSubmitSlotId = (string) ($row['slot_id'] ?? '');
                $researchSubmitTitle = ce_article_headline($row) ?: ((string) ($row['theme'] ?? 'Artikel'));
            }

            if ($uiStatus === 'pending') {
                $pendingCount++;
            } elseif ($uiStatus === 'feedback') {
                $feedbackCount++;
                if ($nextFeedbackSlotId === null) {
                    $nextFeedbackSlotId = (string) ($row['slot_id'] ?? '');
                    $nextFeedbackTitle = ce_article_headline($row) ?: ((string) ($row['theme'] ?? 'Artikel'));
                }
            } elseif ($uiStatus === 'approved') {
                $approvedCount++;
            } elseif (in_array($uiStatus, ['draft', 'written'], true)) {
                $draftCount++;
                if ($nextDraftSlotId === null) {
                    $nextDraftSlotId = (string) ($row['slot_id'] ?? '');
                    $nextDraftTitle = ce_article_headline($row) ?: ((string) ($row['theme'] ?? 'Artikel'));
                }
            }
        }
    }

    $researchOpen = 0;
    if (ce_table_exists($pdo, 'research_items')) {
        if ($clientId !== null && ce_table_has_column($pdo, 'research_items', 'client_id')) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM research_items
                 WHERE client_id = :id AND (feedback IS NULL OR feedback = '' OR feedback = 'none')"
            );
            $stmt->execute(['id' => $clientId]);
            $researchOpen = (int) $stmt->fetchColumn();
        } elseif ($clientId !== null && ce_table_exists($pdo, 'content_slots')) {
            $stmt = $pdo->prepare(
                <<<'SQL'
SELECT COUNT(DISTINCT ri.id)
FROM research_items ri
LEFT JOIN content_slots s ON s.id = ri.slot_id
LEFT JOIN themes t ON t.id = ri.theme_id
WHERE (ri.feedback IS NULL OR ri.feedback = '' OR ri.feedback = 'none')
  AND (s.client_id = :id OR t.client_id = :id)
SQL
            );
            $stmt->execute(['id' => $clientId]);
            $researchOpen = (int) $stmt->fetchColumn();
        } else {
            $researchOpen = (int) $pdo->query(
                "SELECT COUNT(*) FROM research_items WHERE feedback IN ('none', '') OR feedback IS NULL"
            )->fetchColumn();
        }
    }

    $nextAction = null;
    if (!$strategyReady) {
        $nextAction = [
            'label' => 'BrandArchitecture invullen',
            'path' => '/brandarchitecture',
            'reason' => 'Onboarding',
        ];
    } elseif ($sourcesCount === 0) {
        $nextAction = ['label' => 'Sources toevoegen', 'path' => '/sources', 'reason' => 'Nog geen bronnen — start hier na BrandElements'];
    } elseif ($themesProposed === 0 && $agendaTotal === 0) {
        $nextAction = [
            'label' => 'Thema\'s starten',
            'path' => '/theme',
            'reason' => 'Genereer je eerste thema-voorstellen voor deze week',
        ];
    } elseif ($themesProposed > 0) {
        $nextAction = [
            'label' => 'Thema\'s beoordelen',
            'path' => '/theme',
            'reason' => "$themesProposed thema(s) wachten op je beoordeling",
        ];
    } elseif ($feedbackCount > 0 && $nextFeedbackSlotId) {
        $nextAction = [
            'label' => 'Feedback verwerken',
            'path' => "/article/$nextFeedbackSlotId",
            'reason' => (string) $nextFeedbackTitle,
        ];
    } elseif ($pendingCount > 0) {
        $nextAction = ['label' => 'Agenda — pending reviews', 'path' => '/agenda', 'reason' => "$pendingCount wacht op reviewer"];
    } elseif ($approvedCount > 0) {
        $nextAction = [
            'label' => 'Artikelen plaatsen',
            'path' => '/agenda',
            'reason' => "$approvedCount approved — klaar om te publiceren",
        ];
    } elseif ($draftCount > 0 && $nextDraftSlotId) {
        $nextAction = [
            'label' => 'Artikel afmaken',
            'path' => "/article/$nextDraftSlotId",
            'reason' => (string) $nextDraftTitle,
        ];
    }

    $result = ce_fetch_home_result($pdo, $clientId);

    require_once __DIR__ . '/voice-profile-repository.php';
    $hasCoreSource = ce_client_has_core_source($pdo, $clientId);

    $todos = ce_build_home_todos([
        'strategyReady' => $strategyReady,
        'sourcesCount' => $sourcesCount,
        'hasCoreSource' => $hasCoreSource,
        'themesProposed' => $themesProposed,
        'agendaSlots' => $agendaTotal,
        'researchOpen' => $researchOpen,
        'feedbackCount' => $feedbackCount,
        'draftCount' => $draftCount,
        'pendingCount' => $pendingCount,
        'approvedCount' => $approvedCount,
        'nextFeedbackSlotId' => $nextFeedbackSlotId,
        'nextFeedbackTitle' => $nextFeedbackTitle,
        'nextDraftSlotId' => $nextDraftSlotId,
        'nextDraftTitle' => $nextDraftTitle,
        'researchSubmitSlotId' => $researchSubmitSlotId,
        'researchSubmitTitle' => $researchSubmitTitle,
    ]);

    return [
        'companyName' => $companyName,
        'weekLabel' => 'Week ' . ce_week_start(),
        'stats' => [
            'sources' => $sourcesCount,
            'themesProposed' => $themesProposed,
            'agendaSlots' => $agendaTotal,
            'pendingReview' => $pendingCount,
            'feedbackOpen' => $feedbackCount,
            'draftArticles' => $draftCount,
            'approvedArticles' => $approvedCount,
        ],
        'nextAction' => $nextAction,
        'todos' => $todos,
        'returnSummary' => ce_build_home_return_summary($pdo, $clientId, $todos, $result),
        'overviewSignal' => ce_build_home_overview_signal($pdo, $clientId),
        'pendingConnections' => ce_build_home_pending_connections($pdo, $clientId),
        'result' => $result,
        'nextArticle' => $nextSlotId ? [
            'slotId' => $nextSlotId,
            'title' => $nextSlotTitle,
            'status' => $nextSlotStatus,
        ] : null,
    ];
}

/** @return array<string, mixed> */
function ce_fetch_support_info(): array
{
    ce_load_env();

    $email = trim((string) (getenv('CE_SUPPORT_EMAIL') ?: ''));
    if ($email === '') {
        $email = trim((string) (getenv('CE_REVIEW_EMAIL') ?: ''));
    }

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($email === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $email = trim((string) ($config['support_email'] ?? $config['review_email'] ?? ''));
        }
    }

    $humanReview = trim((string) (getenv('N8N_CE04_HUMAN_WEBHOOK_URL') ?: ''));
    if ($humanReview === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $humanReview = trim((string) ($config['ce04_human_webhook_url'] ?? ''));
        }
    }

    return [
        'email' => $email !== '' ? $email : 'support@igisnext.com',
        'docsUrl' => trim((string) (getenv('CE_DOCS_URL') ?: 'https://igisnext.com')),
        'humanReviewConfigured' => $humanReview !== '',
    ];
}
