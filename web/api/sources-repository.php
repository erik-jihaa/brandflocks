<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/source-folders-repository.php';
require_once __DIR__ . '/trello-helper.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/mailbox-helper.php';
require_once __DIR__ . '/scrape-repository.php';

function ce_ensure_sources_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'sources')) {
        try {
            $pdo->exec(
                <<<'SQL'
                CREATE TABLE IF NOT EXISTS sources (
                  id CHAR(36) NOT NULL PRIMARY KEY,
                  client_id CHAR(36) NOT NULL,
                  category VARCHAR(20) NOT NULL,
                  source_kind VARCHAR(50) NULL,
                  title VARCHAR(255) NOT NULL,
                  url VARCHAR(500) NULL,
                  scraper_api_token_ref VARCHAR(100) NULL,
                  scraper_actor_or_task_id VARCHAR(100) NULL,
                  scraper_webhook_url VARCHAR(500) NULL,
                  scraper_linkedin_profile VARCHAR(500) NULL,
                  scraper_linkedin_password_ref VARCHAR(255) NULL,
                  budget_per_unit DECIMAL(10,4) NULL,
                  max_units_per_run INT NULL,
                  mailbox_email VARCHAR(255) NULL,
                  mailbox_password_ref VARCHAR(100) NULL,
                  mailbox_imap_host VARCHAR(255) NULL,
                  analytics_api_secret_ref VARCHAR(100) NULL,
                  analytics_datastream_id VARCHAR(100) NULL,
                  trello_board_id VARCHAR(100) NULL,
                  trello_api_token_ref VARCHAR(100) NULL,
                  description TEXT NULL,
                  active TINYINT(1) NOT NULL DEFAULT 1,
                  url_status VARCHAR(20) NOT NULL DEFAULT 'unchecked',
                  last_checked_at DATETIME NULL,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  KEY idx_client_category (client_id, category)
                )
                SQL
            );
        } catch (Throwable) {
            // Tabel bestaat al.
        }
    }

    ce_ensure_sources_columns($pdo);
    ce_ensure_source_folders_schema($pdo);
    ce_migrate_mailbox_imap_hosts($pdo);
    ce_migrate_default_linkedin_profiles($pdo);
    ce_ensure_sources_category_enum($pdo);
    ce_widen_sources_credential_columns($pdo);
    ce_encrypt_legacy_source_credentials($pdo);

    require_once __DIR__ . '/analytics-oauth-helper.php';
    ce_ensure_source_analytics_oauth_schema($pdo);
    require_once __DIR__ . '/calendar-oauth-helper.php';
    ce_ensure_source_calendar_oauth_schema($pdo);
}

/** Vergroot credential-kolommen zodat AES-GCM blobs passen. */
function ce_widen_sources_credential_columns(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'sources')) {
        return;
    }

    $columns = [
        'mailbox_password_ref',
        'trello_api_token_ref',
        'scraper_api_token_ref',
        'scraper_linkedin_password_ref',
        'analytics_api_secret_ref',
    ];

    foreach ($columns as $column) {
        if (!ce_table_has_column($pdo, 'sources', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE sources MODIFY COLUMN `$column` VARCHAR(512) NULL");
        } catch (Throwable) {
            // Kolom al verbreed of ALTER niet toegestaan.
        }
    }
}

/** Eenmalige migratie: plaintext credentials in DB versleutelen. */
function ce_encrypt_legacy_source_credentials(PDO $pdo): void
{
    static $done = false;
    if ($done || !ce_table_exists($pdo, 'sources')) {
        return;
    }
    $done = true;

    $columns = [
        'mailbox_password_ref',
        'trello_api_token_ref',
        'scraper_api_token_ref',
        'scraper_linkedin_password_ref',
        'analytics_api_secret_ref',
        'analytics_oauth_refresh_token',
        'calendar_oauth_refresh_token',
    ];

    foreach ($columns as $column) {
        if (!ce_table_has_column($pdo, 'sources', $column)) {
            continue;
        }

        $stmt = $pdo->query(
            "SELECT id, `$column` FROM sources WHERE `$column` IS NOT NULL AND `$column` != ''"
        );
        if ($stmt === false) {
            continue;
        }

        $update = $pdo->prepare("UPDATE sources SET `$column` = :value WHERE id = :id");
        while ($row = $stmt->fetch()) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value === '' || ce_credential_is_encrypted($value)) {
                continue;
            }
            try {
                $update->execute([
                    'value' => ce_credential_encrypt($value),
                    'id' => (string) $row['id'],
                ]);
            } catch (Throwable) {
                // Sla over bij ontbrekende encrypt-sleutel.
            }
        }
    }
}

/** Voeg ontbrekende kolommen toe op bestaande sources-tabellen (live DB-migratie). */
function ce_ensure_sources_columns(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'sources')) {
        return;
    }

    $columns = [
        'scraper_api_token_ref' => 'VARCHAR(100) NULL',
        'scraper_actor_or_task_id' => 'VARCHAR(100) NULL',
        'scraper_webhook_url' => 'VARCHAR(500) NULL',
        'scraper_linkedin_profile' => 'VARCHAR(500) NULL',
        'scraper_linkedin_password_ref' => 'VARCHAR(255) NULL',
        'budget_per_unit' => 'DECIMAL(10,4) NULL',
        'max_units_per_run' => 'INT NULL',
        'mailbox_email' => 'VARCHAR(255) NULL',
        'mailbox_password_ref' => 'VARCHAR(100) NULL',
        'mailbox_imap_host' => 'VARCHAR(255) NULL',
        'analytics_api_secret_ref' => 'VARCHAR(100) NULL',
        'analytics_datastream_id' => 'VARCHAR(100) NULL',
        'trello_board_id' => 'VARCHAR(100) NULL',
        'trello_api_token_ref' => 'VARCHAR(100) NULL',
        'description' => 'TEXT NULL',
        'source_kind' => 'VARCHAR(50) NULL',
        'url_status' => "VARCHAR(20) NOT NULL DEFAULT 'unchecked'",
        'last_checked_at' => 'DATETIME NULL',
        'active' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'folder_id' => 'CHAR(36) NULL',
        'asana_pat_ref' => 'VARCHAR(255) NULL',
        'asana_workspace_gid' => 'VARCHAR(100) NULL',
        'asana_project_gid' => 'VARCHAR(100) NULL',
        'asana_workspace_name' => 'VARCHAR(255) NULL',
        'asana_project_name' => 'VARCHAR(255) NULL',
        'drive_file_url' => 'VARCHAR(500) NULL',
        'drive_oauth_refresh_token' => 'TEXT NULL',
        'drive_oauth_email' => 'VARCHAR(255) NULL',
        'document_filename' => 'VARCHAR(255) NULL',
        'document_uploaded_at' => 'DATETIME NULL',
        'document_chunk_count' => 'INT NULL',
        'source_tier' => "VARCHAR(20) NOT NULL DEFAULT 'supplementary'",
    ];

    foreach ($columns as $column => $definition) {
        if (ce_table_has_column($pdo, 'sources', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE sources ADD COLUMN `$column` $definition");
            unset($GLOBALS['ce_table_columns']['sources']);
        } catch (Throwable) {
            // Kolom bestaat al of ALTER niet toegestaan.
        }
    }
}

function ce_migrate_mailbox_imap_hosts(PDO $pdo): void
{
    static $done = false;
    if ($done || !ce_table_exists($pdo, 'sources') || !ce_table_has_column($pdo, 'sources', 'mailbox_imap_host')) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "UPDATE sources
             SET mailbox_imap_host = CONCAT('mail.', SUBSTRING_INDEX(mailbox_email, '@', -1))
             WHERE category = 'mailbox'
               AND mailbox_email IS NOT NULL
               AND TRIM(mailbox_email) <> ''
               AND (mailbox_imap_host IS NULL OR TRIM(mailbox_imap_host) = '')"
        );
    } catch (Throwable) {
        // Migratie optioneel.
    }
}

function ce_migrate_default_linkedin_profiles(PDO $pdo): void
{
    static $done = false;
    if ($done || !ce_table_exists($pdo, 'sources') || !ce_table_has_column($pdo, 'sources', 'scraper_linkedin_profile')) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "UPDATE sources
             SET scraper_linkedin_profile = 'https://www.linkedin.com/in/evalouisedejong'
             WHERE category = 'linkedin'
               AND (scraper_linkedin_profile IS NULL OR TRIM(scraper_linkedin_profile) = '')"
        );
        $pdo->exec(
            "UPDATE sources
             SET scraper_linkedin_profile = 'https://www.linkedin.com/in/evalouisedejong'
             WHERE category = 'scraper'
               AND (scraper_linkedin_profile IS NULL OR TRIM(scraper_linkedin_profile) = '')
               AND (
                 COALESCE(scraper_actor_or_task_id, '') LIKE '%linkedin%'
                 OR COALESCE(url, '') LIKE '%linkedin%'
               )"
        );
    } catch (Throwable) {
        // Migratie optioneel.
    }
}

