<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/event-log-repository.php';

/**
 * LinkedIn "Sign In with LinkedIn using OpenID Connect" + "Share on LinkedIn" (w_member_social).
 * Post op naam van het LinkedIn-profiel dat koppelt via Account. w_organization_social is
 * toegevoegd zodat — als de LinkedIn-app toegang heeft (Community Management API/Advertising
 * API, na LinkedIn-goedkeuring) — ook namens een bedrijfspagina gepost kan worden. Zonder die
 * productgoedkeuring negeert LinkedIn deze scope stilzwijgend en blijft alleen het profiel werken.
 *
 * Community Management-only apps: geen openid/profile — zet LINKEDIN_OAUTH_SCOPES in .env
 * (bijv. r_basicprofile w_member_social w_organization_social).
 */
const CE_LINKEDIN_OAUTH_SCOPE = 'openid profile w_member_social w_organization_social';
const CE_LINKEDIN_API_VERSION = '202401';

/**
 * LinkedIn versioned APIs blijven maar ~12 maanden actief. In plaats van één vaste
 * constante proberen we een reeks recente maandversies (huidige maand terug tot 12
 * maanden geleden) tot LinkedIn een niet-426 antwoord geeft.
 *
 * @return list<string>
 */
function ce_linkedin_api_versions_to_try(): array
{
    $versions = [];
    $timestamp = time();
    for ($i = 0; $i <= 13; $i++) {
        $versions[] = gmdate('Ym', strtotime("-{$i} months", $timestamp));
    }

    return $versions;
}

function ce_ensure_client_linkedin_oauth_schema(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'clients')) {
        return;
    }

    $columns = [
        'linkedin_oauth_access_token' => 'TEXT NULL',
        'linkedin_oauth_refresh_token' => 'TEXT NULL',
        'linkedin_oauth_access_expires_at' => 'DATETIME NULL',
        'linkedin_oauth_refresh_expires_at' => 'DATETIME NULL',
        'linkedin_member_urn' => 'VARCHAR(120) NULL',
        'linkedin_member_name' => 'VARCHAR(255) NULL',
        'linkedin_oauth_scopes' => 'TEXT NULL',
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

/** @return array{clientId: string, clientSecret: string, redirectUri: string}|null */
function ce_linkedin_oauth_config(): ?array
{
    ce_load_env();

    $clientId = trim((string) (getenv('LINKEDIN_CLIENT_ID') ?: ''));
    $clientSecret = trim((string) (getenv('LINKEDIN_CLIENT_SECRET') ?: ''));

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (($clientId === '' || $clientSecret === '') && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            if ($clientId === '') {
                $clientId = trim((string) ($config['linkedin_client_id'] ?? ''));
            }
            if ($clientSecret === '') {
                $clientSecret = trim((string) ($config['linkedin_client_secret'] ?? ''));
            }
        }
    }

    if ($clientId === '' || $clientSecret === '') {
        return null;
    }

    $base = rtrim(trim((string) (getenv('CE_PUBLIC_BASE_URL') ?: '')), '/');
    if ($base === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $base = rtrim(trim((string) ($config['public_base_url'] ?? '')), '/');
        }
    }
    if ($base === '') {
        return null;
    }

    return [
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
        'redirectUri' => $base . '/api/account/linkedin/oauth/callback',
    ];
}

function ce_linkedin_oauth_configured(): bool
{
    return ce_linkedin_oauth_config() !== null;
}

function ce_linkedin_oauth_scope_string(): string
{
    ce_load_env();
    $fromEnv = trim((string) (getenv('LINKEDIN_OAUTH_SCOPES') ?: ''));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $fromLocal = trim((string) ($config['linkedin_oauth_scopes'] ?? ''));
            if ($fromLocal !== '') {
                return $fromLocal;
            }
        }
    }

    return CE_LINKEDIN_OAUTH_SCOPE;
}

function ce_linkedin_oauth_scopes_include_organization(?string $scopeString): bool
{
    $scopeString = trim((string) $scopeString);
    if ($scopeString === '') {
        return false;
    }

    return preg_match('/\bw_organization_social\b/', $scopeString) === 1;
}

