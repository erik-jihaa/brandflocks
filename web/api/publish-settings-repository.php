<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/linkedin-oauth-helper.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/editor-questions.php';
require_once __DIR__ . '/event-log-repository.php';

/**
 * Selecteerbare publish-modes. De DB-enum bevat nog 'automatic', maar die modus bestaat niet meer:
 * een opgeslagen 'automatic' wordt overal gelezen als 'manual_with_review'.
 */
const CE_PUBLISH_MODES = ['manual_with_review', 'manual_without_review'];

function ce_normalize_publish_mode(mixed $mode): string
{
    $mode = strtolower(trim((string) ($mode ?? '')));

    return in_array($mode, CE_PUBLISH_MODES, true) ? $mode : 'manual_with_review';
}

/** Input van account/platform-update: 'automatic' → 'manual_with_review', onbekende waarde → fout. */
function ce_publish_mode_from_input(mixed $mode): string
{
    $mode = strtolower(trim((string) ($mode ?? '')));
    if ($mode === 'automatic') {
        return 'manual_with_review';
    }
    if (!in_array($mode, CE_PUBLISH_MODES, true)) {
        throw new InvalidArgumentException('publishMode is ongeldig');
    }

    return $mode;
}

function ce_ensure_client_publish_columns(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'clients')) {
        return;
    }

    $columns = [
        'website_blog_url' => 'VARCHAR(500) NULL',
        'wordpress_site_url' => 'VARCHAR(500) NULL',
        'wordpress_user' => 'VARCHAR(255) NULL',
        'wordpress_app_password' => 'VARCHAR(500) NULL',
        'wordpress_author_id' => 'INT NULL',
        'wordpress_fallback_media_filename' => 'VARCHAR(255) NULL',
        'site_ftp_host' => 'VARCHAR(255) NULL',
        'site_ftp_port' => 'INT NULL DEFAULT 21',
        'site_ftp_user' => 'VARCHAR(255) NULL',
        'site_ftp_password' => 'VARCHAR(500) NULL',
        'site_ftp_remote_path' => 'VARCHAR(500) NULL',
        'joomla_site_url' => 'VARCHAR(500) NULL',
        'joomla_api_token' => 'VARCHAR(500) NULL',
        'joomla_category_id' => 'INT NULL',
        'website_cms' => 'VARCHAR(20) NULL',
        'publish_extra_destinations' => 'TEXT NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (ce_table_has_column($pdo, 'clients', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE clients ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['clients']);
        } catch (Throwable) {
            // ignore
        }
    }
}

/** WordPress REST base — geen /wp-admin of /wp-json. Submap-installaties (bv. /blog) behouden. */
/** Standaard WP-fallback (bestandsnaam in mediabibliotheek) per tenant — overschrijfbaar via clients.wordpress_fallback_media_filename. */
const CE_WORDPRESS_FALLBACK_MEDIA_FILENAME_BY_CLIENT = [
    'fc767acf-c099-be6f-38a7-4a42f25f28d4' => '593e56d9-0738-4c9e-ad0c-2b3803a74bc8-1.png', // Axiom Strategy Group
];

function ce_wordpress_fallback_media_filename_for_client(PDO $pdo, string $clientId): string
{
    ce_ensure_client_publish_columns($pdo);
    $clientId = trim($clientId);
    if ($clientId === '') {
        return '';
    }

    if (ce_table_has_column($pdo, 'clients', 'wordpress_fallback_media_filename')) {
        $stmt = $pdo->prepare(
            'SELECT wordpress_fallback_media_filename FROM clients WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fromDb = trim((string) ($row['wordpress_fallback_media_filename'] ?? ''));
        if ($fromDb !== '') {
            return $fromDb;
        }
    }

    return CE_WORDPRESS_FALLBACK_MEDIA_FILENAME_BY_CLIENT[$clientId] ?? '';
}

function ce_normalize_wordpress_site_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $url = preg_replace('#/wp-admin/?.*$#i', '', $url) ?? $url;
    $url = preg_replace('#/wp-json/?.*$#i', '', $url) ?? $url;

    return rtrim($url, '/');
}

