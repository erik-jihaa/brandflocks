<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/publish-settings-repository.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/editor-questions.php';
require_once __DIR__ . '/event-log-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/client-repository.php';

/**
 * Statische HTML-site via FTP (JiHaa blogs.html-patroon).
 * Gebruikt clients.site_ftp_* + website_blog_url.
 */

/** @return array{host: string, port: int, user: string, password: string, remote_path: string, blog_url: string, site_origin: string, index_file: string}|null */
function ce_static_site_ftp_config_for_client(PDO $pdo, ?string $clientId): ?array
{
    if ($clientId === null || $clientId === '') {
        return null;
    }

    ce_ensure_client_publish_columns($pdo);
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $host = trim((string) ($row['site_ftp_host'] ?? ''));
    $user = trim((string) ($row['site_ftp_user'] ?? ''));
    $password = ce_credential_resolve_plaintext('', (string) ($row['site_ftp_password'] ?? ''));
    $remotePath = trim((string) ($row['site_ftp_remote_path'] ?? ''));
    $blogUrl = trim((string) ($row['website_blog_url'] ?? ''));
    $port = (int) ($row['site_ftp_port'] ?? 21);
    if ($port <= 0) {
        $port = 21;
    }

    if ($host === '' || $user === '' || $password === '' || $remotePath === '' || $blogUrl === '') {
        return null;
    }

    $parts = parse_url($blogUrl);
    $scheme = (string) ($parts['scheme'] ?? 'https');
    $hostName = (string) ($parts['host'] ?? '');
    if ($hostName === '') {
        return null;
    }
    $origin = $scheme . '://' . $hostName;
    $path = (string) ($parts['path'] ?? '/blogs.html');
    $indexFile = basename($path !== '' ? $path : 'blogs.html');
    if ($indexFile === '' || $indexFile === '/' || !str_contains($indexFile, '.')) {
        $indexFile = 'blogs.html';
    }

    return [
        'host' => $host,
        'port' => $port,
        'user' => $user,
        'password' => $password,
        'remote_path' => rtrim(str_replace('\\', '/', $remotePath), '/') ?: '/',
        'blog_url' => $blogUrl,
        'site_origin' => $origin,
        'index_file' => $indexFile,
    ];
}

function ce_client_static_site_configured(PDO $pdo, ?string $clientId): bool
{
    return ce_static_site_ftp_config_for_client($pdo, $clientId) !== null;
}

function ce_static_site_slugify(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return 'blog-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    $map = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
        'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a',
        'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o',
        'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'Ý' => 'y', 'Ñ' => 'n', 'Ç' => 'c',
    ];
    $title = strtr($title, $map);
    $title = strtolower($title);
    $title = preg_replace('/[^a-z0-9]+/', '-', $title) ?? $title;
    $title = trim($title, '-');
    if ($title === '') {
        $title = 'blog-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    if (strlen($title) > 60) {
        $title = rtrim(substr($title, 0, 60), '-');
    }

    return $title;
}

function ce_static_site_excerpt_from_content(string $content, int $maxLen = 140): string
{
    $plain = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
    if ($plain === '') {
        return '';
    }
    if (preg_match('/^(.{1,' . $maxLen . '}?[.!?])(\s|$)/u', $plain, $m)) {
        return trim($m[1]);
    }
    if (mb_strlen($plain) <= $maxLen) {
        return $plain;
    }

    return rtrim(mb_substr($plain, 0, $maxLen - 1)) . '…';
}

function ce_static_site_content_to_html(string $content): string
{
    $content = trim(ce_strip_editorial_annotations_for_publish($content));
    if ($content === '') {
        return '<p></p>';
    }

    if (preg_match('/<(p|h[1-6]|ul|ol|li|blockquote|div|article)\b/i', $content)) {
        return $content;
    }

    $parts = preg_split("/\n{2,}/", $content) ?: [$content];
    $html = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^###\s+(.+)$/u', $part, $m)) {
            $html[] = '<h3>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</h3>';
            continue;
        }
        if (preg_match('/^##\s+(.+)$/u', $part, $m)) {
            $html[] = '<h2>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</h2>';
            continue;
        }
        $escaped = htmlspecialchars($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $escaped = nl2br($escaped, false);
        $html[] = '<p>' . $escaped . '</p>';
    }

    return $html === [] ? '<p></p>' : implode("\n\n  ", $html);
}

/**
 * @param array{site_origin?: string} $config
 */
