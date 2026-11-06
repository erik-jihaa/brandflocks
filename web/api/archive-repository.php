<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/publish-repository.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/slot-repository.php';

/** @param array<string, mixed> $row */
function ce_social_post_url_from_row(array $row): ?string
{
    if (ce_table_has_column_from_row($row, 'social_post_url')) {
        $url = trim((string) ($row['social_post_url'] ?? ''));
        if ($url !== '') {
            return $url;
        }
    }

    $legacy = trim((string) ($row['social_post_id'] ?? ''));
    if ($legacy !== '' && preg_match('#^https?://#i', $legacy)) {
        return $legacy;
    }

    return null;
}

/** @param array<string, mixed> $row */
function ce_table_has_column_from_row(array $row, string $column): bool
{
    return array_key_exists($column, $row);
}

/** @return list<array<string, mixed>> */
function ce_fetch_archive_reports(PDO $pdo, ?string $query = null): array
{
    require_once __DIR__ . '/report-repository.php';
    ce_ensure_performance_reports_table($pdo);

    $clientId = ce_default_client_id($pdo);
    $titleCol = ce_table_has_column($pdo, 'performance_reports', 'report_title') ? ', report_title' : '';
    $sql = 'SELECT id, period_start, period_end, narrative, generated_at' . $titleCol . ' FROM performance_reports WHERE 1=1';
    $params = [];
    if ($clientId !== null) {
        $sql .= ' AND client_id = :client_id';
        $params['client_id'] = $clientId;
    }
    $q = trim((string) ($query ?? ''));
    if ($q !== '') {
        $searchCols = 'narrative LIKE :q OR period_start LIKE :q OR period_end LIKE :q';
        if ($titleCol !== '') {
            $searchCols .= ' OR report_title LIKE :q';
        }
        $sql .= ' AND (' . $searchCols . ')';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY generated_at DESC LIMIT 30';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $reports = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $reports[] = [
            'reportId' => (string) ($row['id'] ?? ''),
            'title' => ce_report_display_title($row),
            'narrativePreview' => mb_substr(trim((string) ($row['narrative'] ?? '')), 0, 160),
            'generatedAt' => (string) ($row['generated_at'] ?? ''),
            'pdfUrl' => '/api/reports/' . ($row['id'] ?? '') . '/pdf',
        ];
    }

    return $reports;
}

/** @return list<array<string, mixed>> */
function ce_archive_item_matches_query(array $item, string $query): bool
{
    $q = mb_strtolower(trim($query));
    if ($q === '') {
        return true;
    }

    $parts = [
        (string) ($item['title'] ?? ''),
        (string) ($item['weekLabel'] ?? ''),
        (string) ($item['archiveNote'] ?? ''),
        (string) ($item['socialPostUrl'] ?? ''),
        (string) ($item['socialPostId'] ?? ''),
        (string) ($item['publishedAt'] ?? ''),
    ];

    return mb_strpos(mb_strtolower(implode(' ', $parts)), $q) !== false;
}

/** @return list<array<string, mixed>> */
function ce_fetch_archive_items(PDO $pdo, ?string $query = null): array
{
    ce_ensure_publish_columns($pdo);

    $clientId = ce_default_client_id($pdo);
    $scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId, 'archive_client_id');

    $slotArchived = ce_archive_status($pdo, 'content_slots');
    $articleArchived = ce_archive_status($pdo, 'articles');

    $extra = '';
    if (ce_table_has_column($pdo, 'articles', 'published_at')) {
        $extra .= ', a.published_at';
    }
    if (ce_table_has_column($pdo, 'articles', 'social_post_id')) {
        $extra .= ', a.social_post_id';
    }
    if (ce_table_has_column($pdo, 'articles', 'social_post_url')) {
        $extra .= ', a.social_post_url';
    }
    if (ce_table_has_column($pdo, 'articles', 'archive_note')) {
        $extra .= ', a.archive_note';
    }
    if (ce_table_has_column($pdo, 'articles', 'promo_budget')) {
        $extra .= ', a.promo_budget';
    }
    if (ce_table_has_column($pdo, 'articles', 'interaction_views')) {
        $extra .= ', a.interaction_views, a.interaction_likes, a.interaction_comments';
    }

    $sql = <<<SQL