/** Live DB: sources.category is soms ENUM — voeg linkedin toe. */
function ce_ensure_sources_category_enum(PDO $pdo): void
{
    static $checked = false;
    if ($checked || !ce_table_exists($pdo, 'sources')) {
        return;
    }
    $checked = true;

    ce_alter_column_enum($pdo, 'sources', 'category', [
        'url_rss',
        'blogs',
        'document',
        'youtube',
        'asana',
        'drive',
        'linkedin',
        'scraper',
        'mailbox',
        'analytics',
        'trello',
        'calendar',
    ]);
}

function ce_default_client_id(PDO $pdo): ?string
{
    require_once __DIR__ . '/client-context.php';

    return ce_resolve_client_id($pdo);
}

function ce_ui_category_to_db(string $uiCategory): string
{
    return match ($uiCategory) {
        'URL/RSS' => 'url_rss',
        'Blogs' => 'blogs',
        'Document' => 'document',
        'YouTube' => 'youtube',
        'Asana' => 'asana',
        'Drive' => 'drive',
        'Linkedin' => 'linkedin',
        'Scraper' => 'scraper',
        'Mailbox' => 'mailbox',
        'Analythics' => 'analytics',
        'Trello' => 'trello',
        'Calendar' => 'calendar',
        default => strtolower(str_replace(['/', ' '], ['_', ''], $uiCategory)),
    };
}

function ce_db_category_to_ui(string $dbCategory): string
{
    return match ($dbCategory) {
        'url_rss' => 'URL/RSS',
        'blogs' => 'Blogs',
        'document' => 'Document',
        'youtube' => 'YouTube',
        'asana' => 'Asana',
        'drive' => 'Drive',
        'linkedin' => 'Linkedin',
        'scraper' => 'Scraper',
        'mailbox' => 'Mailbox',
        'analytics' => 'Analythics',
        'trello' => 'Trello',
        'calendar' => 'Calendar',
        default => $dbCategory,
    };
}

function ce_url_status_to_check(string $status): string
{
    return match ($status) {
        'reachable' => 'green',
        'unreachable' => 'red',
        default => 'none',
    };
}

/** @return list<array<string, mixed>> */
function ce_fetch_all_sources(PDO $pdo): array
{
    ce_ensure_sources_schema($pdo);

    if (!ce_table_exists($pdo, 'sources')) {
        return [];
    }

    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        return [];
    }

    $folderNames = ce_source_folder_name_map($pdo, $clientId);
    $hasFolders = ce_table_has_column($pdo, 'sources', 'folder_id');

    if ($hasFolders) {
        $stmt = $pdo->prepare(
            <<<'SQL'
            SELECT s.*, sf.sort_order AS folder_sort_order, sf.name AS folder_name
            FROM sources s
            LEFT JOIN source_folders sf ON sf.id = s.folder_id
            WHERE s.client_id = :client_id
            ORDER BY
              CASE WHEN s.folder_id IS NULL THEN 1 ELSE 0 END,
              COALESCE(sf.sort_order, 9999),
              COALESCE(sf.name, ''),
              FIELD(s.category, 'url_rss', 'blogs', 'document', 'youtube', 'asana', 'drive', 'linkedin', 'scraper', 'mailbox', 'analytics', 'trello', 'calendar'),
              s.title ASC
            SQL
        );
    } else {
        $stmt = $pdo->prepare(
            <<<'SQL'
            SELECT * FROM sources
            WHERE client_id = :client_id
            ORDER BY FIELD(category, 'url_rss', 'blogs', 'document', 'youtube', 'asana', 'drive', 'linkedin', 'scraper', 'mailbox', 'analytics', 'trello', 'calendar'), title ASC
            SQL
        );
    }
    $stmt->execute(['client_id' => $clientId]);

    return array_map(
        static fn (array $row): array => ce_format_source_row($row, $folderNames),
        $stmt->fetchAll() ?: [],
    );
}

/** @return list<array<string, mixed>> */
function ce_fetch_sources(PDO $pdo, string $category): array
{
    ce_ensure_sources_schema($pdo);

    if (!ce_table_exists($pdo, 'sources')) {
        return [];
    }

    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM sources WHERE client_id = :client_id AND category = :category ORDER BY title ASC'
    );
    $stmt->execute(['client_id' => $clientId, 'category' => $category]);

    $folderNames = ce_source_folder_name_map($pdo, $clientId);

    return array_map(
        static fn (array $row): array => ce_format_source_row($row, $folderNames),
        $stmt->fetchAll() ?: [],
    );
}

/** @param array<string, mixed> $row @param array<string, string> $folderNames */
function ce_format_source_row(array $row, array $folderNames = []): array
{
    $category = (string) ($row['category'] ?? '');
    $folderId = trim((string) ($row['folder_id'] ?? ''));
    $folderName = $folderId !== '' ? ($folderNames[$folderId] ?? trim((string) ($row['folder_name'] ?? ''))) : null;

    return [
        'id' => $row['id'],
        'category' => ce_db_category_to_ui($category),
        'folderId' => $folderId !== '' ? $folderId : null,
        'folderName' => $folderName !== '' ? $folderName : null,
        'title' => $row['title'],
        'url' => $row['url'],
        'description' => $row['description'],
        'checkStatus' => ce_url_status_to_check((string) ($row['url_status'] ?? 'unchecked')),
        'showBudget' => in_array($category, ['scraper', 'linkedin'], true),
        'budgetPerUnit' => $row['budget_per_unit'] !== null ? (float) $row['budget_per_unit'] : null,
        'maxUnitsPerRun' => $row['max_units_per_run'] !== null ? (int) $row['max_units_per_run'] : null,
        'trelloBoardId' => $row['trello_board_id'] ?? null,
        'trelloTokenConfigured' => trim((string) ($row['trello_api_token_ref'] ?? '')) !== '',
        'trelloApiTokenRef' => null,
        'mailboxEmail' => $row['mailbox_email'] ?? null,
        'mailboxImapHost' => $row['mailbox_imap_host'] ?? null,
        'mailboxPasswordConfigured' => trim((string) ($row['mailbox_password_ref'] ?? '')) !== '',
        'mailboxPasswordRef' => null,
        'analyticsDatastreamId' => $row['analytics_datastream_id'] ?? null,
        'analyticsSecretConfigured' => trim((string) ($row['analytics_api_secret_ref'] ?? '')) !== '',
        'analyticsApiSecretRef' => null,
        'analyticsPropertyId' => $row['analytics_property_id'] ?? null,
        'analyticsOAuthConnected' => trim((string) ($row['analytics_oauth_refresh_token'] ?? '')) !== '',
        'analyticsOAuthEmail' => $row['analytics_oauth_email'] ?? null,
        'calendarOAuthConnected' => trim((string) ($row['calendar_oauth_refresh_token'] ?? '')) !== '',
        'calendarOAuthEmail' => $row['calendar_oauth_email'] ?? null,
        'calendarId' => $row['calendar_id'] ?? null,
        'calendarName' => $row['calendar_name'] ?? null,
        'scraperTokenConfigured' => trim((string) ($row['scraper_api_token_ref'] ?? '')) !== ''
            || ($category === 'linkedin' && ce_apify_platform_configured()),
        'platformApifyConfigured' => ce_apify_platform_configured(),
        'scraperApiTokenRef' => null,
        'scraperActorOrTaskId' => $row['scraper_actor_or_task_id'] ?? null,
        'scraperWebhookUrl' => $row['scraper_webhook_url'] ?? null,
        'scraperLinkedinProfile' => $row['scraper_linkedin_profile'] ?? null,
        'scraperLinkedinPasswordConfigured' => trim((string) ($row['scraper_linkedin_password_ref'] ?? '')) !== '',
        'scraperLinkedinPasswordRef' => null,
        'asanaPatConfigured' => trim((string) ($row['asana_pat_ref'] ?? '')) !== '',
        'asanaWorkspaceGid' => $row['asana_workspace_gid'] ?? null,
        'asanaProjectGid' => $row['asana_project_gid'] ?? null,
        'asanaWorkspaceName' => $row['asana_workspace_name'] ?? null,
        'asanaProjectName' => $row['asana_project_name'] ?? null,
        'documentFilename' => $row['document_filename'] ?? null,
        'documentUploadedAt' => $row['document_uploaded_at'] ?? null,
        'documentChunkCount' => $row['document_chunk_count'] !== null ? (int) $row['document_chunk_count'] : null,
        'driveOAuthConnected' => trim((string) ($row['drive_oauth_refresh_token'] ?? '')) !== '',
        'driveOAuthEmail' => $row['drive_oauth_email'] ?? null,
        'driveFileUrl' => $row['drive_file_url'] ?? null,
        'active' => (bool) ($row['active'] ?? true),
        'lastCheckedAt' => $row['last_checked_at'],
        'sourceTier' => in_array((string) ($row['source_tier'] ?? ''), ['core', 'supplementary'], true)
            ? (string) $row['source_tier']
            : (in_array($category, ['url_rss', 'blogs', 'document', 'linkedin', 'youtube', 'drive'], true) ? 'core' : 'supplementary'),
    ];
}

/** @return array<string, mixed>|null */
function ce_fetch_source(PDO $pdo, string $sourceId, ?string $clientId = null): ?array
{
    ce_ensure_sources_schema($pdo);

    if ($clientId === null || $clientId === '') {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sources WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $sourceId, 'client_id' => $clientId]);
    $row = $stmt->fetch();

    return $row ? ce_format_source_row($row, ce_source_folder_name_map($pdo, $clientId)) : null;
}

