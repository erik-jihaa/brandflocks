<?php

declare(strict_types=1);

require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/hba-v2-repository.php';

/**
 * §8 Portfoliosignalen — max. 2 tegelijk, belangrijkste eerst.
 *
 * @return list<array{id: string, message: string, actionLabel: string, actionHref: string, priority: int}>
 */
function ce_agenda_portfolio_signals(PDO $pdo, ?string $clientId): array
{
    if ($clientId === null) {
        return [];
    }
    if (!ce_table_exists($pdo, 'hba_scores') || !ce_table_exists($pdo, 'articles')) {
        return [];
    }

    $posts = ce_agenda_portfolio_published_posts($pdo, $clientId, 24);
    if (count($posts) < 4) {
        return [];
    }

    $signals = [];
    $declining = ce_agenda_signal_declining_line($posts);
    if ($declining !== null) {
        $signals[] = $declining;
    }

    $kern = ce_agenda_signal_kernformulering($posts);
    if ($kern !== null) {
        $signals[] = $kern;
    }

    $idle = ce_agenda_signal_idle_role($posts);
    if ($idle !== null) {
        $signals[] = $idle;
    }

    $skewed = ce_agenda_signal_skewed_distribution($posts);
    if ($skewed !== null) {
        $signals[] = $skewed;
    }

    usort(
        $signals,
        static fn (array $a, array $b): int => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99),
    );

    return array_slice($signals, 0, 2);
}

/**
 * Zwaarste portfoliosignaal (zelfde rangorde als Agenda — priority 1 eerst).
 *
 * @return array{id: string, message: string, actionLabel: string, actionHref: string, priority: int}|null
 */
function ce_agenda_portfolio_heaviest_signal(PDO $pdo, ?string $clientId): ?array
{
    $signals = ce_agenda_portfolio_signals($pdo, $clientId);

    return $signals[0] ?? null;
}

