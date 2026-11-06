<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/publish-settings-repository.php';

function ce_ensure_account_columns(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'clients')) {
        return;
    }

    ce_ensure_client_publish_columns($pdo);

    $columns = [
        'contact_name' => 'VARCHAR(255) NULL',
        'contact_role' => "VARCHAR(20) NULL DEFAULT 'both'",
        'contact_email' => 'VARCHAR(255) NULL',
        'reviewer_email' => 'VARCHAR(255) NULL',
        'logo_url' => 'VARCHAR(500) NULL',
        'password_hash' => 'VARCHAR(255) NULL',
        'publish_mode' => "ENUM('manual_with_review', 'manual_without_review', 'automatic') NOT NULL DEFAULT 'manual_with_review'",
        'image_database_url' => 'VARCHAR(500) NULL',
        'image_database_password' => 'VARCHAR(500) NULL',
        'writing_style' => "VARCHAR(100) NULL DEFAULT 'zakelijk_direct'",
        'google_place_id' => 'VARCHAR(255) NULL',
        'planning_horizon_weeks' => 'INT NOT NULL DEFAULT 4',
        'posts_per_week' => 'INT NOT NULL DEFAULT 1',
        'source_checks_per_week' => 'INT NOT NULL DEFAULT 1',
        /** 0 = niet meenemen in wekelijkse CE-01-scheduler (n8n); handmatige CE-01 per client_id blijft mogelijk. */
        'ce01_weekly_schedule' => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];

    foreach ($columns as $column => $definition) {
        if (ce_table_has_column($pdo, 'clients', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE clients ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['clients']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }
}

function ce_client_logo_dir(): string
{
    return dirname(__DIR__) . '/uploads/logos';
}

/** Verwijdert alle bekende logo-bestanden van een tenant (png/jpg/webp/svg). */
function ce_remove_client_logo_files(string $clientId): void
{
    $clientId = trim($clientId);
    if ($clientId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        return;
    }
    $dir = ce_client_logo_dir();
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
        $path = $dir . '/' . $clientId . '.' . $ext;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * SVG wordt niet geaccepteerd: dat bestandsformaat kan script bevatten en logo's
 * worden vanaf hetzelfde domein geserveerd.
 *
 * @return 'png'|'jpg'|'webp'|null
 */
function ce_logo_extension_from_upload(string $tmpPath, string $originalName): ?string
{
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($tmpPath) ?: '');
    }
    $ext = match ($mime) {
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        default => null,
    };
    if ($ext !== null) {
        return $ext;
    }

    if ($mime !== '') {
        return null;
    }

    $fromName = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($fromName, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        return $fromName === 'jpeg' ? 'jpg' : $fromName;
    }

    return null;
}

/**
 * Herkent SVG ook als de browser een ander MIME-type meestuurt, zodat we de
 * gebruiker kunnen uitleggen waarom het bestand geweigerd wordt.
 */
function ce_upload_looks_like_svg(string $tmpPath, string $originalName): bool
{
    if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'svg') {
        return true;
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (str_contains(strtolower((string) ($finfo->file($tmpPath) ?: '')), 'svg')) {
            return true;
        }
    }

    $head = (string) @file_get_contents($tmpPath, false, null, 0, 512);

    return $head !== '' && stripos($head, '<svg') !== false;
}

/** @return array<string, mixed> */
function ce_account_dto(array $row): array
{
    $role = strtolower(trim((string) ($row['contact_role'] ?? 'both')));
    if (!in_array($role, ['contact', 'reviewer', 'both'], true)) {
        $role = 'both';
    }

    $publishMode = ce_normalize_publish_mode($row['publish_mode'] ?? null);

    return [
        'clientId' => (string) ($row['id'] ?? ''),
        'company' => (string) ($row['name'] ?? ''),
        'contactName' => (string) ($row['contact_name'] ?? ''),
        'contactRole' => $role,
        'contactEmail' => (string) ($row['contact_email'] ?? ''),
        'reviewerEmail' => (string) ($row['reviewer_email'] ?? ''),
        'logoUrl' => (string) ($row['logo_url'] ?? ''),
        'publishMode' => $publishMode,
        'imageDatabaseUrl' => (string) ($row['image_database_url'] ?? ''),
        'hasImageDatabasePassword' => trim((string) ($row['image_database_password'] ?? '')) !== '',
        'active' => (bool) ($row['active'] ?? true),
        'planningHorizonWeeks' => isset($row['planning_horizon_weeks']) ? (int) $row['planning_horizon_weeks'] : null,
        'postsPerWeek' => isset($row['posts_per_week']) ? (int) $row['posts_per_week'] : null,
        'sourceChecksPerWeek' => isset($row['source_checks_per_week']) ? (int) $row['source_checks_per_week'] : null,
        'writingStyle' => trim((string) ($row['writing_style'] ?? 'zakelijk_direct')) ?: 'zakelijk_direct',
        'googlePlaceId' => (string) ($row['google_place_id'] ?? ''),
        'hasPassword' => trim((string) ($row['password_hash'] ?? '')) !== '',
    ] + ce_account_publish_settings_dto($row);
}

