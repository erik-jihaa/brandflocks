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

/** @param array<string, mixed> $row */
function li_field(array $row, string $path): mixed
{
    if (str_contains($path, '/')) {
        $parts = explode('/', $path);
        $cur = $row;
        foreach ($parts as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return null;
            }
            $cur = $cur[$part];
        }

        return $cur;
    }

    return $row[$path] ?? null;
}

function li_normalize_posted_at(mixed $raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }

    if (is_array($raw)) {
        $date = trim((string) ($raw['date'] ?? $raw['datetime'] ?? ''));
        $fromDate = li_normalize_posted_at($date !== '' ? $date : null);
        if ($fromDate !== null) {
            return $fromDate;
        }

        if (isset($raw['timestamp']) && (is_numeric($raw['timestamp']) || is_string($raw['timestamp']))) {
            return li_normalize_posted_at($raw['timestamp']);
        }

        return null;
    }

    if (is_int($raw) || is_float($raw) || (is_string($raw) && is_numeric(trim($raw)))) {
        $num = (float) (is_string($raw) ? trim($raw) : $raw);
        if ($num <= 0) {
            return null;
        }
        if ($num >= 1_000_000_000_000) {
            $num = $num / 1000;
        }
        if ($num < 946_684_800 || $num > 4_102_444_800) {
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

/** @param list<mixed> $items @return list<array<string, mixed>> */
function li_flatten_apify_items(array $items): array
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

function li_normalize_key(string $value): string
{
    $value = mb_strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? $value;

    return $value;
}

function li_author_matches_company(string $authorName, string $companySlug, ?string $companyDisplayName = null): bool
{
    $authorKey = li_normalize_key($authorName);
    if ($authorKey === '') {
        return false;
    }

    $slugKey = li_normalize_key($companySlug);
    if ($slugKey !== '' && ($authorKey === $slugKey || str_contains($authorKey, $slugKey) || str_contains($slugKey, $authorKey))) {
        return true;
    }

    if ($companyDisplayName !== null && $companyDisplayName !== '') {
        $displayKey = li_normalize_key($companyDisplayName);
        if ($displayKey !== '' && ($authorKey === $displayKey || str_contains($authorKey, $displayKey))) {
            return true;
        }
    }

    return false;
}

function li_text_dedupe_key(array $row): string
{
    $text = trim((string) (li_field($row, 'text') ?? li_field($row, 'commentary') ?? ''));
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $prefix = mb_substr($text, 0, 120);

    return li_normalize_key($prefix);
}

/** @param array<string, mixed> $row */
function li_post_reactions(array $row): int
{
    $stats = li_field($row, 'stats');
    if (!is_array($stats)) {
        $stats = [];
    }

    $reactions = max(0, (int) ($stats['total_reactions'] ?? $stats['totalReactions'] ?? 0));
    if ($reactions === 0) {
        $likes = max(0, (int) ($stats['like'] ?? $stats['likes'] ?? 0));
        $reactions = $likes;
    }

    return $reactions;
}

/** @param array<string, mixed> $row */
function li_post_comments(array $row): int
{
    $stats = li_field($row, 'stats');
    if (!is_array($stats)) {
        return 0;
    }

    return max(0, (int) ($stats['comments'] ?? 0));
}

/** @param array<string, mixed> $row */
function li_post_media_type(array $row): string
{
    $media = li_field($row, 'media');
    if (!is_array($media)) {
        return 'none';
    }

    $type = strtolower(trim((string) ($media['type'] ?? 'none')));

    return $type !== '' ? $type : 'none';
}

function li_slug_to_display_name(string $slug): string
{
    $slug = str_replace(['-', '_'], ' ', $slug);

    return mb_convert_case(trim($slug), MB_CASE_TITLE, 'UTF-8');
}

/** @param list<mixed> $items @return list<array<string, mixed>> */
function li_normalize_school_apify_items(array $items, string $slug): array
{
    $displayName = li_slug_to_display_name($slug);
    $out = [];

    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (isset($row['engagement']) && is_array($row['engagement'])) {
            $author = is_array($row['author'] ?? null) ? $row['author'] : [];
            $profile = is_array($author['profile'] ?? null) ? $author['profile'] : [];
            $engagement = $row['engagement'];
            $media = is_array($row['media'] ?? null) ? $row['media'] : [];
            $mediaType = 'none';
            if (!empty($media['video'])) {
                $mediaType = 'video';
            } elseif (!empty($media['imageUrls']) && is_array($media['imageUrls'])) {
                $mediaType = 'image';
            } elseif (!empty($media['document'])) {
                $mediaType = 'article';
            }

            $out[] = [
                'text' => trim((string) ($row['text'] ?? '')),
                'posted_at' => ['date' => li_normalize_posted_at($row['publishedAt'] ?? $row['postedAt'] ?? null)],
                'stats' => [
                    'total_reactions' => max(0, (int) ($engagement['reactionCount'] ?? 0)),
                    'comments' => max(0, (int) ($engagement['commentCount'] ?? 0)),
                ],
                'media' => ['type' => $mediaType],
                'author' => [
                    'name' => trim((string) ($author['name'] ?? $displayName)),
                    'follower_count' => is_numeric($profile['followerCount'] ?? null)
                        ? (int) $profile['followerCount']
                        : null,
                ],
            ];
            continue;
        }

        if (isset($row['content']) || (isset($row['likes']) && !isset($row['stats']))) {
            $text = trim((string) ($row['content'] ?? $row['text'] ?? ''));
            $postedRaw = $row['timestamp'] ?? $row['text_date'] ?? $row['postedAt'] ?? null;
            $mediaLabel = strtolower(trim((string) ($row['tipo_post'] ?? $row['type'] ?? '')));
            $mediaType = match (true) {
                str_contains($mediaLabel, 'video') => 'video',
                str_contains($mediaLabel, 'image') => 'image',
                str_contains($mediaLabel, 'article') => 'article',
                default => 'none',
            };

            $out[] = [
                'text' => $text,
                'posted_at' => ['date' => li_normalize_posted_at($postedRaw)],
                'stats' => [
                    'total_reactions' => max(0, (int) ($row['likes'] ?? $row['numLikes'] ?? 0)),
                    'comments' => max(0, (int) ($row['comments'] ?? $row['numComments'] ?? 0)),
                ],
                'media' => ['type' => $mediaType],
                'author' => [
                    'name' => trim((string) ($row['authorName'] ?? $displayName)),
                    'follower_count' => is_numeric($row['follower_count'] ?? null) ? (int) $row['follower_count'] : null,
                ],
            ];
            continue;
        }

        if (isset($row['text']) || isset($row['commentary'])) {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $rawItems
 *
 * @return array{
 *   company_slug: string,
 *   company_name: string,
 *   raw_count: int,
 *   posts: list<array<string, mixed>>,
 *   metrics: array<string, mixed>
 * }
 */
function li_analyze_posts(array $rawItems, string $companySlug, ?string $companyDisplayName = null): array
{
    $flat = li_flatten_apify_items($rawItems);
    $rawCount = count($flat);

    $seenKeys = [];
    $posts = [];
    $displayName = $companyDisplayName;

    foreach ($flat as $row) {
        $author = li_field($row, 'author');
        $authorName = trim((string) (li_field($row, 'author/name') ?? (is_array($author) ? ($author['name'] ?? '') : '')));
        if ($authorName === '') {
            continue;
        }

        if (!li_author_matches_company($authorName, $companySlug, $displayName)) {
            continue;
        }

        if ($displayName === null || $displayName === '') {
            $displayName = $authorName;
        }

        $dedupeKey = li_text_dedupe_key($row);
        if ($dedupeKey === '' || isset($seenKeys[$dedupeKey])) {
            continue;
        }
        $seenKeys[$dedupeKey] = true;

        $postedRaw = li_field($row, 'posted_at/date')
            ?? li_field($row, 'posted_at')
            ?? li_field($row, 'postedAt')
            ?? li_field($row, 'timestamp');
        $postedAt = li_normalize_posted_at($postedRaw);

        $text = trim((string) (li_field($row, 'text') ?? li_field($row, 'commentary') ?? ''));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $followerRaw = li_field($row, 'author/follower_count');
        if (!is_numeric($followerRaw) && is_array($author)) {
            $followerRaw = $author['follower_count'] ?? $author['followers'] ?? null;
        }

        $posts[] = [
            'text' => $text,
            'posted_at' => $postedAt,
            'reactions' => li_post_reactions($row),
            'comments' => li_post_comments($row),
            'media_type' => li_post_media_type($row),
            'language' => trim((string) (li_field($row, 'post_language_code') ?? li_field($row, 'language') ?? '')),
            'follower_count' => is_numeric($followerRaw) ? (int) $followerRaw : null,
        ];
    }

    usort($posts, static function (array $a, array $b): int {
        return strcmp((string) ($a['posted_at'] ?? ''), (string) ($b['posted_at'] ?? ''));
    });

    $companyName = $displayName ?? li_slug_to_display_name($companySlug);
    $metrics = li_compute_metrics($posts, $companyName);

    return [
        'company_slug' => $companySlug,
        'company_name' => $companyName,
        'raw_count' => $rawCount,
        'posts' => $posts,
        'metrics' => $metrics,
    ];
}

/** @param list<array<string, mixed>> $posts @return array<string, mixed> */
function li_compute_metrics(array $posts, string $companyName): array
{
    $count = count($posts);
    $dates = array_values(array_filter(array_map(
        static fn (array $p): ?string => is_string($p['posted_at'] ?? null) ? $p['posted_at'] : null,
        $posts,
    )));

    $periodStart = null;
    $periodEnd = null;
    $postsPerWeek = null;

    if ($dates !== []) {
        sort($dates);
        $periodStart = substr($dates[0], 0, 10);
        $periodEnd = substr($dates[count($dates) - 1], 0, 10);

        try {
            $start = new DateTimeImmutable($periodStart);
            $end = new DateTimeImmutable($periodEnd);
            $days = max(1, (int) $start->diff($end)->days + 1);
            // Eén post in een korte periode geeft een misleidend ritme (bijv. 7 posts/week).
            if ($count >= 3 && $days >= 14) {
                $postsPerWeek = $count > 0 ? round($count / ($days / 7), 1) : 0.0;
            }
        } catch (Throwable) {
            $postsPerWeek = null;
        }
    }

    $silences = li_compute_silences($dates);
    $reactions = array_map(static fn (array $p): int => (int) ($p['reactions'] ?? 0), $posts);
    $avgReactions = $count > 0 ? round(array_sum($reactions) / $count, 1) : 0.0;
    $medianReactions = li_median($reactions);

    $noComments = 0;
    foreach ($posts as $post) {
        if (((int) ($post['comments'] ?? 0)) === 0) {
            $noComments++;
        }
    }

    $formatCounts = [];
    foreach ($posts as $post) {
        $type = (string) ($post['media_type'] ?? 'none');
        $formatCounts[$type] = ($formatCounts[$type] ?? 0) + 1;
    }

    $bestPost = null;
    $weakestPosts = [];
    $topPosts = [];
    if ($posts !== []) {
        $sorted = $posts;
        usort($sorted, static fn (array $a, array $b): int => ((int) ($b['reactions'] ?? 0)) <=> ((int) ($a['reactions'] ?? 0)));
        $best = $sorted[0];
        $bestPost = [
            'rank' => 1,
            'reactions' => (int) ($best['reactions'] ?? 0),
            'text' => li_truncate_text((string) ($best['text'] ?? ''), 200),
            'posted_at' => substr((string) ($best['posted_at'] ?? ''), 0, 10),
        ];
        foreach (array_slice($sorted, 0, min(5, count($sorted))) as $rankIdx => $p) {
            $topPosts[] = [
                'rank' => $rankIdx + 1,
                'reactions' => (int) ($p['reactions'] ?? 0),
                'text' => li_truncate_text((string) ($p['text'] ?? ''), 300),
                'posted_at' => substr((string) ($p['posted_at'] ?? ''), 0, 10),
            ];
        }
        $weakest = array_slice(array_reverse($sorted), 0, min(3, count($sorted)));
        foreach ($weakest as $w) {
            $weakestPosts[] = [
                'reactions' => (int) ($w['reactions'] ?? 0),
                'posted_at' => substr((string) ($w['posted_at'] ?? ''), 0, 10),
            ];
        }
    }

    $followers = null;
    foreach ($posts as $post) {
        if (isset($post['follower_count']) && is_int($post['follower_count']) && $post['follower_count'] > 0) {
            $followers = $post['follower_count'];
            break;
        }
    }

    $daysSinceLastPost = li_days_since_ymd($periodEnd);

    $observations = li_generate_observations(
        $posts,
        $silences,
        $avgReactions,
        $medianReactions,
        $noComments,
        $count,
        $formatCounts,
        $bestPost,
        $daysSinceLastPost,
    );

    $activityPoints = [];
    foreach ($posts as $post) {
        $postedAt = substr((string) ($post['posted_at'] ?? ''), 0, 10);
        if ($postedAt === '') {
            continue;
        }
        $activityPoints[] = [
            'date' => $postedAt,
            'reactions' => (int) ($post['reactions'] ?? 0),
        ];
    }
    usort(
        $activityPoints,
        static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']),
    );

    return [
        'company_name' => $companyName,
        'posts_count' => $count,
        'activity_points' => $activityPoints,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'posts_per_week' => $postsPerWeek,
        'days_since_last_post' => $daysSinceLastPost,
        'silences_days' => $silences,
        'avg_reactions' => $avgReactions,
        'median_reactions' => $medianReactions,
        'posts_without_comments' => $noComments,
        'posts_without_comments_pct' => $count > 0 ? (int) round(100 * $noComments / $count) : 0,
        'format_counts' => $formatCounts,
        'best_post' => $bestPost,
        'top_posts' => $topPosts,
        'weakest_posts' => $weakestPosts,
        'followers' => $followers,
        'hashtags' => li_top_hashtags($posts, 10),
        'languages' => li_language_distribution($posts),
        'observations' => $observations,
        'benefits' => li_benefits_from_observations($observations),
    ];
}

/** @param list<string> $dateTimes @return list<int> */
function li_compute_silences(array $dateTimes): array
{
    if (count($dateTimes) < 2) {
        return [];
    }

    $gaps = [];
    for ($i = 1, $n = count($dateTimes); $i < $n; $i++) {
        try {
            $prev = new DateTimeImmutable(substr($dateTimes[$i - 1], 0, 10));
            $cur = new DateTimeImmutable(substr($dateTimes[$i], 0, 10));
            $gaps[] = (int) $prev->diff($cur)->days;
        } catch (Throwable) {
            continue;
        }
    }

    rsort($gaps);

    return array_slice($gaps, 0, 3);
}

/** @param list<int|float> $values */
function li_median(array $values): float
{
    if ($values === []) {
        return 0.0;
    }

    sort($values);
    $n = count($values);
    $mid = intdiv($n, 2);

    if ($n % 2 === 1) {
        return round((float) $values[$mid], 1);
    }

    return round(((float) $values[$mid - 1] + (float) $values[$mid]) / 2, 1);
}

/** @param list<array<string, mixed>> $posts @return list<array{tag: string, count: int}> */
function li_top_hashtags(array $posts, int $limit): array
{
    $counts = [];
    foreach ($posts as $post) {
        $text = (string) ($post['text'] ?? '');
        if (preg_match_all('/#(\w+)/u', $text, $matches)) {
            foreach ($matches[1] as $tag) {
                $key = mb_strtolower($tag);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
    }

    arsort($counts);
    $out = [];
    foreach (array_slice($counts, 0, $limit, true) as $tag => $count) {
        $out[] = ['tag' => $tag, 'count' => $count];
    }

    return $out;
}

/** @param list<array<string, mixed>> $posts @return list<array{code: string, count: int, pct: int}> */
function li_language_distribution(array $posts): array
{
    $counts = [];
    foreach ($posts as $post) {
        $code = trim((string) ($post['language'] ?? ''));
        if ($code === '') {
            $code = 'onbekend';
        }
        $counts[$code] = ($counts[$code] ?? 0) + 1;
    }

    $total = max(1, count($posts));
    $out = [];
    arsort($counts);
    foreach ($counts as $code => $count) {
        $out[] = [
            'code' => $code,
            'count' => $count,
            'pct' => (int) round(100 * $count / $total),
        ];
    }

    return $out;
}

function li_days_between_ymd(?string $fromYmd, ?string $toYmd): ?int
{
    $fromYmd = substr(trim((string) $fromYmd), 0, 10);
    $toYmd = substr(trim((string) $toYmd), 0, 10);
    if ($fromYmd === '' || $toYmd === ''
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromYmd)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toYmd)
    ) {
        return null;
    }

    try {
        $from = new DateTimeImmutable($fromYmd);
        $to = new DateTimeImmutable($toYmd);
        $diff = $from->diff($to);

        return $diff->invert === 1 ? 0 : (int) $diff->days;
    } catch (Throwable) {
        return null;
    }
}

function li_days_since_ymd(?string $ymd): ?int
{
    try {
        $today = new DateTimeImmutable('today');

        return li_days_between_ymd($ymd, $today->format('Y-m-d'));
    } catch (Throwable) {
        return null;
    }
}

function li_truncate_text(string $text, int $maxLen): string
{
    if (mb_strlen($text) <= $maxLen) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $maxLen - 1)) . '…';
}

/**
 * @param list<array<string, mixed>> $posts
 * @param list<int> $silences
 * @param array<string, int> $formatCounts
 * @param array{reactions: int, text: string, posted_at: string}|null $bestPost
 *
 * @return list<array{id: string, title: string, body: string}>
 */
function li_generate_observations(
    array $posts,
    array $silences,
    float $avgReactions,
    float $medianReactions,
    int $noComments,
    int $count,
    array $formatCounts,
    ?array $bestPost,
    ?int $daysSinceLastPost = null,
): array {
    $obs = [];

    if ($daysSinceLastPost !== null && $daysSinceLastPost > 30) {
        $obs[] = [
            'id' => 'stale_page',
            'title' => 'Pagina ligt stil',
            'body' => 'De laatste post dateert van ' . $daysSinceLastPost . ' dagen geleden — een pagina die zo lang stilligt, bouwt geen herkenning op.',
        ];
    }

    if ($silences !== [] && max($silences) > 21) {
        $obs[] = [
            'id' => 'no_rhythm',
            'title' => 'Geen vast ritme',
            'body' => 'De langste stilte is ' . max($silences) . ' dagen — dat wijst op onregelmatig posten in plaats van een vast ritme.',
        ];
    }

    if ($count > 0 && ($noComments / $count) > 0.7) {
        $obs[] = [
            'id' => 'one_way',
            'title' => 'Eenrichtingsverkeer',
            'body' => $noComments === $count
                ? 'Alle ' . $count . ' posts kregen geen opmerkingen — het gesprek blijft uit.'
                : $noComments . ' van de ' . $count . ' posts kregen geen opmerkingen — het gesprek blijft grotendeels uit.',
        ];
    }

    if ($count > 0 && $formatCounts !== []) {
        $maxType = '';
        $maxCount = 0;
        foreach ($formatCounts as $type => $c) {
            if ($c > $maxCount) {
                $maxCount = $c;
                $maxType = $type;
            }
        }
        if ($maxCount / $count > 0.8) {
            $label = $maxType === 'none' ? 'tekst zonder media' : $maxType;
            $obs[] = [
                'id' => 'low_format_variety',
                'title' => 'Weinig variatie in vorm',
                'body' => 'Meer dan 80% van de posts is ' . $label . ' — weinig afwisseling in format.',
            ];
        }
    }

    // 'low_engagement' (reacties t.o.v. volgers) is bewust verwijderd: zonder benchmark tegen
    // vergelijkbare pagina's is 23,8 reacties bij 620 volgers evengoed prima te noemen als slecht —
    // dat hangt af van bereik, branche en posttype. Een vuistregel die dat niet kan onderscheiden
    // hoort niet als observatie in de mail te staan (feedback op Doornvogel-scan, aug 2026).

    if ($bestPost !== null && $medianReactions > 0 && ($bestPost['reactions'] ?? 0) >= 2 * $medianReactions) {
        $obs[] = [
            'id' => 'peak_unused',
            'title' => 'Onbenutte piek',
            'body' => 'De sterkste post (' . ($bestPost['reactions'] ?? 0) . ' reacties) presteert duidelijk boven de rest — dat thema of format is kennelijk interessant voor het publiek.',
        ];
    }

    if (count($posts) >= 6) {
        $recent = array_slice($posts, -3);
        $recentAvg = array_sum(array_map(static fn (array $p): int => (int) ($p['reactions'] ?? 0), $recent)) / 3;
        if ($avgReactions > 0 && $recentAvg < 0.5 * $avgReactions) {
            $obs[] = [
                'id' => 'recent_decline',
                'title' => 'Recente terugval',
                'body' => 'De laatste drie posts scoren gemiddeld ' . round($recentAvg, 1) . ' reacties — merkbaar lager dan het totaalgemiddelde van ' . $avgReactions . '.',
            ];
        }
    }

    return $obs;
}

/**
 * @param list<array{id: string, title: string, body: string}> $observations
 *
 * @return list<array{id: string, title: string, body: string}>
 */
function li_benefits_from_observations(array $observations): array
{
    $map = [
        'no_rhythm' => [
            'title' => 'Vaste planning',
            'body' => 'Een vast ritme (bijvoorbeeld één vaste dag per week) maakt posten voorspelbaar, voor het eigen team én voor de volgers.',
        ],
        'one_way' => [
            'title' => 'Redactiestap vóór publicatie',
            'body' => 'Posts die een vraag stellen of een standpunt innemen, nodigen vaker uit tot een opmerking dan alleen-informatieve berichten.',
        ],
        'stale_page' => [
            'title' => 'Weer zichtbaar worden',
            'body' => 'Een regelmatig ritme, ook als het één post per week is, houdt het merk in beeld bij volgers en bouwt herkenning op.',
        ],
        'low_format_variety' => [
            'title' => 'Variatie in formats',
            'body' => 'Afwisseling tussen tekst, beeld, artikel en video houdt de feed levendig en bereikt verschillende voorkeuren in het netwerk.',
        ],
        'peak_unused' => [
            'title' => 'Herhaal wat werkt',
            'body' => 'De best presterende post laat zien waar het publiek op reageert — de vraag is wel of dat een herhaalbaar concept is, of een eenmalig moment (jubileum, award, unieke gebeurtenis).',
        ],
        'recent_decline' => [
            'title' => 'Momentum herpakken',
            'body' => 'Een terugval in recente posts is vaak te corrigeren door terug te gaan naar thema\'s en formats die eerder beter scoorden.',
        ],
    ];

    $ids = array_column($observations, 'id');
    $benefits = [];
    foreach ($ids as $id) {
        if (isset($map[$id])) {
            $benefits[] = [
                'id' => $id,
                'title' => $map[$id]['title'],
                'body' => $map[$id]['body'],
            ];
        }
    }

    if ($benefits === []) {
        $benefits[] = [
            'id' => 'fallback',
            'title' => 'Blijven meten en bijsturen',
            'body' => 'Zelfs bij een stabiel profiel helpt periodiek terugkijken: wat trok aandacht, wanneer werd er gepost, en wat past bij de doelgroep?',
        ];
    }

    return $benefits;
}

function li_format_dutch_date(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '—';
    }

    $months = [
        1 => 'jan', 2 => 'feb', 3 => 'mrt', 4 => 'apr', 5 => 'mei', 6 => 'jun',
        7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec',
    ];

    try {
        $dt = new DateTimeImmutable($ymd);

        return (int) $dt->format('j') . ' ' . ($months[(int) $dt->format('n')] ?? $dt->format('M')) . ' ' . $dt->format('Y');
    } catch (Throwable) {
        return $ymd;
    }
}

function li_format_period(?string $start, ?string $end): string
{
    if ($start === null || $end === null) {
        return 'onbekende periode';
    }

    return li_format_dutch_date($start) . ' – ' . li_format_dutch_date($end);
}