function ce_static_site_render_article_html(
    string $title,
    string $bodyHtml,
    string $imageRelPath,
    string $hashtags,
    array $config
): string
{
    $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $titleEsc = $esc($title);
    $imgEsc = $esc($imageRelPath);
    $origin = rtrim((string) ($config['site_origin'] ?? 'https://jihaa.nl'), '/');
    $originEsc = $esc($origin);
    $footer = '';
    $tags = trim($hashtags);
    if ($tags !== '') {
        $footer = "\n  <footer>\n    <p>\n      <small>" . $esc($tags) . "</small>\n    </p>\n  </footer>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https:; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com; connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self';">
  <title>{$titleEsc} – JiHaa Blog</title>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-1XFNL8GNMZ"></script>
  <script src="js/ga.js"></script>
  <link rel="icon" type="image/gif" href="svg/JiHaa-Logo.gif">
  <link rel="stylesheet" href="css/styles.css" />
  <link href="https://fonts.googleapis.com/css2?family=Quicksand&family=Work+Sans&display=swap" rel="stylesheet" />
  <script src="js/main.js" defer></script>
</head>
<body>

<header>
  <div class="header-container">
    <a href="{$originEsc}" rel="noopener noreferrer"><img src="svg/JiHaa-24-White.svg" alt="JiHaa Logo" class="logo" /></a>
    <button class="menu-toggle" aria-label="Open main menu">☰</button>
  </div>
</header>

<div id="navOverlayBg" class="nav-overlay-bg"></div>
<nav id="navOverlay" class="nav-overlay" role="navigation" aria-label="Main menu">
    <div class="close">×</div>
    <ul>
      <li><a href="index.html">Home</a></li>
      <li><a href="clients.html">Cases</a></li>
      <li><a href="proces.html">Proces</a></li>
      <li><a href="about.html">About</a></li>
      <li><a href="blogs.html" class="active">Blogs</a></li>
    </ul>
</nav>

<main class="client-case-container archetype-page">
  <div class="case-details">
    <div class="left-column">
      <img src="{$imgEsc}" alt="{$titleEsc}" class="workflow-image" />
      <a href="blogs.html" class="return-to-overview">
        <img src="svg/arrow-left.svg" alt="back arrow" class="arrow-icon" />
        <span>Terug naar blogs</span>
      </a>
    </div>
    <div class="right-column">
      <h1 class="case-name">{$titleEsc}</h1>
      <article>
  {$bodyHtml}{$footer}
</article>
    </div>
  </div>
</main>

</body>
</html>
HTML;
}

function ce_static_site_render_card_html(string $slug, string $title, string $excerpt, string $imageRelPath): string
{
    $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return <<<HTML
		<a href="{$esc($slug)}.html" class="blog-post">
        <div class="blog-image">
          <img src="{$esc($imageRelPath)}" alt="{$esc($title)}" />
        </div>
        <div class="blog-content">
          <h2 class="blog-title">{$esc($title)}</h2>
          <p class="blog-excerpt">{$esc($excerpt)}</p>
          <div class="blog-view">
            <span>View</span>
            <img src="svg/arrow-right.svg" alt="view arrow" />
          </div>
        </div>
      </a>
HTML;
}

/** @return resource */
function ce_static_site_ftp_connect(array $config)
{
    $conn = @ftp_connect($config['host'], (int) $config['port'], 30);
    if ($conn === false) {
        throw new RuntimeException('FTP-verbinding mislukt naar ' . $config['host']);
    }
    if (!@ftp_login($conn, $config['user'], $config['password'])) {
        ftp_close($conn);
        throw new RuntimeException('FTP-login mislukt — controleer gebruiker/wachtwoord.');
    }
    @ftp_pasv($conn, true);

    return $conn;
}

function ce_static_site_ftp_remote(string $remotePath, string $relative): string
{
    $base = rtrim(str_replace('\\', '/', $remotePath), '/');
    $rel = ltrim(str_replace('\\', '/', $relative), '/');

    return ($base === '' || $base === '/' ? '' : $base) . '/' . $rel;
}

function ce_static_site_ftp_get_string($conn, string $remoteFile): string
{
    $tmp = tmpfile();
    if ($tmp === false) {
        throw new RuntimeException('Tijdelijk bestand aanmaken mislukt');
    }
    $meta = stream_get_meta_data($tmp);
    $path = (string) ($meta['uri'] ?? '');
    if ($path === '' || !@ftp_get($conn, $path, $remoteFile, FTP_BINARY)) {
        fclose($tmp);
        throw new RuntimeException('Kon ' . $remoteFile . ' niet downloaden via FTP');
    }
    $contents = stream_get_contents($tmp);
    fclose($tmp);
    if (!is_string($contents)) {
        throw new RuntimeException('Lege of ongeldige download: ' . $remoteFile);
    }

    return $contents;
}

function ce_static_site_ftp_put_string($conn, string $remoteFile, string $contents): void
{
    $tmp = tmpfile();
    if ($tmp === false) {
        throw new RuntimeException('Tijdelijk bestand aanmaken mislukt');
    }
    fwrite($tmp, $contents);
    rewind($tmp);
    $meta = stream_get_meta_data($tmp);
    $path = (string) ($meta['uri'] ?? '');
    $ok = $path !== '' && @ftp_put($conn, $remoteFile, $path, FTP_BINARY);
    fclose($tmp);
    if (!$ok) {
        throw new RuntimeException('Kon ' . $remoteFile . ' niet uploaden via FTP');
    }
}

function ce_static_site_ftp_put_file($conn, string $remoteFile, string $localPath): void
{
    if (!is_readable($localPath)) {
        throw new RuntimeException('Lokaal bestand niet leesbaar: ' . $localPath);
    }
    if (!@ftp_put($conn, $remoteFile, $localPath, FTP_BINARY)) {
        throw new RuntimeException('Kon ' . $remoteFile . ' niet uploaden via FTP');
    }
}

function ce_static_site_ftp_mkdir_p($conn, string $remoteDir): void
{
    $remoteDir = trim(str_replace('\\', '/', $remoteDir), '/');
    if ($remoteDir === '') {
        return;
    }
    $parts = explode('/', $remoteDir);
    $path = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $path .= '/' . $part;
        if (@ftp_chdir($conn, $path)) {
            @ftp_chdir($conn, '/');
            continue;
        }
        @ftp_mkdir($conn, $path);
    }
    @ftp_chdir($conn, '/');
}