/** Vroege fout als de URL naar de CE-app wijst i.p.v. WordPress REST. */
function ce_wordpress_site_url_probe_error(string $siteUrl): ?string
{
    $siteUrl = ce_normalize_wordpress_site_url($siteUrl);
    if ($siteUrl === '') {
        return null;
    }

    $host = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
    $path = rtrim((string) parse_url($siteUrl, PHP_URL_PATH), '/');

    if ($host === 'staging.igisnext.com') {
        return 'staging.igisnext.com is alleen Content Engine — geen WordPress. '
            . 'Vul https://ce.igisnext.com/blog in (ook als je Integrations op staging opent).';
    }

    if ($host === 'ce.igisnext.com' && !str_ends_with(strtolower($path), '/blog') && $path !== '/blog') {
        return 'https://ce.igisnext.com zonder /blog is de CE-app. Site-URL voor publish: https://ce.igisnext.com/blog';
    }

    return null;
}

/** Login slug voor Basic Auth — geen e-mail, geen display name. */
function ce_normalize_wordpress_user(string $user): string
{
    $user = trim($user);
    if ($user === '') {
        return '';
    }

    if (
        (str_starts_with($user, '"') && str_ends_with($user, '"'))
        || (str_starts_with($user, "'") && str_ends_with($user, "'"))
    ) {
        $user = trim(substr($user, 1, -1));
    }

    if (strcasecmp($user, 'Content Engine') === 0) {
        return 'content-engine';
    }

    return $user;
}

/** Application Password — spaties en per ongeluk gekopieerde quotes verwijderen. */
function ce_normalize_wordpress_app_password(string $password): string
{
    $password = trim($password);
    if ($password === '') {
        return '';
    }

    if (
        (str_starts_with($password, '"') && str_ends_with($password, '"'))
        || (str_starts_with($password, "'") && str_ends_with($password, "'"))
    ) {
        $password = trim(substr($password, 1, -1));
    }

    return str_replace(' ', '', $password);
}

function ce_client_id_for_slot(PDO $pdo, string $slotId): ?string
{
    $stmt = $pdo->prepare('SELECT client_id FROM content_slots WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $slotId]);
    $id = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($id === '' || !preg_match('/^[0-9a-f-]{36}$/i', $id)) {
        return null;
    }

    return $id;
}

/** @return list<array<string, mixed>> */
function ce_publish_extra_destinations_from_json(?string $raw): array
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $out = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $kind = strtolower(trim((string) ($item['kind'] ?? '')));
        if (!in_array($kind, ['linkedin', 'magazine'], true)) {
            continue;
        }
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '') {
            $id = bin2hex(random_bytes(8));
        }
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') {
            $label = $kind === 'linkedin' ? 'LinkedIn' : 'Online magazine';
        }
        $entry = [
            'id' => $id,
            'kind' => $kind,
            'label' => $label,
            'enabled' => !empty($item['enabled']),
        ];
        if ($kind === 'magazine') {
            $entry['magazineUrl'] = trim((string) ($item['magazineUrl'] ?? ''));
        }
        if ($kind === 'linkedin') {
            $entry['organizationUrn'] = trim((string) ($item['organizationUrn'] ?? ''));
        }
        $out[] = $entry;
    }

    return $out;
}

/**
 * Extra bestemmingen voor publish — als LinkedIn OAuth gekoppeld is maar de rij ontbreekt in Account,
 * voeg LinkedIn automatisch toe (en sla op) zodat Publiceer niet stil overslaat.
 *
 * @return list<array<string, mixed>>
 */