/** @param array<string, mixed> $body */
function ce_create_source(PDO $pdo, string $category, array $body): array
{
    ce_ensure_sources_schema($pdo);

    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden — voeg een rij toe in clients.');
    }

    $title = trim((string) ($body['title'] ?? ''));
    if ($title === '') {
        $title = trim((string) ($body['url'] ?? ''));
    }
    if ($title === '') {
        $title = trim((string) ($body['trelloBoardId'] ?? ''));
    }
    if ($title === '') {
        $title = trim((string) ($body['mailboxEmail'] ?? ''));
    }
    if ($title === '' && $category === 'analytics') {
        $title = 'GA4';
    }
    if ($title === '' && $category === 'calendar') {
        $title = 'Google Calendar';
    }
    if ($title === '') {
        $title = trim((string) ($body['analyticsDatastreamId'] ?? ''));
    }
    if ($title === '') {
        throw new InvalidArgumentException('Titel of verplicht veld ontbreekt.');
    }

    if ($category === 'scraper') {
        $body = ce_sources_normalize_scraper_apify($body);
    }
    if ($category === 'linkedin') {
        $body = ce_sources_normalize_linkedin($body);
    }

    $id = ce_uuid_v4();

    $folderId = trim((string) ($body['folderId'] ?? '')) ?: null;
    if ($folderId !== null) {
        $folderCheck = $pdo->prepare(
            'SELECT id FROM source_folders WHERE id = :id AND client_id = :client_id LIMIT 1'
        );
        $folderCheck->execute(['id' => $folderId, 'client_id' => $clientId]);
        if (!$folderCheck->fetch()) {
            throw new InvalidArgumentException('Onbekende map.');
        }
    }

    $url = trim((string) ($body['url'] ?? '')) ?: null;
    $trelloBoardId = trim((string) ($body['trelloBoardId'] ?? '')) ?: null;
    $trelloTokenRef = ce_credential_prepare_for_storage(
        array_key_exists('trelloApiTokenRef', $body) ? (string) ($body['trelloApiTokenRef'] ?? '') : null,
        null,
    ) ?: null;
    $mailboxEmail = trim((string) ($body['mailboxEmail'] ?? '')) ?: null;
    $mailboxImapHost = trim((string) ($body['mailboxImapHost'] ?? '')) ?: null;
    if ($category === 'mailbox' && $mailboxEmail !== null && $mailboxImapHost === null) {
        $mailboxImapHost = ce_mailbox_default_imap_host($mailboxEmail) ?: null;
    }
    $mailboxPassRef = ce_credential_prepare_for_storage(
        array_key_exists('mailboxPasswordRef', $body) ? (string) ($body['mailboxPasswordRef'] ?? '') : null,
        null,
    ) ?: null;
    $analyticsDs = trim((string) ($body['analyticsDatastreamId'] ?? '')) ?: null;
    $analyticsSecretRef = ce_credential_prepare_for_storage(
        array_key_exists('analyticsApiSecretRef', $body) ? (string) ($body['analyticsApiSecretRef'] ?? '') : null,
        null,
    ) ?: null;
    $scraperTokenRef = ce_credential_prepare_for_storage(
        array_key_exists('scraperApiTokenRef', $body) ? (string) ($body['scraperApiTokenRef'] ?? '') : null,
        null,
    ) ?: null;
    $scraperActorId = trim((string) ($body['scraperActorOrTaskId'] ?? '')) ?: null;
    $scraperWebhook = trim((string) ($body['scraperWebhookUrl'] ?? '')) ?: null;
    $scraperLinkedinProfile = trim((string) ($body['scraperLinkedinProfile'] ?? '')) ?: null;
    $scraperLinkedinPasswordRef = ce_credential_prepare_for_storage(
        array_key_exists('scraperLinkedinPasswordRef', $body) ? (string) ($body['scraperLinkedinPasswordRef'] ?? '') : null,
        null,
    ) ?: null;

    if ($category === 'trello' && $trelloBoardId === null) {
        throw new InvalidArgumentException('Trello board ID is verplicht.');
    }
    if ($category === 'mailbox' && $mailboxEmail === null) {
        throw new InvalidArgumentException('Mailbox e-mailadres is verplicht.');
    }
    if ($category === 'blogs' && $url === null) {
        throw new InvalidArgumentException('Blogoverzicht-URL is verplicht.');
    }
    if ($category === 'youtube' && $url === null) {
        throw new InvalidArgumentException('YouTube-kanaal-URL is verplicht.');
    }
    if ($category === 'asana' && trim((string) ($body['asanaProjectGid'] ?? '')) === '') {
        throw new InvalidArgumentException('Asana-project is verplicht.');
    }
    if ($category === 'drive' && trim((string) ($body['driveFileUrl'] ?? '')) === '') {
        throw new InvalidArgumentException('Google Drive-bestands-URL is verplicht.');
    }

    if ($category === 'youtube' && $url !== null) {
        require_once __DIR__ . '/youtube-repository.php';
        $feedUrl = ce_youtube_resolve_feed_url($url);
        if ($feedUrl !== null) {
            $url = $feedUrl;
        }
    }

    $hasFolderColumn = ce_table_has_column($pdo, 'sources', 'folder_id');
    $folderSql = $hasFolderColumn ? ', folder_id' : '';
    $folderValueSql = $hasFolderColumn ? ', :folder_id' : '';

    $insertParams = [
        'id' => $id,
        'client_id' => $clientId,
        'category' => $category,
        'source_kind' => $category,
        'title' => $title,
        'url' => $url,
        'description' => trim((string) ($body['description'] ?? '')) ?: null,
        'scraper_api_token_ref' => $scraperTokenRef,
        'scraper_actor_or_task_id' => $scraperActorId,
        'scraper_webhook_url' => $scraperWebhook,
        'scraper_linkedin_profile' => $scraperLinkedinProfile,
        'scraper_linkedin_password_ref' => $scraperLinkedinPasswordRef,
        'budget_per_unit' => isset($body['budgetPerUnit']) ? (float) $body['budgetPerUnit'] : null,
        'max_units_per_run' => isset($body['maxUnitsPerRun'])
            ? (int) $body['maxUnitsPerRun']
            : ($category === 'linkedin' ? 1 : null),
        'trello_board_id' => $trelloBoardId,
        'trello_api_token_ref' => $trelloTokenRef,
        'mailbox_email' => $mailboxEmail,
        'mailbox_password_ref' => $mailboxPassRef,
        'mailbox_imap_host' => $mailboxImapHost,
        'analytics_datastream_id' => $analyticsDs,
        'analytics_api_secret_ref' => $analyticsSecretRef,
    ];
    if ($hasFolderColumn) {
        $insertParams['folder_id'] = $folderId;
    }

    $hasTier = ce_table_has_column($pdo, 'sources', 'source_tier');
    $tier = trim((string) ($body['sourceTier'] ?? $body['source_tier'] ?? ''));
    if (!in_array($tier, ['core', 'supplementary'], true)) {
        require_once __DIR__ . '/voice-profile-repository.php';
        $tier = ce_default_source_tier($category);
    }
    $tierSql = $hasTier ? ', source_tier' : '';
    $tierValueSql = $hasTier ? ', :source_tier' : '';
    if ($hasTier) {
        $insertParams['source_tier'] = $tier;
    }

    $pdo->prepare(
        <<<SQL
        INSERT INTO sources (
            id, client_id, category, source_kind, title, url, description,
            scraper_api_token_ref, scraper_actor_or_task_id, scraper_webhook_url,
            scraper_linkedin_profile, scraper_linkedin_password_ref,
            budget_per_unit, max_units_per_run,
            trello_board_id, trello_api_token_ref,
            mailbox_email, mailbox_password_ref, mailbox_imap_host,
            analytics_datastream_id, analytics_api_secret_ref,
            url_status{$folderSql}{$tierSql}
        )
        VALUES (
            :id, :client_id, :category, :source_kind, :title, :url, :description,
            :scraper_api_token_ref, :scraper_actor_or_task_id, :scraper_webhook_url,
            :scraper_linkedin_profile, :scraper_linkedin_password_ref,
            :budget_per_unit, :max_units_per_run,
            :trello_board_id, :trello_api_token_ref,
            :mailbox_email, :mailbox_password_ref, :mailbox_imap_host,
            :analytics_datastream_id, :analytics_api_secret_ref,
            'unchecked'{$folderValueSql}{$tierValueSql}
        )
        SQL
    )->execute($insertParams);

    ce_sources_apply_category_fields($pdo, $id, $category, $body);

    return ce_fetch_source($pdo, $id) ?? [];
}