function ce_linkedin_store_oauth_scopes(PDO $pdo, string $clientId, ?string $scopeString): void
{
    ce_ensure_client_linkedin_oauth_schema($pdo);
    $pdo->prepare('UPDATE clients SET linkedin_oauth_scopes = :scopes WHERE id = :id')->execute([
        'scopes' => trim((string) $scopeString) !== '' ? trim((string) $scopeString) : null,
        'id' => $clientId,
    ]);
}

/** @return array{authUrl: string}|null */
function ce_linkedin_oauth_start(PDO $pdo, string $clientId): ?array
{
    ce_ensure_client_linkedin_oauth_schema($pdo);

    $config = ce_linkedin_oauth_config();
    if ($config === null) {
        return null;
    }

    if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    if (!$stmt->fetch()) {
        return null;
    }

    ce_session_start();
    $state = bin2hex(random_bytes(16));
    $_SESSION['linkedin_oauth_state'] = $state;
    $_SESSION['linkedin_oauth_client_id'] = $clientId;

    $params = http_build_query([
        'response_type' => 'code',
        'client_id' => $config['clientId'],
        'redirect_uri' => $config['redirectUri'],
        'state' => $state,
        'scope' => ce_linkedin_oauth_scope_string(),
    ]);

    return ['authUrl' => 'https://www.linkedin.com/oauth/v2/authorization?' . $params];
}

/** @return string clientId */
function ce_linkedin_oauth_handle_callback(PDO $pdo, string $code, string $state): string
{
    ce_ensure_client_linkedin_oauth_schema($pdo);

    ce_session_start();
    $expectedState = (string) ($_SESSION['linkedin_oauth_state'] ?? '');
    $clientId = (string) ($_SESSION['linkedin_oauth_client_id'] ?? '');
    unset($_SESSION['linkedin_oauth_state'], $_SESSION['linkedin_oauth_client_id']);

    if ($expectedState === '' || !hash_equals($expectedState, $state)) {
        throw new InvalidArgumentException('Ongeldige OAuth-state.');
    }
    if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        throw new InvalidArgumentException('Client ontbreekt in sessie.');
    }

    $config = ce_linkedin_oauth_config();
    if ($config === null) {
        throw new RuntimeException('LinkedIn OAuth is niet geconfigureerd.');
    }

    $token = ce_linkedin_oauth_exchange_code($config, $code);
    $accessToken = trim((string) ($token['access_token'] ?? ''));
    if ($accessToken === '') {
        throw new RuntimeException('Geen access token ontvangen.');
    }

    $expiresIn = (int) ($token['expires_in'] ?? 0);
    $refreshToken = trim((string) ($token['refresh_token'] ?? ''));
    $refreshExpiresIn = (int) ($token['refresh_token_expires_in'] ?? 0);

    $scopeString = trim((string) ($token['scope'] ?? ''));

    $userinfo = ce_linkedin_fetch_member_identity($accessToken, $scopeString);
    $memberId = trim((string) ($userinfo['memberId'] ?? ''));
    if ($memberId === '') {
        throw new RuntimeException('LinkedIn-profiel kon niet worden opgehaald.');
    }
    $memberName = trim((string) ($userinfo['memberName'] ?? ''));

    $sets = [
        'linkedin_oauth_access_token = :access_token',
        'linkedin_oauth_access_expires_at = :access_expires',
        'linkedin_member_urn = :member_urn',
        'linkedin_member_name = :member_name',
    ];
    $params = [
        'id' => $clientId,
        'access_token' => ce_credential_encrypt($accessToken),
        'access_expires' => $expiresIn > 0 ? gmdate('Y-m-d H:i:s', time() + $expiresIn - 60) : null,
        'member_urn' => 'urn:li:person:' . $memberId,
        'member_name' => $memberName !== '' ? $memberName : null,
    ];
    if ($refreshToken !== '') {
        $sets[] = 'linkedin_oauth_refresh_token = :refresh_token';
        $sets[] = 'linkedin_oauth_refresh_expires_at = :refresh_expires';
        $params['refresh_token'] = ce_credential_encrypt($refreshToken);
        $params['refresh_expires'] = $refreshExpiresIn > 0 ? gmdate('Y-m-d H:i:s', time() + $refreshExpiresIn - 60) : null;
    }

    $pdo->prepare('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

    ce_linkedin_store_oauth_scopes($pdo, $clientId, trim((string) ($token['scope'] ?? '')));

    return $clientId;
}