function ce_publish_extras_resolved_for_client(PDO $pdo, ?string $clientId): array
{
    if ($clientId === null || $clientId === '') {
        return [];
    }

    $stmt = $pdo->prepare('SELECT publish_extra_destinations FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $clientRow = $stmt->fetch();
    $extras = ce_publish_extra_destinations_from_json(
        (string) ($clientRow['publish_extra_destinations'] ?? '')
    );

    $linkedinStatus = ce_linkedin_oauth_status_for_client($pdo, $clientId);
    if ($linkedinStatus['connected']) {
        $hasLinkedin = false;
        foreach ($extras as $extra) {
            if (($extra['kind'] ?? '') === 'linkedin') {
                $hasLinkedin = true;
                break;
            }
        }
        if (!$hasLinkedin) {
            $extras[] = [
                'id' => bin2hex(random_bytes(8)),
                'kind' => 'linkedin',
                'label' => 'LinkedIn',
                'enabled' => true,
            ];
            if ($clientRow && ce_table_has_column($pdo, 'clients', 'publish_extra_destinations')) {
                $pdo->prepare(
                    'UPDATE clients SET publish_extra_destinations = :json WHERE id = :id'
                )->execute([
                    'json' => ce_publish_extra_destinations_to_json($extras),
                    'id' => $clientId,
                ]);
            }
        }
    }

    return array_values(array_filter(
        $extras,
        static fn(array $entry): bool => !empty($entry['enabled']),
    ));
}

/** Na LinkedIn OAuth: extra bestemming in Account-veld zetten als die nog ontbreekt. */
function ce_linkedin_persist_publish_extra_row(PDO $pdo, string $clientId): void
{
    if (!ce_table_has_column($pdo, 'clients', 'publish_extra_destinations')) {
        return;
    }

    $stmt = $pdo->prepare('SELECT publish_extra_destinations FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $extras = ce_publish_extra_destinations_from_json(
        (string) ($row['publish_extra_destinations'] ?? '')
    );
    foreach ($extras as $extra) {
        if (($extra['kind'] ?? '') === 'linkedin') {
            return;
        }
    }

    $extras[] = [
        'id' => bin2hex(random_bytes(8)),
        'kind' => 'linkedin',
        'label' => 'LinkedIn',
        'enabled' => true,
    ];

    $pdo->prepare('UPDATE clients SET publish_extra_destinations = :json WHERE id = :id')->execute([
        'json' => ce_publish_extra_destinations_to_json($extras),
        'id' => $clientId,
    ]);
}

/** @param list<array<string, mixed>> $destinations */
function ce_publish_extra_destinations_to_json(array $destinations): string
{
    $normalized = ce_publish_extra_destinations_from_json(json_encode($destinations, JSON_THROW_ON_ERROR));

    return json_encode($normalized, JSON_THROW_ON_ERROR);
}

/** @return 'wordpress'|'joomla'|'static'|'' */
function ce_normalize_website_cms(?string $value): string
{
    $value = strtolower(trim((string) $value));

    return in_array($value, ['wordpress', 'joomla', 'static'], true) ? $value : '';
}

/** @param array<string, mixed> $row */
function ce_infer_website_cms_from_client_row(array $row): string
{
    require_once __DIR__ . '/joomla-publish-repository.php';

    $hasWp = ce_normalize_wordpress_site_url((string) ($row['wordpress_site_url'] ?? '')) !== ''
        || trim((string) ($row['wordpress_user'] ?? '')) !== ''
        || trim((string) ($row['wordpress_app_password'] ?? '')) !== '';

    $hasJoomla = ce_normalize_joomla_site_url((string) ($row['joomla_site_url'] ?? '')) !== ''
        || trim((string) ($row['joomla_api_token'] ?? '')) !== ''
        || (int) ($row['joomla_category_id'] ?? 0) > 0;

    $hasStatic = trim((string) ($row['site_ftp_host'] ?? '')) !== ''
        || trim((string) ($row['site_ftp_user'] ?? '')) !== ''
        || trim((string) ($row['site_ftp_password'] ?? '')) !== ''
        || trim((string) ($row['site_ftp_remote_path'] ?? '')) !== '';

    if ($hasWp) {
        return 'wordpress';
    }
    if ($hasJoomla) {
        return 'joomla';
    }
    if ($hasStatic) {
        return 'static';
    }

    return '';
}

/** @param array<string, mixed> $row */
function ce_client_website_cms_from_row(array $row): string
{
    $stored = ce_normalize_website_cms((string) ($row['website_cms'] ?? ''));

    return $stored !== '' ? $stored : ce_infer_website_cms_from_client_row($row);
}

/** @return list<string> */
function ce_website_cms_clear_sets(PDO $pdo, string $keepCms): array
{
    $keep = ce_normalize_website_cms($keepCms);
    $sets = [];

    if ($keep !== 'wordpress') {
        foreach (['wordpress_site_url', 'wordpress_user', 'wordpress_app_password', 'wordpress_author_id'] as $column) {
            if (ce_table_has_column($pdo, 'clients', $column)) {
                $sets[] = "$column = NULL";
            }
        }
    }

    if ($keep !== 'joomla') {
        foreach (['joomla_site_url', 'joomla_api_token', 'joomla_category_id'] as $column) {
            if (ce_table_has_column($pdo, 'clients', $column)) {
                $sets[] = "$column = NULL";
            }
        }
    }

    if ($keep !== 'static') {
        foreach (
            ['site_ftp_host', 'site_ftp_port', 'site_ftp_user', 'site_ftp_password', 'site_ftp_remote_path'] as $column
        ) {
            if (ce_table_has_column($pdo, 'clients', $column)) {
                $sets[] = $column === 'site_ftp_port' ? 'site_ftp_port = 21' : "$column = NULL";
            }
        }
    }

    return $sets;
}

/** @param list<string> $sets @param array<string, mixed> $params @param array<string, mixed> $body */
function ce_apply_website_cms_patch(PDO $pdo, array &$sets, array &$params, array $body): void
{
    if (!array_key_exists('websiteCms', $body) || !ce_table_has_column($pdo, 'clients', 'website_cms')) {
        return;
    }

    $cms = ce_normalize_website_cms((string) ($body['websiteCms'] ?? ''));
    $sets[] = 'website_cms = :website_cms';
    $params['website_cms'] = $cms !== '' ? $cms : null;

    foreach (ce_website_cms_clear_sets($pdo, $cms) as $clearSet) {
        $sets[] = $clearSet;
    }
}

/** @return array<string, mixed> */
function ce_account_publish_settings_dto(array $row): array
{
    $authorId = isset($row['wordpress_author_id']) ? (int) $row['wordpress_author_id'] : 0;
    $ftpPort = isset($row['site_ftp_port']) ? (int) $row['site_ftp_port'] : 21;

    return [
        'websiteCms' => ce_client_website_cms_from_row($row),
        'websiteBlogUrl' => (string) ($row['website_blog_url'] ?? ''),
        'wordpressSiteUrl' => (string) ($row['wordpress_site_url'] ?? ''),
        'wordpressUser' => (string) ($row['wordpress_user'] ?? ''),
        'hasWordpressAppPassword' => trim((string) ($row['wordpress_app_password'] ?? '')) !== '',
        'wordpressAuthorId' => $authorId > 0 ? $authorId : null,
        'siteFtpHost' => (string) ($row['site_ftp_host'] ?? ''),
        'siteFtpPort' => $ftpPort > 0 ? $ftpPort : 21,
        'siteFtpUser' => (string) ($row['site_ftp_user'] ?? ''),
        'hasSiteFtpPassword' => trim((string) ($row['site_ftp_password'] ?? '')) !== '',
        'siteFtpRemotePath' => (string) ($row['site_ftp_remote_path'] ?? ''),
        'joomlaSiteUrl' => (string) ($row['joomla_site_url'] ?? ''),
        'joomlaCategoryId' => isset($row['joomla_category_id']) ? (int) $row['joomla_category_id'] : null,
        'hasJoomlaApiToken' => trim((string) ($row['joomla_api_token'] ?? '')) !== '',
        'publishExtraDestinations' => ce_publish_extra_destinations_from_json(
            (string) ($row['publish_extra_destinations'] ?? '')
        ),
    ];
}

/** Of deze tenant zelf WordPress-velden heeft ingevuld (geen platform-fallback). */
function ce_client_wordpress_configured(PDO $pdo, ?string $clientId): bool
{
    if ($clientId === null || $clientId === '') {
        return false;
    }

    ce_ensure_client_publish_columns($pdo);

    $stmt = $pdo->prepare(
        'SELECT wordpress_site_url, wordpress_user, wordpress_app_password FROM clients WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    $site = ce_normalize_wordpress_site_url((string) ($row['wordpress_site_url'] ?? ''));
    $user = trim((string) ($row['wordpress_user'] ?? ''));
    $pass = ce_credential_resolve_plaintext('', (string) ($row['wordpress_app_password'] ?? ''));

    return $site !== '' && $user !== '' && $pass !== '';
}

function ce_client_website_publish_configured(PDO $pdo, ?string $clientId): bool
{
    return ce_client_website_cms_kind($pdo, $clientId) !== null;
}

/** @return 'wordpress'|'joomla'|'static'|null */
function ce_client_website_cms_kind(PDO $pdo, ?string $clientId): ?string
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

    $selected = ce_client_website_cms_from_row($row);
    if ($selected === '') {
        return null;
    }

    if ($selected === 'wordpress' && ce_client_wordpress_configured($pdo, $clientId)) {
        return 'wordpress';
    }

    require_once __DIR__ . '/joomla-publish-repository.php';
    if ($selected === 'joomla' && ce_client_joomla_configured($pdo, $clientId)) {
        return 'joomla';
    }

    require_once __DIR__ . '/static-site-publish-repository.php';
    if ($selected === 'static' && ce_client_static_site_configured($pdo, $clientId)) {
        return 'static';
    }

    return null;
}

/** @return array{site_url: string, user: string, app_password: string, author_id: int, blog_url: string} */
function ce_wordpress_config_for_client(PDO $pdo, ?string $clientId, bool $allowEnvFallback = true): array
{
    ce_ensure_client_publish_columns($pdo);
    ce_load_env();

    $site = '';
    $user = '';
    $pass = '';
    $authorId = 0;
    $blogUrl = '';

    if ($clientId !== null && $clientId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $row = $stmt->fetch();
        if ($row) {
            $site = ce_normalize_wordpress_site_url((string) ($row['wordpress_site_url'] ?? ''));
            $user = ce_normalize_wordpress_user((string) ($row['wordpress_user'] ?? ''));
            $pass = ce_normalize_wordpress_app_password(
                ce_credential_resolve_plaintext('', (string) ($row['wordpress_app_password'] ?? ''))
            );
            $authorId = (int) ($row['wordpress_author_id'] ?? 0);
            $blogUrl = trim((string) ($row['website_blog_url'] ?? ''));
        }
    }

    if ($allowEnvFallback) {
        $fallback = ce_wordpress_config_from_env();
        if ($site === '') {
            $site = $fallback['site_url'];
        }
        if ($user === '') {
            $user = $fallback['user'];
        }
        if ($pass === '') {
            $pass = $fallback['app_password'];
        }
        if ($authorId <= 0) {
            $authorId = $fallback['author_id'];
        }
    }

    if ($blogUrl === '' && $site !== '') {
        if (preg_match('#/blog/?$#i', $site)) {
            $blogUrl = rtrim($site, '/') . '/';
        } else {
            $blogUrl = rtrim($site, '/') . '/blog/';
        }
    }

    return [
        'site_url' => rtrim($site, '/'),
        'user' => $user,
        'app_password' => $pass,
        'author_id' => $authorId > 0 ? $authorId : 1,
        'blog_url' => $blogUrl,
    ];
}

/** @return array{site_url: string, user: string, app_password: string, author_id: int} */
function ce_wordpress_config_from_env(): array
{
    ce_load_env();

    $site = trim((string) (getenv('WORDPRESS_SITE_URL') ?: ''));
    $user = trim((string) (getenv('WORDPRESS_USER') ?: ''));
    $pass = trim((string) (getenv('WORDPRESS_APP_PASSWORD') ?: ''));
    $authorId = (int) (getenv('WORDPRESS_AUTHOR_ID') ?: 0);

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $site = $site !== '' ? $site : trim((string) ($config['wordpress_site_url'] ?? ''));
            $user = $user !== '' ? $user : trim((string) ($config['wordpress_user'] ?? ''));
            $pass = $pass !== '' ? $pass : trim((string) ($config['wordpress_app_password'] ?? ''));
            if ($authorId <= 0) {
                $authorId = (int) ($config['wordpress_author_id'] ?? 0);
            }
        }
    }

    return [
        'site_url' => rtrim($site, '/'),
        'user' => $user,
        'app_password' => $pass,
        'author_id' => $authorId > 0 ? $authorId : 1,
    ];
}

