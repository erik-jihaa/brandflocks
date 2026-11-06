<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/publish-settings-repository.php';

/** Joomla site root — geen /administrator of /api/... */
function ce_normalize_joomla_site_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $url = preg_replace('#/administrator/?.*$#i', '', $url) ?? $url;
    $url = preg_replace('#/api/index\.php/v1/?.*$#i', '', $url) ?? $url;

    return rtrim($url, '/');
}

function ce_joomla_api_base(string $siteUrl): string
{
    return rtrim(ce_normalize_joomla_site_url($siteUrl), '/') . '/api/index.php/v1';
}

/** @return array{site_url: string, api_token: string, category_id: int, blog_url: string} */
function ce_joomla_config_for_client(PDO $pdo, ?string $clientId): array
{
    ce_ensure_client_publish_columns($pdo);

    $site = '';
    $token = '';
    $categoryId = 0;
    $blogUrl = '';

    if ($clientId !== null && $clientId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $row = $stmt->fetch();
        if ($row) {
            $site = ce_normalize_joomla_site_url((string) ($row['joomla_site_url'] ?? ''));
            $token = ce_credential_resolve_plaintext('', (string) ($row['joomla_api_token'] ?? ''));
            $categoryId = (int) ($row['joomla_category_id'] ?? 0);
            $blogUrl = trim((string) ($row['website_blog_url'] ?? ''));
        }
    }

    return [
        'site_url' => $site,
        'api_token' => $token,
        'category_id' => $categoryId > 0 ? $categoryId : 0,
        'blog_url' => $blogUrl,
    ];
}

function ce_client_joomla_configured(PDO $pdo, ?string $clientId): bool
{
    if ($clientId === null || $clientId === '') {
        return false;
    }

    ce_ensure_client_publish_columns($pdo);
    $config = ce_joomla_config_for_client($pdo, $clientId);

    return $config['site_url'] !== ''
        && $config['api_token'] !== ''
        && $config['category_id'] > 0;
}

/**
 * @param array{site_url: string, api_token: string, category_id?: int, blog_url?: string} $config
 * @return array{http_code: int, body: ?string, json: ?array}
 */
function ce_joomla_http_request(array $config, string $method, string $relativePath, ?string $jsonBody = null): array
{
    $path = str_starts_with($relativePath, '/') ? $relativePath : '/' . $relativePath;
    $endpoint = ce_joomla_api_base($config['site_url']) . $path;
    $token = trim((string) ($config['api_token'] ?? ''));

    // X-Joomla-Token werkt ook als de host Authorization: Bearer wegstript (gangbaar op Apache/nginx).
    $headers = [
        'Accept: application/vnd.api+json',
        'X-Joomla-Token: ' . $token,
        'Authorization: Bearer ' . $token,
    ];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $jsonBody ?? '',
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($endpoint, false, $context);
    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $matches)) {
        $httpCode = (int) $matches[1];
    }

    if (!is_string($response) || $response === '') {
        return ['http_code' => $httpCode, 'body' => null, 'json' => null];
    }

    $data = json_decode($response, true);

    return [
        'http_code' => $httpCode,
        'body' => $response,
        'json' => is_array($data) ? $data : null,
    ];
}

