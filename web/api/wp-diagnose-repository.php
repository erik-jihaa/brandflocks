<?php

declare(strict_types=1);

require_once __DIR__ . '/publish-repository.php';
require_once __DIR__ . '/publish-settings-repository.php';
require_once __DIR__ . '/linkedin-oauth-helper.php';

/** @return array<string, mixed> */
function ce_wp_diagnose_for_client(PDO $pdo, string $clientId): array
{
    if ($clientId === '') {
        throw new InvalidArgumentException('client_id is verplicht');
    }

    $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Tenant niet gevonden');
    }

    $config = ce_wordpress_config_for_client($pdo, $clientId);

    $safeConfig = [
        'site_url' => $config['site_url'],
        'user' => $config['user'],
        'has_app_password' => $config['app_password'] !== '',
        'app_password_length' => strlen($config['app_password']),
        'app_password_fingerprint' => $config['app_password'] !== ''
            ? substr(hash('sha256', $config['app_password']), 0, 10)
            : null,
        'author_id_configured' => $config['author_id'],
        'blog_url' => $config['blog_url'],
    ];

    $meRaw = null;
    $authError = null;
    $wpErrorCode = null;
    $wpErrorMessage = null;
    if ($config['site_url'] !== '' && $config['user'] !== '' && $config['app_password'] !== '') {
        $meRaw = ce_wordpress_rest_json($config, 'GET', '/wp-json/wp/v2/users/me?context=edit');
        if ($meRaw === null) {
            $authError = 'Geen geldig antwoord van /wp-json/wp/v2/users/me — auth of site-URL klopt niet.';
        } elseif (!isset($meRaw['id'])) {
            $wpErrorCode = $meRaw['code'] ?? null;
            $wpErrorMessage = $meRaw['message'] ?? null;
            $authError = 'WordPress wees de login af: ' . ($wpErrorMessage ?? 'onbekende fout')
                . ($wpErrorCode ? ' (code: ' . $wpErrorCode . ')' : '');
            $meRaw = null;
        }
    } else {
        $authError = 'Config incompleet (site-URL, gebruiker of app-wachtwoord ontbreekt).';
    }

    $resolvedAuthorId = null;
    if ($meRaw !== null) {
        $resolvedAuthorId = ce_wordpress_resolve_post_author_id($config);
    }

    $linkedinOAuth = ce_linkedin_oauth_status_for_client($pdo, $clientId);
    $linkedinConfigured = ce_linkedin_oauth_configured();
    $tenantWordPressConfigured = ce_client_wordpress_configured($pdo, $clientId);
    $usingPlatformFallback = !$tenantWordPressConfigured
        && $config['site_url'] !== ''
        && $config['user'] !== ''
        && $config['app_password'] !== '';
    $publishExtras = [];
    if ($linkedinOAuth['connected']) {
        ce_linkedin_persist_publish_extra_row($pdo, $clientId);
    }
    $extraStmt = $pdo->prepare('SELECT publish_extra_destinations FROM clients WHERE id = :id LIMIT 1');
    $extraStmt->execute(['id' => $clientId]);
    $extraRow = $extraStmt->fetch();
    if ($extraRow) {
        $publishExtras = ce_publish_extra_destinations_from_json(
            (string) ($extraRow['publish_extra_destinations'] ?? '')
        );
    }

    $healthy = $meRaw !== null && $authError === null;

    $postInspect = null;
    $postId = trim((string) ($_GET['post_id'] ?? ''));
    if ($postId !== '' && ctype_digit($postId) && $meRaw !== null) {
        $postRaw = ce_wordpress_rest_json($config, 'GET', '/wp-json/wp/v2/posts/' . $postId . '?context=edit');
        if (is_array($postRaw) && isset($postRaw['id'])) {
            $link = trim((string) ($postRaw['link'] ?? ''));
            $postInspect = [
                'id' => $postRaw['id'],
                'status' => $postRaw['status'] ?? null,
                'slug' => $postRaw['slug'] ?? null,
                'link' => $link,
                'author' => $postRaw['author'] ?? null,
                'contentLength' => strlen(strip_tags((string) ($postRaw['content']['raw'] ?? $postRaw['content']['rendered'] ?? ''))),
                'publicHeadStatus' => $link !== '' ? ce_http_head_status_code($link) : null,
            ];
        } else {
            $postInspect = [
                'error' => is_array($postRaw) ? ($postRaw['message'] ?? 'Post niet gevonden') : 'Geen geldig antwoord',
                'code' => is_array($postRaw) ? ($postRaw['code'] ?? null) : null,
            ];
        }
    }

    return [
        'ok' => true,
        'healthy' => $healthy,
        'postInspect' => $postInspect,
        'cmsType' => 'wordpress',
        'cmsLabel' => 'WordPress',
        'clientId' => $clientId,
        'config' => $safeConfig,
        'authenticatedAs' => $meRaw !== null ? [
            'id' => $meRaw['id'] ?? null,
            'slug' => $meRaw['slug'] ?? null,
            'name' => $meRaw['name'] ?? null,
            'roles' => $meRaw['roles'] ?? null,
        ] : null,
        'resolvedPostAuthorId' => $resolvedAuthorId,
        'authError' => $authError,
        'wpErrorCode' => $wpErrorCode,
        'linkedinOAuthConfigured' => $linkedinConfigured,
        'linkedinConnected' => $linkedinOAuth['connected'],
        'linkedinMemberName' => $linkedinOAuth['memberName'],
        'tenantWordPressConfigured' => $tenantWordPressConfigured,
        'usingPlatformFallback' => $usingPlatformFallback,
        'publishExtraDestinations' => $publishExtras,
    ];
}