/** @param array<string, mixed> $row @param array<string, mixed> $dest */
function ce_publish_extra_destination(PDO $pdo, array $row, array $dest, ?string $clientId = null): array
{
    $kind = (string) ($dest['kind'] ?? '');
    $label = (string) ($dest['label'] ?? $kind);

    if ($kind === 'linkedin') {
        if ($clientId === null) {
            return [
                'kind' => 'linkedin',
                'label' => $label,
                'ok' => false,
                'message' => 'LinkedIn: geen client gevonden voor dit artikel.',
            ];
        }

        $status = ce_linkedin_oauth_status_for_client($pdo, $clientId);
        if (!$status['connected']) {
            return [
                'kind' => 'linkedin',
                'label' => $label,
                'ok' => false,
                'message' => 'LinkedIn niet gekoppeld — koppel je profiel via Integrations → LinkedIn.',
            ];
        }

        $text = ce_linkedin_post_text_from_article($row);
        $imageUrl = ce_article_primary_image_url($row);
        $organizationUrn = trim((string) ($dest['organizationUrn'] ?? ''));
        $result = ce_linkedin_publish_post(
            $pdo,
            $clientId,
            $text,
            $imageUrl,
            $organizationUrn !== '' ? $organizationUrn : null
        );

        if (empty($result['ok'])) {
            ce_log_event($pdo, 'error', 'publish', 'linkedin_publish_failed', (string) ($result['error'] ?? ''), [
                'clientId' => $clientId,
                'slotId' => $row['slot_id'] ?? null,
                'organization' => $organizationUrn !== '' ? 'company' : 'member',
            ]);
        }

        require_once __DIR__ . '/publications-repository.php';
        $ok = !empty($result['ok']);
        $postUrl = trim((string) ($result['postUrl'] ?? ''));
        ce_record_publication_outcome(
            $pdo,
            (string) ($row['id'] ?? ''),
            'linkedin',
            $ok,
            null,
            $ok && $postUrl !== '' ? $postUrl : null,
            $ok ? null : (string) ($result['error'] ?? 'LinkedIn publiceren mislukt'),
        );

        return [
            'kind' => 'linkedin',
            'label' => $label,
            'ok' => $ok,
            'postUrl' => $result['postUrl'] ?? null,
            'message' => $ok
                ? null
                : (string) ($result['error'] ?? 'LinkedIn publiceren mislukt'),
        ];
    }

    if ($kind === 'magazine') {
        $magUrl = trim((string) ($dest['magazineUrl'] ?? ''));

        return [
            'kind' => 'magazine',
            'label' => $label,
            'ok' => false,
            'pending' => true,
            'message' => $magUrl !== ''
                ? 'Magazine (' . $magUrl . '): automatische upload volgt — plaats handmatig indien nodig.'
                : 'Magazine: vul de URL in bij Account → extra bestemming.',
        ];
    }

    return [
        'kind' => $kind,
        'label' => $label,
        'ok' => false,
        'message' => 'Onbekende bestemming',
    ];
}