SELECT
    s.id AS slot_id,
    s.phase_key,
    s.theme,
    a.id AS article_id,
    a.headline,
    a.headline_human,
    a.status AS article_status
    $extra
FROM content_slots s
LEFT JOIN articles a ON a.slot_id = s.id
WHERE (s.status = :slot_status OR a.status = :article_status)
SQL;
    $sql .= $scope['sql'] . "\nORDER BY s.phase_key DESC, a.published_at DESC, s.theme ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(
        ['slot_status' => $slotArchived, 'article_status' => $articleArchived],
        $scope['params'],
    ));

    $items = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $items[] = [
            'slotId' => $row['slot_id'],
            'articleId' => $row['article_id'],
            'weekLabel' => ce_week_label($row['phase_key'] ?? null),
            'title' => ce_article_headline($row) ?: ($row['theme'] ?: 'Untitled'),
            'publishedAt' => $row['published_at'] ?? null,
            'socialPostId' => $row['social_post_id'] ?? null,
            'socialPostUrl' => ce_social_post_url_from_row($row),
            'promoBudget' => isset($row['promo_budget']) ? (float) $row['promo_budget'] : null,
            'archiveNote' => ce_redact_archive_note_for_tenant(
                $pdo,
                $row['archive_note'] ?? null,
                ce_article_headline($row),
                $row['theme'] ?? null,
            ),
            'interactionViews' => isset($row['interaction_views']) ? (int) $row['interaction_views'] : null,
            'interactionLikes' => isset($row['interaction_likes']) ? (int) $row['interaction_likes'] : null,
            'interactionComments' => isset($row['interaction_comments']) ? (int) $row['interaction_comments'] : null,
        ];
    }

    $q = trim((string) ($query ?? ''));
    if ($q !== '') {
        $items = array_values(array_filter(
            $items,
            static fn (array $item): bool => ce_archive_item_matches_query($item, $q),
        ));
    }

    return $items;
}

/** Zet ISO-8601 / datetime-local naar MySQL DATETIME (Y-m-d H:i:s). */
/** A6: testverwachting niet tonen aan ingelogde klantgebruikers (alle tenantrollen). */
function ce_redact_archive_note_for_tenant(PDO $pdo, ?string $note, ?string $headline = null, ?string $theme = null): ?string
{
    if ($note === null || trim($note) === '') {
        return $note;
    }
    if (!ce_request_is_tenant_scoped($pdo)) {
        return $note;
    }
    if (str_starts_with(trim($note), 'Verwacht:')) {
        return null;
    }
    $title = trim((string) ($headline ?? ''));
    $th = trim((string) ($theme ?? ''));
    if (str_contains($title, 'HBA-test:') || str_starts_with($th, 'HBA-test:')) {
        return null;
    }

    return $note;
}