/** @param array<string, mixed> $body */
function ce_update_source(PDO $pdo, string $sourceId, array $body): ?array
{
    ce_ensure_sources_schema($pdo);

    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        return null;
    }

    $existingStmt = $pdo->prepare(
        'SELECT mailbox_password_ref, trello_api_token_ref, scraper_api_token_ref,
                scraper_linkedin_password_ref, analytics_api_secret_ref, asana_pat_ref
         FROM sources WHERE id = :id AND client_id = :client_id LIMIT 1'
    );
    $existingStmt->execute(['id' => $sourceId, 'client_id' => $clientId]);
    $existingRow = $existingStmt->fetch() ?: [];
    if (!$existingRow) {
        return null;
    }
    $existingPass = (string) ($existingRow['mailbox_password_ref'] ?? '');
    $existingTrelloToken = (string) ($existingRow['trello_api_token_ref'] ?? '');
    $existingScraperToken = (string) ($existingRow['scraper_api_token_ref'] ?? '');
    $existingLinkedinPassword = (string) ($existingRow['scraper_linkedin_password_ref'] ?? '');
    $existingAnalyticsSecret = (string) ($existingRow['analytics_api_secret_ref'] ?? '');
    $existingAsanaPat = (string) ($existingRow['asana_pat_ref'] ?? '');

    if (array_key_exists('url', $body) || array_key_exists('scraperActorOrTaskId', $body)) {
        $body = ce_sources_normalize_scraper_apify($body);
    }
    if (array_key_exists('scraperLinkedinProfile', $body)) {
        $body = ce_sources_normalize_linkedin($body);
    }

    $fields = [];
    $params = ['id' => $sourceId, 'client_id' => $clientId];

    $map = [
        'title' => 'title',
        'url' => 'url',
        'description' => 'description',
        'budgetPerUnit' => 'budget_per_unit',
        'maxUnitsPerRun' => 'max_units_per_run',
        'trelloBoardId' => 'trello_board_id',
        'mailboxEmail' => 'mailbox_email',
        'mailboxImapHost' => 'mailbox_imap_host',
        'analyticsDatastreamId' => 'analytics_datastream_id',
        'analyticsPropertyId' => 'analytics_property_id',
        'calendarId' => 'calendar_id',
        'calendarName' => 'calendar_name',
        'scraperActorOrTaskId' => 'scraper_actor_or_task_id',
        'scraperWebhookUrl' => 'scraper_webhook_url',
        'scraperLinkedinProfile' => 'scraper_linkedin_profile',
        'asanaWorkspaceGid' => 'asana_workspace_gid',
        'asanaProjectGid' => 'asana_project_gid',
        'asanaWorkspaceName' => 'asana_workspace_name',
        'asanaProjectName' => 'asana_project_name',
        'driveFileUrl' => 'drive_file_url',
        'folderId' => 'folder_id',
        'sourceTier' => 'source_tier',
    ];

    foreach ($map as $key => $column) {
        if (!array_key_exists($key, $body)) {
            continue;
        }
        $value = $body[$key];
        if ($key === 'budgetPerUnit') {
            $value = $value === null || $value === '' ? null : (float) $value;
        } elseif ($key === 'maxUnitsPerRun') {
            $value = $value === null || $value === '' ? null : (int) $value;
        } else {
            $value = trim((string) $value) ?: null;
            if ($key === 'title' && $value === null) {
                continue;
            }
        }
        if ($key === 'folderId' && !ce_table_has_column($pdo, 'sources', 'folder_id')) {
            continue;
        }
        if ($key === 'sourceTier' && !ce_table_has_column($pdo, 'sources', 'source_tier')) {
            continue;
        }
        if ($key === 'sourceTier') {
            $value = in_array((string) $value, ['core', 'supplementary'], true) ? (string) $value : 'supplementary';
        }
        if ($key === 'folderId' && $value !== null) {
            $clientId = ce_default_client_id($pdo);
            if ($clientId === null) {
                throw new RuntimeException('Geen client gevonden.');
            }
            $folderCheck = $pdo->prepare(
                'SELECT id FROM source_folders WHERE id = :id AND client_id = :client_id LIMIT 1'
            );
            $folderCheck->execute(['id' => $value, 'client_id' => $clientId]);
            if (!$folderCheck->fetch()) {
                throw new InvalidArgumentException('Onbekende map.');
            }
        }
        $fields[] = "$column = :$column";
        $params[$column] = $value;
    }

    if (array_key_exists('mailboxPasswordRef', $body)) {
        $incomingPass = ce_credential_prepare_for_storage((string) ($body['mailboxPasswordRef'] ?? ''), $existingPass);
        if ($incomingPass !== '') {
            $fields[] = 'mailbox_password_ref = :mailbox_password_ref';
            $params['mailbox_password_ref'] = $incomingPass;
        }
    }

    if (array_key_exists('trelloApiTokenRef', $body)) {
        $incomingToken = ce_credential_prepare_for_storage((string) ($body['trelloApiTokenRef'] ?? ''), $existingTrelloToken);
        if ($incomingToken !== '') {
            $fields[] = 'trello_api_token_ref = :trello_api_token_ref';
            $params['trello_api_token_ref'] = $incomingToken;
        }
    }

    if (array_key_exists('scraperApiTokenRef', $body)) {
        $incomingToken = ce_credential_prepare_for_storage((string) ($body['scraperApiTokenRef'] ?? ''), $existingScraperToken);
        if ($incomingToken !== '') {
            $fields[] = 'scraper_api_token_ref = :scraper_api_token_ref';
            $params['scraper_api_token_ref'] = $incomingToken;
        }
    }

    if (array_key_exists('scraperLinkedinPasswordRef', $body)) {
        $incomingPassword = ce_credential_prepare_for_storage(
            (string) ($body['scraperLinkedinPasswordRef'] ?? ''),
            $existingLinkedinPassword,
        );
        if ($incomingPassword !== '') {
            $fields[] = 'scraper_linkedin_password_ref = :scraper_linkedin_password_ref';
            $params['scraper_linkedin_password_ref'] = $incomingPassword;
        }
    }

    if (array_key_exists('analyticsApiSecretRef', $body)) {
        $incomingSecret = ce_credential_prepare_for_storage((string) ($body['analyticsApiSecretRef'] ?? ''), $existingAnalyticsSecret);
        if ($incomingSecret !== '') {
            $fields[] = 'analytics_api_secret_ref = :analytics_api_secret_ref';
            $params['analytics_api_secret_ref'] = $incomingSecret;
        }
    }

    if (array_key_exists('asanaPatRef', $body)) {
        $incomingPat = ce_credential_prepare_for_storage((string) ($body['asanaPatRef'] ?? ''), $existingAsanaPat);
        if ($incomingPat !== '') {
            $fields[] = 'asana_pat_ref = :asana_pat_ref';
            $params['asana_pat_ref'] = $incomingPat;
        }
    }

    if ($fields === []) {
        return ce_fetch_source($pdo, $sourceId);
    }

    $sql = 'UPDATE sources SET ' . implode(', ', $fields) . ' WHERE id = :id AND client_id = :client_id';
    $pdo->prepare($sql)->execute($params);

    return ce_fetch_source($pdo, $sourceId);
}

function ce_delete_source_dependencies(PDO $pdo, string $sourceId): void
{
    if (ce_table_exists($pdo, 'research_items') && ce_table_has_column($pdo, 'research_items', 'source_id')) {
        $pdo->prepare('UPDATE research_items SET source_id = NULL WHERE source_id = :id')
            ->execute(['id' => $sourceId]);
    }

    if (ce_table_exists($pdo, 'newsletter_items') && ce_table_has_column($pdo, 'newsletter_items', 'source_id')) {
        $pdo->prepare('DELETE FROM newsletter_items WHERE source_id = :id')
            ->execute(['id' => $sourceId]);
    }

    if (ce_table_exists($pdo, 'linkedin_engagement') && ce_table_has_column($pdo, 'linkedin_engagement', 'source_id')) {
        $pdo->prepare('DELETE FROM linkedin_engagement WHERE source_id = :id')
            ->execute(['id' => $sourceId]);
    }
}

