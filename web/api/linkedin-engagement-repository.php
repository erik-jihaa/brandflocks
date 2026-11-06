<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/publish-repository.php';

function ce_ensure_linkedin_posts_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'linkedin_posts')) {
        try {
            $pdo->exec(
                <<<'SQL'
                CREATE TABLE IF NOT EXISTS linkedin_posts (
                  id CHAR(36) NOT NULL PRIMARY KEY,
                  client_id CHAR(36) NOT NULL,
                  source_id CHAR(36) NOT NULL,
                  activity_urn VARCHAR(32) NOT NULL,
                  post_url VARCHAR(512) NULL,
                  posted_at DATETIME NULL,
                  text_preview TEXT NULL,
                  reactions INT NOT NULL DEFAULT 0,
                  likes INT NOT NULL DEFAULT 0,
                  comments INT NOT NULL DEFAULT 0,
                  reposts INT NOT NULL DEFAULT 0,
                  article_id CHAR(36) NULL,
                  scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY uniq_client_activity (client_id, activity_urn),
                  KEY idx_client_posted (client_id, posted_at),
                  KEY idx_source (source_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL
            );
        } catch (Throwable) {
            // Tabel bestaat al of rechten ontbreken.
        }
    } else {
        try {
            $pdo->exec(
                'ALTER TABLE linkedin_posts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (Throwable) {
            // Conversie niet nodig of geen ALTER-rechten.
        }
        if (ce_table_has_column($pdo, 'linkedin_posts', 'text_preview')) {
            try {
                $pdo->exec('ALTER TABLE linkedin_posts MODIFY text_preview TEXT NULL');
            } catch (Throwable) {
                // Kolom bestaat al als TEXT.
            }
        }
    }
}

function ce_linkedin_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function ce_linkedin_activity_id(?string $urlOrUrn): ?string
{
    $value = trim((string) ($urlOrUrn ?? ''));
    if ($value === '') {
        return null;
    }

    if (preg_match('#activity[-:](\d+)#i', $value, $matches)) {
        return $matches[1];
    }

    if (preg_match('#urn:li:activity:(\d+)#i', $value, $matches)) {
        return $matches[1];
    }

    if (ctype_digit($value)) {
        return $value;
    }

    return null;
}

function ce_linkedin_has_source_for_client(PDO $pdo, ?string $clientId): bool
{
    if ($clientId === null || !ce_table_exists($pdo, 'sources')) {
        return false;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT 1
FROM sources
WHERE client_id = :client_id
  AND category IN ('linkedin', 'scraper')
  AND (
    scraper_linkedin_profile IS NOT NULL AND scraper_linkedin_profile != ''
    OR url LIKE '%linkedin%'
    OR scraper_actor_or_task_id LIKE '%linkedin%'
  )
LIMIT 1
SQL
    );
    $stmt->execute(['client_id' => $clientId]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Normaliseer Apify/LinkedIn posted_at naar MySQL DATETIME (Y-m-d H:i:s).
 * Ondersteunt: object {date,timestamp}, ISO/string, unix sec/ms, relatieve strings.
 */
function ce_linkedin_normalize_posted_at(mixed $raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }

    if (is_array($raw)) {
        $date = trim((string) ($raw['date'] ?? $raw['datetime'] ?? ''));
        $fromDate = ce_linkedin_normalize_posted_at($date !== '' ? $date : null);
        if ($fromDate !== null) {
            return $fromDate;
        }

        if (isset($raw['timestamp']) && (is_numeric($raw['timestamp']) || is_string($raw['timestamp']))) {
            return ce_linkedin_normalize_posted_at($raw['timestamp']);
        }

        return null;
    }

    if (is_int($raw) || is_float($raw) || (is_string($raw) && is_numeric(trim($raw)))) {
        $num = (float) (is_string($raw) ? trim($raw) : $raw);
        if ($num <= 0) {
            return null;
        }
        // Apify levert vaak ms; unix-seconden blijven onder ~1e12 tot jaar 33658.
        if ($num >= 1_000_000_000_000) {
            $num = $num / 1000;
        }
        if ($num < 946_684_800 || $num > 4_102_444_800) { // 2000-01-01 .. 2100-01-01
            return null;
        }

        return gmdate('Y-m-d H:i:s', (int) round($num));
    }

    if (!is_string($raw)) {
        return null;
    }

    $value = trim($raw);
    if ($value === '') {
        return null;
    }

    // Relatieve LinkedIn/Apify-strings: "2d", "3 weeks ago", "1 month ago • Visible…"
    if (preg_match(
        '/^(\d+)\s*(seconds?|minutes?|hours?|days?|weeks?|months?|years?|s|m|h|d|w)\b/i',
        $value,
        $rel,
    )) {
        $amount = (int) $rel[1];
        $unit = strtolower($rel[2]);
        $map = [
            's' => 'seconds', 'second' => 'seconds', 'seconds' => 'seconds',
            'm' => 'minutes', 'minute' => 'minutes', 'minutes' => 'minutes',
            'h' => 'hours', 'hour' => 'hours', 'hours' => 'hours',
            'd' => 'days', 'day' => 'days', 'days' => 'days',
            'w' => 'weeks', 'week' => 'weeks', 'weeks' => 'weeks',
            'month' => 'months', 'months' => 'months',
            'year' => 'years', 'years' => 'years',
        ];
        $phpUnit = $map[$unit] ?? null;
        if ($phpUnit !== null && $amount >= 0) {
            try {
                $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
                return $dt->modify("-{$amount} {$phpUnit}")->format('Y-m-d H:i:s');
            } catch (Throwable) {
                // fall through
            }
        }
    }

    // "2025-05-15 14:30:20", ISO-8601, etc.
    $normalized = str_replace('T', ' ', $value);
    $normalized = preg_replace('/\.\d+/', '', $normalized) ?? $normalized;
    $normalized = preg_replace('/(Z|[+-]\d{2}:?\d{2})$/', '', $normalized) ?? $normalized;
    $normalized = trim($normalized);

    try {
        $dt = new DateTimeImmutable($normalized);
        $year = (int) $dt->format('Y');
        if ($year < 2000 || $year > 2100) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

/**
 * Batch-actors wrappen posts soms als { posts: [...] }; flatten naar platte post-items.
 *
 * @param list<mixed> $items
 * @return list<array<string, mixed>>
 */
function ce_linkedin_flatten_apify_items(array $items): array
{
    $out = [];
    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (isset($row['posts']) && is_array($row['posts'])) {
            foreach ($row['posts'] as $post) {
                if (is_array($post)) {
                    $out[] = $post;
                }
            }
            continue;
        }
        $out[] = $row;
    }

    return $out;
}

/** @return array<string, mixed>|null */
function ce_linkedin_parse_apify_item(array $row): ?array
{
    $text = trim((string) ($row['text'] ?? $row['commentary'] ?? $row['postText'] ?? ''));
    if ($text === '') {
        return null;
    }

    $activityUrn = '';
    if (is_array($row['urn'] ?? null)) {
        $activityUrn = trim((string) ($row['urn']['activity_urn'] ?? ''));
        if ($activityUrn === '' && isset($row['urn']['activityUrn'])) {
            $activityUrn = trim((string) $row['urn']['activityUrn']);
        }
    }
    if ($activityUrn === '' && is_scalar($row['urn'] ?? null)) {
        $activityUrn = ce_linkedin_activity_id((string) $row['urn']) ?? '';
    }
    if ($activityUrn === '') {
        $activityUrn = ce_linkedin_activity_id((string) ($row['full_urn'] ?? $row['fullUrn'] ?? '')) ?? '';
    }
    if ($activityUrn === '') {
        $activityUrn = ce_linkedin_activity_id((string) ($row['url'] ?? $row['postUrl'] ?? $row['post_url'] ?? '')) ?? '';
    }
    if ($activityUrn === '') {
        return null;
    }

    $postedRaw = $row['posted_at'] ?? $row['postedAt'] ?? $row['published_at'] ?? $row['publishedAt'] ?? null;
    $postedAt = ce_linkedin_normalize_posted_at($postedRaw);
    if ($postedAt === null && isset($row['timestamp'])) {
        $postedAt = ce_linkedin_normalize_posted_at($row['timestamp']);
    }

    $stats = is_array($row['stats'] ?? null) ? $row['stats'] : [];
    $likes = max(0, (int) ($stats['like'] ?? $stats['likes'] ?? 0));
    $reactions = max(0, (int) ($stats['total_reactions'] ?? $stats['totalReactions'] ?? 0));
    if ($reactions === 0 && $likes > 0) {
        $reactions = $likes;
    }

    return [
        'activity_urn' => $activityUrn,
        'post_url' => trim((string) ($row['url'] ?? $row['postUrl'] ?? $row['post_url'] ?? '')),
        'posted_at' => $postedAt,
        'text_preview' => mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 500),
        'reactions' => $reactions,
        'likes' => $likes,
        'comments' => max(0, (int) ($stats['comments'] ?? 0)),
        'reposts' => max(0, (int) ($stats['reposts'] ?? 0)),
    ];
}

/** @return array<string, string|null> */
function ce_linkedin_article_url_map(PDO $pdo, string $clientId): array
{
    if (!ce_table_exists($pdo, 'articles') || !ce_table_exists($pdo, 'content_slots')) {
        return [];
    }

    require_once __DIR__ . '/archive-repository.php';

    $sql = <<<'SQL'
SELECT a.id, a.social_post_url, a.social_post_id
FROM articles a
INNER JOIN content_slots s ON s.id = a.slot_id
WHERE s.client_id = :client_id
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $clientId]);

    $map = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $url = ce_social_post_url_from_row($row);
        if ($url === null) {
            continue;
        }
        $activityId = ce_linkedin_activity_id($url);
        if ($activityId !== null) {
            $map[$activityId] = (string) ($row['id'] ?? '');
        }
    }

    return $map;
}