function ce_normalize_mysql_datetime(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
        return $raw;
    }

    try {
        return (new DateTimeImmutable($raw))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

/** @param array<string, mixed> $body */
function ce_update_archive_metadata(PDO $pdo, string $slotId, array $body): ?array
{
    ce_ensure_publish_columns($pdo);

    $row = ce_fetch_article_by_slot($pdo, $slotId);
    if (!$row) {
        return null;
    }

    $articleId = (string) $row['id'];
    $fields = [];
    $params = ['id' => $articleId];

    $map = [
        'publishedAt' => 'published_at',
        'socialPostId' => 'social_post_id',
        'socialPostUrl' => 'social_post_url',
        'promoBudget' => 'promo_budget',
        'archiveNote' => 'archive_note',
        'interactionViews' => 'interaction_views',
        'interactionLikes' => 'interaction_likes',
        'interactionComments' => 'interaction_comments',
    ];

    foreach ($map as $key => $column) {
        if (!array_key_exists($key, $body) || !ce_table_has_column($pdo, 'articles', $column)) {
            continue;
        }
        $value = $body[$key];
        if (str_starts_with($column, 'interaction_')) {
            $value = $value === null || $value === '' ? null : (int) $value;
        } elseif ($column === 'promo_budget') {
            $value = $value === null || $value === '' ? null : (float) $value;
        } elseif ($column === 'published_at') {
            $value = ce_normalize_mysql_datetime($value);
        } else {
            $value = $value === null ? null : trim((string) $value);
            if ($value === '') {
                $value = null;
            }
        }
        $fields[] = "$column = :$column";
        $params[$column] = $value;
    }

    if ($fields !== []) {
        $pdo->prepare('UPDATE articles SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
    }

    return ce_archive_item_from_row($pdo, ce_fetch_article_by_slot($pdo, $slotId));
}

/** @param array<string, mixed>|null $row */
function ce_archive_item_from_row(PDO $pdo, ?array $row): ?array
{
    if (!$row) {
        return null;
    }

    $row = ce_hydrate_article_publish_url($pdo, $row);

    $slotArchived = ce_archive_status($pdo, 'content_slots');
    $articleArchived = ce_archive_status($pdo, 'articles');
    $slotStatus = strtolower(trim((string) ($row['slot_status'] ?? '')));
    $articleStatus = strtolower(trim((string) ($row['status'] ?? '')));
    $isArchived = $slotStatus === strtolower($slotArchived) || $articleStatus === strtolower($articleArchived);

    return [
        'slotId' => $row['slot_id'],
        'articleId' => $row['id'] ?? null,
        'weekLabel' => ce_week_label($row['phase_key'] ?? null),
        'title' => ce_article_headline($row) ?: ($row['theme'] ?? 'Untitled'),
        'publishedAt' => $row['published_at'] ?? null,
        'socialPostId' => $row['social_post_id'] ?? null,
        'socialPostUrl' => ce_social_post_url_from_row($row),
        'publishLinks' => ce_article_publish_links($pdo, $row),
        'promoBudget' => isset($row['promo_budget']) ? (float) $row['promo_budget'] : null,
        'archiveNote' => ce_redact_archive_note_for_tenant(
            $pdo,
            $row['archive_note'] ?? null,
            ce_article_headline($row),
            $row['theme'] ?? null,
        ),
        'interactionViews' => isset($row['interaction_views']) ? (int) $row['interaction_views'] : null,
        'interactionLikes' => isset($row['interaction_likes']) ? (int) $row['interaction_likes'] : null,
        'interactionComments' => isset($row['interaction_comments']) ? (int) $row['interaction_comments'] : null,
        'isArchived' => $isArchived,
    ];
}

/** @return array<string, mixed>|null */
function ce_fetch_archive_detail(PDO $pdo, string $slotId): ?array
{
    ce_ensure_publish_columns($pdo);

    require_once __DIR__ . '/article-repository.php';
    $row = ce_fetch_article_for_api($pdo, $slotId);
    if ($row) {
        $slotId = trim((string) ($row['slot_id'] ?? $slotId));
    } elseif (!ce_slot_belongs_to_client($pdo, $slotId)) {
        return null;
    }

    if ($row) {
        $article = ce_format_article_row($row, 0);
        $meta = ce_archive_item_from_row($pdo, $row);

        return array_merge($article, $meta ?? [], [
            'hasArticle' => true,
            'reports' => ce_fetch_archive_reports($pdo),
        ]);
    }

    require_once __DIR__ . '/slot-repository.php';
    if (!ce_slot_exists($pdo, $slotId)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, theme, phase_key, status FROM content_slots WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $slotId]);
    $slot = $stmt->fetch();
    if (!$slot) {
        return null;
    }

    $title = trim((string) ($slot['theme'] ?? ''));
    if ($title === '') {
        $title = 'Untitled';
    }

    $slotStatus = strtolower(trim((string) ($slot['status'] ?? '')));
    $archived = ce_archive_status($pdo, 'content_slots');
    $isArchived = $slotStatus === strtolower($archived);

    return [
        'hasArticle' => false,
        'slotId' => $slotId,
        'articleId' => null,
        'id' => '',
        'headline' => $title,
        'title' => $title,
        'weekLabel' => ce_week_label($slot['phase_key'] ?? null),
        'content' => '',
        'hashtags' => '',
        'rationale' => '',
        'sourceUrl' => null,
        'tone' => '',
        'angle' => '',
        'imageRatio' => '',
        'platform' => '',
        'imageUrlSquare' => null,
        'imageUrlLandscape' => null,
        'publishedAt' => null,
        'socialPostId' => null,
        'socialPostUrl' => null,
        'promoBudget' => null,
        'archiveNote' => null,
        'interactionViews' => null,
        'interactionLikes' => null,
        'interactionComments' => null,
        'isArchived' => $isArchived,
        'reports' => ce_fetch_archive_reports($pdo),
    ];
}

/** @return string|null */
function ce_normalize_check_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    return $url;
}

