<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/editor-questions.php';
require_once __DIR__ . '/publish-settings-repository.php';
require_once __DIR__ . '/event-log-repository.php';

/** @return array{site_url: string, user: string, app_password: string, author_id: int} */
function ce_wordpress_config(): array
{
    $config = ce_wordpress_config_from_env();

    return [
        'site_url' => $config['site_url'],
        'user' => $config['user'],
        'app_password' => $config['app_password'],
        'author_id' => $config['author_id'],
    ];
}

/** Upload lokale afbeelding naar WordPress media library — retourneert media-id. */
function ce_wordpress_upload_media(array $config, string $localPath, string $filename): ?int
{
    if (!is_readable($localPath)) {
        return null;
    }

    $bytes = file_get_contents($localPath);
    if ($bytes === false || $bytes === '') {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($localPath) ?: 'image/jpeg';
    $auth = base64_encode($config['user'] . ':' . $config['app_password']);
    $endpoint = $config['site_url'] . '/wp-json/wp/v2/media';

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: ' . $mime,
                'Content-Disposition: attachment; filename="' . basename($filename) . '"',
                'Authorization: Basic ' . $auth,
            ]),
            'content' => $bytes,
            'timeout' => 60,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($endpoint, false, $context);
    if (!is_string($response)) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['id'])) {
        return null;
    }

    return (int) $data['id'];
}

/** Zoek bestaand media-item in WordPress op bestandsnaam (featured image fallback). */
function ce_wordpress_find_media_id_by_filename(array $config, string $filename): ?int
{
    $basename = basename(trim($filename));
    if ($basename === '') {
        return null;
    }

    $stem = pathinfo($basename, PATHINFO_FILENAME);
    $searches = array_values(array_unique(array_filter([$basename, $stem])));

    foreach ($searches as $term) {
        $path = '/wp-json/wp/v2/media?per_page=50&search=' . rawurlencode($term);
        $data = ce_wordpress_rest_json($config, 'GET', $path);
        if (!is_array($data)) {
            continue;
        }

        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            $source = (string) ($item['source_url'] ?? '');
            if ($source !== '' && str_ends_with(basename(parse_url($source, PHP_URL_PATH) ?: ''), $basename)) {
                return (int) $item['id'];
            }
            if ($source !== '' && str_contains($source, $stem)) {
                return (int) $item['id'];
            }
        }
    }

    return null;
}

function ce_local_image_path(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    if (str_starts_with($url, '/uploads/') || str_starts_with($url, '/images/')) {
        $path = dirname(__DIR__) . $url;
        return is_readable($path) ? $path : null;
    }

    return null;
}

/** Maak WordPress-fouten leesbaar — geen HTML-dump in de UI. */
function ce_wordpress_error_message(int $httpCode, ?string $response, string $endpoint): string
{
    $snippet = trim((string) $response);
    if ($snippet !== '' && str_starts_with(strtolower(ltrim($snippet)), '<!doctype html')) {
        return 'WordPress REST API niet bereikbaar op '
            . $endpoint
            . ' (HTTP '
            . $httpCode
            . '). Controleer WORDPRESS_SITE_URL — verwacht bv. https://igisnext.com';
    }

    if ($snippet !== '') {
        $decoded = json_decode($snippet, true);
        if (is_array($decoded)) {
            $message = trim((string) ($decoded['message'] ?? ''));
            if ($message !== '') {
                if (stripos($message, 'not allowed to create posts') !== false) {
                    return 'WordPress-weigering: deze gebruiker mag geen posts aanmaken. Controleer gebruiker, app-wachtwoord en auteur-ID onder Integrations → WordPress.';
                }

                return $message;
            }
        }
        $plain = trim(strip_tags($snippet));
        if ($plain !== '' && strlen($plain) <= 240) {
            return $plain;
        }
    }

    return 'WordPress publish mislukt (HTTP ' . $httpCode . ')';
}

/**
 * @param array{site_url: string, user: string, app_password: string, author_id?: int} $config
 * @return array<string, mixed>|null
 */
function ce_wordpress_rest_json(array $config, string $method, string $relativePath, ?string $jsonBody = null): ?array
{
    $path = str_starts_with($relativePath, '/') ? $relativePath : '/' . $relativePath;
    $endpoint = $config['site_url'] . $path;
    $auth = base64_encode($config['user'] . ':' . $config['app_password']);

    $headers = [
        'Accept: application/json',
        'Authorization: Basic ' . $auth,
    ];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $jsonBody ?? '',
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($endpoint, false, $context);
    if (!is_string($response) || $response === '') {
        return null;
    }

    $data = json_decode($response, true);

    return is_array($data) ? $data : null;
}

/** @param array{site_url: string, user: string, app_password: string, author_id?: int} $config */
function ce_wordpress_authenticated_user_id(array $config): ?int
{
    $data = ce_wordpress_rest_json($config, 'GET', '/wp-json/wp/v2/users/me');
    if (!is_array($data) || !isset($data['id'])) {
        return null;
    }

    $id = (int) $data['id'];

    return $id > 0 ? $id : null;
}

/**
 * @param array{site_url: string, user: string, app_password: string, author_id?: int} $config
 * @return array{
 *   connected: bool,
 *   authError: ?string,
 *   wpErrorCode: ?string,
 *   authenticatedAs: ?array{id: mixed, slug: mixed, name: mixed, roles: mixed}
 * }
 */