/** @param array{clientId: string, clientSecret: string, redirectUri: string} $config @return array<string, mixed> */
function ce_linkedin_oauth_exchange_code(array $config, string $code): array
{
    $body = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $config['redirectUri'],
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
    ]);

    $response = ce_linkedin_http_post(
        'https://www.linkedin.com/oauth/v2/accessToken',
        $body,
        ['Content-Type: application/x-www-form-urlencoded']
    );

    $json = json_decode($response['body'], true);
    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($json)) {
        $detail = is_array($json) ? trim((string) ($json['error_description'] ?? $json['error'] ?? '')) : '';
        throw new RuntimeException('Token exchange mislukt' . ($detail !== '' ? ': ' . $detail : ''));
    }

    return $json;
}

/** @return array{memberId: string, memberName: string} */
function ce_linkedin_fetch_member_identity(string $accessToken, string $scopeString = ''): array
{
    $scopeString = trim($scopeString);
    $useOpenId = $scopeString === '' || preg_match('/\b(openid|profile)\b/', $scopeString) === 1;

    if ($useOpenId) {
        $userinfo = ce_linkedin_fetch_userinfo($accessToken);
        $memberId = trim((string) ($userinfo['sub'] ?? ''));
        if ($memberId !== '') {
            return [
                'memberId' => $memberId,
                'memberName' => trim((string) ($userinfo['name'] ?? '')),
            ];
        }
    }

    $response = ce_linkedin_http_get(
        'https://api.linkedin.com/v2/me?projection=(id,localizedFirstName,localizedLastName)',
        [
            'Authorization: Bearer ' . $accessToken,
            'X-Restli-Protocol-Version: 2.0.0',
        ],
    );
    $json = json_decode($response['body'], true);
    if ($response['status'] >= 200 && $response['status'] < 300 && is_array($json)) {
        $memberId = trim((string) ($json['id'] ?? ''));
        if ($memberId !== '') {
            $first = trim((string) ($json['localizedFirstName'] ?? ''));
            $last = trim((string) ($json['localizedLastName'] ?? ''));
            $name = trim($first . ' ' . $last);

            return ['memberId' => $memberId, 'memberName' => $name];
        }
    }

    throw new RuntimeException('LinkedIn-profiel kon niet worden opgehaald.');
}

/** @return array<string, mixed> */
function ce_linkedin_fetch_userinfo(string $accessToken): array
{
    $response = ce_linkedin_http_get(
        'https://api.linkedin.com/v2/userinfo',
        ['Authorization: Bearer ' . $accessToken]
    );

    $json = json_decode($response['body'], true);

    return is_array($json) ? $json : [];
}

function ce_linkedin_oauth_disconnect(PDO $pdo, string $clientId): void
{
    ce_ensure_client_linkedin_oauth_schema($pdo);
    $pdo->prepare(
        <<<'SQL'
        UPDATE clients
        SET linkedin_oauth_access_token = NULL,
            linkedin_oauth_refresh_token = NULL,
            linkedin_oauth_access_expires_at = NULL,
            linkedin_oauth_refresh_expires_at = NULL,
            linkedin_member_urn = NULL,
            linkedin_member_name = NULL,
            linkedin_oauth_scopes = NULL
        WHERE id = :id
        SQL
    )->execute(['id' => $clientId]);
}

/** @return array{connected: bool, memberName: ?string, organizationSocialGranted: bool, oauthScopes: ?string} */
function ce_linkedin_oauth_status_for_client(PDO $pdo, ?string $clientId): array
{
    ce_ensure_client_linkedin_oauth_schema($pdo);
    if ($clientId === null || $clientId === '') {
        return ['connected' => false, 'memberName' => null, 'organizationSocialGranted' => false, 'oauthScopes' => null];
    }

    $stmt = $pdo->prepare(
        'SELECT linkedin_member_urn, linkedin_member_name, linkedin_oauth_scopes FROM clients WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['connected' => false, 'memberName' => null, 'organizationSocialGranted' => false, 'oauthScopes' => null];
    }

    $urn = trim((string) ($row['linkedin_member_urn'] ?? ''));
    $scopes = trim((string) ($row['linkedin_oauth_scopes'] ?? ''));

    return [
        'connected' => $urn !== '',
        'memberName' => trim((string) ($row['linkedin_member_name'] ?? '')) ?: null,
        'organizationSocialGranted' => ce_linkedin_oauth_scopes_include_organization($scopes),
        'oauthScopes' => $scopes !== '' ? $scopes : null,
    ];
}

