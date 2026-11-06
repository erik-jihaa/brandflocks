<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/analytics-oauth-helper.php';

const CE_CALENDAR_OAUTH_SCOPE = 'https://www.googleapis.com/auth/calendar.readonly';

function ce_ensure_source_calendar_oauth_schema(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'sources')) {
        return;
    }

    foreach ([
        'calendar_oauth_refresh_token' => 'TEXT NULL',
        'calendar_oauth_email' => 'VARCHAR(255) NULL',
        'calendar_id' => 'VARCHAR(255) NULL',
        'calendar_name' => 'VARCHAR(255) NULL',
    ] as $column => $definition) {
        if (!ce_table_has_column($pdo, 'sources', $column)) {
            try {
                $pdo->exec("ALTER TABLE sources ADD COLUMN $column $definition");
            } catch (Throwable) {
                // Kolom bestaat al.
            }
        }
    }
}

/** @return array{clientId: string, clientSecret: string, redirectUri: string}|null */
function ce_calendar_oauth_config(): ?array
{
    $baseConfig = ce_analytics_oauth_config();
    if ($baseConfig === null) {
        return null;
    }

    // Zelfde redirect URI als GA4 — staat al in Google Cloud Console.
    return [
        'clientId' => $baseConfig['clientId'],
        'clientSecret' => $baseConfig['clientSecret'],
        'redirectUri' => $baseConfig['redirectUri'],
    ];
}

function ce_calendar_oauth_configured(): bool
{
    return ce_calendar_oauth_config() !== null;
}

/** @return array{authUrl: string, state: string}|null */
function ce_calendar_oauth_start(PDO $pdo, string $sourceId, string $returnTo = 'sources'): ?array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'source', $sourceId);

    ce_ensure_source_calendar_oauth_schema($pdo);

    $config = ce_calendar_oauth_config();
    if ($config === null) {
        return null;
    }

    if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
        return null;
    }

    require_once __DIR__ . '/client-context.php';
    ce_session_start();
    $clientId = ce_resolve_client_id($pdo);
    if ($clientId === null || $clientId === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM sources WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $sourceId, 'client_id' => $clientId]);
    if (!$stmt->fetch()) {
        return null;
    }

    $state = bin2hex(random_bytes(16));
    unset(
        $_SESSION['analytics_oauth_state'],
        $_SESSION['analytics_oauth_source_id'],
        $_SESSION['analytics_oauth_return_to'],
    );
    $_SESSION['google_oauth_flow'] = 'calendar';
    $_SESSION['google_oauth_client_id'] = $clientId;
    $_SESSION['google_oauth_return_to'] = in_array($returnTo, ['sources', 'integrations'], true)
        ? $returnTo
        : 'sources';
    $_SESSION['calendar_oauth_state'] = $state;
    $_SESSION['calendar_oauth_source_id'] = $sourceId;
    $_SESSION['calendar_oauth_return_to'] = $_SESSION['google_oauth_return_to'];

    $params = http_build_query([
        'client_id' => $config['clientId'],
        'redirect_uri' => $config['redirectUri'],
        'response_type' => 'code',
        'scope' => CE_CALENDAR_OAUTH_SCOPE,
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
    ]);

    return [
        'authUrl' => 'https://accounts.google.com/o/oauth2/v2/auth?' . $params,
        'state' => $state,
    ];
}

/** @return string source id */
function ce_calendar_oauth_handle_callback(PDO $pdo, string $code, string $state): string
{
    ce_ensure_source_calendar_oauth_schema($pdo);

    require_once __DIR__ . '/client-context.php';
    ce_session_start();
    $expectedState = (string) ($_SESSION['calendar_oauth_state'] ?? '');
    $sourceId = (string) ($_SESSION['calendar_oauth_source_id'] ?? '');

    unset($_SESSION['calendar_oauth_state'], $_SESSION['calendar_oauth_source_id']);

    if ($expectedState === '' || !hash_equals($expectedState, $state)) {
        throw new InvalidArgumentException('Ongeldige OAuth-state.');
    }

    if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
        throw new InvalidArgumentException('Bron ontbreekt in sessie.');
    }

    ce_require_resource_access($pdo, 'source', $sourceId);

    $config = ce_calendar_oauth_config();
    if ($config === null) {
        throw new RuntimeException('Google OAuth is niet geconfigureerd.');
    }

    $token = ce_analytics_oauth_exchange_code($config, $code);
    $refreshToken = trim((string) ($token['refresh_token'] ?? ''));
    if ($refreshToken === '') {
        throw new RuntimeException('Geen refresh token ontvangen — probeer opnieuw en geef toestemming.');
    }

    $email = ce_analytics_fetch_user_email((string) ($token['access_token'] ?? ''));

    $pdo->prepare(
        <<<'SQL'
        UPDATE sources
        SET calendar_oauth_refresh_token = :refresh,
            calendar_oauth_email = :email,
            url_status = 'reachable',
            last_checked_at = NOW()
        WHERE id = :id
        SQL
    )->execute([
        'refresh' => ce_credential_encrypt($refreshToken),
        'email' => $email,
        'id' => $sourceId,
    ]);

    $accessToken = trim((string) ($token['access_token'] ?? ''));
    if ($accessToken !== '') {
        $primary = ce_calendar_discover_primary_calendar($accessToken);
        if ($primary !== null) {
            ce_calendar_persist_selection($pdo, $sourceId, $primary['id'], $primary['name']);
        }
    }

    return $sourceId;
}