function ce_wordpress_test_connection(array $config): array
{
    $site = trim((string) ($config['site_url'] ?? ''));
    $user = ce_normalize_wordpress_user(trim((string) ($config['user'] ?? '')));
    $pass = ce_normalize_wordpress_app_password(trim((string) ($config['app_password'] ?? '')));
    $config['user'] = $user;
    $config['app_password'] = $pass;

    if ($site === '' || $user === '' || $pass === '') {
        return [
            'connected' => false,
            'authError' => 'Vul site-URL, WordPress-gebruiker en Application Password in.',
            'wpErrorCode' => null,
            'authenticatedAs' => null,
        ];
    }

    $siteProbe = ce_wordpress_site_url_probe_error($site);
    if ($siteProbe !== null) {
        return [
            'connected' => false,
            'authError' => $siteProbe,
            'wpErrorCode' => null,
            'authenticatedAs' => null,
        ];
    }

    $meRaw = ce_wordpress_rest_json($config, 'GET', '/wp-json/wp/v2/users/me?context=edit');
    if ($meRaw === null) {
        $hint = ce_wordpress_site_url_probe_error($site)
            ?? 'Geen geldig antwoord van WordPress — controleer site-URL (moet wp-json tonen, geen CE-app). '
            . 'CE-blog: https://ce.igisnext.com/blog';

        return [
            'connected' => false,
            'authError' => $hint,
            'wpErrorCode' => null,
            'authenticatedAs' => null,
        ];
    }

    if (!isset($meRaw['id'])) {
        $wpErrorCode = isset($meRaw['code']) ? (string) $meRaw['code'] : null;
        $wpErrorMessage = isset($meRaw['message']) ? (string) $meRaw['message'] : 'onbekende fout';
        $hint = '';
        if ($wpErrorCode === 'rest_not_logged_in') {
            $hint = ' Gebruik de **loginnaam** (bv. content-engine), geen e-mail of display name.'
                . ' Maak het app-wachtwoord op déze WordPress-site (wp-admin → Gebruikers → Application Passwords).'
                . ' Plak opnieuw, klik Opslaan, daarna Test.';
            if (str_contains($user, '@')) {
                $hint .= ' E-mailadressen werken niet als gebruikersnaam.';
            }
        }

        return [
            'connected' => false,
            'authError' => 'WordPress wees de login af: ' . $wpErrorMessage
                . ($wpErrorCode !== null ? ' (code: ' . $wpErrorCode . ')' : '')
                . $hint,
            'wpErrorCode' => $wpErrorCode,
            'authenticatedAs' => null,
        ];
    }

    return [
        'connected' => true,
        'authError' => null,
        'wpErrorCode' => null,
        'authenticatedAs' => [
            'id' => $meRaw['id'] ?? null,
            'slug' => $meRaw['slug'] ?? null,
            'name' => $meRaw['name'] ?? null,
            'roles' => $meRaw['roles'] ?? null,
        ],
    ];
}

/**
 * Editors mogen meestal niet posten als andere auteur (author=1 terwijl je content-engine bent).
 *
 * @param array{site_url: string, user: string, app_password: string, author_id?: int} $config
 */
function ce_wordpress_resolve_post_author_id(array $config): int
{
    $me = ce_wordpress_authenticated_user_id($config);
    $requested = (int) ($config['author_id'] ?? 0);

    if ($me !== null && $me > 0) {
        if ($requested <= 0 || $requested === $me) {
            return $me;
        }

        return $me;
    }

    return $requested > 0 ? $requested : 1;
}

/** @param array<string, mixed> $row */
function ce_wordpress_article_body_for_publish(array $row): string
{
    $content = trim((string) ($row['content_human'] ?? ''));
    if ($content === '') {
        $content = (string) ($row['content_ai'] ?? '');
    }
    $content = ce_strip_editorial_annotations_for_publish($content);

    $hashtags = trim((string) ($row['hashtags_final'] ?? $row['hashtags_suggested'] ?? ''));
    if ($hashtags !== '') {
        $content .= "\n\n" . $hashtags;
    }

    return trim($content);
}

/** @param array<string, mixed> $row */
function ce_wordpress_article_html_for_publish(array $row): string
{
    require_once __DIR__ . '/static-site-publish-repository.php';
    $plain = ce_wordpress_article_body_for_publish($row);

    return ce_static_site_content_to_html($plain);
}

/**
 * @param array{site_url: string, user: string, app_password: string, author_id?: int} $config
 * @return array{httpCode: int, body: ?string, data: ?array}
 */
function ce_wordpress_http_json(array $config, string $method, string $relativePath, ?array $payload = null): array
{
    $path = str_starts_with($relativePath, '/') ? $relativePath : '/' . $relativePath;
    $endpoint = $config['site_url'] . $path;
    $auth = base64_encode($config['user'] . ':' . $config['app_password']);
    $jsonBody = $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null;

    $headers = "Accept: application/json\r\nAuthorization: Basic $auth\r\n";
    if ($jsonBody !== null) {
        $headers .= "Content-Type: application/json\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $jsonBody ?? '',
            'timeout' => 45,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($endpoint, false, $context);
    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $matches)) {
        $httpCode = (int) $matches[1];
    }

    $data = is_string($response) && $response !== '' ? json_decode($response, true) : null;

    return [
        'httpCode' => $httpCode,
        'body' => is_string($response) ? $response : null,
        'data' => is_array($data) ? $data : null,
    ];
}