/** Geeft een geldig access token terug, verversd indien nodig. Null als niet gekoppeld/verlopen zonder refresh. */
function ce_linkedin_oauth_valid_access_token(PDO $pdo, string $clientId): ?string
{
    ce_ensure_client_linkedin_oauth_schema($pdo);

    $stmt = $pdo->prepare(
        <<<'SQL'
        SELECT linkedin_oauth_access_token, linkedin_oauth_access_expires_at,
               linkedin_oauth_refresh_token, linkedin_oauth_refresh_expires_at
        FROM clients WHERE id = :id LIMIT 1
        SQL
    );
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $accessEncrypted = trim((string) ($row['linkedin_oauth_access_token'] ?? ''));
    $accessExpiresAt = trim((string) ($row['linkedin_oauth_access_expires_at'] ?? ''));
    $stillValid = $accessExpiresAt === '' || strtotime($accessExpiresAt) > time();

    if ($accessEncrypted !== '' && $stillValid) {
        $access = ce_credential_try_decrypt($accessEncrypted);
        if ($access !== null && $access !== '') {
            return $access;
        }
    }

    $refreshEncrypted = trim((string) ($row['linkedin_oauth_refresh_token'] ?? ''));
    $refreshExpiresAt = trim((string) ($row['linkedin_oauth_refresh_expires_at'] ?? ''));
    $refreshValid = $refreshEncrypted !== ''
        && ($refreshExpiresAt === '' || strtotime($refreshExpiresAt) > time());

    if (!$refreshValid) {
        if ($accessEncrypted !== '') {
            ce_log_event($pdo, 'error', 'oauth', 'oauth_expired', 'LinkedIn refresh token verlopen of afwezig', [
                'clientId' => $clientId,
                'provider' => 'linkedin',
                'refreshExpiresAt' => $refreshExpiresAt !== '' ? $refreshExpiresAt : null,
            ]);
        }

        return null;
    }

    $config = ce_linkedin_oauth_config();
    if ($config === null) {
        return null;
    }

    $refreshToken = ce_credential_try_decrypt($refreshEncrypted);
    if ($refreshToken === null || $refreshToken === '') {
        return null;
    }
    $body = http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
    ]);

    $response = ce_linkedin_http_post(
        'https://www.linkedin.com/oauth/v2/accessToken',
        $body,
        ['Content-Type: application/x-www-form-urlencoded']
    );
    $json = json_decode($response['body'], true);
    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($json)) {
        ce_log_event($pdo, 'error', 'oauth', 'oauth_refresh_failed', 'LinkedIn token refresh geweigerd', [
            'clientId' => $clientId,
            'provider' => 'linkedin',
            'httpStatus' => $response['status'],
        ]);

        return null;
    }

    $newAccessToken = trim((string) ($json['access_token'] ?? ''));
    if ($newAccessToken === '') {
        ce_log_event($pdo, 'error', 'oauth', 'oauth_refresh_failed', 'LinkedIn gaf geen access_token terug', [
            'clientId' => $clientId,
            'provider' => 'linkedin',
            'httpStatus' => $response['status'],
        ]);

        return null;
    }
    $expiresIn = (int) ($json['expires_in'] ?? 0);

    $pdo->prepare(
        'UPDATE clients SET linkedin_oauth_access_token = :token, linkedin_oauth_access_expires_at = :expires WHERE id = :id'
    )->execute([
        'token' => ce_credential_encrypt($newAccessToken),
        'expires' => $expiresIn > 0 ? gmdate('Y-m-d H:i:s', time() + $expiresIn - 60) : null,
        'id' => $clientId,
    ]);

    $scope = trim((string) ($json['scope'] ?? ''));
    if ($scope !== '') {
        ce_linkedin_store_oauth_scopes($pdo, $clientId, $scope);
    }

    return $newAccessToken;
}