function ce_delete_source(PDO $pdo, string $sourceId): bool
{
    ce_ensure_sources_schema($pdo);

    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        return false;
    }

    $check = $pdo->prepare('SELECT id FROM sources WHERE id = :id AND client_id = :client_id LIMIT 1');
    $check->execute(['id' => $sourceId, 'client_id' => $clientId]);
    if (!$check->fetch()) {
        return false;
    }

    ce_delete_source_dependencies($pdo, $sourceId);

    try {
        $stmt = $pdo->prepare('DELETE FROM sources WHERE id = :id AND client_id = :client_id');
        $stmt->execute(['id' => $sourceId, 'client_id' => $clientId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // scrape_runs houdt source_id vast (NOT NULL FK) — historie behouden, bron toch verwijderen
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        if ($sqlState !== '23000') {
            throw $e;
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $stmt = $pdo->prepare('DELETE FROM sources WHERE id = :id AND client_id = :client_id');
            $stmt->execute(['id' => $sourceId, 'client_id' => $clientId]);

            return $stmt->rowCount() > 0;
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}

/** @return array<string, mixed>|null */
function ce_check_source(PDO $pdo, string $sourceId, ?array $body = null): ?array
{
    ce_ensure_sources_schema($pdo);

    $source = ce_fetch_source($pdo, $sourceId);
    if (!$source) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT category, url, mailbox_email, mailbox_password_ref, mailbox_imap_host FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $status = 'reachable';
    $category = (string) $row['category'];
    $url = trim((string) ($row['url'] ?? ''));
    $checkMessage = null;

    if ($category === 'url_rss' && $url !== '') {
        $probe = ce_probe_rss_feed($url);
        $webProbe = null;
        if (!($probe['ok'] ?? false)) {
            $webProbe = ce_probe_webpage_url($url);
            if ($webProbe['ok'] ?? false) {
                $probe = $webProbe;
            }
        }
        if (!($probe['ok'] ?? false) && !ce_url_looks_like_feed($url)) {
            $feedFallback = ce_probe_rss_feed_fallback_for_blocked_page($url);
            if ($feedFallback['ok'] ?? false) {
                $probe = $feedFallback;
            } elseif (($probe['botBlocked'] ?? false) || ($webProbe['botBlocked'] ?? false)) {
                $probe['message'] = $feedFallback['message'] ?? $probe['message'];
            }
        }
        if (!($probe['ok'] ?? false) && (($probe['botBlocked'] ?? false) || ($webProbe['botBlocked'] ?? false))) {
            $googleFallback = ce_probe_rss_google_news_fallback($url);
            if ($googleFallback['ok'] ?? false) {
                $probe = $googleFallback;
            } else {
                $googleUrl = ce_google_news_rss_url_for_site($url);
                if ($googleUrl !== null && !str_contains((string) ($probe['message'] ?? ''), 'news.google.com')) {
                    $probe['message'] = trim((string) ($probe['message'] ?? ''))
                        . " Alternatief (Google News): {$googleUrl}";
                }
            }
        }
        $status = ($probe['ok'] ?? false) ? 'reachable' : 'unreachable';
        $checkMessage = $probe['message'] ?? null;
        $resolvedFeedUrl = trim((string) ($probe['resolvedFeedUrl'] ?? ''));
        if ($resolvedFeedUrl !== '' && strcasecmp(rtrim($resolvedFeedUrl, '/'), rtrim($url, '/')) !== 0) {
            $pdo->prepare('UPDATE sources SET url = :url WHERE id = :id')
                ->execute(['url' => $resolvedFeedUrl, 'id' => $sourceId]);
        }
        if (ce_table_has_column($pdo, 'sources', 'source_kind')) {
            $kind = ($probe['ok'] ?? false)
                ? (string) ($probe['kind'] ?? 'rss')
                : 'url_rss';
            if ($kind === '') {
                $kind = 'rss';
            }
            $pdo->prepare('UPDATE sources SET source_kind = :kind WHERE id = :id')
                ->execute(['kind' => $kind, 'id' => $sourceId]);
        }
    } elseif ($category === 'blogs' && $url !== '') {
        require_once __DIR__ . '/blog-overview-repository.php';
        $probe = ce_probe_blog_overview($url);
        $status = ($probe['ok'] ?? false) ? 'reachable' : 'unreachable';
        $checkMessage = $probe['message'] ?? null;
        if (ce_table_has_column($pdo, 'sources', 'source_kind')) {
            $kind = ($probe['ok'] ?? false)
                ? (string) ($probe['kind'] ?? 'blog_index')
                : 'blogs';
            if ($kind === '') {
                $kind = 'blog_index';
            }
            $pdo->prepare('UPDATE sources SET source_kind = :kind WHERE id = :id')
                ->execute(['kind' => $kind, 'id' => $sourceId]);
        }
    } elseif ($category === 'youtube' && $url !== '') {
        require_once __DIR__ . '/youtube-repository.php';
        $probe = ce_probe_youtube_channel($url);
        $status = ($probe['ok'] ?? false) ? 'reachable' : 'unreachable';
        $checkMessage = $probe['message'] ?? null;
        $resolvedFeedUrl = trim((string) ($probe['resolvedFeedUrl'] ?? ''));
        if ($resolvedFeedUrl !== '') {
            $pdo->prepare('UPDATE sources SET url = :url WHERE id = :id')
                ->execute(['url' => $resolvedFeedUrl, 'id' => $sourceId]);
        }
        if (ce_table_has_column($pdo, 'sources', 'source_kind')) {
            $kind = ($probe['ok'] ?? false) ? 'youtube_rss' : 'youtube';
            $pdo->prepare('UPDATE sources SET source_kind = :kind WHERE id = :id')
                ->execute(['kind' => $kind, 'id' => $sourceId]);
        }
    } elseif ($category === 'document') {
        require_once __DIR__ . '/source-document-repository.php';
        $probe = ce_probe_document_source($pdo, $sourceId);
        $status = ($probe['ok'] ?? false) ? 'reachable' : 'unreachable';
        $checkMessage = $probe['message'] ?? null;
    } elseif ($category === 'asana') {
        require_once __DIR__ . '/asana-helper.php';
        $plainPat = is_array($body) ? trim((string) ($body['asanaPatRef'] ?? '')) : '';
        $probe = ce_probe_asana_source($pdo, $sourceId, $plainPat !== '' ? $plainPat : null);
        $status = ($probe['ok'] ?? false) ? 'reachable' : 'unreachable';
        $checkMessage = $probe['message'] ?? null;
    } elseif ($category === 'drive') {
        require_once __DIR__ . '/drive-repository.php';
        $probe = ce_probe_drive_source($pdo, $sourceId);
        $status = ($probe['ok'] ?? false) ? 'reachable' : 'unreachable';
        $checkMessage = $probe['message'] ?? null;
    } elseif ($category === 'trello') {
        $boardStmt = $pdo->prepare(
            'SELECT trello_board_id, trello_api_token_ref FROM sources WHERE id = :id LIMIT 1'
        );
        $boardStmt->execute(['id' => $sourceId]);
        $boardRow = $boardStmt->fetch();
        $boardId = trim((string) ($boardRow['trello_board_id'] ?? ''));
        $sourceToken = trim((string) ($boardRow['trello_api_token_ref'] ?? ''));
        if ($boardId === '') {
            $status = 'unreachable';
            $checkMessage = 'Board-ID ontbreekt.';
        } else {
            $probe = ce_probe_trello_board_content($boardId, $sourceToken);
            $status = ($probe['ok'] ?? false) ? 'reachable' : 'unreachable';
            $checkMessage = $probe['message'] ?? null;
        }
    } elseif ($category === 'analytics') {
        require_once __DIR__ . '/analytics-oauth-helper.php';
        $status = ce_analytics_verify_connection($pdo, $sourceId) ? 'reachable' : 'unreachable';
    } elseif ($category === 'calendar') {
        require_once __DIR__ . '/calendar-oauth-helper.php';
        $probe = ce_calendar_verify_source($pdo, $sourceId);
        $status = ($probe['ok'] ?? false) ? 'reachable' : 'unreachable';
        $checkMessage = $probe['message'] ?? null;
    } elseif ($category === 'mailbox') {
        $email = trim((string) ($row['mailbox_email'] ?? ''));
        $imapHost = ce_mailbox_resolve_imap_host($email, (string) ($row['mailbox_imap_host'] ?? ''));
        $password = '';
        $error = '';
        try {
            $password = ce_credential_resolve_plaintext(
                is_array($body) ? (string) ($body['mailboxPasswordRef'] ?? '') : null,
                (string) ($row['mailbox_password_ref'] ?? ''),
            );
        } catch (Throwable) {
            $password = trim((string) (is_array($body) ? ($body['mailboxPasswordRef'] ?? '') : ''));
            if ($password === '') {
                $checkMessage = 'Opgeslagen wachtwoord kon niet worden gelezen — vul het opnieuw in.';
            }
        }
        if ($email === '' || $password === '') {
            $status = 'unreachable';
            $checkMessage ??= 'E-mailadres en wachtwoord zijn verplicht.';
        } elseif (!ce_mailbox_probe($email, $password, $error, $imapHost)) {
            $status = 'unreachable';
            $checkMessage = $error !== '' ? $error : 'IMAP-verbinding mislukt.';
        } else {
            $syncDebug = [];
            $synced = ce_mailbox_sync_newsletters($pdo, $sourceId, $email, $password, 15, $imapHost, $syncDebug);
            $collected = (int) ($syncDebug['collected'] ?? 0);
            if ($collected === 0) {
                $status = 'unreachable';
                $hostLabel = trim((string) ($syncDebug['imapHost'] ?? $imapHost ?? ''));
                $checkMessage = $hostLabel !== ''
                    ? "Verbinding OK op {$hostLabel}, maar 0 berichten in INBOX."
                    : 'Verbinding OK, maar 0 berichten in INBOX.';
            } elseif ($synced > 0) {
                $status = 'reachable';
                $checkMessage = "{$synced} nieuwsbrief(s) gesynchroniseerd.";
            } else {
                $status = 'unreachable';
                $checkMessage = 'Verbinding OK, maar berichten konden niet worden opgehaald — geen data voor research.';
            }
            $encrypted = ce_credential_encrypt($password);
            if ($encrypted !== (string) ($row['mailbox_password_ref'] ?? '')) {
                $pdo->prepare('UPDATE sources SET mailbox_password_ref = :pass WHERE id = :id')
                    ->execute(['pass' => $encrypted, 'id' => $sourceId]);
            }
        }
    } elseif ($category === 'scraper' || $category === 'linkedin') {
        require_once __DIR__ . '/scrape-repository.php';
        $probe = ce_probe_scraper_source($pdo, $sourceId);
        $status = ($probe['reachable'] ?? false) ? 'reachable' : 'unreachable';
        $checkMessage = $probe['message'] ?? null;
    } elseif ($url !== '') {
        $status = ce_probe_url($url) ? 'reachable' : 'unreachable';
    } else {
        $status = 'unreachable';
    }

    $pdo->prepare(
        'UPDATE sources SET url_status = :status, last_checked_at = NOW() WHERE id = :id'
    )->execute(['status' => $status, 'id' => $sourceId]);

    $result = ce_fetch_source($pdo, $sourceId);
    if ($result !== null && $checkMessage !== null && $checkMessage !== '') {
        $result['checkMessage'] = $checkMessage;
    }

    return $result;
}

function ce_probe_url(string $url): bool
{
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    require_once __DIR__ . '/url-guard.php';
    if (!ce_url_is_safe($url)) {
        return false;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'timeout' => 8,
            'ignore_errors' => true,
            'follow_location' => 0,
            'user_agent' => 'ContentEngine/1.0',
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false && empty($http_response_header)) {
        return false;
    }

    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $m)) {
        $code = (int) $m[1];
        return $code >= 200 && $code < 400;
    }

    return $result !== false;
}

function ce_http_browser_user_agent(): string
{
    return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
}

/** @return array{body: ?string, httpCode: int} */
function ce_http_fetch_url(string $url, int $timeout = 12): array
{
    $url = trim($url);
    if ($url === '') {
        return ['body' => null, 'httpCode' => 0];
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    require_once __DIR__ . '/url-guard.php';
    if (!ce_url_is_safe($url)) {
        return ['body' => null, 'httpCode' => 0];
    }

    if (function_exists('curl_init')) {
        // Redirects volgen we zelf, zodat elke hop opnieuw langs de guard gaat.
        $current = $url;
        for ($hop = 0; $hop <= 5; $hop++) {
            $ch = curl_init($current);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
                CURLOPT_USERAGENT => ce_http_browser_user_agent(),
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/rss+xml,application/atom+xml,*/*;q=0.8',
                    'Accept-Language: nl-NL,nl;q=0.9,en;q=0.8',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            $location = trim((string) curl_getinfo($ch, CURLINFO_REDIRECT_URL));
            curl_close($ch);

            if (
                ($body === false || ($code === 0 && ($body === false || $body === '')))
                && $hop === 0
                && $curlErr !== ''
                && (stripos($curlErr, 'HTTP/2') !== false || stripos($curlErr, 'timed out') !== false)
            ) {
                $ch = curl_init($current);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_USERAGENT => ce_http_browser_user_agent(),
                    CURLOPT_HTTPHEADER => [
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/rss+xml,application/atom+xml,*/*;q=0.8',
                        'Accept-Language: nl-NL,nl;q=0.9,en;q=0.8',
                    ],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $body = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $location = trim((string) curl_getinfo($ch, CURLINFO_REDIRECT_URL));
                curl_close($ch);
            }

            if ($code >= 300 && $code < 400 && $location !== '' && $hop < 5) {
                $next = ce_url_resolve_redirect($current, $location);
                if ($next === '' || !ce_url_is_safe($next)) {
                    return ['body' => null, 'httpCode' => 0];
                }
                $current = $next;
                continue;
            }

            if ($body === false) {
                return ['body' => null, 'httpCode' => $code > 0 ? $code : 0];
            }

            return ['body' => is_string($body) ? $body : null, 'httpCode' => $code];
        }

        return ['body' => null, 'httpCode' => 0];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            // Zonder curl volgen we geen redirects: die kunnen naar een intern adres wijzen.
            'follow_location' => 0,
            'user_agent' => ce_http_browser_user_agent(),
            'header' => "Accept: text/html,application/xml,*/*\r\nAccept-Language: nl-NL,nl;q=0.9\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $context);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    if (!is_string($body) || $body === '') {
        return ['body' => null, 'httpCode' => $code];
    }

    return ['body' => $body, 'httpCode' => $code];
}