function ce_http_head_status_code(string $url): int
{
    $url = trim($url);
    if ($url === '') {
        return 0;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'timeout' => 15,
            'ignore_errors' => true,
            'follow_location' => 1,
        ],
    ]);
    @file_get_contents($url, false, $context);
    if (!isset($http_response_header[0]) || !preg_match('/ (\d{3}) /', (string) $http_response_header[0], $matches)) {
        return 0;
    }

    return (int) $matches[1];
}

/**
 * @param array{site_url: string, user: string, app_password: string, author_id?: int} $config
 */
function ce_wordpress_post_publicly_viewable(array $config, string $postUrl): bool
{
    $code = ce_http_head_status_code($postUrl);

    return $code >= 200 && $code < 400;
}

function ce_ensure_publish_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [
        'published_at' => 'DATETIME NULL',
        'social_post_id' => 'VARCHAR(255) NULL',
        'social_post_url' => 'VARCHAR(512) NULL',
        'publish_urls' => 'JSON NULL',
        'promo_budget' => 'DECIMAL(10,2) NULL',
        'archive_note' => 'TEXT NULL',
        'interaction_views' => 'INT NULL',
        'interaction_likes' => 'INT NULL',
        'interaction_comments' => 'INT NULL',
        'image_advice' => 'VARCHAR(1000) NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!ce_table_has_column($pdo, 'articles', $column)) {
            try {
                $pdo->exec("ALTER TABLE articles ADD COLUMN $column $definition");
                unset($GLOBALS['ce_table_columns']['articles']);
            } catch (Throwable) {
                // ignore
            }
        }
    }
}

/** Haal publieke WordPress-URL op via opgeslagen post-ID. */
function ce_wordpress_fetch_post_link(PDO $pdo, ?string $clientId, string $postId): ?string
{
    $postId = trim($postId);
    if ($postId === '' || !ctype_digit($postId)) {
        return null;
    }

    $config = ce_wordpress_config_for_client($pdo, $clientId);
    if ($config['site_url'] === '') {
        return null;
    }

    $data = ce_wordpress_rest_json($config, 'GET', '/wp-json/wp/v2/posts/' . $postId . '?context=view');
    if (!is_array($data)) {
        return null;
    }

    $link = trim((string) ($data['link'] ?? ''));

    return $link !== '' ? $link : null;
}

/**
 * Vul social_post_url aan uit WordPress post-ID als die nog leeg is (backfill bij archief/lezen).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function ce_hydrate_article_publish_url(PDO $pdo, array $row): array
{
    ce_ensure_publish_columns($pdo);

    $existing = trim((string) ($row['social_post_url'] ?? ''));
    if ($existing !== '') {
        return $row;
    }

    $legacy = trim((string) ($row['social_post_id'] ?? ''));
    if ($legacy !== '' && preg_match('#^https?://#i', $legacy)) {
        $row['social_post_url'] = $legacy;

        return $row;
    }

    $wpPostId = ce_wordpress_post_id_from_row($row);
    if ($wpPostId === null) {
        return $row;
    }

    $clientId = trim((string) ($row['client_id'] ?? ''));
    $link = ce_wordpress_fetch_post_link($pdo, $clientId !== '' ? $clientId : null, $wpPostId);
    if ($link === null) {
        require_once __DIR__ . '/joomla-publish-repository.php';
        if (ce_client_joomla_configured($pdo, $clientId !== '' ? $clientId : null)) {
            $link = ce_joomla_fetch_post_link($pdo, $clientId !== '' ? $clientId : null, $wpPostId);
        }
    }
    if ($link === null) {
        return $row;
    }

    $articleId = trim((string) ($row['id'] ?? ''));
    if ($articleId !== '' && ce_table_has_column($pdo, 'articles', 'social_post_url')) {
        $pdo->prepare(
            'UPDATE articles SET social_post_url = :url
             WHERE id = :id AND (social_post_url IS NULL OR TRIM(social_post_url) = \'\')'
        )->execute(['url' => $link, 'id' => $articleId]);
    }

    $row['social_post_url'] = $link;

    return $row;
}

/** @return 'blog'|'linkedin'|'x'|null */
function ce_publish_url_kind(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '') {
        return 'blog';
    }
    if (str_contains($host, 'linkedin.com')) {
        return 'linkedin';
    }
    if (str_contains($host, 'twitter.com') || $host === 'x.com' || str_ends_with($host, '.x.com')) {
        return 'x';
    }

    return 'blog';
}

/** @return array<string, string> */
function ce_publish_urls_from_row(array $row): array
{
    $raw = $row['publish_urls'] ?? null;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_filter(array_map('strval', $decoded)) : [];
    }

    return is_array($raw) ? array_filter(array_map('strval', $raw)) : [];
}