/** @return array<string, mixed> */
function ce_fetch_account(PDO $pdo, ?string $clientId = null): array
{
    ce_ensure_account_columns($pdo);
    $clientId = ce_resolve_client_id($pdo, $clientId);
    if ($clientId === null) {
        return ['account' => null, 'error' => 'Geen client gevonden'];
    }

    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['account' => null, 'error' => 'Client niet gevonden'];
    }

    return ['account' => ce_account_dto($row)];
}

/**
 * Tenants die bij dit verzoek horen. Een ingelogde tenant ziet alleen de tenants
 * waar zijn eigen e-mailadres contact of reviewer is — niet de hele klantenlijst.
 */
function ce_search_clients(PDO $pdo, ?string $query = null): array
{
    if (!ce_table_exists($pdo, 'clients')) {
        return ['clients' => []];
    }

    ce_ensure_account_columns($pdo);

    $sql = 'SELECT id, name, contact_email FROM clients WHERE 1=1';
    $params = [];

    if (!ce_request_is_service_call() && !ce_request_has_platform_session()) {
        $email = ce_session_login_email();
        if ($email === null) {
            return ['clients' => []];
        }
        $sql .= ' AND (LOWER(TRIM(contact_email)) = :email';
        if (ce_table_has_column($pdo, 'clients', 'reviewer_email')) {
            $sql .= ' OR LOWER(TRIM(reviewer_email)) = :email';
        }
        $sql .= ')';
        $params['email'] = $email;
    }

    $q = trim((string) ($query ?? ''));
    if ($q !== '') {
        $sql .= ' AND (name LIKE :q OR contact_email LIKE :q OR contact_name LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY name ASC LIMIT 25';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $clients = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $clients[] = [
            'clientId' => (string) ($row['id'] ?? ''),
            'company' => (string) ($row['name'] ?? ''),
            'contactEmail' => (string) ($row['contact_email'] ?? ''),
        ];
    }

    return ['clients' => $clients];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_create_client(PDO $pdo, array $body): array
{
    if (!ce_table_exists($pdo, 'clients')) {
        throw new RuntimeException('clients-tabel ontbreekt');
    }

    ce_ensure_account_columns($pdo);

    $company = trim((string) ($body['company'] ?? $body['name'] ?? ''));
    if ($company === '') {
        throw new InvalidArgumentException('company is verplicht');
    }

    $contactEmail = strtolower(trim((string) ($body['contactEmail'] ?? $body['contact_email'] ?? '')));
    if ($contactEmail === '') {
        throw new InvalidArgumentException('contactEmail is verplicht');
    }

    $dup = $pdo->prepare('SELECT id FROM clients WHERE LOWER(TRIM(name)) = LOWER(:name) LIMIT 1');
    $dup->execute(['name' => $company]);
    if ($dup->fetch()) {
        throw new InvalidArgumentException('Er bestaat al een client met deze bedrijfsnaam');
    }

    $clientId = trim((string) ($body['clientId'] ?? ''));
    if ($clientId === '') {
        $clientId = sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6)),
        );
    }

    $fieldMap = [
        'name' => $company,
        'archetype' => (string) ($body['archetype'] ?? ''),
        'archetype_traits' => (string) ($body['archetypeTraits'] ?? $body['archetype_traits'] ?? ''),
        'adversary' => (string) ($body['adversary'] ?? ''),
        'brand_goal' => (string) ($body['brandstory'] ?? $body['brandGoal'] ?? $body['brand_goal'] ?? ''),
        'tone_of_voice' => (string) ($body['toneOfVoice'] ?? $body['tone_of_voice'] ?? ''),
        'writing_guide' => (string) ($body['writingGuide'] ?? $body['writing_guide'] ?? ''),
        'contact_name' => (string) ($body['contactName'] ?? $body['contact_name'] ?? ''),
        'contact_role' => strtolower((string) ($body['contactRole'] ?? $body['contact_role'] ?? 'both')),
        'contact_email' => $contactEmail,
        'reviewer_email' => (string) ($body['reviewerEmail'] ?? $body['reviewer_email'] ?? $contactEmail),
        'planning_horizon_weeks' => max(1, (int) ($body['planningHorizonWeeks'] ?? $body['planning_horizon_weeks'] ?? 4)),
        'posts_per_week' => max(1, (int) ($body['postsPerWeek'] ?? $body['posts_per_week'] ?? 1)),
        'source_checks_per_week' => max(1, (int) ($body['sourceChecksPerWeek'] ?? $body['source_checks_per_week'] ?? 1)),
    ];

    if (!in_array($fieldMap['contact_role'], ['contact', 'reviewer', 'both'], true)) {
        $fieldMap['contact_role'] = 'both';
    }

    $cols = ['id'];
    $placeholders = [':id'];
    $params = ['id' => $clientId];

    foreach ($fieldMap as $column => $value) {
        if (!ce_table_has_column($pdo, 'clients', $column)) {
            continue;
        }
        $cols[] = $column;
        $placeholders[] = ':' . $column;
        $params[$column] = $value;
    }

    if (ce_table_has_column($pdo, 'clients', 'active')) {
        $cols[] = 'active';
        $placeholders[] = ':active';
        $params['active'] = !empty($body['active']) || !array_key_exists('active', $body) ? 1 : 0;
    }

    $password = (string) ($body['password'] ?? '');
    if ($password !== '' && ce_table_has_column($pdo, 'clients', 'password_hash')) {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Wachtwoord moet minimaal 8 tekens zijn');
        }
        $cols[] = 'password_hash';
        $placeholders[] = ':password_hash';
        $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql = 'INSERT INTO clients (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $pdo->prepare($sql)->execute($params);

    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Client aanmaken mislukt');
    }

    return [
        'ok' => true,
        'client' => ce_account_dto($row),
        'strategy' => ce_strategy_dto($pdo, $row),
    ];
}

