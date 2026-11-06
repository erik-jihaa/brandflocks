<?php

declare(strict_types=1);

require_once __DIR__ . '/drive-repository.php';
require_once __DIR__ . '/analytics-oauth-helper.php';
require_once __DIR__ . '/client-context.php';

/** @return array{authUrl: string, state: string}|null */
function ce_drive_oauth_start(PDO $pdo, string $sourceId, string $returnTo = 'sources'): ?array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'source', $sourceId);

    ce_ensure_source_drive_oauth_schema($pdo);
    $config = ce_drive_oauth_config();
    if ($config === null) {
        return null;
    }

    require_once __DIR__ . '/client-context.php';
    ce_session_start();
    unset(
        $_SESSION['analytics_oauth_state'],
        $_SESSION['analytics_oauth_source_id'],
        $_SESSION['calendar_oauth_state'],
        $_SESSION['calendar_oauth_source_id'],
    );
    $_SESSION['google_oauth_flow'] = 'drive';
    $_SESSION['google_oauth_client_id'] = ce_resolve_client_id($pdo) ?? '';
    $_SESSION['google_oauth_return_to'] = in_array($returnTo, ['sources', 'integrations'], true)
        ? $returnTo
        : 'sources';
    $_SESSION['drive_oauth_state'] = bin2hex(random_bytes(16));
    $_SESSION['drive_oauth_source_id'] = $sourceId;

    $state = (string) $_SESSION['drive_oauth_state'];
    $params = http_build_query([
        'client_id' => $config['clientId'],
        'redirect_uri' => $config['redirectUri'],
        'response_type' => 'code',
        'scope' => CE_DRIVE_OAUTH_SCOPE,
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
function ce_drive_oauth_handle_callback(PDO $pdo, string $code, string $state): string
{
    ce_ensure_source_drive_oauth_schema($pdo);
    ce_session_start();

    $expectedState = (string) ($_SESSION['drive_oauth_state'] ?? '');
    $sourceId = (string) ($_SESSION['drive_oauth_source_id'] ?? '');
    unset($_SESSION['drive_oauth_state'], $_SESSION['drive_oauth_source_id']);

    if ($expectedState === '' || !hash_equals($expectedState, $state)) {
        throw new InvalidArgumentException('Ongeldige OAuth-state.');
    }
    if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
        throw new InvalidArgumentException('Bron ontbreekt in sessie.');
    }

    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'source', $sourceId);

    $config = ce_drive_oauth_config();
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
        SET drive_oauth_refresh_token = :refresh,
            drive_oauth_email = :email,
            url_status = 'reachable',
            last_checked_at = NOW()
        WHERE id = :id
        SQL
    )->execute([
        'refresh' => ce_credential_encrypt($refreshToken),
        'email' => $email,
        'id' => $sourceId,
    ]);

    return $sourceId;
}

function ce_drive_oauth_status(PDO $pdo, string $sourceId): array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'source', $sourceId);

    ce_ensure_source_drive_oauth_schema($pdo);
    $stmt = $pdo->prepare(
        'SELECT drive_oauth_refresh_token, drive_oauth_email FROM sources WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch() ?: [];

    return [
        'connected' => trim((string) ($row['drive_oauth_refresh_token'] ?? '')) !== '',
        'email' => $row['drive_oauth_email'] ?? null,
        'configured' => ce_drive_oauth_config() !== null,
    ];
}