function ce_static_site_local_image_path(?string $url): ?string
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

/** @return array{path: string, cleanup: bool} */
function ce_static_site_resolve_image_file(array $row, string $slug): array
{
    $imageUrl = ce_article_primary_image_url($row);
    if ($imageUrl === null) {
        throw new RuntimeException('Geen afbeelding bij het artikel — voeg eerst een beeld toe.');
    }

    $local = ce_static_site_local_image_path($imageUrl);
    if ($local !== null) {
        return ['path' => $local, 'cleanup' => false];
    }

    if (!preg_match('#^https?://#i', $imageUrl)) {
        $abs = dirname(__DIR__) . '/' . ltrim($imageUrl, '/');
        if (is_readable($abs)) {
            return ['path' => $abs, 'cleanup' => false];
        }
        throw new RuntimeException('Afbeelding niet gevonden: ' . $imageUrl);
    }

    $bytes = @file_get_contents($imageUrl);
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException('Afbeelding downloaden mislukt: ' . $imageUrl);
    }

    $ext = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg');
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $ext = 'jpg';
    }
    $tmp = sys_get_temp_dir() . '/ce-static-' . $slug . '.' . $ext;
    if (file_put_contents($tmp, $bytes) === false) {
        throw new RuntimeException('Kon afbeelding niet tijdelijk opslaan');
    }

    $webp = sys_get_temp_dir() . '/ce-static-' . $slug . '.webp';
    if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
        $img = @imagecreatefromstring($bytes);
        if ($img !== false) {
            if (@imagewebp($img, $webp, 82)) {
                imagedestroy($img);
                @unlink($tmp);

                return ['path' => $webp, 'cleanup' => true];
            }
            imagedestroy($img);
        }
    }

    return ['path' => $tmp, 'cleanup' => true];
}

function ce_static_site_insert_card(string $indexHtml, string $cardHtml, string $slug): string
{
    // Idempotent: vervang bestaande kaart voor dezelfde slug.
    $pattern = '#<a\s+href="' . preg_quote($slug, '#') . '\.html"[^>]*>.*?</a>#si';
    $indexHtml = preg_replace($pattern, '', $indexHtml) ?? $indexHtml;

    if (preg_match('/(<div\s+class="blogs-grid"\s*>)/i', $indexHtml, $m, PREG_OFFSET_CAPTURE)) {
        $pos = (int) $m[0][1] + strlen($m[1][0]);

        return substr($indexHtml, 0, $pos) . "\n" . $cardHtml . substr($indexHtml, $pos);
    }

    throw new RuntimeException('blogs-grid niet gevonden in ' . 'index — controleer website_blog_url / site-template.');
}

/**
 * Publiceer artikel als statische HTML via FTP + update blogs-overzicht.
 *
 * @return array{ok: bool, error?: string, postId?: string, postUrl?: string}
 */