/** @return list<array{contentrol: string, brandContribution: ?float, kernformuleringHit: ?int, publishedAt: string}> */
function ce_agenda_portfolio_published_posts(PDO $pdo, string $clientId, int $limit): array
{
    $kernSelect = ce_table_has_column($pdo, 'hba_scores', 'kernformulering_hit')
        ? 'hs.kernformulering_hit'
        : 'NULL AS kernformulering_hit';
    $publishedSelect = ce_table_has_column($pdo, 'articles', 'published_at')
        ? 'a.published_at'
        : 'NULL AS published_at';
    $order = ce_table_has_column($pdo, 'articles', 'published_at')
        ? 'COALESCE(a.published_at, hs.scored_at) DESC'
        : 'hs.scored_at DESC';

    $stmt = $pdo->prepare(
        "SELECT hs.contentrol, hs.brand_contribution_score, {$kernSelect}, {$publishedSelect}, hs.scored_at
         FROM hba_scores hs
         INNER JOIN articles a ON a.id = hs.post_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND a.status IN ('published', 'archived')
           AND COALESCE(cs.origin, '') <> 'baseline'
           AND COALESCE(hs.origin, 'app') = 'app'
           AND hs.contentrol IS NOT NULL
           AND TRIM(hs.contentrol) <> ''
         ORDER BY {$order}
         LIMIT " . max(1, min(50, $limit))
    );
    $stmt->execute(['client_id' => $clientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $posts = [];
    foreach ($rows as $row) {
        $publishedAt = trim((string) ($row['published_at'] ?? ''));
        if ($publishedAt === '') {
            $publishedAt = trim((string) ($row['scored_at'] ?? ''));
        }
        if ($publishedAt === '') {
            continue;
        }

        $posts[] = [
            'contentrol' => strtolower(trim((string) ($row['contentrol'] ?? ''))),
            'brandContribution' => $row['brand_contribution_score'] !== null
                ? (float) $row['brand_contribution_score']
                : null,
            'kernformuleringHit' => $row['kernformulering_hit'] !== null
                ? (int) $row['kernformulering_hit']
                : null,
            'publishedAt' => substr($publishedAt, 0, 10),
        ];
    }

    return $posts;
}

/** @param list<array{contentrol: string, brandContribution: ?float, kernformuleringHit: ?int, publishedAt: string}> $posts */
function ce_agenda_signal_declining_line(array $posts): ?array
{
    $scored = array_values(array_filter(
        $posts,
        static fn (array $post): bool => $post['brandContribution'] !== null,
    ));
    if (count($scored) < 12) {
        return null;
    }

    $recent = array_slice($scored, 0, 6);
    $previous = array_slice($scored, 6, 6);
    if (count($previous) < 6) {
        return null;
    }

    $avgRecent = array_sum(array_column($recent, 'brandContribution')) / 6;
    $avgPrevious = array_sum(array_column($previous, 'brandContribution')) / 6;
    if ($avgRecent >= $avgPrevious - 0.05) {
        return null;
    }

    return [
        'id' => 'declining_line',
        'priority' => 1,
        'message' => sprintf(
            'Positionering per post daalt: gemiddeld %s in de laatste zes posts, daarvoor %s.',
            number_format($avgRecent, 1, ',', ''),
            number_format($avgPrevious, 1, ',', ''),
        ),
        'actionLabel' => 'Rapport',
        'actionHref' => '/report',
    ];
}

/** @param list<array{contentrol: string, brandContribution: ?float, kernformuleringHit: ?int, publishedAt: string}> $posts */
function ce_agenda_signal_kernformulering(array $posts): ?array
{
    $cutoff = (new DateTimeImmutable('now', ce_ce_timezone()))->modify('-8 weeks')->format('Y-m-d');
    $recent = array_values(array_filter(
        $posts,
        static fn (array $post): bool => $post['publishedAt'] >= $cutoff,
    ));
    if (count($recent) < 3) {
        return null;
    }

    foreach ($recent as $post) {
        if (($post['kernformuleringHit'] ?? 0) > 0) {
            return null;
        }
    }

    return [
        'id' => 'kernformulering_missing',
        'priority' => 2,
        'message' => 'Geen kernformulering in de laatste acht weken — je merkboodschap is niet zichtbaar in recente posts.',
        'actionLabel' => 'Merkarchitectuur',
        'actionHref' => '/brandarchitecture',
    ];
}

/** @param list<array{contentrol: string, brandContribution: ?float, kernformuleringHit: ?int, publishedAt: string}> $posts */
function ce_agenda_signal_idle_role(array $posts): ?array
{
    $now = new DateTimeImmutable('now', ce_ce_timezone());
    $threeWeeksAgo = $now->modify('-3 weeks')->format('Y-m-d');
    $recentAny = array_values(array_filter(
        $posts,
        static fn (array $post): bool => $post['publishedAt'] >= $threeWeeksAgo,
    ));
    if ($recentAny === []) {
        return null;
    }

    $lastByRole = [];
    foreach ($posts as $post) {
        $role = $post['contentrol'];
        if ($role === '') {
            continue;
        }
        if (!isset($lastByRole[$role]) || $post['publishedAt'] > $lastByRole[$role]) {
            $lastByRole[$role] = $post['publishedAt'];
        }
    }

    $idleRole = null;
    $idleSince = null;
    foreach ($lastByRole as $role => $lastDate) {
        if ($lastDate >= $threeWeeksAgo) {
            continue;
        }
        if ($idleSince === null || $lastDate < $idleSince) {
            $idleRole = $role;
            $idleSince = $lastDate;
        }
    }

    if ($idleRole === null) {
        return null;
    }

    return [
        'id' => 'idle_role',
        'priority' => 3,
        'message' => sprintf(
            'Contentrol %s staat al drie weken stil terwijl je wel publiceert.',
            ce_agenda_contentrol_label($idleRole),
        ),
        'actionLabel' => 'Thema\'s',
        'actionHref' => '/theme',
    ];
}

/** @param list<array{contentrol: string, brandContribution: ?float, kernformuleringHit: ?int, publishedAt: string}> $posts */
function ce_agenda_signal_skewed_distribution(array $posts): ?array
{
    $lastSix = array_slice($posts, 0, 6);
    if (count($lastSix) < 6) {
        return null;
    }

    $counts = [];
    foreach ($lastSix as $post) {
        $role = $post['contentrol'];
        if ($role === '') {
            continue;
        }
        $counts[$role] = ($counts[$role] ?? 0) + 1;
    }

    arsort($counts);
    $topRole = array_key_first($counts);
    $topCount = $topRole !== null ? (int) ($counts[$topRole] ?? 0) : 0;
    if ($topRole === null || $topCount < 4) {
        return null;
    }

    return [
        'id' => 'skewed_distribution',
        'priority' => 4,
        'message' => sprintf(
            'Vier van de laatste zes posts zijn %s — je portfolio is scheef.',
            ce_agenda_contentrol_label($topRole),
        ),
        'actionLabel' => 'Rapport',
        'actionHref' => '/report',
    ];
}

/** §6 klanttaal — Community/Service blijven. */
function ce_agenda_contentrol_label(string $role): string
{
    return match (strtolower(trim($role))) {
        'positionering' => 'Positionering',
        'bewijs' => 'Bewijs',
        'educatie' => 'Kennis delen',
        'activatie' => 'Oproep tot actie',
        'community' => 'Community',
        'service' => 'Service',
        'employer_culture' => 'Team en cultuur',
        'reactief' => 'Reactie op nieuws',
        default => str_replace('_', ' ', $role),
    };
}