function ce_joomla_error_message(int $httpCode, ?string $response, string $endpoint): string
{
    $snippet = trim((string) $response);
    if ($snippet !== '' && str_starts_with(strtolower(ltrim($snippet)), '<!doctype html')) {
        return 'Joomla Web Services API niet bereikbaar op '
            . $endpoint
            . ' (HTTP '
            . $httpCode
            . '). Controleer site-URL en of de API-plugins actief zijn.';
    }

    if ($snippet !== '') {
        $decoded = json_decode($snippet, true);
        if (is_array($decoded)) {
            $errors = $decoded['errors'] ?? null;
            if (is_array($errors) && isset($errors[0]['title'])) {
                return trim((string) $errors[0]['title']);
            }
            $message = trim((string) ($decoded['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }
        $plain = trim(strip_tags($snippet));
        if ($plain !== '' && strlen($plain) <= 240) {
            return $plain;
        }
    }

    return 'Joomla publish mislukt (HTTP ' . $httpCode . ')';
}

function ce_joomla_slug_from_title(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? 'article';
    $slug = trim((string) $slug, '-');

    return $slug !== '' ? $slug : 'article';
}

function ce_joomla_html_content(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $parts = preg_split("/\n{2,}/", $text) ?: [];
    if (count($parts) <= 1) {
        return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $html = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $html[] = '<p>' . nl2br(htmlspecialchars($part, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</p>';
    }

    return implode("\n", $html);
}

/** @param array<string, mixed>|null $json */
function ce_joomla_article_id_from_response(?array $json): ?string
{
    if (!is_array($json)) {
        return null;
    }

    if (isset($json['id']) && (is_string($json['id']) || is_int($json['id']))) {
        $id = trim((string) $json['id']);

        return $id !== '' ? $id : null;
    }

    $nested = $json['data'] ?? null;
    if (is_array($nested) && isset($nested['id'])) {
        $id = trim((string) $nested['id']);

        return $id !== '' ? $id : null;
    }

    return null;
}

/** @param array<string, mixed>|null $json */
function ce_joomla_article_alias_from_response(?array $json): ?string
{
    if (!is_array($json)) {
        return null;
    }

    $alias = trim((string) ($json['alias'] ?? ''));
    if ($alias !== '') {
        return $alias;
    }

    $attrs = $json['data']['attributes'] ?? null;
    if (is_array($attrs)) {
        $alias = trim((string) ($attrs['alias'] ?? ''));

        return $alias !== '' ? $alias : null;
    }

    return null;
}

/**
 * @param array{site_url: string, api_token: string, category_id?: int, blog_url?: string} $config
 */
function ce_joomla_build_article_url(array $config, string $articleId, ?string $alias = null): string
{
    $blogUrl = trim((string) ($config['blog_url'] ?? ''));
    if ($alias !== null && $alias !== '') {
        if ($blogUrl !== '') {
            $parts = parse_url($blogUrl);
            if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
                $origin = $parts['scheme'] . '://' . $parts['host'];
                if (!empty($parts['port'])) {
                    $origin .= ':' . $parts['port'];
                }

                return rtrim($origin, '/') . '/' . ltrim($alias, '/');
            }
        }

        return rtrim($config['site_url'], '/') . '/' . ltrim($alias, '/');
    }

    return rtrim($config['site_url'], '/')
        . '/index.php?option=com_content&view=article&id='
        . rawurlencode($articleId);
}

/**
 * @param array{site_url: string, api_token: string, category_id?: int, blog_url?: string} $config
 * @return array{
 *   connected: bool,
 *   authError: ?string,
 *   categoryOk: bool,
 *   categoryTitle: ?string
 * }
 */
function ce_joomla_test_connection(array $config): array
{
    $site = trim((string) ($config['site_url'] ?? ''));
    $token = trim((string) ($config['api_token'] ?? ''));
    $categoryId = (int) ($config['category_id'] ?? 0);

    if ($site === '' || $token === '') {
        return [
            'connected' => false,
            'authError' => 'Vul Joomla site-URL en API-token in.',
            'categoryOk' => false,
            'categoryTitle' => null,
        ];
    }

    if ($categoryId <= 0) {
        return [
            'connected' => false,
            'authError' => 'Vul een Joomla categorie-ID in (Content → Categories).',
            'categoryOk' => false,
            'categoryTitle' => null,
        ];
    }

    $articles = ce_joomla_http_request($config, 'GET', '/content/articles?page[offset]=0&page[limit]=1');
    if ($articles['http_code'] === 401 || $articles['http_code'] === 403) {
        $detail = ce_joomla_error_message($articles['http_code'], $articles['body'], ce_joomla_api_base($site) . '/content/articles');
        $authError = 'Joomla wees het API-token af (HTTP ' . $articles['http_code'] . '). '
            . 'Genereer een nieuw token in Joomla (Users → jouw gebruiker → tab Joomla API Token) '
            . 'en plak het opnieuw in Integrations. Controleer ook: plugins '
            . 'User – Joomla API Token, API Authentication – Joomla Token en Web Services – Content. '
            . 'Schakel API Authentication – Basic Auth uit als die aan staat.';
        if ($detail !== '' && !str_contains($detail, 'HTTP ')) {
            $authError .= ' Joomla: ' . $detail . '.';
        }

        return [
            'connected' => false,
            'authError' => $authError,
            'categoryOk' => false,
            'categoryTitle' => null,
        ];
    }
    if ($articles['http_code'] < 200 || $articles['http_code'] >= 300) {
        $endpoint = ce_joomla_api_base($site) . '/content/articles';
        return [
            'connected' => false,
            'authError' => ce_joomla_error_message($articles['http_code'], $articles['body'], $endpoint),
            'categoryOk' => false,
            'categoryTitle' => null,
        ];
    }

    $category = ce_joomla_http_request($config, 'GET', '/content/categories/' . $categoryId);
    if ($category['http_code'] === 404) {
        return [
            'connected' => true,
            'authError' => 'API-token OK, maar categorie #' . $categoryId . ' bestaat niet.',
            'categoryOk' => false,
            'categoryTitle' => null,
        ];
    }
    if ($category['http_code'] < 200 || $category['http_code'] >= 300) {
        return [
            'connected' => true,
            'authError' => 'API-token OK, maar categorie kon niet worden gelezen (HTTP ' . $category['http_code'] . ').',
            'categoryOk' => false,
            'categoryTitle' => null,
        ];
    }

    $categoryTitle = null;
    $json = $category['json'];
    if (is_array($json)) {
        $categoryTitle = trim((string) ($json['title'] ?? $json['data']['attributes']['title'] ?? ''));
        if ($categoryTitle === '') {
            $categoryTitle = null;
        }
    }

    return [
        'connected' => true,
        'authError' => null,
        'categoryOk' => true,
        'categoryTitle' => $categoryTitle,
    ];
}

function ce_joomla_fetch_post_link(PDO $pdo, ?string $clientId, string $postId): ?string
{
    $postId = trim($postId);
    if ($postId === '' || !ctype_digit($postId)) {
        return null;
    }

    $config = ce_joomla_config_for_client($pdo, $clientId);
    if ($config['site_url'] === '' || $config['api_token'] === '') {
        return null;
    }

    $response = ce_joomla_http_request($config, 'GET', '/content/articles/' . rawurlencode($postId));
    if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
        return null;
    }

    $alias = ce_joomla_article_alias_from_response($response['json']);
    $url = ce_joomla_build_article_url($config, $postId, $alias);

    return $url !== '' ? $url : null;
}