/** @return array{id: string, name: string}|null */
function ce_calendar_discover_primary_calendar(string $accessToken): ?array
{
    $calendars = ce_calendar_fetch_calendar_list($accessToken);
    if ($calendars === []) {
        return null;
    }

    foreach ($calendars as $calendar) {
        if (!empty($calendar['primary'])) {
            return ['id' => (string) $calendar['id'], 'name' => (string) $calendar['name']];
        }
    }

    return ['id' => (string) $calendars[0]['id'], 'name' => (string) $calendars[0]['name']];
}

/** @return array{items: list<array{id: string, name: string, primary: bool}>, error: string} */
function ce_calendar_fetch_calendar_list_result(string $accessToken): array
{
    $empty = ['items' => [], 'error' => ''];

    if ($accessToken === '') {
        return ['items' => [], 'error' => 'Geen access token.'];
    }

    $response = ce_analytics_http_get(
        'https://www.googleapis.com/calendar/v3/users/me/calendarList?maxResults=100',
        ['Authorization: Bearer ' . $accessToken]
    );

    if ($response['status'] < 200 || $response['status'] >= 300) {
        $json = json_decode($response['body'], true);
        $detail = '';
        if (is_array($json)) {
            $detail = trim((string) ($json['error']['message'] ?? $json['error_description'] ?? ''));
        }
        if ($response['status'] === 403 && ($detail === '' || stripos($detail, 'insufficient') !== false)) {
            $detail = 'Schakel Google Calendar API in via Google Cloud Console → APIs & Services → Library, en koppel daarna opnieuw.';
        }
        if ($detail === '') {
            $detail = 'HTTP ' . $response['status'];
        }

        return ['items' => [], 'error' => 'Agenda\'s ophalen mislukt: ' . $detail];
    }

    $json = json_decode($response['body'], true);
    if (!is_array($json)) {
        return ['items' => [], 'error' => 'Ongeldig antwoord van Google Calendar.'];
    }

    $items = [];
    foreach ($json['items'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $items[] = [
            'id' => $id,
            'name' => trim((string) ($row['summary'] ?? $id)),
            'primary' => (bool) ($row['primary'] ?? false),
        ];
    }

    if ($items === []) {
        return ['items' => [], 'error' => 'Geen agenda\'s gevonden op dit Google-account.'];
    }

    return ['items' => $items, 'error' => ''];
}

/** @return list<array{id: string, name: string, primary: bool}> */
function ce_calendar_fetch_calendar_list(string $accessToken): array
{
    return ce_calendar_fetch_calendar_list_result($accessToken)['items'];
}

/** @return array{items: list<array{id: string, name: string, primary: bool}>, error: string} */
function ce_calendar_list_result_for_source(PDO $pdo, string $sourceId): array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'source', $sourceId);

    $token = ce_calendar_access_token_result_for_source($pdo, $sourceId);
    if (!empty($token['error'])) {
        return ['items' => [], 'error' => (string) $token['error']];
    }
    if (empty($token['access_token'])) {
        return ['items' => [], 'error' => 'Geen geldig Google-token — koppel opnieuw via Inloggen met Google.'];
    }

    return ce_calendar_fetch_calendar_list_result((string) $token['access_token']);
}

function ce_calendar_persist_selection(PDO $pdo, string $sourceId, string $calendarId, string $calendarName): void
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'source', $sourceId);

    ce_ensure_source_calendar_oauth_schema($pdo);

    $pdo->prepare(
        'UPDATE sources SET calendar_id = :calendar_id, calendar_name = :calendar_name WHERE id = :id'
    )->execute([
        'calendar_id' => $calendarId,
        'calendar_name' => $calendarName,
        'id' => $sourceId,
    ]);
}

function ce_calendar_get_refresh_token(PDO $pdo, string $sourceId): ?string
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'source', $sourceId);

    ce_ensure_source_calendar_oauth_schema($pdo);

    if (!ce_table_has_column($pdo, 'sources', 'calendar_oauth_refresh_token')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT calendar_oauth_refresh_token FROM sources WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $token = trim((string) ($row['calendar_oauth_refresh_token'] ?? ''));
    if ($token === '') {
        return null;
    }

    try {
        return ce_credential_decrypt($token);
    } catch (Throwable) {
        return null;
    }
}

