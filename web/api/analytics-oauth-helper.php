<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/credential-crypto.php';

/** GA4 — Google Analytics Data API via OAuth (geen API-key). */
const CE_ANALYTICS_OAUTH_SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

function ce_ensure_source_analytics_oauth_schema(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'sources')) {
        return;
    }

    $columns = [
        'analytics_oauth_refresh_token' => 'TEXT NULL',
        'analytics_oauth_email' => 'VARCHAR(255) NULL',
        'analytics_property_id' => 'VARCHAR(100) NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!ce_table_has_column($pdo, 'sources', $column)) {
            try {
                $pdo->exec("ALTER TABLE sources ADD COLUMN $column $definition");
            } catch (Throwable) {
                // Kolom bestaat al of ALTER niet toegestaan.
            }
        }
    }
}

/** @return array{clientId: string, clientSecret: string, redirectUri: string}|null */
function ce_analytics_oauth_config(): ?array
{
    ce_load_env();

    $clientId = trim((string) (getenv('GOOGLE_CLIENT_ID') ?: ''));
    $clientSecret = trim((string) (getenv('GOOGLE_CLIENT_SECRET') ?: ''));

    if ($clientId === '' || $clientSecret === '') {
        $local = dirname(__DIR__) . '/includes/ce.local.php';
        if (is_readable($local)) {
            $config = require $local;
            if (is_array($config)) {
                if ($clientId === '') {
                    $clientId = trim((string) ($config['google_client_id'] ?? ''));
                }
                if ($clientSecret === '') {
                    $clientSecret = trim((string) ($config['google_client_secret'] ?? ''));
                }
            }
        }
    }

    if ($clientId === '' || $clientSecret === '') {
        return null;
    }

    $base = rtrim(trim((string) (getenv('CE_PUBLIC_BASE_URL') ?: '')), '/');
    if ($base === '') {
        $local = dirname(__DIR__) . '/includes/ce.local.php';
        if (is_readable($local)) {
            $config = require $local;
            if (is_array($config)) {
                $base = rtrim(trim((string) ($config['public_base_url'] ?? '')), '/');
            }
        }
    }
    if ($base === '') {
        return null;
    }

    return [
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
        'redirectUri' => $base . '/api/sources/analytics/oauth/callback',
    ];
}

function ce_analytics_oauth_configured(): bool
{
    return ce_analytics_oauth_config() !== null;
}