function ce_http_get_body(string $url, int $timeout = 12): ?string
{
    $fetch = ce_http_fetch_url($url, $timeout);
    if ($fetch['httpCode'] >= 400 || $fetch['body'] === null || $fetch['body'] === '') {
        return null;
    }

    return $fetch['body'];
}

function ce_http_body_is_bot_block(?string $body, int $httpCode): bool
{
    if ($httpCode !== 403) {
        return false;
    }
    if ($body === null || $body === '') {
        return true;
    }
    if (ce_rss_body_looks_like_feed($body)) {
        return false;
    }
    if (stripos($body, '403 - Forbidden') !== false) {
        return true;
    }
    if (stripos($body, 'cloud-blue') !== false && stripos($body, 'bg_error_lines') !== false) {
        return true;
    }

    return stripos($body, '<html') !== false;
}

function ce_url_looks_like_feed(string $url): bool
{
    $u = strtolower(trim($url));
    if ($u === '') {
        return false;
    }
    if (str_contains($u, 'news.google.com/rss/')) {
        return true;
    }
    if (preg_match('#/feed(/|$|\?)#', $u)) {
        return true;
    }
    if (preg_match('#/rss(/|$|\?)#', $u)) {
        return true;
    }

    return preg_match('#\.(?:xml|rss|atom)(?:\?|$)#', $u) === 1;
}

function ce_url_looks_like_local_path(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    if (str_starts_with(strtolower($url), 'file://')) {
        return true;
    }
    if (str_starts_with($url, '/Users/') || str_starts_with($url, '/home/') || str_starts_with($url, '/private/')) {
        return true;
    }
    if (preg_match('#^[A-Za-z]:[\\\\/]#', $url) === 1) {
        return true;
    }

    return false;
}

function ce_google_news_rss_url_for_site(string $url): ?string
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('#^www\.#', '', $host);
    if ($host === '' || str_contains($host, 'news.google.com')) {
        return null;
    }

    $q = rawurlencode('site:' . $host);

    return "https://news.google.com/rss/search?q={$q}&hl=nl&gl=NL&ceid=NL:nl";
}

/** Google News RSS scoped to the blog overview path (without generic index slugs). */
function ce_google_news_rss_url_for_page(string $pageUrl): ?string
{
    $host = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
    $host = preg_replace('#^www\.#', '', $host);
    if ($host === '' || str_contains($host, 'news.google.com')) {
        return null;
    }

    $path = (string) (parse_url($pageUrl, PHP_URL_PATH) ?? '/');
    $path = rtrim($path, '/') ?: '/';
    if ($path !== '/') {
        $path = preg_replace(
            '#/(our-insights|insights|inzichten|blog|blogs|news|nieuws|artikelen|articles|posts)$#i',
            '',
            $path,
        ) ?? $path;
        $path = rtrim($path, '/') ?: '/';
    }

    $siteTerm = $path === '/' ? $host : $host . $path;
    $q = rawurlencode('site:' . $siteTerm);

    return "https://news.google.com/rss/search?q={$q}&hl=nl&gl=NL&ceid=NL:nl";
}

/**
 * @return array{ok: bool, message: ?string, itemCount: int, resolvedFeedUrl?: string, kind?: string}
 */
function ce_probe_rss_google_news_fallback(string $siteUrl): array
{
    $googleUrl = ce_google_news_rss_url_for_site($siteUrl);
    if ($googleUrl === null) {
        return ['ok' => false, 'message' => null, 'itemCount' => 0];
    }

    $nested = ce_probe_rss_feed($googleUrl, false);
    if (!($nested['ok'] ?? false)) {
        return $nested;
    }

    $nested['resolvedFeedUrl'] = $googleUrl;
    $host = preg_replace('#^www\.#', '', strtolower((string) parse_url($siteUrl, PHP_URL_HOST)));
    $countMsg = trim((string) ($nested['message'] ?? ''));
    $nested['message'] = trim(
        $countMsg . " Site {$host} blokkeert bots — Google News-feed gekoppeld als alternatief."
    );

    return $nested;
}

/** @return list<string> */
function ce_rss_guess_feed_urls_for_page(string $pageUrl): array
{
    $parsed = parse_url($pageUrl);
    if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
        return [];
    }

    $origin = $parsed['scheme'] . '://' . $parsed['host'];
    $paths = ['/feed/', '/rss/', '/feed.xml', '/rss.xml', '/atom.xml'];
    $guesses = [];
    foreach ($paths as $path) {
        $guesses[] = $origin . $path;
    }

    return $guesses;
}

/**
 * @return array{ok: bool, message: ?string, itemCount: int, resolvedFeedUrl?: string, kind?: string}
 */