function ce_publish_article_to_static_site(PDO $pdo, string $slotId): array
{
    ce_ensure_publish_columns($pdo);

    $clientId = ce_client_id_for_slot($pdo, $slotId);
    $row = ce_fetch_article_by_slot($pdo, $slotId, $clientId);
    if (!$row) {
        return ['ok' => false, 'error' => 'Artikel niet gevonden'];
    }

    $uiStatus = ce_status_to_ui($row['status'] ?? null);
    $existingId = trim((string) ($row['social_post_id'] ?? ''));
    $mayPublish = $uiStatus === 'approved'
        || ($uiStatus === 'published' && ($existingId === '' || str_starts_with($existingId, 'static:')));
    if (!$mayPublish) {
        return ['ok' => false, 'error' => 'Alleen goedgekeurde artikelen kunnen naar de website (status: ' . $uiStatus . ')'];
    }

    $config = ce_static_site_ftp_config_for_client($pdo, $clientId);
    if ($config === null) {
        return [
            'ok' => false,
            'error' => 'Statische site (FTP) is niet geconfigureerd. Vul Blog-URL en FTP in onder Integrations → WordPress.',
        ];
    }

    $title = ce_article_headline($row);
    if ($title === '') {
        return ['ok' => false, 'error' => 'Artikel heeft geen titel'];
    }

    $content = trim((string) ($row['content_human'] ?? ''));
    if ($content === '') {
        $content = (string) ($row['content_ai'] ?? '');
    }
    $content = ce_strip_editorial_annotations_for_publish($content);
    if (trim($content) === '') {
        return ['ok' => false, 'error' => 'Artikel heeft geen inhoud'];
    }

    $hashtags = trim((string) ($row['hashtags_final'] ?? $row['hashtags_suggested'] ?? ''));
    $slug = ce_static_site_slugify($title);
    if (str_starts_with($existingId, 'static:')) {
        $existingSlug = substr($existingId, strlen('static:'));
        if ($existingSlug !== '') {
            $slug = $existingSlug;
        }
    }

    $imageInfo = null;
    $conn = null;
    try {
        $imageInfo = ce_static_site_resolve_image_file($row, $slug);
        $imageExt = strtolower(pathinfo($imageInfo['path'], PATHINFO_EXTENSION) ?: 'webp');
        if (!in_array($imageExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $imageExt = 'webp';
        }
        $imageRel = 'blogs/' . $slug . '.' . $imageExt;
        $bodyHtml = ce_static_site_content_to_html($content);
        $articleHtml = ce_static_site_render_article_html($title, $bodyHtml, $imageRel, $hashtags, $config);
        $excerpt = ce_static_site_excerpt_from_content($content);
        if ($excerpt === '') {
            $excerpt = $title;
        }
        $cardHtml = ce_static_site_render_card_html($slug, $title, $excerpt, $imageRel);

        $conn = ce_static_site_ftp_connect($config);
        $remoteRoot = $config['remote_path'];
        ce_static_site_ftp_mkdir_p($conn, ce_static_site_ftp_remote($remoteRoot, 'blogs'));

        $indexRemote = ce_static_site_ftp_remote($remoteRoot, $config['index_file']);
        $indexHtml = ce_static_site_ftp_get_string($conn, $indexRemote);
        $indexHtml = ce_static_site_insert_card($indexHtml, $cardHtml, $slug);

        ce_static_site_ftp_put_file(
            $conn,
            ce_static_site_ftp_remote($remoteRoot, $imageRel),
            $imageInfo['path']
        );
        ce_static_site_ftp_put_string(
            $conn,
            ce_static_site_ftp_remote($remoteRoot, $slug . '.html'),
            $articleHtml
        );
        ce_static_site_ftp_put_string($conn, $indexRemote, $indexHtml);

        $postUrl = rtrim($config['site_origin'], '/') . '/' . $slug . '.html';
        $postId = 'static:' . $slug;
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
        if (ce_table_has_column($pdo, 'articles', 'social_post_url')) {
            $sets[] = 'social_post_url = :social_post_url';
            $params['social_post_url'] = $postUrl;
        }
        $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        ce_apply_ui_status($pdo, $slotId, $articleId, 'published');
        require_once __DIR__ . '/hba-v2-repository.php';
        ce_hba_v2_score_on_publish($pdo, $articleId, $clientId);

        $urls = ce_publish_urls_from_row($row);
        $urls['blog'] = $postUrl;
        ce_save_publish_urls($pdo, $articleId, $urls);

        require_once __DIR__ . '/tenant-activity-repository.php';
        ce_track_tenant_activity($pdo, $clientId, 'articles_published');

        return ['ok' => true, 'postId' => $postId, 'postUrl' => $postUrl];
    } catch (Throwable $e) {
        ce_log_event($pdo, 'error', 'publish', 'static_site_publish_failed', $e->getMessage(), [
            'clientId' => $clientId,
            'slotId' => $slotId,
        ]);

        return ['ok' => false, 'error' => $e->getMessage()];
    } finally {
        if ($conn) {
            @ftp_close($conn);
        }
        if (is_array($imageInfo) && !empty($imageInfo['cleanup']) && is_string($imageInfo['path'] ?? null)) {
            @unlink((string) $imageInfo['path']);
        }
    }
}