/** @param array<string, mixed> $row @return string|null lokale of remote afbeelding-URL */
function ce_article_primary_image_url(array $row): ?string
{
    $imageRatio = ce_image_ratio_to_ui($row['image_ratio_final'] ?? $row['image_ratio_suggested'] ?? null);
    $square = trim((string) ($row['image_url_square'] ?? ''));
    $landscape = trim((string) ($row['image_url_landscape'] ?? ''));
    $imageUrl = $imageRatio === 'landscape'
        ? ($landscape !== '' ? $landscape : $square)
        : ($square !== '' ? $square : $landscape);

    return $imageUrl !== '' ? $imageUrl : null;
}

/** @param array<string, mixed> $row */
function ce_linkedin_post_text_from_article(array $row): string
{
    $title = ce_article_headline($row);
    $content = trim((string) ($row['content_human'] ?? ''));
    if ($content === '') {
        $content = (string) ($row['content_ai'] ?? '');
    }
    $content = ce_strip_editorial_annotations_for_publish($content);
    $content = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;

    $hashtags = trim((string) ($row['hashtags_final'] ?? $row['hashtags_suggested'] ?? ''));

    $parts = [];
    if ($title !== '') {
        $parts[] = $title;
    }
    if ($content !== '') {
        $parts[] = $content;
    }
    if ($hashtags !== '') {
        $parts[] = $hashtags;
    }

    return implode("\n\n", $parts);
}