/** @param array<string, mixed> $post */
function ce_linkedin_apply_post_to_article(PDO $pdo, string $articleId, array $post): void
{
    ce_ensure_publish_columns($pdo);

    $sets = [];
    $params = ['id' => $articleId];

    if (ce_table_has_column($pdo, 'articles', 'interaction_likes')) {
        $sets[] = 'interaction_likes = :likes';
        $params['likes'] = (int) ($post['likes'] ?? 0);
    }
    if (ce_table_has_column($pdo, 'articles', 'interaction_comments')) {
        $sets[] = 'interaction_comments = :comments';
        $params['comments'] = (int) ($post['comments'] ?? 0);
    }
    // interaction_views is sinds aug 2026 gereserveerd voor echte GA4-paginaweergaven
    // (ce_analytics_sync_article_views) — LinkedIn schrijft hier bewust niet meer naar,
    // dat veroorzaakte dubbeltelling met de losstaande LinkedIn-engagement-som.
    if (ce_table_has_column($pdo, 'articles', 'social_post_url') && !empty($post['post_url'])) {
        $sets[] = 'social_post_url = COALESCE(NULLIF(social_post_url, \'\'), :post_url)';
        $params['post_url'] = (string) $post['post_url'];
    }

    if ($sets === []) {
        return;
    }

    $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')
        ->execute($params);
}

