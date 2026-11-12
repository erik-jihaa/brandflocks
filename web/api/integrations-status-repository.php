<?php

declare(strict_types=1);

require_once __DIR__ . '/publish-settings-repository.php';
require_once __DIR__ . '/publish-repository.php';
require_once __DIR__ . '/linkedin-oauth-helper.php';
require_once __DIR__ . '/analytics-oauth-helper.php';
require_once __DIR__ . '/calendar-oauth-helper.php';
require_once __DIR__ . '/scrape-repository.php';

/** @return array{status: string, label: string, context: string, note: ?string, since: ?string, meta: array<string, mixed>} */
function ce_integration_status_entry(
    string $status,
    string $label,
    string $context = '',
    ?string $note = null,
    ?string $since = null,
    array $meta = [],
): array {
    return [
        'status' => $status,
        'label' => $label,
        'context' => $context,
        'note' => $note,
        'since' => $since,
        'meta' => $meta,
    ];
}

function ce_integration_status_label(string $status): string
{
    return match ($status) {
        'connected' => 'Gekoppeld',
        'action_required' => 'Actie nodig',
        'unavailable' => 'Niet beschikbaar',
        default => 'Niet ingesteld',
    };
}

/** @param list<string> $statuses */
function ce_integration_worst_status(array $statuses): string
{
    $order = ['action_required' => 0, 'unavailable' => 1, 'not_set' => 2, 'connected' => 3];
    $worst = 'connected';
    $worstRank = 99;
    foreach ($statuses as $status) {
        $rank = $order[$status] ?? 2;
        if ($rank < $worstRank) {
            $worstRank = $rank;
            $worst = $status;
        }
    }

    return $worst;
}