/** Lokale of remote afbeelding ophalen als bytes — voor upload naar LinkedIn. */
function ce_linkedin_resolve_image_bytes(?string $url): ?array
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    if (str_starts_with($url, '/uploads/')) {
        $path = dirname(__DIR__) . $url;
        if (!is_readable($path)) {
            return null;
        }
        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        return ['bytes' => $bytes, 'mime' => $finfo->file($path) ?: 'image/jpeg'];
    }

    if (preg_match('#^https?://#i', $url)) {
        $context = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
        $bytes = @file_get_contents($url, false, $context);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        return ['bytes' => $bytes, 'mime' => $finfo->buffer($bytes) ?: 'image/jpeg'];
    }

    return null;
}

/** @return string|null LinkedIn image asset URN (urn:li:image:...) of null bij falen. */
function ce_linkedin_upload_image(string $accessToken, string $ownerUrn, string $bytes, string $mime): ?string
{
    $initPayload = json_encode([
        'initializeUploadRequest' => ['owner' => $ownerUrn],
    ], JSON_THROW_ON_ERROR);

    $response = ce_linkedin_http_post(
        'https://api.linkedin.com/rest/images?action=initializeUpload',
        $initPayload,
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'LinkedIn-Version: ' . ce_linkedin_api_versions_to_try()[0],
            'X-Restli-Protocol-Version: 2.0.0',
        ]
    );

    $json = json_decode($response['body'], true);
    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($json)) {
        return null;
    }

    $uploadUrl = trim((string) ($json['value']['uploadUrl'] ?? ''));
    $imageUrn = trim((string) ($json['value']['image'] ?? ''));
    if ($uploadUrl === '' || $imageUrn === '') {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: ' . $mime,
            ]),
            'content' => $bytes,
            'timeout' => 60,
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($uploadUrl, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    if ($result === false || $status < 200 || $status >= 300) {
        return null;
    }

    return $imageUrn;
}

/** Normaliseert vrije invoer (numeriek ID of urn) naar urn:li:organization:ID. */
function ce_linkedin_normalize_organization_urn(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('#^urn:li:organization:\d+$#', $raw)) {
        return $raw;
    }
    if (preg_match('#/company/(\d+)#', $raw, $m)) {
        return 'urn:li:organization:' . $m[1];
    }
    if (ctype_digit($raw)) {
        return 'urn:li:organization:' . $raw;
    }

    return null;
}

function ce_linkedin_organization_publish_help(): string
{
    return 'Posten als bedrijfspagina vereist LinkedIn-product Community Management API (status Approved) '
        . 'en scope w_organization_social in je token. '
        . 'Developers → Products → Community Management, daarna CE Account: Ontkoppel → Koppel LinkedIn → Save. '
        . 'Pagina-verificatie alleen is niet genoeg.';
}

function ce_linkedin_is_organization_permission_error(string $detail): bool
{
    $detail = strtolower($detail);

    return str_contains($detail, 'organization')
        && (str_contains($detail, 'permission') || str_contains($detail, 'permissions'));
}

/**
 * Plaatst een LinkedIn-post namens het gekoppelde profiel, of — als $organizationUrn is
 * meegegeven en de LinkedIn-app w_organization_social mag gebruiken — namens de bedrijfspagina.
 * Geeft postUrl terug bij succes.
 *
 * @return array{ok: bool, postUrl?: string, error?: string}
 */