function ce_probe_rss_feed_fallback_for_blocked_page(string $pageUrl): array
{
    foreach (ce_rss_guess_feed_urls_for_page($pageUrl) as $feedUrl) {
        if (strcasecmp(rtrim($feedUrl, '/'), rtrim($pageUrl, '/')) === 0) {
            continue;
        }
        $nested = ce_probe_rss_feed($feedUrl, false);
        if ($nested['ok'] ?? false) {
            $nested['resolvedFeedUrl'] = $feedUrl;
            $countMsg = trim((string) ($nested['message'] ?? ''));
            $nested['message'] = trim(
                $countMsg . ' Pagina-URL blokkeert bots — feed gekoppeld: ' . $feedUrl . '.'
            );

            return $nested;
        }
    }

    $feedHint = ce_rss_guess_feed_urls_for_page($pageUrl)[0] ?? '';
    $hint = $feedHint !== '' ? " Probeer de RSS-feed: {$feedHint}." : '';
    $googleUrl = ce_google_news_rss_url_for_site($pageUrl);
    $googleHint = $googleUrl !== null ? " Of Google News: {$googleUrl}." : '';

    return [
        'ok' => false,
        'message' => 'Deze site blokkeert automatische toegang (403).'
            . $hint
            . $googleHint
            . ' Werkt dat ook niet: voeg de bron toe onder Scraper (Apify).',
        'itemCount' => 0,
    ];
}

function ce_rss_extract_title(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('#<!\[CDATA\[([\s\S]*?)\]\]>#', $raw, $matches)) {
        return trim($matches[1]);
    }

    return trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function ce_rss_block_has_title(string $block): bool
{
    if (!preg_match('#<title(?:\s[^>]*)?>([\s\S]*?)</title>#i', $block, $matches)) {
        if (preg_match('#<dc:title(?:\s[^>]*)?>([\s\S]*?)</dc:title>#i', $block, $matches)) {
            return ce_rss_extract_title($matches[1]) !== '';
        }

        return false;
    }

    return ce_rss_extract_title($matches[1]) !== '';
}

function ce_rss_body_looks_like_feed(string $body): bool
{
    $head = substr(ltrim($body), 0, 4096);

    return preg_match('#<(rss|feed|rdf:RDF)\b#i', $head) === 1;
}

/** @return list<string> */
function ce_rss_discover_feed_urls(string $pageUrl, string $html): array
{
    $candidates = [];
    $seen = [];

    $add = static function (string $href) use (&$candidates, &$seen, $pageUrl): void {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '') {
            return;
        }
        if (!preg_match('#^https?://#i', $href)) {
            $base = parse_url($pageUrl);
            if ($base === false || !isset($base['scheme'], $base['host'])) {
                return;
            }
            $origin = $base['scheme'] . '://' . $base['host'];
            if (str_starts_with($href, '//')) {
                $href = $base['scheme'] . ':' . $href;
            } elseif (str_starts_with($href, '/')) {
                $href = $origin . $href;
            } else {
                $path = $base['path'] ?? '/';
                $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
                $href = $origin . rtrim($dir, '/') . '/' . $href;
            }
        }
        $key = strtolower(rtrim($href, '/'));
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $candidates[] = $href;
    };

    if (preg_match_all(
        '#<link\b[^>]*\btype=["\']application/(?:rss\+xml|atom\+xml)["\'][^>]*>#i',
        $html,
        $tags,
    )) {
        foreach ($tags[0] as $tag) {
            if (preg_match('#\bhref=(["\'])([^"\']+)\1#i', $tag, $m)) {
                $add($m[2]);
            }
        }
    }
    if (preg_match_all(
        '#<link\b[^>]*\bhref=(["\'])([^"\']+)\1[^>]*\btype=["\']application/(?:rss\+xml|atom\+xml)["\']#i',
        $html,
        $m,
    )) {
        foreach ($m[2] as $href) {
            $add($href);
        }
    }

    $isWordPress = stripos($html, 'wp-content') !== false
        || stripos($html, 'wp-json') !== false
        || stripos($html, 'xmlrpc.php') !== false;
    if ($isWordPress) {
        $parts = parse_url($pageUrl);
        if ($parts !== false && isset($parts['scheme'], $parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'];
            $path = rtrim((string) ($parts['path'] ?? ''), '/');
            $add($origin . '/feed/');
            if ($path !== '') {
                $add($origin . $path . '/feed/');
            }
        }
    }

    return $candidates;
}

function ce_count_rss_items_in_body(string $body): int
{
    $count = 0;
    foreach (['item', 'entry'] as $tag) {
        if (!preg_match_all('#<' . $tag . '\b[\s\S]*?</' . $tag . '>#i', $body, $blocks)) {
            continue;
        }
        foreach ($blocks[0] as $block) {
            if (ce_rss_block_has_title($block)) {
                $count++;
            }
        }
    }

    return $count;
}