/** @return bool */
function ce_url_likely_blocks_bots(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('#^www\.#', '', $host);

    $blockedHosts = [
        'linkedin.com',
        'lnkd.in',
        'facebook.com',
        'fb.com',
        'instagram.com',
        'x.com',
        'twitter.com',
    ];

    foreach ($blockedHosts as $blocked) {
        if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
            return true;
        }
    }

    return false;
}

/** @return array{httpCode: int, curlError: int} */
function ce_http_probe_url(string $url, string $method): array
{
    require_once __DIR__ . '/url-guard.php';
    if (!ce_url_is_safe($url)) {
        return ['httpCode' => 0, 'curlError' => 1];
    }

    if (!function_exists('curl_init')) {
        return ['httpCode' => 0, 'curlError' => 1];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'],
    ]);

    if (strtoupper($method) === 'HEAD') {
        curl_setopt($ch, CURLOPT_NOBODY, true);
    } else {
        curl_setopt($ch, CURLOPT_RANGE, '0-4095');
    }

    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = (int) curl_errno($ch);
    curl_close($ch);

    return ['httpCode' => $httpCode, 'curlError' => $curlError];
}

/** @return array{ok: bool, status: string, httpCode?: int|null, note?: string} */
function ce_check_post_url(string $url): array
{
    $normalized = ce_normalize_check_url($url);
    if ($normalized === null) {
        return ['ok' => false, 'status' => 'invalid'];
    }

    $httpCode = 0;
    foreach (['HEAD', 'GET'] as $method) {
        $probe = ce_http_probe_url($normalized, $method);
        $httpCode = $probe['httpCode'];

        if ($probe['curlError'] === 0 && $httpCode >= 200 && $httpCode < 400) {
            return [
                'ok' => true,
                'status' => 'green',
                'httpCode' => $httpCode,
            ];
        }

        if ($method === 'HEAD' && in_array($httpCode, [405, 501], true)) {
            continue;
        }

        if ($method === 'GET') {
            break;
        }
    }

    // WAF / social platforms often block datacenter requests while the page works in a browser.
    $blockedCodes = [403, 405, 429, 999];
    if (ce_url_likely_blocks_bots($normalized)) {
        $blockedCodes[] = 404;
    }

    if ($httpCode > 0 && in_array($httpCode, $blockedCodes, true)) {
        return [
            'ok' => true,
            'status' => 'blocked',
            'httpCode' => $httpCode,
            'note' => 'automated check blocked',
        ];
    }

    if ($httpCode === 404 || $httpCode === 410) {
        return [
            'ok' => false,
            'status' => 'red',
            'httpCode' => $httpCode,
        ];
    }

    if ($httpCode > 0) {
        return [
            'ok' => false,
            'status' => 'red',
            'httpCode' => $httpCode,
        ];
    }

    return ['ok' => false, 'status' => 'invalid'];
}