function ce_linkedin_publish_post(
    PDO $pdo,
    string $clientId,
    string $text,
    ?string $imageUrl = null,
    ?string $organizationUrn = null
): array {
    ce_ensure_client_linkedin_oauth_schema($pdo);

    $stmt = $pdo->prepare('SELECT linkedin_member_urn FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $memberUrn = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($memberUrn === '') {
        return ['ok' => false, 'error' => 'LinkedIn niet gekoppeld — koppel eerst via Account.'];
    }

    $authorUrn = ce_linkedin_normalize_organization_urn($organizationUrn) ?? $memberUrn;
    $postAsOrganization = $authorUrn !== $memberUrn;

    $accessToken = ce_linkedin_oauth_valid_access_token($pdo, $clientId);
    if ($accessToken === null) {
        return ['ok' => false, 'error' => 'LinkedIn-koppeling verlopen — koppel opnieuw via Account.'];
    }

    if ($postAsOrganization) {
        $scopeStmt = $pdo->prepare('SELECT linkedin_oauth_scopes FROM clients WHERE id = :id LIMIT 1');
        $scopeStmt->execute(['id' => $clientId]);
        $scopes = trim((string) ($scopeStmt->fetchColumn() ?: ''));
        if ($scopes !== '' && !ce_linkedin_oauth_scopes_include_organization($scopes)) {
            return [
                'ok' => false,
                'error' => 'LinkedIn-token heeft geen w_organization_social. ' . ce_linkedin_organization_publish_help(),
            ];
        }
    }

    $text = trim($text);
    if ($text === '') {
        return ['ok' => false, 'error' => 'Geen tekst om te posten.'];
    }
    if (mb_strlen($text) > 3000) {
        $text = mb_substr($text, 0, 2990) . '…';
    }

    $imageAsset = null;
    if ($imageUrl !== null) {
        $image = ce_linkedin_resolve_image_bytes($imageUrl);
        if ($image !== null) {
            $imageAsset = ce_linkedin_upload_image($accessToken, $authorUrn, $image['bytes'], $image['mime']);
        }
    }

    $postFields = [
        'author' => $authorUrn,
        'commentary' => $text,
        'visibility' => 'PUBLIC',
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities' => [],
            'thirdPartyDistributionChannels' => [],
        ],
        'lifecycleState' => 'PUBLISHED',
        'isReshareDisabledByAuthor' => false,
    ];
    if ($imageAsset !== null) {
        $postFields['content'] = ['media' => ['id' => $imageAsset]];
    }

    $payload = json_encode($postFields, JSON_THROW_ON_ERROR);

    $httpCode = 0;
    $postUrn = null;
    $response = null;
    $lastVersionTried = null;

    foreach (ce_linkedin_api_versions_to_try() as $version) {
        $lastVersionTried = $version;
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'LinkedIn-Version: ' . $version,
                    'X-Restli-Protocol-Version: 2.0.0',
                ]),
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents('https://api.linkedin.com/rest/posts', false, $context);
        $httpCode = 0;
        $postUrn = null;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/ (\d{3}) /', $header, $matches)) {
                    $httpCode = (int) $matches[1];
                }
                if (stripos($header, 'x-restli-id:') === 0) {
                    $postUrn = trim(substr($header, strlen('x-restli-id:')));
                }
            }
        }

        // 426 = "Requested version ... is not active" — probeer de volgende maandversie.
        if ($httpCode !== 426) {
            break;
        }
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $detail = '';
        if (is_string($response)) {
            $json = json_decode($response, true);
            if (is_array($json)) {
                $detail = trim((string) ($json['message'] ?? ''));
            }
        }

        return [
            'ok' => false,
            'error' => 'LinkedIn publiceren mislukt (HTTP ' . $httpCode . ', versie ' . $lastVersionTried . ')'
                . ($detail !== '' ? ': ' . $detail : '')
                . ($detail !== '' && ce_linkedin_is_organization_permission_error($detail)
                    ? ' — ' . ce_linkedin_organization_publish_help()
                    : ''),
        ];
    }

    $postUrl = $postUrn !== null
        ? 'https://www.linkedin.com/feed/update/' . $postUrn . '/'
        : null;

    return ['ok' => true, 'postUrl' => $postUrl];
}

/** @param list<string> $headers */
function ce_linkedin_http_post(string $url, string $body, array $headers = []): array
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
function ce_linkedin_http_get(string $url, array $headers = []): array
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

function ce_linkedin_oauth_frontend_redirect(string $query = ''): string
{
    $base = rtrim(trim((string) (getenv('CE_PUBLIC_BASE_URL') ?: '')), '/');
    // Sinds aug 2026: koppelen/ontkoppelen gebeurt via Settings → Integrations,
    // niet meer via Account (zie IntegrationsPage.tsx).
    $path = '/integrations';
    if ($query !== '') {
        $path .= '?' . ltrim($query, '?');
    }

    return $base !== '' ? $base . $path : $path;
}