/** @param array<string, string|null> $urls */
function ce_save_publish_urls(PDO $pdo, string $articleId, array $urls): void
{
    if ($articleId === '' || !ce_table_has_column($pdo, 'articles', 'publish_urls')) {
        return;
    }

    $filtered = [];
    foreach (['blog', 'linkedin', 'x'] as $kind) {
        $value = trim((string) ($urls[$kind] ?? ''));
        if ($value !== '') {
            $filtered[$kind] = $value;
        }
    }

    $pdo->prepare('UPDATE articles SET publish_urls = :json WHERE id = :id')->execute([
        'json' => $filtered === [] ? null : json_encode($filtered, JSON_THROW_ON_ERROR),
        'id' => $articleId,
    ]);
}

/** @return list<array{kind: string, label: string, url: string}> */
function ce_article_publish_links(PDO $pdo, array $row): array
{
    $row = ce_hydrate_article_publish_url($pdo, $row);
    $urls = ce_publish_urls_from_row($row);

    if (($urls['blog'] ?? '') === '') {
        $wpPostId = ce_wordpress_post_id_from_row($row);
        if ($wpPostId !== null) {
            $clientId = trim((string) ($row['client_id'] ?? ''));
            $blogUrl = ce_wordpress_fetch_post_link($pdo, $clientId !== '' ? $clientId : null, $wpPostId);
            if ($blogUrl === null) {
                require_once __DIR__ . '/joomla-publish-repository.php';
                if (ce_client_joomla_configured($pdo, $clientId !== '' ? $clientId : null)) {
                    $blogUrl = ce_joomla_fetch_post_link($pdo, $clientId !== '' ? $clientId : null, $wpPostId);
                }
            }
            if ($blogUrl !== null) {
                $urls['blog'] = $blogUrl;
            }
        }
    }

    $socialUrl = trim((string) ($row['social_post_url'] ?? ''));
    if ($socialUrl !== '') {
        $kind = ce_publish_url_kind($socialUrl);
        if ($kind !== null && ($urls[$kind] ?? '') === '') {
            $urls[$kind] = $socialUrl;
        }
    }

    $articleId = trim((string) ($row['id'] ?? ''));
    if ($articleId !== '' && ($urls['linkedin'] ?? '') === '' && ce_table_exists($pdo, 'linkedin_posts')) {
        $stmt = $pdo->prepare(
            "SELECT post_url FROM linkedin_posts
             WHERE article_id = :id AND post_url IS NOT NULL AND TRIM(post_url) <> ''
             ORDER BY posted_at DESC LIMIT 1"
        );
        $stmt->execute(['id' => $articleId]);
        $linkedInUrl = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($linkedInUrl !== '') {
            $urls['linkedin'] = $linkedInUrl;
        }
    }

    $labels = ['blog' => 'Blog', 'linkedin' => 'LinkedIn', 'x' => 'X'];
    $links = [];
    foreach (['blog', 'linkedin', 'x'] as $kind) {
        $url = trim((string) ($urls[$kind] ?? ''));
        if ($url === '') {
            continue;
        }
        $links[] = [
            'kind' => $kind,
            'label' => $labels[$kind],
            'url' => $url,
        ];
    }

    return $links;
}