/** @return array{title: string, snippet: string}|null */
function ce_parse_webpage_metadata(string $html, string $pageUrl): ?array
{
    $html = trim($html);
    if ($html === '') {
        return null;
    }

    $metaContent = static function (string $blob, string $attr, string $value): string {
        $pattern = '#<meta[^>]+' . preg_quote($attr, '#') . '=["\']'
            . preg_quote($value, '#') . '["\'][^>]+content=["\']([^"\']+)#i';
        if (preg_match($pattern, $blob, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $pattern2 = '#<meta[^>]+content=["\']([^"\']+)["\'][^>]+' . preg_quote($attr, '#')
            . '=["\']' . preg_quote($value, '#') . '["\']#i';
        if (preg_match($pattern2, $blob, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    };

    $title = $metaContent($html, 'property', 'og:title');
    if ($title === '' && preg_match('#<title[^>]*>([\s\S]*?)</title>#i', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($title === '' && preg_match('#<h1[^>]*>([\s\S]*?)</h1>#i', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $snippet = $metaContent($html, 'property', 'og:description');
    if ($snippet === '') {
        $snippet = $metaContent($html, 'name', 'description');
    }
    if ($snippet === '' && preg_match('#<meta[^>]+name=["\']twitter:description["\'][^>]+content=["\']([^"\']+)#i', $html, $m)) {
        $snippet = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $title = preg_replace('/\s+/u', ' ', $title);
    $snippet = preg_replace('/\s+/u', ' ', $snippet);

    if ($title === '') {
        return null;
    }

    if ($snippet === '') {
        $snippet = 'Webpagina: ' . $pageUrl;
    }

    return [
        'title' => mb_substr($title, 0, 500),
        'snippet' => mb_substr($snippet, 0, 2000),
    ];
}

/** @return array{ok: bool, message: ?string, itemCount: int, kind?: string} */
function ce_probe_webpage_url(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'message' => 'URL ontbreekt.', 'itemCount' => 0];
    }
    if (ce_url_looks_like_local_path($url)) {
        return [
            'ok' => false,
            'message' => 'Gebruik een web-URL (https://…). Een RSS-bestand op je computer kan Content Engine niet ophalen.',
            'itemCount' => 0,
        ];
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $fetch = ce_http_fetch_url($url, 15);
    $body = $fetch['body'];
    if ($body === null || $body === '' || $fetch['httpCode'] >= 400) {
        if (ce_http_body_is_bot_block($body, $fetch['httpCode'])) {
            return [
                'ok' => false,
                'message' => 'Webpagina blokkeert automatische toegang (403).',
                'itemCount' => 0,
                'botBlocked' => true,
            ];
        }

        return ['ok' => false, 'message' => 'Webpagina kon niet worden opgehaald.', 'itemCount' => 0];
    }

    $isHtml = stripos($body, '<html') !== false || stripos($body, '<!doctype html') !== false;
    if (!$isHtml || ce_rss_body_looks_like_feed($body)) {
        return ['ok' => false, 'message' => 'Geen leesbare HTML-webpagina.', 'itemCount' => 0];
    }

    $meta = ce_parse_webpage_metadata($body, $url);
    if ($meta === null) {
        return [
            'ok' => false,
            'message' => 'Webpagina bereikbaar, maar geen titel gevonden (og:title of <title>).',
            'itemCount' => 0,
        ];
    }

    return [
        'ok' => true,
        'message' => 'Webpagina — wordt als 1 bron bij research ingelezen.',
        'itemCount' => 1,
        'kind' => 'webpage',
    ];
}

/** @return array{ok: bool, message: ?string, itemCount: int, resolvedFeedUrl?: string} */
function ce_probe_rss_feed(string $url, bool $allowDiscover = true): array
{
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'message' => 'URL ontbreekt.', 'itemCount' => 0];
    }
    if (ce_url_looks_like_local_path($url)) {
        return [
            'ok' => false,
            'message' => 'Gebruik een web-URL (https://…). Een RSS-bestand op je computer kan Content Engine niet ophalen.',
            'itemCount' => 0,
        ];
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $fetch = ce_http_fetch_url($url, 15);
    $body = $fetch['body'];
    if ($body === null || $body === '' || $fetch['httpCode'] >= 400) {
        if (ce_http_body_is_bot_block($body, $fetch['httpCode'])) {
            return [
                'ok' => false,
                'message' => 'Feed/pagina blokkeert automatische toegang (403).',
                'itemCount' => 0,
                'botBlocked' => true,
            ];
        }

        return ['ok' => false, 'message' => 'Feed kon niet worden opgehaald.', 'itemCount' => 0];
    }

    $itemCount = ce_count_rss_items_in_body($body);
    if ($itemCount > 0) {
        return [
            'ok' => true,
            'message' => $itemCount === 1 ? '1 artikel in feed.' : "{$itemCount} artikelen in feed.",
            'itemCount' => $itemCount,
            'kind' => 'rss',
        ];
    }

    if (preg_match('#^https?://substack\.com/@([a-zA-Z0-9_-]+)#i', $url, $matches)) {
        $feedUrl = 'https://' . strtolower($matches[1]) . '.substack.com/feed';
        $feedBody = ce_http_get_body($feedUrl, 15);
        $feedCount = $feedBody !== null ? ce_count_rss_items_in_body($feedBody) : 0;
        if ($feedCount > 0) {
            return [
                'ok' => false,
                'message' => "Geen RSS op profiel-URL — gebruik {$feedUrl}.",
                'itemCount' => 0,
            ];
        }
    }

    $isHtml = (stripos($body, '<html') !== false || stripos($body, '<!doctype html') !== false)
        && !ce_rss_body_looks_like_feed($body);

    if ($allowDiscover && $isHtml) {
        foreach (ce_rss_discover_feed_urls($url, $body) as $feedUrl) {
            if (strcasecmp(rtrim($feedUrl, '/'), rtrim($url, '/')) === 0) {
                continue;
            }
            $nested = ce_probe_rss_feed($feedUrl, false);
            if ($nested['ok'] ?? false) {
                $nested['resolvedFeedUrl'] = $feedUrl;
                $countMsg = $nested['message'] ?? '';
                $nested['message'] = trim($countMsg . ' URL bijgewerkt naar feed: ' . $feedUrl . '.');

                return $nested;
            }
        }

        $hint = '';
        $guesses = ce_rss_discover_feed_urls($url, $body);
        if ($guesses !== []) {
            $hint = ' Probeer: ' . $guesses[0] . '.';
        }

        return [
            'ok' => false,
            'message' => 'URL geeft een webpagina terug, geen RSS-feed met artikelen.' . $hint,
            'itemCount' => 0,
        ];
    }

    if ($isHtml) {
        return [
            'ok' => false,
            'message' => 'URL geeft een webpagina terug, geen RSS-feed met artikelen.',
            'itemCount' => 0,
        ];
    }

    return [
        'ok' => false,
        'message' => 'RSS-feed bereikbaar maar bevat geen artikelen.',
        'itemCount' => 0,
    ];
}

/** @param array<string, mixed> $body */
function ce_sources_normalize_linkedin(array $body): array
{
    require_once __DIR__ . '/scrape-repository.php';

    $profile = trim((string) ($body['scraperLinkedinProfile'] ?? $body['scraper_linkedin_profile'] ?? ''));
    $isCompany = $profile !== '' && ce_scraper_linkedin_is_company_profile($profile);
    $url = trim((string) ($body['url'] ?? ''));
    $actor = trim((string) ($body['scraperActorOrTaskId'] ?? ''));
    $haystack = strtolower($url . ' ' . $actor);
    $isCompanyActor = str_contains($haystack, 'company');

    if ($isCompany) {
        $companyActor = ce_scraper_linkedin_company_actor_id();
        $body['url'] = 'https://api.apify.com/v2/acts/' . rawurlencode($companyActor) . '/run-sync-get-dataset-items';
        $body['scraperActorOrTaskId'] = $companyActor;
    } else {
        if ($url === '' || $isCompanyActor) {
            $profileActor = ce_scraper_linkedin_profile_actor_id();
            $body['url'] = 'https://api.apify.com/v2/acts/' . rawurlencode($profileActor) . '/run-sync-get-dataset-items';
        }
        if ($actor === '' || $isCompanyActor) {
            $body['scraperActorOrTaskId'] = ce_scraper_linkedin_profile_actor_id();
        }
    }

    return $body;
}

/** @param array<string, mixed> $body */
function ce_sources_normalize_scraper_apify(array $body): array
{
    $url = trim((string) ($body['url'] ?? ''));
    $actor = trim((string) ($body['scraperActorOrTaskId'] ?? ''));

    if ($url !== '' && preg_match('#^https?://api\.apify\.com/v2/acts/([^/?]+)#i', $url, $matches)) {
        $body['url'] = preg_split('#\?#', $url, 2)[0];
        if ($actor === '') {
            $body['scraperActorOrTaskId'] = str_replace('/', '~', rawurldecode($matches[1]));
        }
    }

    return $body;
}

/**
 * Kopieer alle sources van bron- naar doel-tenant (nieuwe ids, zelfde credentials).
 *
 * @return array<string, mixed>
 */
function ce_copy_client_sources(PDO $pdo, string $fromClientId, string $toClientId, bool $replace = true): array
{
    require_once __DIR__ . '/content-reset.php';

    ce_ensure_sources_schema($pdo);

    $fromClientId = trim($fromClientId);
    $toClientId = trim($toClientId);
    if ($fromClientId === '' || $toClientId === '') {
        throw new InvalidArgumentException('fromClientId en toClientId zijn verplicht');
    }
    if ($fromClientId === $toClientId) {
        throw new InvalidArgumentException('Bron en doel mogen niet hetzelfde zijn');
    }

    foreach ([$fromClientId => 'from', $toClientId => 'to'] as $clientId => $label) {
        $check = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
        $check->execute(['id' => $clientId]);
        if (!$check->fetch()) {
            throw new InvalidArgumentException("Client niet gevonden ({$label}): {$clientId}");
        }
    }

    $removed = 0;
    if ($replace) {
        $existingIds = ce_fetch_ids_for_client($pdo, 'sources', $toClientId);
        $removed = ce_delete_by_ids($pdo, 'sources', 'id', $existingIds);
    }

    $stmt = $pdo->prepare('SELECT * FROM sources WHERE client_id = :client_id ORDER BY category ASC, title ASC');
    $stmt->execute(['client_id' => $fromClientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $tableCols = ce_table_columns($pdo, 'sources');
    $skipOnCopy = ['created_at'];
    $copied = [];

    foreach ($rows as $row) {
        $newId = ce_uuid_v4();
        $insert = [
            'id' => $newId,
            'client_id' => $toClientId,
            'url_status' => 'unchecked',
            'last_checked_at' => null,
        ];

        foreach ($row as $column => $value) {
            if (!isset($tableCols[$column])) {
                continue;
            }
            if (in_array($column, ['id', 'client_id', 'url_status', 'last_checked_at', ...$skipOnCopy], true)) {
                continue;
            }
            $insert[$column] = $value;
        }

        $category = trim((string) ($insert['category'] ?? $row['category'] ?? 'url_rss'));
        if ($category === '') {
            $category = 'url_rss';
        }
        $insert['category'] = $category;
        if (isset($tableCols['source_kind'])) {
            $kind = trim((string) ($insert['source_kind'] ?? $row['source_kind'] ?? $category));
            $insert['source_kind'] = $kind !== '' ? $kind : $category;
        }
        if (isset($tableCols['active']) && !array_key_exists('active', $insert)) {
            $insert['active'] = 1;
        }

        $cols = array_keys($insert);
        $placeholders = array_map(static fn (string $col): string => ':' . $col, $cols);
        $sql = 'INSERT INTO sources (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $pdo->prepare($sql)->execute($insert);

        $copied[] = [
            'id' => $newId,
            'category' => (string) ($insert['category'] ?? ''),
            'title' => (string) ($insert['title'] ?? ''),
        ];
    }

    return [
        'ok' => true,
        'fromClientId' => $fromClientId,
        'toClientId' => $toClientId,
        'removed' => $removed,
        'copied' => count($copied),
        'sources' => $copied,
    ];
}

/** @param array<string, mixed> $body */
function ce_sources_apply_category_fields(PDO $pdo, string $sourceId, string $category, array $body): void
{
    ce_ensure_sources_schema($pdo);

    if ($category === 'asana') {
        $fields = [];
        $params = ['id' => $sourceId];
        if (array_key_exists('asanaPatRef', $body)) {
            $pat = ce_credential_prepare_for_storage((string) ($body['asanaPatRef'] ?? ''), null);
            if ($pat !== '') {
                $fields[] = 'asana_pat_ref = :asana_pat_ref';
                $params['asana_pat_ref'] = $pat;
            }
        }
        $map = [
            'asanaWorkspaceGid' => 'asana_workspace_gid',
            'asanaProjectGid' => 'asana_project_gid',
            'asanaWorkspaceName' => 'asana_workspace_name',
            'asanaProjectName' => 'asana_project_name',
        ];
        foreach ($map as $key => $column) {
            if (!array_key_exists($key, $body) || !ce_table_has_column($pdo, 'sources', $column)) {
                continue;
            }
            $fields[] = "`$column` = :$column";
            $params[$column] = trim((string) $body[$key]) ?: null;
        }
        if ($fields !== []) {
            $pdo->prepare('UPDATE sources SET ' . implode(', ', $fields) . ' WHERE id = :id')
                ->execute($params);
        }
    }

    if ($category === 'drive' && array_key_exists('driveFileUrl', $body)
        && ce_table_has_column($pdo, 'sources', 'drive_file_url')) {
        $pdo->prepare('UPDATE sources SET drive_file_url = :url WHERE id = :id')
            ->execute([
                'url' => trim((string) $body['driveFileUrl']) ?: null,
                'id' => $sourceId,
            ]);
    }
}