/**
 * @param list<array<string, mixed>> $items
 *
 * @return array{ingested: int, syncedArticles: int}
 */
function ce_linkedin_ingest_apify_items(PDO $pdo, string $sourceId, string $clientId, array $items): array
{
    ce_ensure_linkedin_posts_schema($pdo);
    if (!ce_table_exists($pdo, 'linkedin_posts') || $clientId === '' || $sourceId === '') {
        return ['ingested' => 0, 'syncedArticles' => 0];
    }

    // Tenant-isolatie: alleen schrijven als de bron echt bij deze client hoort.
    // Voorkomt dat LinkedIn-data van tenant A in client_id van tenant B belandt.
    if (!ce_table_exists($pdo, 'sources')) {
        return ['ingested' => 0, 'syncedArticles' => 0];
    }
    $ownerStmt = $pdo->prepare('SELECT client_id FROM sources WHERE id = :id LIMIT 1');
    $ownerStmt->execute(['id' => $sourceId]);
    $sourceClientId = trim((string) ($ownerStmt->fetchColumn() ?: ''));
    if ($sourceClientId === '' || !hash_equals($sourceClientId, $clientId)) {
        return ['ingested' => 0, 'syncedArticles' => 0];
    }

    $articleMap = ce_linkedin_article_url_map($pdo, $clientId);
    $ingested = 0;
    $syncedArticles = 0;

    $stmt = $pdo->prepare(
        <<<'SQL'
INSERT INTO linkedin_posts (
    id, client_id, source_id, activity_urn, post_url, posted_at, text_preview,
    reactions, likes, comments, reposts, article_id
) VALUES (
    :id, :client_id, :source_id, :activity_urn, :post_url, :posted_at, :text_preview,
    :reactions, :likes, :comments, :reposts, :article_id
)
ON DUPLICATE KEY UPDATE
    source_id = VALUES(source_id),
    post_url = VALUES(post_url),
    posted_at = COALESCE(VALUES(posted_at), posted_at),
    text_preview = VALUES(text_preview),
    reactions = VALUES(reactions),
    likes = VALUES(likes),
    comments = VALUES(comments),
    reposts = VALUES(reposts),
    article_id = COALESCE(VALUES(article_id), article_id),
    scraped_at = CURRENT_TIMESTAMP
SQL
    );

    foreach (ce_linkedin_flatten_apify_items($items) as $row) {
        $post = ce_linkedin_parse_apify_item($row);
        if ($post === null) {
            continue;
        }

        $articleId = $articleMap[$post['activity_urn']] ?? null;

        try {
            $stmt->execute([
                'id' => ce_linkedin_uuid_v4(),
                'client_id' => $clientId,
                'source_id' => $sourceId,
                'activity_urn' => $post['activity_urn'],
                'post_url' => $post['post_url'] !== '' ? $post['post_url'] : null,
                'posted_at' => $post['posted_at'],
                'text_preview' => $post['text_preview'],
                'reactions' => $post['reactions'],
                'likes' => $post['likes'],
                'comments' => $post['comments'],
                'reposts' => $post['reposts'],
                'article_id' => $articleId,
            ]);
            $ingested++;

            if ($articleId !== null) {
                ce_linkedin_apply_post_to_article($pdo, $articleId, $post);
                $syncedArticles++;
            }
        } catch (Throwable) {
            // Sla individuele post over bij DB-fout; rest van batch blijft doorgaan.
            continue;
        }
    }

    return ['ingested' => $ingested, 'syncedArticles' => $syncedArticles];
}