/** @param list<array<string, mixed>> $destinations */
function ce_persist_publish_destination_urls(PDO $pdo, string $articleId, array $destinations): void
{
    if ($articleId === '') {
        return;
    }

    ce_ensure_publish_columns($pdo);

    $websiteUrl = null;
    $linkedinUrl = null;
    foreach ($destinations as $dest) {
        if (empty($dest['ok'])) {
            continue;
        }
        $postUrl = trim((string) ($dest['postUrl'] ?? ''));
        if ($postUrl === '') {
            continue;
        }
        if (($dest['kind'] ?? '') === 'website') {
            $websiteUrl = $postUrl;
        } elseif (($dest['kind'] ?? '') === 'linkedin') {
            $linkedinUrl = $postUrl;
        }
    }

    if ($websiteUrl === null && $linkedinUrl === null) {
        return;
    }

    $stmt = $pdo->prepare('SELECT publish_urls, social_post_url FROM articles WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $articleId]);
    $existing = $stmt->fetch() ?: [];

    $urls = ce_publish_urls_from_row($existing);
    if ($websiteUrl !== null) {
        $urls['blog'] = $websiteUrl;
    }
    if ($linkedinUrl !== null) {
        $urls['linkedin'] = $linkedinUrl;
    }
    ce_save_publish_urls($pdo, $articleId, $urls);

    if (!ce_table_has_column($pdo, 'articles', 'social_post_url')) {
        return;
    }

    if ($websiteUrl !== null) {
        $pdo->prepare('UPDATE articles SET social_post_url = :url WHERE id = :id')
            ->execute(['url' => $websiteUrl, 'id' => $articleId]);

        return;
    }

    if ($linkedinUrl !== null) {
        $pdo->prepare(
            'UPDATE articles SET social_post_url = :url
             WHERE id = :id AND (social_post_url IS NULL OR TRIM(social_post_url) = \'\')'
        )->execute(['url' => $linkedinUrl, 'id' => $articleId]);
    }
}

/** @return array{ok: bool, error?: string, postId?: int|string, postUrl?: string} */
function ce_publish_article_to_wordpress(PDO $pdo, string $slotId): array
{
    ce_ensure_publish_columns($pdo);

    $clientId = ce_client_id_for_slot($pdo, $slotId);
    $row = ce_fetch_article_by_slot($pdo, $slotId, $clientId);
    if (!$row) {
        return ['ok' => false, 'error' => 'Artikel niet gevonden'];
    }

    $uiStatus = ce_status_to_ui($row['status'] ?? null);
    $existingWpPostId = trim((string) ($row['social_post_id'] ?? ''));
    if ($existingWpPostId !== '' && !ctype_digit($existingWpPostId)) {
        $existingWpPostId = '';
    }
    $isUpdate = $existingWpPostId !== '';
    if (!in_array($uiStatus, ['approved', 'published'], true)) {
        return ['ok' => false, 'error' => 'Alleen goedgekeurde artikelen kunnen naar WordPress (status: ' . $uiStatus . ')'];
    }
    if (!ce_client_wordpress_configured($pdo, $clientId)) {
        return [
            'ok' => false,
            'error' => 'Geen website-publicatie geconfigureerd. Vul WordPress in, of Blog-URL + FTP voor een statische site (Integrations).',
        ];
    }
    $config = ce_wordpress_config_for_client($pdo, $clientId, false);

    $title = ce_article_headline($row);
    $plainBody = ce_wordpress_article_body_for_publish($row);
    if ($plainBody === '') {
        return ['ok' => false, 'error' => 'Artikel heeft geen publiceerbare inhoud na het verwijderen van redactie-aantekeningen.'];
    }
    $contentHtml = ce_wordpress_article_html_for_publish($row);

    $imageRatio = ce_image_ratio_to_ui($row['image_ratio_final'] ?? $row['image_ratio_suggested'] ?? null);
    $square = trim((string) ($row['image_url_square'] ?? ''));
    $landscape = trim((string) ($row['image_url_landscape'] ?? ''));
    $imageUrl = $imageRatio === 'landscape'
        ? ($landscape !== '' ? $landscape : $square)
        : ($square !== '' ? $square : $landscape);

    $featuredMediaId = null;
    $localImage = ce_local_image_path($imageUrl);
    if ($localImage !== null) {
        $featuredMediaId = ce_wordpress_upload_media($config, $localImage, basename($localImage));
    }
    if ($featuredMediaId === null) {
        $fallbackFilename = ce_wordpress_fallback_media_filename_for_client($pdo, $clientId);
        if ($fallbackFilename !== '') {
            $featuredMediaId = ce_wordpress_find_media_id_by_filename($config, $fallbackFilename);
        }
    }

    $postPayload = [
        'title' => $title,
        'content' => $contentHtml,
        'status' => 'publish',
        'author' => ce_wordpress_resolve_post_author_id($config),
    ];
    if ($featuredMediaId !== null) {
        $postPayload['featured_media'] = $featuredMediaId;
    }

    $endpoint = $isUpdate
        ? '/wp-json/wp/v2/posts/' . $existingWpPostId
        : '/wp-json/wp/v2/posts';
    $responseWrap = ce_wordpress_http_json($config, 'POST', $endpoint, $postPayload);
    $httpCode = $responseWrap['httpCode'];
    $response = $responseWrap['body'];

    if ($httpCode < 200 || $httpCode >= 300 || !is_string($response)) {
        $error = ce_wordpress_error_message($httpCode, is_string($response) ? $response : null, $config['site_url'] . $endpoint);
        ce_log_event($pdo, 'error', 'publish', 'wp_publish_failed', $error, [
            'clientId' => $clientId,
            'slotId' => $slotId,
            'httpStatus' => $httpCode,
            'endpoint' => '/wp-json/wp/v2/posts',
        ]);
        require_once __DIR__ . '/publications-repository.php';
        ce_record_publication_outcome($pdo, (string) ($row['id'] ?? ''), 'wordpress', false, null, null, $error);

        return ['ok' => false, 'error' => $error];
    }

    $data = $responseWrap['data'];
    if (!is_array($data)) {
        ce_log_event($pdo, 'error', 'publish', 'wp_publish_failed', 'Ongeldig WordPress-antwoord', [
            'clientId' => $clientId,
            'slotId' => $slotId,
            'httpStatus' => $httpCode,
        ]);

        return ['ok' => false, 'error' => 'Ongeldig WordPress-antwoord'];
    }

    $wpStatus = trim((string) ($data['status'] ?? ''));
    if ($wpStatus !== '' && $wpStatus !== 'publish') {
        $statusHint = match ($wpStatus) {
            'pending' => 'De post staat op “pending” — de WordPress-gebruiker mag waarschijnlijk niet direct publiceren.',
            'draft' => 'De post is een concept gebleven.',
            'private' => 'De post is privé — bezoekers zien een 404.',
            default => 'WordPress-status: ' . $wpStatus,
        };
        ce_log_event($pdo, 'error', 'publish', 'wp_publish_not_public', $statusHint, [
            'clientId' => $clientId,
            'slotId' => $slotId,
            'wpStatus' => $wpStatus,
            'postId' => $data['id'] ?? null,
        ]);

        return [
            'ok' => false,
            'error' => $statusHint . ' Geef de Content Engine-gebruiker de rol Editor met publish-rechten, of publiceer via een Administrator-app-wachtwoord.',
            'postId' => (string) ($data['id'] ?? ''),
            'postUrl' => trim((string) ($data['link'] ?? '')),
        ];
    }

    $postId = (string) ($data['id'] ?? '');
    $postUrl = trim((string) ($data['link'] ?? ''));
    if ($postUrl !== '' && !ce_wordpress_post_publicly_viewable($config, $postUrl)) {
        ce_log_event($pdo, 'error', 'publish', 'wp_publish_not_viewable', 'Blog-URL niet publiek bereikbaar', [
            'clientId' => $clientId,
            'slotId' => $slotId,
            'postUrl' => $postUrl,
            'postId' => $postId,
        ]);

        return [
            'ok' => false,
            'error' => 'WordPress gaf een URL terug, maar die is niet publiek bereikbaar (404). Controleer post-status en rechten in WordPress (post #' . $postId . ').',
            'postId' => $postId,
            'postUrl' => $postUrl,
        ];
    }
    $articleId = (string) $row['id'];

    $sets = ['status = :status'];
    $params = [
        'id' => $articleId,
        'status' => ce_db_status_for_ui($pdo, 'articles', 'published'),
    ];

    if (ce_table_has_column($pdo, 'articles', 'social_post_id') && $postId !== '') {
        $sets[] = 'social_post_id = :social_post_id';
        $params['social_post_id'] = $postId;
    }
    if (ce_table_has_column($pdo, 'articles', 'published_at')) {
        $sets[] = 'published_at = NOW()';
    }
    if (ce_table_has_column($pdo, 'articles', 'social_post_url') && $postUrl !== '') {
        $sets[] = 'social_post_url = :social_post_url';
        $params['social_post_url'] = $postUrl;
    }

    $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    ce_apply_ui_status($pdo, $slotId, $articleId, 'published');
    require_once __DIR__ . '/hba-v2-repository.php';
    ce_hba_v2_score_on_publish($pdo, $articleId, $clientId);

    if ($postUrl !== '') {
        $urls = ce_publish_urls_from_row($row);
        $urls['blog'] = $postUrl;
        ce_save_publish_urls($pdo, $articleId, $urls);
        if (!isset($params['social_post_url'])) {
            $pdo->prepare('UPDATE articles SET social_post_url = :url WHERE id = :id')
                ->execute(['url' => $postUrl, 'id' => $articleId]);
        }
    }

    require_once __DIR__ . '/tenant-activity-repository.php';
    ce_track_tenant_activity($pdo, $clientId, 'articles_published');
    require_once __DIR__ . '/publications-repository.php';
    ce_record_publication_outcome($pdo, $articleId, 'wordpress', true, $postId !== '' ? $postId : null, $postUrl !== '' ? $postUrl : null);

    return ['ok' => true, 'postId' => $postId, 'postUrl' => $postUrl];
}

/** @return array{ok: bool, error?: string, postId?: string, postUrl?: string} */
function ce_publish_article_to_joomla(PDO $pdo, string $slotId): array
{
    require_once __DIR__ . '/joomla-publish-repository.php';
    ce_ensure_publish_columns($pdo);

    $clientId = ce_client_id_for_slot($pdo, $slotId);
    $row = ce_fetch_article_by_slot($pdo, $slotId, $clientId);
    if (!$row) {
        return ['ok' => false, 'error' => 'Artikel niet gevonden'];
    }

    $uiStatus = ce_status_to_ui($row['status'] ?? null);
    $existingPostId = trim((string) ($row['social_post_id'] ?? ''));
    if ($existingPostId !== '' && !ctype_digit($existingPostId)) {
        $existingPostId = '';
    }
    $mayPublish = $uiStatus === 'approved'
        || ($uiStatus === 'published' && $existingPostId === '');
    if (!$mayPublish) {
        if ($uiStatus === 'published' && $existingPostId !== '') {
            return ['ok' => false, 'error' => 'Dit artikel staat al op Joomla (artikel #' . $existingPostId . ')'];
        }

        return ['ok' => false, 'error' => 'Alleen goedgekeurde artikelen kunnen naar Joomla (status: ' . $uiStatus . ')'];
    }
    if (!ce_client_joomla_configured($pdo, $clientId)) {
        return [
            'ok' => false,
            'error' => 'Geen Joomla-publicatie geconfigureerd. Vul site-URL, API-token en categorie-ID in (Integrations).',
        ];
    }

    $config = ce_joomla_config_for_client($pdo, $clientId);
    $title = ce_article_headline($row);
    $content = trim((string) ($row['content_human'] ?? ''));
    if ($content === '') {
        $content = (string) ($row['content_ai'] ?? '');
    }
    $content = ce_strip_editorial_annotations_for_publish($content);

    $hashtags = trim((string) ($row['hashtags_final'] ?? $row['hashtags_suggested'] ?? ''));
    if ($hashtags !== '') {
        $content .= "\n\n" . $hashtags;
    }

    $payload = json_encode([
        'title' => $title,
        'alias' => ce_joomla_slug_from_title($title),
        'articletext' => ce_joomla_html_content($content),
        'catid' => $config['category_id'],
        'state' => 1,
        'language' => '*',
        'metadesc' => '',
        'metakey' => '',
    ], JSON_THROW_ON_ERROR);

    $endpoint = ce_joomla_api_base($config['site_url']) . '/content/articles';
    $response = ce_joomla_http_request($config, 'POST', '/content/articles', $payload);
    $httpCode = $response['http_code'];

    if ($httpCode < 200 || $httpCode >= 300) {
        $error = ce_joomla_error_message($httpCode, $response['body'], $endpoint);
        ce_log_event($pdo, 'error', 'publish', 'joomla_publish_failed', $error, [
            'clientId' => $clientId,
            'slotId' => $slotId,
            'httpStatus' => $httpCode,
            'endpoint' => '/content/articles',
        ]);

        return ['ok' => false, 'error' => $error];
    }

    $postId = ce_joomla_article_id_from_response($response['json']);
    if ($postId === null) {
        ce_log_event($pdo, 'error', 'publish', 'joomla_publish_failed', 'Ongeldig Joomla-antwoord', [
            'clientId' => $clientId,
            'slotId' => $slotId,
            'httpStatus' => $httpCode,
        ]);

        return ['ok' => false, 'error' => 'Ongeldig Joomla-antwoord'];
    }

    $alias = ce_joomla_article_alias_from_response($response['json']);
    $postUrl = ce_joomla_build_article_url($config, $postId, $alias);
    $articleId = (string) $row['id'];

    $sets = ['status = :status'];
    $params = [
        'id' => $articleId,
        'status' => ce_db_status_for_ui($pdo, 'articles', 'published'),
    ];

    if (ce_table_has_column($pdo, 'articles', 'social_post_id')) {
        $sets[] = 'social_post_id = :social_post_id';
        $params['social_post_id'] = $postId;
    }
    if (ce_table_has_column($pdo, 'articles', 'published_at')) {
        $sets[] = 'published_at = NOW()';
    }
    if (ce_table_has_column($pdo, 'articles', 'social_post_url') && $postUrl !== '') {
        $sets[] = 'social_post_url = :social_post_url';
        $params['social_post_url'] = $postUrl;
    }

    $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    ce_apply_ui_status($pdo, $slotId, $articleId, 'published');
    require_once __DIR__ . '/hba-v2-repository.php';
    ce_hba_v2_score_on_publish($pdo, $articleId, $clientId);

    if ($postUrl !== '') {
        $urls = ce_publish_urls_from_row($row);
        $urls['blog'] = $postUrl;
        ce_save_publish_urls($pdo, $articleId, $urls);
        if (!isset($params['social_post_url'])) {
            $pdo->prepare('UPDATE articles SET social_post_url = :url WHERE id = :id')
                ->execute(['url' => $postUrl, 'id' => $articleId]);
        }
    }

    require_once __DIR__ . '/tenant-activity-repository.php';
    ce_track_tenant_activity($pdo, $clientId, 'articles_published');
    require_once __DIR__ . '/publications-repository.php';
    ce_record_publication_outcome($pdo, $articleId, 'joomla', true, $postId !== '' ? $postId : null, $postUrl !== '' ? $postUrl : null);

    return ['ok' => true, 'postId' => $postId, 'postUrl' => $postUrl];
}

/**
 * Publiceer naar website (WordPress) plus ingeschakelde extra bestemmingen uit Account.
 *
 * @return array{
 *   ok: bool,
 *   error?: string,
 *   postId?: int|string,
 *   postUrl?: string,
 *   destinations?: list<array<string, mixed>>
 * }
 */
function ce_publish_article_destinations(PDO $pdo, string $slotId): array
{
    require_once __DIR__ . '/static-site-publish-repository.php';

    $clientId = ce_client_id_for_slot($pdo, $slotId);
    $row = ce_fetch_article_by_slot($pdo, $slotId, $clientId);
    if (!$row) {
        return ['ok' => false, 'error' => 'Artikel niet gevonden'];
    }

    require_once __DIR__ . '/hba-v2-repository.php';
    $publishText = trim(ce_article_headline($row) . "\n" . ce_wordpress_article_body_for_publish($row));
    if (ce_hba_text_has_open_placeholders($publishText)) {
        return ['ok' => false, 'error' => ce_hba_open_placeholder_message()];
    }
    if (ce_hba_article_self_review_blocks_flow($row['self_review'] ?? null)) {
        return ['ok' => false, 'error' => ce_hba_self_review_unsupported_assumption_message()];
    }

    $existingPostId = trim((string) ($row['social_post_id'] ?? ''));
    $destinations = [];
    $cmsKind = ce_client_website_cms_kind($pdo, $clientId);

    if ($cmsKind === 'static') {
        $staticResult = ce_publish_article_to_static_site($pdo, $slotId);
        $destinations[] = [
            'kind' => 'website',
            'label' => 'Website (statische HTML)',
            'ok' => !empty($staticResult['ok']),
            'postId' => $staticResult['postId'] ?? null,
            'postUrl' => $staticResult['postUrl'] ?? null,
            'message' => empty($staticResult['ok'])
                ? (string) ($staticResult['error'] ?? 'Publiceren mislukt')
                : null,
        ];

        if (empty($staticResult['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($staticResult['error'] ?? 'Publiceren mislukt'),
                'destinations' => $destinations,
                'postId' => $staticResult['postId'] ?? null,
                'postUrl' => $staticResult['postUrl'] ?? null,
            ];
        }

        $extras = [];
        if ($clientId !== null) {
            $extras = ce_publish_extras_resolved_for_client($pdo, $clientId);
        }
        foreach ($extras as $extra) {
            $destinations[] = ce_publish_extra_destination($pdo, $row, $extra, $clientId);
        }
        ce_persist_publish_destination_urls($pdo, (string) $row['id'], $destinations);

        return [
            'ok' => true,
            'postId' => $staticResult['postId'] ?? null,
            'postUrl' => $staticResult['postUrl'] ?? null,
            'destinations' => $destinations,
        ];
    }

    if ($cmsKind === 'joomla') {
        $joomlaResult = ce_publish_article_to_joomla($pdo, $slotId);
        $joomlaAlreadyOnSite = empty($joomlaResult['ok'])
            && str_contains((string) ($joomlaResult['error'] ?? ''), 'staat al op Joomla');

        if (empty($joomlaResult['ok']) && !$joomlaAlreadyOnSite) {
            $destinations[] = [
                'kind' => 'website',
                'label' => 'Website (Joomla)',
                'ok' => false,
                'postId' => $joomlaResult['postId'] ?? null,
                'postUrl' => $joomlaResult['postUrl'] ?? null,
                'message' => (string) ($joomlaResult['error'] ?? 'Publiceren mislukt'),
            ];

            return [
                'ok' => false,
                'error' => (string) ($joomlaResult['error'] ?? 'Publiceren mislukt'),
                'destinations' => $destinations,
                'postId' => $joomlaResult['postId'] ?? null,
                'postUrl' => $joomlaResult['postUrl'] ?? null,
            ];
        }

        if ($joomlaAlreadyOnSite) {
            $joomlaResult = [
                'ok' => true,
                'postId' => $existingPostId,
                'postUrl' => null,
                'skipped' => true,
            ];
        }

        $destinations[] = [
            'kind' => 'website',
            'label' => 'Website (Joomla)',
            'ok' => true,
            'postId' => $joomlaResult['postId'] ?? null,
            'postUrl' => $joomlaResult['postUrl'] ?? null,
            'message' => !empty($joomlaResult['skipped'])
                ? 'Stond al op Joomla (artikel #' . ($joomlaResult['postId'] ?? '') . ').'
                : null,
        ];

        $extras = [];
        if ($clientId !== null) {
            $extras = ce_publish_extras_resolved_for_client($pdo, $clientId);
        }
        foreach ($extras as $extra) {
            $destinations[] = ce_publish_extra_destination($pdo, $row, $extra, $clientId);
        }
        ce_persist_publish_destination_urls($pdo, (string) $row['id'], $destinations);

        return [
            'ok' => true,
            'postId' => $joomlaResult['postId'] ?? null,
            'postUrl' => $joomlaResult['postUrl'] ?? null,
            'destinations' => $destinations,
        ];
    }

    if ($cmsKind === null) {
        return [
            'ok' => false,
            'error' => 'Geen website-publicatie geconfigureerd. Vul WordPress, Joomla of FTP in (Integrations).',
            'destinations' => [],
        ];
    }

    $wpResult = ce_publish_article_to_wordpress($pdo, $slotId);
    $wpAlreadyOnSite = empty($wpResult['ok'])
        && str_contains((string) ($wpResult['error'] ?? ''), 'staat al op WordPress');

    if (empty($wpResult['ok']) && !$wpAlreadyOnSite) {
        $destinations[] = [
            'kind' => 'website',
            'label' => 'Website (WordPress)',
            'ok' => false,
            'postId' => $wpResult['postId'] ?? null,
            'postUrl' => $wpResult['postUrl'] ?? null,
            'message' => (string) ($wpResult['error'] ?? 'Publiceren mislukt'),
        ];

        return [
            'ok' => false,
            'error' => (string) ($wpResult['error'] ?? 'Publiceren mislukt'),
            'destinations' => $destinations,
            'postId' => $wpResult['postId'] ?? null,
            'postUrl' => $wpResult['postUrl'] ?? null,
        ];
    }

    if ($wpAlreadyOnSite) {
        $wpResult = [
            'ok' => true,
            'postId' => $existingPostId,
            'postUrl' => null,
            'skipped' => true,
        ];
    }

    $destinations[] = [
        'kind' => 'website',
        'label' => 'Website (WordPress)',
        'ok' => true,
        'postId' => $wpResult['postId'] ?? null,
        'postUrl' => $wpResult['postUrl'] ?? null,
        'message' => !empty($wpResult['skipped'])
            ? 'Stond al op WordPress (post #' . ($wpResult['postId'] ?? '') . ').'
            : null,
    ];

    $extras = [];
    if ($clientId !== null) {
        $extras = ce_publish_extras_resolved_for_client($pdo, $clientId);
    }

    foreach ($extras as $extra) {
        $destinations[] = ce_publish_extra_destination($pdo, $row, $extra, $clientId);
    }

    ce_persist_publish_destination_urls($pdo, (string) $row['id'], $destinations);

    return [
        'ok' => true,
        'postId' => $wpResult['postId'] ?? null,
        'postUrl' => $wpResult['postUrl'] ?? null,
        'destinations' => $destinations,
    ];
}

function ce_client_publish_mode(PDO $pdo, ?string $clientId): string
{
    if ($clientId === null || $clientId === '') {
        return 'manual_with_review';
    }

    require_once __DIR__ . '/client-repository.php';
    $row = ce_fetch_client_row($pdo, $clientId);

    return ce_normalize_publish_mode($row['publish_mode'] ?? null);
}