/** BrandArchitecture “klaar” — merkarchitectuur (brand_narratives / complete-flag), fallback archetype + tegenstander. */
function ce_client_styleguide_ready(?array $clientRow, ?PDO $pdo = null): bool
{
    if ($clientRow === null) {
        return false;
    }

    if (trim((string) ($clientRow['hba_architecture_completed_at'] ?? '')) !== '') {
        return true;
    }

    $clientId = trim((string) ($clientRow['id'] ?? ''));
    if ($pdo !== null && $clientId !== '') {
        require_once __DIR__ . '/hba-v2-repository.php';
        if (ce_hba_v2_architecture_ready($pdo, $clientId)) {
            return true;
        }
    }

    return trim((string) ($clientRow['archetype'] ?? '')) !== ''
        && trim((string) ($clientRow['adversary'] ?? '')) !== '';
}

function ce_client_password_matches(
    array $row,
    string $password,
    ?PDO $pdo = null,
    string $email = '',
): bool {
    $hash = trim((string) ($row['password_hash'] ?? ''));
    if ($hash !== '' && password_verify($password, $hash)) {
        return true;
    }

    if ($pdo !== null && ce_login_email_is_guest_of_client($row, $email)) {
        if (ce_login_email_credentials_match($pdo, $email, $password)) {
            return true;
        }
    }

    return false;
}

/** Het e-mailadres waarmee deze sessie is ingelogd, of null. */
function ce_session_login_email(): ?string
{
    ce_session_start();
    if (empty($_SESSION['ce_authenticated'])) {
        return null;
    }

    $email = strtolower(trim((string) ($_SESSION['ce_login_email'] ?? '')));

    return $email !== '' ? $email : null;
}

/**
 * Wisselen mag alleen naar een tenant waar het ingelogde e-mailadres contact of
 * reviewer is. Platform-beheerders en service-aanroepen mogen overal heen.
 *
 * @param array<string, mixed> $target
 */
function ce_may_switch_to_client(PDO $pdo, array $target): bool
{
    if (ce_request_is_service_call() || ce_request_has_platform_session()) {
        return true;
    }

    $email = ce_session_login_email();
    if ($email === null) {
        return false;
    }

    return ce_login_email_is_guest_of_client($target, $email);
}

/** Meekijker: e-mailadres staat als contact of reviewer op deze tenant. */
function ce_login_email_is_guest_of_client(array $row, string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }

    $linked = [
        strtolower(trim((string) ($row['contact_email'] ?? ''))),
        strtolower(trim((string) ($row['reviewer_email'] ?? ''))),
    ];

    return in_array($email, array_filter($linked), true);
}

/**
 * Verifieert het wachtwoord tegen de eigen inloggegevens van dit e-mailadres:
 * het platform-account of de tenant waar het adres zelf hoofdcontact is.
 * Zo houdt de hoofdtenant zijn eigen wachtwoord bij meekijk-toegang.
 */
function ce_login_email_credentials_match(PDO $pdo, string $email, string $password): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        return false;
    }

    if (ce_table_exists($pdo, 'platform_users')) {
        $stmt = $pdo->prepare(
            'SELECT password_hash FROM platform_users
             WHERE LOWER(TRIM(email)) = :email AND active = 1'
        );
        $stmt->execute(['email' => $email]);
        foreach ($stmt->fetchAll() ?: [] as $user) {
            $hash = trim((string) ($user['password_hash'] ?? ''));
            if ($hash !== '' && password_verify($password, $hash)) {
                return true;
            }
        }
    }

    if (ce_table_exists($pdo, 'clients')) {
        $stmt = $pdo->prepare(
            'SELECT password_hash FROM clients WHERE LOWER(TRIM(contact_email)) = :email'
        );
        $stmt->execute(['email' => $email]);
        foreach ($stmt->fetchAll() ?: [] as $owned) {
            $hash = trim((string) ($owned['password_hash'] ?? ''));
            if ($hash !== '' && password_verify($password, $hash)) {
                return true;
            }
        }
    }

    return false;
}