/** @return list<array<string, mixed>> */
function ce_integrations_sources_for_client(PDO $pdo, string $clientId, ?string $category = null): array
{
    if (!ce_table_exists($pdo, 'sources')) {
        return [];
    }

    $sql = "SELECT * FROM sources WHERE client_id = :client_id AND category <> 'asana'";
    $params = ['client_id' => $clientId];
    if ($category !== null) {
        $sql .= ' AND category = :category';
        $params['category'] = $category;
    }
    $sql .= ' ORDER BY created_at ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed> */
function ce_integrations_website_cms_status(PDO $pdo, string $clientId, array $clientRow): array
{
    $selected = ce_client_website_cms_from_row($clientRow);
    $cmsLabel = match ($selected) {
        'wordpress' => 'WordPress',
        'joomla' => 'Joomla',
        'static' => 'Statische HTML (FTP)',
        default => 'Website',
    };

    if ($selected === '') {
        $hasPartial = trim((string) ($clientRow['wordpress_site_url'] ?? '')) !== ''
            || trim((string) ($clientRow['joomla_site_url'] ?? '')) !== ''
            || trim((string) ($clientRow['site_ftp_host'] ?? '')) !== '';
        if (!$hasPartial) {
            return ce_integration_status_entry('not_set', ce_integration_status_label('not_set'), 'Publiceren');
        }
        $selected = ce_infer_website_cms_from_client_row($clientRow);
        $cmsLabel = match ($selected) {
            'wordpress' => 'WordPress',
            'joomla' => 'Joomla',
            'static' => 'Statische HTML (FTP)',
            default => 'Website',
        };
    }

    $testOk = false;
    $testNote = null;

    if ($selected === 'wordpress') {
        $config = ce_wordpress_config_for_client($pdo, $clientId, false);
        if ($config['site_url'] === '' || $config['user'] === '' || $config['app_password'] === '') {
            $testNote = 'WordPress-config incompleet (site-URL, gebruiker of app-wachtwoord).';
        } else {
            $test = ce_wordpress_test_connection($config);
            $testOk = $test['connected'] ?? false;
            $testNote = $testOk ? null : ($test['authError'] ?? 'WordPress-verbinding mislukt.');
        }
    } elseif ($selected === 'joomla') {
        require_once __DIR__ . '/joomla-publish-repository.php';
        $config = ce_joomla_config_for_client($pdo, $clientId);
        if ($config['site_url'] === '' || $config['api_token'] === '' || ($config['category_id'] ?? 0) <= 0) {
            $testNote = 'Joomla-config incompleet (site-URL, API-token of categorie).';
        } else {
            $test = ce_joomla_test_connection($config);
            $testOk = ($test['connected'] ?? false) && ($test['categoryOk'] ?? false);
            $testNote = $testOk ? null : ($test['authError'] ?? 'Joomla-verbinding mislukt.');
        }
    } elseif ($selected === 'static') {
        require_once __DIR__ . '/static-site-publish-repository.php';
        $config = ce_static_site_ftp_config_for_client($pdo, $clientId);
        if ($config === null) {
            $testNote = 'FTP-config incompleet.';
        } else {
            try {
                $conn = ce_static_site_ftp_connect($config);
                ftp_close($conn);
                $testOk = true;
            } catch (Throwable $e) {
                $testNote = $e->getMessage();
            }
        }
    }

    $status = $testOk ? 'connected' : 'action_required';
    $entry = ce_integration_status_entry(
        $status,
        ce_integration_status_label($status),
        'Publiceren · ' . $cmsLabel,
        $testNote,
        null,
        ['cmsKind' => $selected !== '' ? $selected : null],
    );

    return $entry;
}

/** @return array<string, mixed> */
function ce_integrations_linkedin_publish_status(PDO $pdo, string $clientId, array $extras): array
{
    if (!ce_linkedin_oauth_configured()) {
        return ce_integration_status_entry(
            'unavailable',
            ce_integration_status_label('unavailable'),
            'Publiceren',
            'LinkedIn OAuth ontbreekt in de serverconfiguratie.',
        );
    }

    $oauth = ce_linkedin_oauth_status_for_client($pdo, $clientId);
    $tokenValid = ce_linkedin_oauth_valid_access_token($pdo, $clientId) !== null;
    $context = 'Publiceren';
    if ($oauth['memberName']) {
        $context .= ' · als ' . $oauth['memberName'];
    }

    if ($tokenValid) {
        return ce_integration_status_entry('connected', ce_integration_status_label('connected'), $context);
    }

    $linkedinEnabled = false;
    foreach ($extras as $extra) {
        if (($extra['kind'] ?? '') === 'linkedin' && !empty($extra['enabled'])) {
            $linkedinEnabled = true;
            break;
        }
    }

    if ($oauth['connected'] || $linkedinEnabled) {
        return ce_integration_status_entry(
            'action_required',
            ce_integration_status_label('action_required'),
            $context,
            $oauth['connected']
                ? 'LinkedIn-token verlopen — koppel opnieuw.'
                : 'LinkedIn staat aan op Account maar is nog niet gekoppeld.',
        );
    }

    return ce_integration_status_entry('not_set', ce_integration_status_label('not_set'), $context);
}

/** @return array<string, mixed> */
function ce_integrations_ga4_status(PDO $pdo, string $clientId): array
{
    if (!ce_analytics_oauth_configured()) {
        return ce_integration_status_entry(
            'unavailable',
            ce_integration_status_label('unavailable'),
            'Meten · Via je Google-account',
            'Google OAuth ontbreekt in de serverconfiguratie.',
        );
    }

    $sources = ce_integrations_sources_for_client($pdo, $clientId, 'analytics');
    if ($sources === []) {
        return ce_integration_status_entry(
            'not_set',
            ce_integration_status_label('not_set'),
            'Meten · Via je Google-account',
            null,
            null,
            ['sourceCount' => 0, 'workingCount' => 0],
        );
    }

    $statuses = [];
    $working = 0;
    foreach ($sources as $row) {
        $hasOAuth = trim((string) ($row['analytics_oauth_refresh_token'] ?? '')) !== '';
        if (!$hasOAuth) {
            $statuses[] = 'action_required';
            continue;
        }
        if (!ce_analytics_verify_connection($pdo, (string) $row['id'])) {
            $statuses[] = 'action_required';
            continue;
        }
        $propertyId = ce_analytics_resolve_property_id($pdo, (string) $row['id']);
        if ($propertyId === null || $propertyId === '') {
            $statuses[] = 'action_required';
            continue;
        }
        $statuses[] = 'connected';
        $working++;
    }

    $worst = ce_integration_worst_status($statuses);
    $count = count($sources);
    $context = 'Meten · Via je Google-account';
    if ($count > 1) {
        $context .= ' · ' . $working . ' van ' . $count . ' sources leveren data';
    } elseif ($count === 1 && $worst === 'connected') {
        $email = trim((string) ($sources[0]['analytics_oauth_email'] ?? ''));
        if ($email !== '') {
            $context .= ' · ' . $email;
        }
    }

    $note = null;
    if ($worst === 'action_required') {
        $note = $working > 0 && $count > 1
            ? 'Niet alle Analytics-sources zijn compleet (OAuth of Property ID).'
            : 'Koppel Google Analytics en vul het Property ID in op Sources.';
    }

    return ce_integration_status_entry(
        $worst,
        ce_integration_status_label($worst),
        $context,
        $note,
        null,
        ['sourceCount' => $count, 'workingCount' => $working, 'primarySourceId' => (string) ($sources[0]['id'] ?? '')],
    );
}

/** @return array<string, mixed> */
function ce_integrations_calendar_status(PDO $pdo, string $clientId): array
{
    if (!ce_calendar_oauth_configured()) {
        return ce_integration_status_entry(
            'unavailable',
            ce_integration_status_label('unavailable'),
            'Input · Via je Google-account',
            'Google Calendar OAuth ontbreekt in de serverconfiguratie.',
        );
    }

    $sources = ce_integrations_sources_for_client($pdo, $clientId, 'calendar');
    if ($sources === []) {
        return ce_integration_status_entry(
            'not_set',
            ce_integration_status_label('not_set'),
            'Input · Via je Google-account',
            null,
            null,
            ['sourceCount' => 0],
        );
    }

    $statuses = [];
    $working = 0;
    foreach ($sources as $row) {
        $probe = ce_calendar_verify_source($pdo, (string) $row['id']);
        if ($probe['ok'] ?? false) {
            $statuses[] = 'connected';
            $working++;
        } else {
            $statuses[] = 'action_required';
        }
    }

    $worst = ce_integration_worst_status($statuses);
    $count = count($sources);
    $context = 'Input · Via je Google-account';
    if ($count > 1) {
        $context .= ' · ' . $working . ' van ' . $count . ' sources OK';
    }

    return ce_integration_status_entry(
        $worst,
        ce_integration_status_label($worst),
        $context,
        $worst === 'action_required' ? 'Koppel Google Calendar en kies een agenda op Sources.' : null,
        null,
        ['sourceCount' => $count, 'workingCount' => $working, 'primarySourceId' => (string) ($sources[0]['id'] ?? '')],
    );
}

/** @return array<string, mixed> */
function ce_integrations_apify_status(PDO $pdo, string $clientId): array
{
    $sources = array_values(array_filter(
        ce_integrations_sources_for_client($pdo, $clientId),
        static fn (array $row): bool => in_array((string) ($row['category'] ?? ''), ['scraper', 'linkedin'], true),
    ));

    $customTokenCount = 0;
    foreach ($sources as $row) {
        if (trim((string) ($row['scraper_api_token_ref'] ?? '')) !== '') {
            $customTokenCount++;
        }
    }

    $platformConfigured = ce_apify_platform_configured();
    if (!$platformConfigured && $customTokenCount === 0) {
        return ce_integration_status_entry(
            'unavailable',
            ce_integration_status_label('unavailable'),
            'Input · platformtoken',
            'Apify-token ontbreekt — stel APIFY_API_TOKEN in op de server.',
            null,
            ['sourceCount' => count($sources), 'customTokenCount' => 0],
        );
    }

    if ($sources === []) {
        $tokenOk = $platformConfigured && ce_apify_probe_token(ce_apify_platform_token());
        $status = $tokenOk ? 'connected' : 'action_required';

        return ce_integration_status_entry(
            $status,
            ce_integration_status_label($status),
            'Input · platformtoken · 0 scraper-sources',
            $tokenOk ? null : 'Apify-token ongeldig of API niet bereikbaar.',
            null,
            ['sourceCount' => 0, 'customTokenCount' => 0],
        );
    }

    $reachable = 0;
    $statuses = [];
    foreach ($sources as $row) {
        $probe = ce_probe_scraper_source($pdo, (string) $row['id']);
        if ($probe['reachable'] ?? false) {
            $statuses[] = 'connected';
            $reachable++;
        } else {
            $statuses[] = 'action_required';
        }
    }

    $worst = ce_integration_worst_status($statuses);
    $count = count($sources);
    $context = 'Input · platformtoken · ' . $count . ' scraper-source' . ($count === 1 ? '' : 's');
    if ($customTokenCount > 0) {
        $context .= ' · ' . $customTokenCount . ' eigen token' . ($customTokenCount === 1 ? '' : 's');
    }

    return ce_integration_status_entry(
        $worst,
        ce_integration_status_label($worst),
        $context,
        $worst === 'action_required' ? 'Eén of meer scraper-sources zijn incompleet of onbereikbaar.' : null,
        null,
        ['sourceCount' => $count, 'workingCount' => $reachable, 'customTokenCount' => $customTokenCount],
    );
}

/** @return array<string, mixed> */
function ce_integrations_source_category_status(PDO $pdo, string $clientId, string $category, string $groupLabel): array
{
    $sources = ce_integrations_sources_for_client($pdo, $clientId, $category);
    if ($sources === []) {
        return ce_integration_status_entry('not_set', ce_integration_status_label('not_set'), 'Input · ' . $groupLabel);
    }

    $statuses = [];
    $working = 0;
    foreach ($sources as $row) {
        $urlStatus = (string) ($row['url_status'] ?? '');
        if ($urlStatus === 'reachable') {
            $statuses[] = 'connected';
            $working++;
        } elseif ($urlStatus === 'unreachable') {
            $statuses[] = 'action_required';
        } else {
            $statuses[] = 'action_required';
        }
    }

    $worst = ce_integration_worst_status($statuses);
    $count = count($sources);
    $context = 'Input · ' . $groupLabel . ' · ' . $count . ' source' . ($count === 1 ? '' : 's');

    return ce_integration_status_entry(
        $worst,
        ce_integration_status_label($worst),
        $context,
        $worst === 'action_required' ? 'Controleer de source op Sources (Test-knop).' : null,
        null,
        ['sourceCount' => $count, 'workingCount' => $working],
    );
}

/** @return list<array{key: string, label: string, context: string, note: ?string}> */
function ce_platform_integration_issues_light(PDO $pdo, string $clientId, array $clientRow): array
{
    $issues = [];
    $labels = [
        'mailbox' => 'Mailbox',
        'calendar' => 'Google Calendar',
        'ga4' => 'Google Analytics (GA4)',
        'apify_scraper' => 'Apify-scraper',
        'website_cms' => 'Website',
        'linkedin_publish' => 'LinkedIn',
    ];

    $checks = [
        'mailbox' => ce_integrations_source_category_status($pdo, $clientId, 'mailbox', 'Mailbox'),
        'website_cms' => ce_integrations_website_cms_status_overview_light($pdo, $clientId, $clientRow),
    ];

    foreach ($checks as $key => $entry) {
        if (($entry['status'] ?? '') !== 'action_required') {
            continue;
        }
        $issues[] = [
            'key' => $key,
            'label' => $labels[$key] ?? $key,
            'context' => (string) ($entry['context'] ?? ''),
            'note' => isset($entry['note']) ? (string) $entry['note'] : null,
        ];
    }

    return $issues;
}

/** Config-check voor overview — geen live HTTP-test (detail-pagina doet live check). */
function ce_integrations_website_cms_status_overview_light(PDO $pdo, string $clientId, array $clientRow): array
{
    $selected = ce_client_website_cms_from_row($clientRow);
    $cmsLabel = match ($selected) {
        'wordpress' => 'WordPress',
        'joomla' => 'Joomla',
        'static' => 'Statische HTML (FTP)',
        default => 'Website',
    };

    if ($selected === '') {
        $hasPartial = trim((string) ($clientRow['wordpress_site_url'] ?? '')) !== ''
            || trim((string) ($clientRow['joomla_site_url'] ?? '')) !== ''
            || trim((string) ($clientRow['site_ftp_host'] ?? '')) !== '';
        if (!$hasPartial) {
            return ce_integration_status_entry('not_set', ce_integration_status_label('not_set'), 'Publiceren');
        }
    }

    if ($selected === 'wordpress') {
        $config = ce_wordpress_config_for_client($pdo, $clientId, false);
        if ($config['site_url'] === '' || $config['user'] === '' || $config['app_password'] === '') {
            return ce_integration_status_entry(
                'action_required',
                ce_integration_status_label('action_required'),
                'Publiceren · WordPress',
                'WordPress-config incompleet (site-URL, gebruiker of app-wachtwoord).',
            );
        }
    } elseif ($selected === 'joomla') {
        require_once __DIR__ . '/joomla-publish-repository.php';
        $config = ce_joomla_config_for_client($pdo, $clientId);
        if ($config['site_url'] === '' || $config['api_token'] === '' || ($config['category_id'] ?? 0) <= 0) {
            return ce_integration_status_entry(
                'action_required',
                ce_integration_status_label('action_required'),
                'Publiceren · Joomla',
                'Joomla-config incompleet (site-URL, API-token of categorie).',
            );
        }
    } elseif ($selected === 'static') {
        require_once __DIR__ . '/static-site-publish-repository.php';
        if (ce_static_site_ftp_config_for_client($pdo, $clientId) === null) {
            return ce_integration_status_entry(
                'action_required',
                ce_integration_status_label('action_required'),
                'Publiceren · Statische HTML (FTP)',
                'FTP-config incompleet.',
            );
        }
    }

    return ce_integration_status_entry(
        'not_set',
        ce_integration_status_label('not_set'),
        'Publiceren · ' . $cmsLabel,
    );
}

function ce_platform_mailbox_sources_ok(PDO $pdo, string $clientId): bool
{
    $sources = ce_integrations_sources_for_client($pdo, $clientId, 'mailbox');
    if ($sources === []) {
        return true;
    }

    foreach ($sources as $row) {
        if ((string) ($row['url_status'] ?? '') !== 'reachable') {
            return false;
        }
    }

    return true;
}

/** @return array{integrations: array<string, mixed>, summary: array<string, int>} */
function ce_integrations_status_for_client(PDO $pdo, string $clientId): array
{
    ce_ensure_client_publish_columns($pdo);

    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $clientRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$clientRow) {
        throw new RuntimeException('Tenant niet gevonden');
    }

    $publishMode = ce_client_publish_mode($pdo, $clientId);
    $extras = ce_publish_extra_destinations_from_json(
        (string) ($clientRow['publish_extra_destinations'] ?? '')
    );

    $integrations = [
        'calendar' => ce_integrations_calendar_status($pdo, $clientId),
        'mailbox' => ce_integrations_source_category_status($pdo, $clientId, 'mailbox', 'Mailbox'),
        'apify_scraper' => ce_integrations_apify_status($pdo, $clientId),
        'website_cms' => ce_integrations_website_cms_status($pdo, $clientId, $clientRow),
        'linkedin_publish' => ce_integrations_linkedin_publish_status($pdo, $clientId, $extras),
        'ga4' => ce_integrations_ga4_status($pdo, $clientId),
    ];

    $summary = ['active' => 0, 'actionRequired' => 0, 'notSet' => 0];
    foreach ($integrations as $entry) {
        $status = (string) ($entry['status'] ?? 'not_set');
        if ($status === 'connected') {
            $summary['active']++;
        } elseif ($status === 'action_required') {
            $summary['actionRequired']++;
        } elseif ($status === 'not_set') {
            $summary['notSet']++;
        }
    }

    return [
        'integrations' => $integrations,
        'summary' => $summary,
        'publishMode' => $publishMode,
    ];
}

function ce_google_oauth_disconnect_for_client(PDO $pdo, string $clientId): int
{
    ce_ensure_source_analytics_oauth_schema($pdo);
    ce_ensure_source_calendar_oauth_schema($pdo);

    if (!ce_table_exists($pdo, 'sources')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
        UPDATE sources
        SET analytics_oauth_refresh_token = NULL,
            analytics_oauth_email = NULL,
            calendar_oauth_refresh_token = NULL,
            calendar_oauth_email = NULL
        WHERE client_id = :client_id
        SQL
    );
    $stmt->execute(['client_id' => $clientId]);

    return $stmt->rowCount();
}