/** @return array{reactions: int, likes: int, comments: int, reposts: int, posts: int} */
function ce_linkedin_engagement_aggregate(
    PDO $pdo,
    ?string $clientId,
    string $startDate,
    string $endDate,
): array {
    ce_ensure_linkedin_posts_schema($pdo);

    $empty = [
        'reactions' => 0,
        'likes' => 0,
        'comments' => 0,
        'reposts' => 0,
        'posts' => 0,
    ];

    if ($clientId === null || !ce_table_exists($pdo, 'linkedin_posts')) {
        return $empty;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT
    COALESCE(SUM(reactions), 0) AS reactions,
    COALESCE(SUM(likes), 0) AS likes,
    COALESCE(SUM(comments), 0) AS comments,
    COALESCE(SUM(reposts), 0) AS reposts,
    COUNT(*) AS posts
FROM linkedin_posts
WHERE client_id = :client_id
  AND posted_at IS NOT NULL
  AND DATE(posted_at) BETWEEN :start_date AND :end_date
SQL
    );
    $stmt->execute([
        'client_id' => $clientId,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        return $empty;
    }

    return [
        'reactions' => (int) ($row['reactions'] ?? 0),
        'likes' => (int) ($row['likes'] ?? 0),
        'comments' => (int) ($row['comments'] ?? 0),
        'reposts' => (int) ($row['reposts'] ?? 0),
        'posts' => (int) ($row['posts'] ?? 0),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function ce_linkedin_weekly_engagement(
    PDO $pdo,
    ?string $clientId,
    string $periodStart,
    string $periodEnd,
): array {
    require_once __DIR__ . '/metrics-repository.php';

    $rows = [];
    foreach (ce_iso_weeks_in_range($periodStart, $periodEnd) as $week) {
        $totals = ce_linkedin_engagement_aggregate(
            $pdo,
            $clientId,
            $week['startDate'],
            $week['endDate'],
        );

        $rows[] = [
            'weekLabel' => $week['weekLabel'],
            'weekNumber' => $week['weekNumber'],
            'isoYear' => $week['isoYear'],
            'startDate' => $week['startDate'],
            'endDate' => $week['endDate'],
            'reactions' => $totals['reactions'],
            'likes' => $totals['likes'],
            'comments' => $totals['comments'],
            'reposts' => $totals['reposts'],
            'posts' => $totals['posts'],
        ];
    }

    return $rows;
}

/** @return array<string, mixed> */
function ce_linkedin_engagement_snapshot(PDO $pdo, ?string $clientId): array
{
    $hasSource = ce_linkedin_has_source_for_client($pdo, $clientId);

    $weekNumber = ce_week_start();
    $isoYear = ce_resolve_iso_week_year($weekNumber);
    $range = ce_iso_week_range($weekNumber, $isoYear);
    $weekStart = $range['start']->format('Y-m-d');
    $weekEnd = $range['end']->format('Y-m-d');

    $tz = ce_ce_timezone();
    try {
        $ninetyStart = (new DateTimeImmutable('today', $tz))->modify('-90 days')->format('Y-m-d');
    } catch (Throwable) {
        $ninetyStart = date('Y-m-d', strtotime('-90 days'));
    }
    $today = (new DateTimeImmutable('today', $tz))->format('Y-m-d');

    return [
        'hasSource' => $hasSource,
        'thisWeek' => ce_linkedin_engagement_aggregate($pdo, $clientId, $weekStart, $weekEnd),
        'last90Days' => ce_linkedin_engagement_aggregate($pdo, $clientId, $ninetyStart, $today),
    ];
}

/** @return list<string> */
function ce_linkedin_client_ids_with_sources(PDO $pdo): array
{
    if (!ce_table_exists($pdo, 'sources')) {
        return [];
    }

    $stmt = $pdo->query(
        <<<'SQL'
SELECT DISTINCT client_id
FROM sources
WHERE category IN ('linkedin', 'scraper')
  AND client_id IS NOT NULL
  AND scraper_linkedin_profile IS NOT NULL
  AND scraper_linkedin_profile != ''
ORDER BY client_id ASC
SQL
    );

    $clientIds = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $clientId = trim((string) ($row['client_id'] ?? ''));
        if ($clientId !== '') {
            $clientIds[] = $clientId;
        }
    }

    return $clientIds;
}

/** @return array{clients: int, sources: int, ingested: int, syncedArticles: int} */
function ce_linkedin_sync_all_clients(PDO $pdo): array
{
    $totals = [
        'clients' => 0,
        'sources' => 0,
        'ingested' => 0,
        'syncedArticles' => 0,
    ];

    foreach (ce_linkedin_client_ids_with_sources($pdo) as $clientId) {
        $batch = ce_linkedin_sync_client_sources($pdo, $clientId);
        $totals['clients']++;
        $totals['sources'] += (int) ($batch['sources'] ?? 0);
        $totals['ingested'] += (int) ($batch['ingested'] ?? 0);
        $totals['syncedArticles'] += (int) ($batch['syncedArticles'] ?? 0);
    }

    return $totals;
}

/** @return array{ingested: int, syncedArticles: int, sources: int} */
function ce_linkedin_sync_client_sources(PDO $pdo, ?string $clientId): array
{
    if ($clientId === null || !ce_table_exists($pdo, 'sources')) {
        return ['ingested' => 0, 'syncedArticles' => 0, 'sources' => 0];
    }

    require_once __DIR__ . '/scrape-repository.php';

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT id
FROM sources
WHERE client_id = :client_id
  AND category IN ('linkedin', 'scraper')
  AND scraper_linkedin_profile IS NOT NULL
  AND scraper_linkedin_profile != ''
SQL
    );
    $stmt->execute(['client_id' => $clientId]);

    $ingested = 0;
    $syncedArticles = 0;
    $sources = 0;

    foreach ($stmt->fetchAll() ?: [] as $row) {
        $sourceId = (string) ($row['id'] ?? '');
        if ($sourceId === '') {
            continue;
        }

        $result = ce_run_scraper_source($pdo, $sourceId, null, null, null);
        if (!($result['ok'] ?? false)) {
            continue;
        }

        $sources++;
        $ingested += (int) ($result['run']['linkedin_ingested'] ?? 0);
        $syncedArticles += (int) ($result['run']['linkedin_synced_articles'] ?? 0);
    }

    return [
        'ingested' => $ingested,
        'syncedArticles' => $syncedArticles,
        'sources' => $sources,
    ];
}