/** @return list<array<string, mixed>> */
function ce_clients_matching_login_email(PDO $pdo, string $email): array
{
    ce_ensure_account_columns($pdo);
    if (!ce_table_exists($pdo, 'clients')) {
        return [];
    }

    $email = strtolower(trim($email));
    if ($email === '') {
        return [];
    }

    $sql = 'SELECT * FROM clients WHERE LOWER(TRIM(contact_email)) = :email';
    if (ce_table_has_column($pdo, 'clients', 'reviewer_email')) {
        $sql .= ' OR LOWER(TRIM(reviewer_email)) = :email';
    }
    $sql .= ' ORDER BY name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);

    return $stmt->fetchAll() ?: [];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_update_client_admin(PDO $pdo, array $body): array
{
    if (!ce_table_exists($pdo, 'clients')) {
        throw new RuntimeException('clients-tabel ontbreekt');
    }

    ce_ensure_account_columns($pdo);

    $clientId = trim((string) ($body['clientId'] ?? ''));
    if ($clientId === '') {
        throw new InvalidArgumentException('clientId is verplicht');
    }

    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Client niet gevonden');
    }

    $map = [
        'company' => 'name',
        'name' => 'name',
        'archetype' => 'archetype',
        'archetypeTraits' => 'archetype_traits',
        'archetype_traits' => 'archetype_traits',
        'adversary' => 'adversary',
        'brandstory' => 'brand_goal',
        'brandGoal' => 'brand_goal',
        'brand_goal' => 'brand_goal',
        'toneOfVoice' => 'tone_of_voice',
        'tone_of_voice' => 'tone_of_voice',
        'writingGuide' => 'writing_guide',
        'writing_guide' => 'writing_guide',
        'contactName' => 'contact_name',
        'contact_name' => 'contact_name',
        'contactRole' => 'contact_role',
        'contact_role' => 'contact_role',
        'contactEmail' => 'contact_email',
        'contact_email' => 'contact_email',
        'reviewerEmail' => 'reviewer_email',
        'reviewer_email' => 'reviewer_email',
        'logoUrl' => 'logo_url',
        'logo_url' => 'logo_url',
    ];

    $sets = [];
    $params = ['id' => $clientId];

    foreach ($map as $jsonKey => $dbCol) {
        if (!array_key_exists($jsonKey, $body)) {
            continue;
        }
        if (!ce_table_has_column($pdo, 'clients', $dbCol)) {
            continue;
        }
        $value = trim((string) $body[$jsonKey]);
        if ($jsonKey === 'contactEmail' || $jsonKey === 'contact_email') {
            $value = strtolower($value);
        }
        if (($jsonKey === 'contactRole' || $jsonKey === 'contact_role') && $value !== '') {
            $value = strtolower($value);
            if (!in_array($value, ['contact', 'reviewer', 'both'], true)) {
                throw new InvalidArgumentException('contactRole moet contact, reviewer of both zijn');
            }
        }
        $sets[] = "$dbCol = :$dbCol";
        $params[$dbCol] = $value;
    }

    if (array_key_exists('planningHorizonWeeks', $body) && ce_table_has_column($pdo, 'clients', 'planning_horizon_weeks')) {
        $sets[] = 'planning_horizon_weeks = :planning_horizon_weeks';
        $params['planning_horizon_weeks'] = max(1, (int) $body['planningHorizonWeeks']);
    }
    if (array_key_exists('postsPerWeek', $body) && ce_table_has_column($pdo, 'clients', 'posts_per_week')) {
        $sets[] = 'posts_per_week = :posts_per_week';
        $params['posts_per_week'] = max(1, (int) $body['postsPerWeek']);
    }
    if (array_key_exists('sourceChecksPerWeek', $body) && ce_table_has_column($pdo, 'clients', 'source_checks_per_week')) {
        $sets[] = 'source_checks_per_week = :source_checks_per_week';
        $params['source_checks_per_week'] = max(1, (int) $body['sourceChecksPerWeek']);
    }
    if (
        (array_key_exists('ce01WeeklySchedule', $body) || array_key_exists('ce01_weekly_schedule', $body))
        && ce_table_has_column($pdo, 'clients', 'ce01_weekly_schedule')
    ) {
        $raw = $body['ce01WeeklySchedule'] ?? $body['ce01_weekly_schedule'];
        $sets[] = 'ce01_weekly_schedule = :ce01_weekly_schedule';
        $params['ce01_weekly_schedule'] = !empty($raw) ? 1 : 0;
    }
    if (array_key_exists('active', $body) && ce_table_has_column($pdo, 'clients', 'active')) {
        $sets[] = 'active = :active';
        $params['active'] = !empty($body['active']) ? 1 : 0;
    }

    $copyFrom = trim((string) ($body['copyPasswordFromClientId'] ?? ''));
    if ($copyFrom !== '' && ce_table_has_column($pdo, 'clients', 'password_hash')) {
        $source = $pdo->prepare('SELECT password_hash FROM clients WHERE id = :id LIMIT 1');
        $source->execute(['id' => $copyFrom]);
        $sourceRow = $source->fetch();
        $sourceHash = trim((string) ($sourceRow['password_hash'] ?? ''));
        if ($sourceHash === '') {
            throw new InvalidArgumentException('Bron-client heeft geen wachtwoord');
        }
        $sets[] = 'password_hash = :password_hash';
        $params['password_hash'] = $sourceHash;
    }

    $copyStrategyFrom = trim((string) ($body['copyStrategyFromClientId'] ?? ''));
    if ($copyStrategyFrom !== '') {
        require_once __DIR__ . '/client-repository.php';
        ce_ensure_strategy_columns($pdo);
        $source = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $source->execute(['id' => $copyStrategyFrom]);
        $sourceRow = $source->fetch();
        if (!$sourceRow) {
            throw new InvalidArgumentException('Bron-client voor strategy niet gevonden');
        }
        foreach (
            [
                'archetype',
                'archetype_traits',
                'adversary',
                'brand_goal',
                'tone_of_voice',
                'writing_guide',
            ] as $column
        ) {
            if (!ce_table_has_column($pdo, 'clients', $column)) {
                continue;
            }
            $sets[] = "$column = :copy_$column";
            $params["copy_$column"] = (string) ($sourceRow[$column] ?? '');
        }
    }

    $copyWordpressFrom = trim((string) ($body['copyWordpressFromClientId'] ?? ''));
    if ($copyWordpressFrom !== '') {
        require_once __DIR__ . '/publish-settings-repository.php';
        ce_ensure_client_publish_columns($pdo);
        $source = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $source->execute(['id' => $copyWordpressFrom]);
        $sourceRow = $source->fetch();
        if (!$sourceRow) {
            throw new InvalidArgumentException('Bron-client voor WordPress niet gevonden');
        }
        foreach (
            [
                'wordpress_site_url',
                'wordpress_user',
                'wordpress_app_password',
                'wordpress_author_id',
                'website_blog_url',
            ] as $column
        ) {
            if (!ce_table_has_column($pdo, 'clients', $column)) {
                continue;
            }
            $sets[] = "$column = :copy_wp_$column";
            $params["copy_wp_$column"] = $sourceRow[$column] ?? null;
        }
    }

    if (array_key_exists('publishMode', $body) && ce_table_has_column($pdo, 'clients', 'publish_mode')) {
        $mode = ce_publish_mode_from_input($body['publishMode']);
        $sets[] = 'publish_mode = :publish_mode';
        $params['publish_mode'] = $mode;
    }

    require_once __DIR__ . '/publish-settings-repository.php';
    ce_ensure_client_publish_columns($pdo);
    ce_apply_website_cms_patch($pdo, $sets, $params, $body);
    $publishScalars = [
        'websiteBlogUrl' => 'website_blog_url',
        'wordpressSiteUrl' => 'wordpress_site_url',
        'wordpressUser' => 'wordpress_user',
        'siteFtpHost' => 'site_ftp_host',
        'siteFtpUser' => 'site_ftp_user',
        'siteFtpRemotePath' => 'site_ftp_remote_path',
    ];
    foreach ($publishScalars as $jsonKey => $dbCol) {
        if (!array_key_exists($jsonKey, $body) || !ce_table_has_column($pdo, 'clients', $dbCol)) {
            continue;
        }
        $value = trim((string) $body[$jsonKey]);
        if ($jsonKey === 'wordpressSiteUrl') {
            $value = ce_normalize_wordpress_site_url($value);
        }
        if ($jsonKey === 'wordpressUser') {
            $value = ce_normalize_wordpress_user($value);
        }
        $sets[] = "$dbCol = :$dbCol";
        $params[$dbCol] = $value;
    }
    if (array_key_exists('wordpressAuthorId', $body) && ce_table_has_column($pdo, 'clients', 'wordpress_author_id')) {
        $rawAuthor = $body['wordpressAuthorId'];
        $authorId = $rawAuthor === null || $rawAuthor === '' ? null : max(1, (int) $rawAuthor);
        $sets[] = 'wordpress_author_id = :wordpress_author_id';
        $params['wordpress_author_id'] = $authorId;
    }
    if (array_key_exists('siteFtpPort', $body) && ce_table_has_column($pdo, 'clients', 'site_ftp_port')) {
        $sets[] = 'site_ftp_port = :site_ftp_port';
        $params['site_ftp_port'] = max(1, (int) ($body['siteFtpPort'] ?? 21));
    }
    if (($body['wordpressAppPassword'] ?? '') !== '' && ce_table_has_column($pdo, 'clients', 'wordpress_app_password')) {
        require_once __DIR__ . '/credential-crypto.php';
        $sets[] = 'wordpress_app_password = :wordpress_app_password';
        $params['wordpress_app_password'] = ce_credential_encrypt(
            ce_normalize_wordpress_app_password(trim((string) $body['wordpressAppPassword']))
        );
    }
    if (($body['siteFtpPassword'] ?? '') !== '' && ce_table_has_column($pdo, 'clients', 'site_ftp_password')) {
        require_once __DIR__ . '/credential-crypto.php';
        $sets[] = 'site_ftp_password = :site_ftp_password';
        $params['site_ftp_password'] = ce_credential_encrypt(trim((string) $body['siteFtpPassword']));
    }
    if (array_key_exists('joomlaSiteUrl', $body) && ce_table_has_column($pdo, 'clients', 'joomla_site_url')) {
        require_once __DIR__ . '/joomla-publish-repository.php';
        $sets[] = 'joomla_site_url = :joomla_site_url';
        $params['joomla_site_url'] = ce_normalize_joomla_site_url(trim((string) $body['joomlaSiteUrl']));
    }
    if (array_key_exists('joomlaCategoryId', $body) && ce_table_has_column($pdo, 'clients', 'joomla_category_id')) {
        $rawCategory = $body['joomlaCategoryId'];
        $categoryId = $rawCategory === null || $rawCategory === '' ? null : max(1, (int) $rawCategory);
        $sets[] = 'joomla_category_id = :joomla_category_id';
        $params['joomla_category_id'] = $categoryId;
    }
    if (($body['joomlaApiToken'] ?? '') !== '' && ce_table_has_column($pdo, 'clients', 'joomla_api_token')) {
        require_once __DIR__ . '/credential-crypto.php';
        $sets[] = 'joomla_api_token = :joomla_api_token';
        $params['joomla_api_token'] = ce_credential_encrypt(trim((string) $body['joomlaApiToken']));
    }

    $password = (string) ($body['password'] ?? '');
    if ($password !== '' && ce_table_has_column($pdo, 'clients', 'password_hash')) {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Wachtwoord moet minimaal 8 tekens zijn');
        }
        $sets[] = 'password_hash = :password_hash';
        $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($sets !== []) {
        $pdo->prepare('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    }

    $galleryCopied = 0;
    $copyGalleryFrom = trim((string) ($body['copyGalleryFromClientId'] ?? ''));
    if ($copyGalleryFrom !== '') {
        require_once __DIR__ . '/client-images-repository.php';
        $galleryLimit = max(1, min(20, (int) ($body['copyGalleryLimit'] ?? 1)));
        $galleryCopied = ce_copy_client_gallery($pdo, $copyGalleryFrom, $clientId, $galleryLimit);
    }

    $updatedStmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $updatedStmt->execute(['id' => $clientId]);
    $updated = $updatedStmt->fetch();
    if (!$updated) {
        throw new RuntimeException('Client bijwerken mislukt');
    }

    $result = [
        'ok' => true,
        'client' => ce_account_dto($updated),
        'strategy' => ce_strategy_dto($pdo, $updated),
    ];
    if ($copyGalleryFrom !== '') {
        $result['galleryCopied'] = $galleryCopied;
    }

    return $result;
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_patch_account(PDO $pdo, array $body): array
{
    ce_ensure_account_columns($pdo);

    $clientId = ce_resolve_client_id($pdo, (string) ($body['clientId'] ?? ''));
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    if (!empty($body['switchClientId'])) {
        $switchId = trim((string) $body['switchClientId']);
        $check = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $check->execute(['id' => $switchId]);
        $target = $check->fetch();
        if (!$target) {
            throw new InvalidArgumentException('Onbekende client');
        }
        if (!ce_may_switch_to_client($pdo, $target)) {
            ce_log_cross_tenant_attempt($pdo, (string) $clientId, $switchId);
            ce_json_error('Geen toegang tot deze client', 403);
        }
        ce_set_active_client_id($switchId);
        $clientId = $switchId;
    }

    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Client niet gevonden');
    }

    $map = [
        'company' => 'name',
        'contactName' => 'contact_name',
        'contactRole' => 'contact_role',
        'contactEmail' => 'contact_email',
        'reviewerEmail' => 'reviewer_email',
        'logoUrl' => 'logo_url',
        'publishMode' => 'publish_mode',
        'imageDatabaseUrl' => 'image_database_url',
    ];

    $sets = [];
    $params = ['id' => $clientId];

    foreach ($map as $jsonKey => $dbCol) {
        if (!array_key_exists($jsonKey, $body)) {
            continue;
        }
        if ($jsonKey === 'reviewerEmail') {
            continue;
        }
        if (!ce_table_has_column($pdo, 'clients', $dbCol)) {
            continue;
        }
        $value = trim((string) $body[$jsonKey]);
        if ($jsonKey === 'contactRole') {
            $value = strtolower($value);
            if (!in_array($value, ['contact', 'reviewer', 'both'], true)) {
                throw new InvalidArgumentException('contactRole moet contact, reviewer of both zijn');
            }
        }
        if ($jsonKey === 'publishMode') {
            $value = ce_publish_mode_from_input($value);
        }
        $sets[] = "$dbCol = :$dbCol";
        $params[$dbCol] = $value;
    }

    if (array_key_exists('logoUrl', $body) && trim((string) $body['logoUrl']) === '') {
        ce_remove_client_logo_files($clientId);
    }

    $imageDbPassword = (string) ($body['imageDatabasePassword'] ?? '');
    if ($imageDbPassword !== '' && ce_table_has_column($pdo, 'clients', 'image_database_password')) {
        require_once __DIR__ . '/credential-crypto.php';
        $sets[] = 'image_database_password = :image_database_password';
        $params['image_database_password'] = ce_credential_encrypt($imageDbPassword);
    }

    if (array_key_exists('active', $body) && ce_table_has_column($pdo, 'clients', 'active')) {
        $sets[] = 'active = :active';
        $params['active'] = !empty($body['active']) ? 1 : 0;
    }
    if (array_key_exists('planningHorizonWeeks', $body) && ce_table_has_column($pdo, 'clients', 'planning_horizon_weeks')) {
        $sets[] = 'planning_horizon_weeks = :planning_horizon_weeks';
        $params['planning_horizon_weeks'] = max(1, (int) $body['planningHorizonWeeks']);
    }
    if (array_key_exists('postsPerWeek', $body) && ce_table_has_column($pdo, 'clients', 'posts_per_week')) {
        $sets[] = 'posts_per_week = :posts_per_week';
        $params['posts_per_week'] = max(1, (int) $body['postsPerWeek']);
    }
    if (array_key_exists('sourceChecksPerWeek', $body) && ce_table_has_column($pdo, 'clients', 'source_checks_per_week')) {
        $sets[] = 'source_checks_per_week = :source_checks_per_week';
        $params['source_checks_per_week'] = max(1, (int) $body['sourceChecksPerWeek']);
    }
    if (array_key_exists('writingStyle', $body) && ce_table_has_column($pdo, 'clients', 'writing_style')) {
        $sets[] = 'writing_style = :writing_style';
        $params['writing_style'] = trim((string) $body['writingStyle']) ?: 'zakelijk_direct';
    }
    if (array_key_exists('googlePlaceId', $body) && ce_table_has_column($pdo, 'clients', 'google_place_id')) {
        $sets[] = 'google_place_id = :google_place_id';
        $params['google_place_id'] = trim((string) $body['googlePlaceId']) ?: null;
    }

    require_once __DIR__ . '/publish-settings-repository.php';
    ce_ensure_client_publish_columns($pdo);
    ce_apply_website_cms_patch($pdo, $sets, $params, $body);

    $publishScalars = [
        'websiteBlogUrl' => 'website_blog_url',
        'wordpressSiteUrl' => 'wordpress_site_url',
        'wordpressUser' => 'wordpress_user',
        'siteFtpHost' => 'site_ftp_host',
        'siteFtpUser' => 'site_ftp_user',
        'siteFtpRemotePath' => 'site_ftp_remote_path',
    ];
    foreach ($publishScalars as $jsonKey => $dbCol) {
        if (!array_key_exists($jsonKey, $body) || !ce_table_has_column($pdo, 'clients', $dbCol)) {
            continue;
        }
        $value = trim((string) $body[$jsonKey]);
        if ($jsonKey === 'wordpressSiteUrl') {
            require_once __DIR__ . '/publish-settings-repository.php';
            $value = ce_normalize_wordpress_site_url($value);
        }
        if ($jsonKey === 'wordpressUser') {
            require_once __DIR__ . '/publish-settings-repository.php';
            $value = ce_normalize_wordpress_user($value);
        }
        $sets[] = "$dbCol = :$dbCol";
        $params[$dbCol] = $value;
    }
    if (array_key_exists('wordpressAuthorId', $body) && ce_table_has_column($pdo, 'clients', 'wordpress_author_id')) {
        $rawAuthor = $body['wordpressAuthorId'];
        $authorId = $rawAuthor === null || $rawAuthor === '' ? null : max(1, (int) $rawAuthor);
        $sets[] = 'wordpress_author_id = :wordpress_author_id';
        $params['wordpress_author_id'] = $authorId;
    }
    if (array_key_exists('siteFtpPort', $body) && ce_table_has_column($pdo, 'clients', 'site_ftp_port')) {
        $sets[] = 'site_ftp_port = :site_ftp_port';
        $params['site_ftp_port'] = max(1, (int) ($body['siteFtpPort'] ?? 21));
    }
    if (($body['wordpressAppPassword'] ?? '') !== '' && ce_table_has_column($pdo, 'clients', 'wordpress_app_password')) {
        require_once __DIR__ . '/credential-crypto.php';
        $sets[] = 'wordpress_app_password = :wordpress_app_password';
        $params['wordpress_app_password'] = ce_credential_encrypt(
            ce_normalize_wordpress_app_password(trim((string) $body['wordpressAppPassword']))
        );
    }
    if (($body['siteFtpPassword'] ?? '') !== '' && ce_table_has_column($pdo, 'clients', 'site_ftp_password')) {
        require_once __DIR__ . '/credential-crypto.php';
        $sets[] = 'site_ftp_password = :site_ftp_password';
        $params['site_ftp_password'] = ce_credential_encrypt(trim((string) $body['siteFtpPassword']));
    }
    if (array_key_exists('joomlaSiteUrl', $body) && ce_table_has_column($pdo, 'clients', 'joomla_site_url')) {
        require_once __DIR__ . '/joomla-publish-repository.php';
        $sets[] = 'joomla_site_url = :joomla_site_url';
        $params['joomla_site_url'] = ce_normalize_joomla_site_url(trim((string) $body['joomlaSiteUrl']));
    }
    if (array_key_exists('joomlaCategoryId', $body) && ce_table_has_column($pdo, 'clients', 'joomla_category_id')) {
        $rawCategory = $body['joomlaCategoryId'];
        $categoryId = $rawCategory === null || $rawCategory === '' ? null : max(1, (int) $rawCategory);
        $sets[] = 'joomla_category_id = :joomla_category_id';
        $params['joomla_category_id'] = $categoryId;
    }
    if (($body['joomlaApiToken'] ?? '') !== '' && ce_table_has_column($pdo, 'clients', 'joomla_api_token')) {
        require_once __DIR__ . '/credential-crypto.php';
        $sets[] = 'joomla_api_token = :joomla_api_token';
        $params['joomla_api_token'] = ce_credential_encrypt(trim((string) $body['joomlaApiToken']));
    }
    if (array_key_exists('publishExtraDestinations', $body) && ce_table_has_column($pdo, 'clients', 'publish_extra_destinations')) {
        $raw = $body['publishExtraDestinations'];
        $list = is_array($raw) ? $raw : [];
        $sets[] = 'publish_extra_destinations = :publish_extra_destinations';
        $params['publish_extra_destinations'] = ce_publish_extra_destinations_to_json($list);
    }

    $role = strtolower(trim((string) ($params['contact_role'] ?? $row['contact_role'] ?? 'both')));
    $contactEmail = trim((string) ($params['contact_email'] ?? $row['contact_email'] ?? ''));
    if (array_key_exists('contact_email', $params) && $contactEmail === '') {
        throw new InvalidArgumentException('Email adres contact is verplicht');
    }

    if (ce_table_has_column($pdo, 'clients', 'reviewer_email')) {
        if (in_array($role, ['reviewer', 'both'], true)) {
            $syncEmail = $contactEmail !== '' ? $contactEmail : trim((string) ($row['contact_email'] ?? ''));
            $sets[] = 'reviewer_email = :reviewer_email';
            $params['reviewer_email'] = $syncEmail;
        } elseif (array_key_exists('reviewerEmail', $body)) {
            $reviewer = trim((string) $body['reviewerEmail']);
            if ($reviewer === '') {
                throw new InvalidArgumentException('Email adres reviewer is verplicht als Role = Contact');
            }
            $sets[] = 'reviewer_email = :reviewer_email';
            $params['reviewer_email'] = $reviewer;
        }
    }

    $password = (string) ($body['password'] ?? '');
    $passwordRetype = (string) ($body['passwordRetype'] ?? $body['passwordConfirm'] ?? '');
    if ($password !== '' || $passwordRetype !== '') {
        if ($password !== $passwordRetype) {
            throw new InvalidArgumentException('Wachtwoorden komen niet overeen');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Wachtwoord moet minimaal 8 tekens zijn');
        }
        if (ce_table_has_column($pdo, 'clients', 'password_hash')) {
            $sets[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    if ($sets !== []) {
        $pdo->prepare('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    }

    if (array_key_exists('postsPerWeek', $body) || array_key_exists('posts_per_week', $body)) {
        require_once __DIR__ . '/content-rhythm-repository.php';
        ce_content_rhythm_sync_posts_from_client($pdo, $clientId);
    }

    return ce_fetch_account($pdo, $clientId);
}

/** @return array<string, mixed> */
function ce_auth_login(PDO $pdo, array $body): array
{
    ce_ensure_account_columns($pdo);
    ce_session_start();

    $email = strtolower(trim((string) ($body['email'] ?? $body['contactEmail'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    if ($password === '') {
        throw new InvalidArgumentException('Wachtwoord is verplicht');
    }

    require_once __DIR__ . '/login-rate-limit.php';
    ce_login_rate_limit_assert($email);

    $explicitClientId = trim((string) ($body['clientId'] ?? ''));
    $row = null;
    $candidates = [];

    if ($email !== '') {
        foreach (ce_clients_matching_login_email($pdo, $email) as $candidate) {
            if (ce_client_password_matches($candidate, $password, $pdo, $email)) {
                $candidates[] = $candidate;
            }
        }
    }

    if ($explicitClientId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $explicitClientId]);
        $explicitRow = $stmt->fetch() ?: null;
        if ($explicitRow && ce_client_password_matches($explicitRow, $password, $pdo, $email)) {
            $row = $explicitRow;
        }
    }

    if ($row === null && count($candidates) === 1) {
        $row = $candidates[0];
    }

    if ($row === null && count($candidates) > 1 && $explicitClientId === '') {
        $clients = [];
        foreach ($candidates as $candidate) {
            $clients[] = [
                'clientId' => (string) ($candidate['id'] ?? ''),
                'company' => (string) ($candidate['name'] ?? ''),
                'contactEmail' => (string) ($candidate['contact_email'] ?? ''),
                'publishMode' => ce_normalize_publish_mode($candidate['publish_mode'] ?? null),
            ];
        }

        return [
            'ok' => false,
            'requiresClientPick' => true,
            'clients' => $clients,
        ];
    }

    if ($row === null && $email === '' && $explicitClientId === '') {
        $clientId = ce_resolve_client_id($pdo);
        if ($clientId !== null) {
            $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $clientId]);
            $fallbackRow = $stmt->fetch() ?: null;
            if ($fallbackRow && ce_client_password_matches($fallbackRow, $password)) {
                $row = $fallbackRow;
            }
        }
    }

    if (!$row) {
        ce_login_attempts_register($email);
        throw new InvalidArgumentException('Onjuist e-mailadres of wachtwoord');
    }

    require_once __DIR__ . '/session-repository.php';
    $isGuest = $email !== '' && strtolower(trim((string) ($row['contact_email'] ?? ''))) !== $email;
    // Zonder e-mail is er alleen met het eigen tenantwachtwoord ingelogd: dat is het hoofdcontact.
    $sessionEmail = $email !== '' ? $email : strtolower(trim((string) ($row['contact_email'] ?? '')));
    ce_establish_auth_session((string) $row['id'], !$isGuest, $sessionEmail);
    ce_login_attempts_reset($email);

    if ($isGuest) {
        try {
            require_once __DIR__ . '/event-log-repository.php';
            ce_log_event($pdo, 'info', 'auth', 'guest_login', $email . ' kijkt mee bij deze tenant', [
                'email' => $email,
                'clientId' => (string) $row['id'],
            ]);
        } catch (Throwable) {
            // Logging mag de login niet blokkeren.
        }
    }

    return [
        'ok' => true,
        'account' => ce_account_dto($row),
    ];
}

/** @return array<string, mixed> */
function ce_auth_session(PDO $pdo): array
{
    require_once __DIR__ . '/auth-guard.php';
    require_once __DIR__ . '/session-repository.php';
    ce_session_start();
    ce_enforce_tenant_session_idle();

    $authenticated = !empty($_SESSION['ce_authenticated']);
    if ($authenticated) {
        ce_touch_tenant_session_activity();
    }

    return [
        'authenticated' => $authenticated,
        'clientId' => ce_resolve_client_id($pdo),
        'authRequired' => ce_auth_is_required($pdo),
    ];
}

function ce_auth_logout(): array
{
    ce_session_start();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    return ['ok' => true];
}