/** @return array{authUrl: string, state: string}|null */
function ce_analytics_oauth_start(PDO $pdo, string $sourceId, string $returnTo = 'sources'): ?array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'source', $sourceId);

    ce_ensure_source_analytics_oauth_schema($pdo);

    $config = ce_analytics_oauth_config();
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
        $_SESSION['calendar_oauth_state'],
        $_SESSION['calendar_oauth_source_id'],
        $_SESSION['calendar_oauth_return_to'],
    );
    $_SESSION['google_oauth_flow'] = 'analytics';
    $_SESSION['google_oauth_client_id'] = $clientId;
    $_SESSION['google_oauth_return_to'] = in_array($returnTo, ['sources', 'integrations'], true)
        ? $returnTo
        : 'sources';
    $_SESSION['analytics_oauth_state'] = $state;
    $_SESSION['analytics_oauth_source_id'] = $sourceId;
    $_SESSION['analytics_oauth_return_to'] = $_SESSION['google_oauth_return_to'];

    $params = http_build_query([
        'client_id' => $config['clientId'],
        'redirect_uri' => $config['redirectUri'],
        'response_type' => 'code',
        'scope' => CE_ANALYTICS_OAUTH_SCOPE,
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
function ce_analytics_oauth_handle_callback(PDO $pdo, string $code, string $state): string
{
    ce_ensure_source_analytics_oauth_schema($pdo);

    require_once __DIR__ . '/client-context.php';
    ce_session_start();
    $expectedState = (string) ($_SESSION['analytics_oauth_state'] ?? '');
    $sourceId = (string) ($_SESSION['analytics_oauth_source_id'] ?? '');

    unset($_SESSION['analytics_oauth_state'], $_SESSION['analytics_oauth_source_id']);

    if ($expectedState === '' || !hash_equals($expectedState, $state)) {
        throw new InvalidArgumentException('Ongeldige OAuth-state.');
    }

    if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
        throw new InvalidArgumentException('Bron ontbreekt in sessie.');
    }

    ce_require_resource_access($pdo, 'source', $sourceId);

    $config = ce_analytics_oauth_config();
    if ($config === null) {
        throw new RuntimeException('Google OAuth is niet geconfigureerd.');
    }

    $token = ce_analytics_oauth_exchange_code($config, $code);
    $refreshToken = trim((string) ($token['refresh_token'] ?? ''));
    if ($refreshToken === '') {
        throw new RuntimeException('Geen refresh token ontvangen — probeer opnieuw en geef toestemming.');
    }

    $email = ce_analytics_fetch_user_email($token['access_token'] ?? '');

    $pdo->prepare(
        <<<'SQL'
        UPDATE sources
        SET analytics_oauth_refresh_token = :refresh,
            analytics_oauth_email = :email,
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
        $propertyId = ce_analytics_discover_first_property_id($accessToken);
        if ($propertyId !== null) {
            ce_analytics_persist_property_id($pdo, $sourceId, $propertyId);
        }
    }

    return $sourceId;
}

/** @param array{clientId: string, clientSecret: string, redirectUri: string} $config
 *  @return array<string, mixed>
 */
function ce_analytics_oauth_exchange_code(array $config, string $code): array
{
    $body = http_build_query([
        'code' => $code,
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
        'redirect_uri' => $config['redirectUri'],
        'grant_type' => 'authorization_code',
    ]);

    $response = ce_analytics_http_post('https://oauth2.googleapis.com/token', $body, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    if ($response['status'] < 200 || $response['status'] >= 300) {
        $json = json_decode($response['body'], true);
        $detail = '';
        if (is_array($json)) {
            $detail = trim((string) ($json['error_description'] ?? $json['error'] ?? ''));
        }
        $message = 'Token exchange mislukt';
        if ($detail !== '') {
            $message .= ': ' . $detail;
        }
        throw new RuntimeException($message);
    }

    $json = json_decode($response['body'], true);
    if (!is_array($json)) {
        throw new RuntimeException('Ongeldig token-antwoord.');
    }

    return $json;
}

/** @return array{access_token?: string, expires_in?: int, error?: string} */
function ce_analytics_refresh_access_token_result(string $refreshToken): array
{
    $config = ce_analytics_oauth_config();
    if ($config === null || $refreshToken === '') {
        return ['error' => 'Google OAuth is niet geconfigureerd.'];
    }

    $body = http_build_query([
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);

    $response = ce_analytics_http_post('https://oauth2.googleapis.com/token', $body, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    if ($response['status'] < 200 || $response['status'] >= 300) {
        $json = json_decode($response['body'], true);
        $detail = '';
        if (is_array($json)) {
            $detail = trim((string) ($json['error_description'] ?? $json['error'] ?? ''));
        }
        if ($detail === '') {
            $detail = 'HTTP ' . $response['status'];
        }

        return ['error' => 'Google-token vernieuwen mislukt: ' . $detail];
    }

    $json = json_decode($response['body'], true);
    if (!is_array($json) || empty($json['access_token'])) {
        return ['error' => 'Google-token vernieuwen mislukt: ongeldig antwoord.'];
    }

    return $json;
}

/** @return array{access_token: string, expires_in: int}|null */
function ce_analytics_refresh_access_token(string $refreshToken): ?array
{
    $result = ce_analytics_refresh_access_token_result($refreshToken);
    if (!empty($result['error']) || empty($result['access_token'])) {
        return null;
    }

    return $result;
}

function ce_analytics_fetch_user_email(string $accessToken): ?string
{
    if ($accessToken === '') {
        return null;
    }

    $response = ce_analytics_http_get(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        ['Authorization: Bearer ' . $accessToken]
    );

    if ($response['status'] !== 200) {
        return null;
    }

    $json = json_decode($response['body'], true);
    if (!is_array($json)) {
        return null;
    }

    $email = trim((string) ($json['email'] ?? ''));

    return $email !== '' ? $email : null;
}

function ce_analytics_get_refresh_token(PDO $pdo, string $sourceId): ?string
{
    ce_ensure_source_analytics_oauth_schema($pdo);

    if (!ce_table_has_column($pdo, 'sources', 'analytics_oauth_refresh_token')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT analytics_oauth_refresh_token FROM sources WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $token = trim((string) ($row['analytics_oauth_refresh_token'] ?? ''));
    if ($token === '') {
        return null;
    }

    $decrypted = ce_credential_try_decrypt($token);
    if ($decrypted === null || $decrypted === '') {
        return null;
    }

    return $decrypted;
}

function ce_analytics_normalize_property_id(?string $raw): ?string
{
    $propertyId = trim((string) ($raw ?? ''));
    if ($propertyId === '') {
        return null;
    }

    if (preg_match('#properties/(\d+)#', $propertyId, $matches)) {
        return $matches[1];
    }

    return ctype_digit($propertyId) ? $propertyId : null;
}

function ce_analytics_discover_first_property_id(string $accessToken): ?string
{
    if ($accessToken === '') {
        return null;
    }

    $response = ce_analytics_http_get(
        'https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=200',
        ['Authorization: Bearer ' . $accessToken]
    );

    if ($response['status'] < 200 || $response['status'] >= 300) {
        return null;
    }

    $json = json_decode($response['body'], true);
    if (!is_array($json)) {
        return null;
    }

    foreach ($json['accountSummaries'] ?? [] as $account) {
        if (!is_array($account)) {
            continue;
        }
        foreach ($account['propertySummaries'] ?? [] as $property) {
            if (!is_array($property)) {
                continue;
            }
            $normalized = ce_analytics_normalize_property_id($property['property'] ?? null);
            if ($normalized !== null) {
                return $normalized;
            }
        }
    }

    return null;
}

function ce_analytics_persist_property_id(PDO $pdo, string $sourceId, string $propertyId): void
{
    ce_ensure_source_analytics_oauth_schema($pdo);
    if (!ce_table_has_column($pdo, 'sources', 'analytics_property_id')) {
        return;
    }

    $propertyId = ce_analytics_normalize_property_id($propertyId);
    if ($propertyId === null) {
        return;
    }

    $pdo->prepare(
        'UPDATE sources SET analytics_property_id = :property_id WHERE id = :id'
    )->execute([
        'property_id' => $propertyId,
        'id' => $sourceId,
    ]);
}

function ce_analytics_resolve_property_id(PDO $pdo, string $sourceId): ?string
{
    ce_ensure_source_analytics_oauth_schema($pdo);

    $sql = 'SELECT analytics_property_id';
    if (ce_table_has_column($pdo, 'sources', 'analytics_datastream_id')) {
        $sql .= ', analytics_datastream_id';
    }
    $sql .= ' FROM sources WHERE id = :id LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $propertyId = ce_analytics_normalize_property_id($row['analytics_property_id'] ?? null);
    if ($propertyId !== null) {
        return $propertyId;
    }

    if (isset($row['analytics_datastream_id'])) {
        $propertyId = ce_analytics_normalize_property_id((string) $row['analytics_datastream_id']);
        if ($propertyId !== null) {
            ce_analytics_persist_property_id($pdo, $sourceId, $propertyId);

            return $propertyId;
        }
    }

    $refreshToken = ce_analytics_get_refresh_token($pdo, $sourceId);
    if ($refreshToken === null) {
        return null;
    }

    $token = ce_analytics_refresh_access_token($refreshToken);
    if ($token === null || empty($token['access_token'])) {
        return null;
    }

    $discovered = ce_analytics_discover_first_property_id((string) $token['access_token']);
    if ($discovered !== null) {
        ce_analytics_persist_property_id($pdo, $sourceId, $discovered);
    }

    return $discovered;
}

/** @return array{id: string, propertyId: string}|null */
function ce_analytics_oauth_source_for_client(PDO $pdo, ?string $clientId): ?array
{
    if ($clientId === null || !ce_table_exists($pdo, 'sources')) {
        return null;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT id
FROM sources
WHERE client_id = :client_id AND category = 'analytics'
  AND analytics_oauth_refresh_token IS NOT NULL
  AND analytics_oauth_refresh_token != ''
ORDER BY created_at ASC
LIMIT 1
SQL
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $sourceId = (string) ($row['id'] ?? '');
    $propertyId = ce_analytics_resolve_property_id($pdo, $sourceId);
    if ($propertyId === null) {
        return null;
    }

    return [
        'id' => $sourceId,
        'propertyId' => $propertyId,
    ];
}

function ce_analytics_oauth_connected_for_client(PDO $pdo, ?string $clientId): bool
{
    if ($clientId === null || !ce_table_exists($pdo, 'sources')) {
        return false;
    }

    if (!ce_table_has_column($pdo, 'sources', 'analytics_oauth_refresh_token')) {
        return false;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT 1
FROM sources
WHERE client_id = :client_id AND category = 'analytics'
  AND analytics_oauth_refresh_token IS NOT NULL
  AND analytics_oauth_refresh_token != ''
LIMIT 1
SQL
    );
    $stmt->execute(['client_id' => $clientId]);

    return (bool) $stmt->fetchColumn();
}

function ce_analytics_verify_connection(PDO $pdo, string $sourceId): bool
{
    $refreshToken = ce_analytics_get_refresh_token($pdo, $sourceId);
    if ($refreshToken === null) {
        return false;
    }

    $token = ce_analytics_refresh_access_token($refreshToken);
    if ($token === null || empty($token['access_token'])) {
        return false;
    }

    $response = ce_analytics_http_get(
        'https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=1',
        ['Authorization: Bearer ' . $token['access_token']]
    );

    return $response['status'] >= 200 && $response['status'] < 300;
}

/** @param list<string> $headers */
function ce_analytics_http_post(string $url, string $body, array $headers = []): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    return ['status' => $status, 'body' => $result !== false ? (string) $result : ''];
}

/** @param list<string> $headers */
function ce_analytics_http_get(string $url, array $headers = []): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    return ['status' => $status, 'body' => $result !== false ? (string) $result : ''];
}

function ce_google_oauth_restore_client_from_session(?PDO $pdo = null): ?string
{
    require_once __DIR__ . '/client-context.php';
    ce_session_start();
    $clientId = trim((string) ($_SESSION['google_oauth_client_id'] ?? ''));
    if ($clientId !== '') {
        ce_set_active_client_id($clientId);

        return $clientId;
    }

    return $pdo !== null ? ce_resolve_client_id($pdo) : null;
}

function ce_google_oauth_frontend_redirect(?string $sourceId, string $query = ''): string
{
    require_once __DIR__ . '/client-context.php';
    ce_session_start();
    $returnTo = (string) ($_SESSION['google_oauth_return_to']
        ?? $_SESSION['analytics_oauth_return_to']
        ?? $_SESSION['calendar_oauth_return_to']
        ?? 'sources');
    unset(
        $_SESSION['google_oauth_return_to'],
        $_SESSION['analytics_oauth_return_to'],
        $_SESSION['calendar_oauth_return_to'],
    );

    $base = rtrim(trim((string) (getenv('CE_PUBLIC_BASE_URL') ?: '')), '/');
    if ($returnTo === 'integrations') {
        $path = '/integrations';
    } elseif (
        $sourceId !== null
        && $sourceId !== ''
        && preg_match('/^[0-9a-f-]{36}$/i', $sourceId)
        && strcasecmp($sourceId, '00000000-0000-0000-0000-000000000000') !== 0
    ) {
        $path = '/sources/' . rawurlencode($sourceId) . '/edit';
    } else {
        $path = '/sources';
    }
    if ($query !== '') {
        $path .= '?' . ltrim($query, '?');
    }

    return $base !== '' ? $base . $path : $path;
}

function ce_analytics_oauth_frontend_redirect(string $sourceId, string $query = ''): string
{
    return ce_google_oauth_frontend_redirect($sourceId, $query);
}