/** @return array{access_token?: string, error?: string} */
function ce_calendar_access_token_result_for_source(PDO $pdo, string $sourceId): array
{
    $refreshToken = ce_calendar_get_refresh_token($pdo, $sourceId);
    if ($refreshToken === null) {
        $stmt = $pdo->prepare('SELECT calendar_oauth_refresh_token FROM sources WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $sourceId]);
        $row = $stmt->fetch();
        $stored = trim((string) ($row['calendar_oauth_refresh_token'] ?? ''));
        if ($stored !== '') {
            return ['error' => 'Opgeslagen Google-token kon niet worden gelezen — koppel opnieuw via Inloggen met Google.'];
        }

        return ['error' => 'Koppel eerst je Google-account.'];
    }

    return ce_analytics_refresh_access_token_result($refreshToken);
}

/** @return array{access_token: string}|null */
function ce_calendar_access_token_for_source(PDO $pdo, string $sourceId): ?array
{
    $result = ce_calendar_access_token_result_for_source($pdo, $sourceId);
    if (!empty($result['error']) || empty($result['access_token'])) {
        return null;
    }

    return ['access_token' => (string) $result['access_token']];
}

/** @return list<array{id: string, name: string, primary: bool}> */
function ce_calendar_list_for_source(PDO $pdo, string $sourceId): array
{
    return ce_calendar_list_result_for_source($pdo, $sourceId)['items'];
}

/** @return array{ok: bool, message: string, eventCount: int} */
function ce_calendar_verify_source(PDO $pdo, string $sourceId): array
{
    ce_ensure_source_calendar_oauth_schema($pdo);

    $stmt = $pdo->prepare(
        'SELECT calendar_id, calendar_name FROM sources WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'message' => 'Bron niet gevonden.', 'eventCount' => 0];
    }

    $refreshToken = ce_calendar_get_refresh_token($pdo, $sourceId);
    if ($refreshToken === null) {
        return ['ok' => false, 'message' => 'Koppel eerst je Google-account.', 'eventCount' => 0];
    }

    $calendarId = trim((string) ($row['calendar_id'] ?? ''));
    if ($calendarId === '') {
        return ['ok' => false, 'message' => 'Kies een agenda.', 'eventCount' => 0];
    }

    $token = ce_analytics_refresh_access_token($refreshToken);
    if ($token === null || empty($token['access_token'])) {
        return ['ok' => false, 'message' => 'Google-token verlopen — koppel opnieuw.', 'eventCount' => 0];
    }

    $events = ce_calendar_fetch_upcoming_events((string) $token['access_token'], $calendarId, 5);
    if ($events === null) {
        return ['ok' => false, 'message' => 'Agenda kon niet worden gelezen.', 'eventCount' => 0];
    }

    $count = count($events);
    $calendarName = trim((string) ($row['calendar_name'] ?? 'agenda'));
    $message = $count > 0
        ? "{$count} aankomende event(s) in {$calendarName}."
        : "Verbinding OK — geen events in de komende 90 dagen in {$calendarName}.";

    return ['ok' => true, 'message' => $message, 'eventCount' => $count];
}

/** @return list<array{summary: string, start: string}>|null */
function ce_calendar_fetch_upcoming_events(string $accessToken, string $calendarId, int $limit = 5): ?array
{
    $timeMin = (new DateTimeImmutable('now'))->format(DateTimeInterface::RFC3339);
    $timeMax = (new DateTimeImmutable('+90 days'))->format(DateTimeInterface::RFC3339);
    $query = http_build_query([
        'maxResults' => max(1, min($limit, 20)),
        'singleEvents' => 'true',
        'orderBy' => 'startTime',
        'timeMin' => $timeMin,
        'timeMax' => $timeMax,
    ]);

    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId) . '/events?' . $query;
    $response = ce_analytics_http_get($url, ['Authorization: Bearer ' . $accessToken]);

    if ($response['status'] < 200 || $response['status'] >= 300) {
        return null;
    }

    $json = json_decode($response['body'], true);
    if (!is_array($json)) {
        return null;
    }

    $events = [];
    foreach ($json['items'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $start = (string) (($item['start']['dateTime'] ?? $item['start']['date'] ?? ''));
        $events[] = [
            'summary' => trim((string) ($item['summary'] ?? '(zonder titel)')),
            'start' => $start,
        ];
    }

    return $events;
}

function ce_calendar_oauth_frontend_redirect(string $sourceId, string $query = ''): string
{
    return ce_google_oauth_frontend_redirect($sourceId, $query);
}
