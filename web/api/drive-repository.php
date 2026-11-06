<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/analytics-oauth-helper.php';

const CE_DRIVE_OAUTH_SCOPE = 'https://www.googleapis.com/auth/drive.file';

function ce_ensure_source_drive_oauth_schema(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'sources')) {
        return;
    }

    $columns = [
        'drive_oauth_refresh_token' => 'TEXT NULL',
        'drive_oauth_email' => 'VARCHAR(255) NULL',
        'drive_file_url' => 'VARCHAR(500) NULL',
    ];
    foreach ($columns as $column => $definition) {
        if (!ce_table_has_column($pdo, 'sources', $column)) {
            try {
                $pdo->exec("ALTER TABLE sources ADD COLUMN `$column` $definition");
            } catch (Throwable) {
                // optional migration
            }
        }
    }
}

/** @return array{clientId: string, clientSecret: string, redirectUri: string}|null */
function ce_drive_oauth_config(): ?array
{
    $config = ce_analytics_oauth_config();
    if ($config === null) {
        return null;
    }

    return [
        'clientId' => $config['clientId'],
        'clientSecret' => $config['clientSecret'],
        'redirectUri' => $config['redirectUri'],
    ];
}

function ce_drive_extract_file_id(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (preg_match('#/document/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
        return $m[1];
    }
    if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
        return $m[1];
    }
    if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
        return $m[1];
    }
    if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m)) {
        return $m[1];
    }

    return null;
}

function ce_drive_fetch_access_token(PDO $pdo, string $sourceId): ?string
{
    ce_ensure_source_drive_oauth_schema($pdo);
    $stmt = $pdo->prepare('SELECT drive_oauth_refresh_token FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    try {
        $refresh = ce_credential_resolve_plaintext(null, (string) ($row['drive_oauth_refresh_token'] ?? ''));
    } catch (Throwable) {
        return null;
    }
    if ($refresh === '') {
        return null;
    }

    $config = ce_drive_oauth_config();
    if ($config === null) {
        return null;
    }

    $token = ce_analytics_refresh_access_token($refresh);

    return is_array($token) ? trim((string) ($token['access_token'] ?? '')) ?: null : null;
}

/** @return array{title: string, snippet: string}|null */
function ce_drive_export_file_text(string $accessToken, string $fileId): ?array
{
    $fileId = trim($fileId);
    if ($fileId === '' || $accessToken === '') {
        return null;
    }

    $metaUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
        . '?fields=name,mimeType&supportsAllDrives=true';
    $meta = ce_drive_http_get($metaUrl, $accessToken);
    if ($meta === null) {
        return null;
    }

    $mime = (string) ($meta['mimeType'] ?? '');
    $title = trim((string) ($meta['name'] ?? 'Google Drive-bestand'));
    $text = '';

    if ($mime === 'application/vnd.google-apps.document') {
        $exportUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
            . '/export?mimeType=text/plain&supportsAllDrives=true';
        $text = ce_drive_http_get_raw($exportUrl, $accessToken) ?? '';
    } elseif ($mime === 'application/pdf') {
        $downloadUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
            . '?alt=media&supportsAllDrives=true';
        $binary = ce_drive_http_get_raw($downloadUrl, $accessToken);
        if ($binary !== null) {
            $tmp = tempnam(sys_get_temp_dir(), 'ce-drive-');
            if ($tmp !== false) {
                file_put_contents($tmp, $binary);
                require_once __DIR__ . '/source-document-repository.php';
                $text = ce_source_document_extract_pdf_text($tmp);
                @unlink($tmp);
            }
        }
    } else {
        return null;
    }

    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        return null;
    }

    return [
        'title' => mb_substr($title, 0, 500),
        'snippet' => mb_substr($text, 0, 2000),
    ];
}

/** @return array<string, mixed>|null */
function ce_drive_http_get(string $url, string $accessToken): ?array
{
    $raw = ce_drive_http_get_raw($url, $accessToken);
    if ($raw === null) {
        return null;
    }
    $json = json_decode($raw, true);

    return is_array($json) ? $json : null;
}

function ce_drive_http_get_raw(string $url, string $accessToken): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json,text/plain,*/*',
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        return null;
    }

    return is_string($body) ? $body : null;
}

/** @return array{ok: bool, message: string, itemCount: int} */
function ce_probe_drive_source(PDO $pdo, string $sourceId): array
{
    ce_ensure_source_drive_oauth_schema($pdo);
    $stmt = $pdo->prepare('SELECT drive_file_url FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'message' => 'Bron niet gevonden.', 'itemCount' => 0];
    }

    $fileUrl = trim((string) ($row['drive_file_url'] ?? ''));
    $fileId = ce_drive_extract_file_id($fileUrl);
    if ($fileId === null) {
        return ['ok' => false, 'message' => 'Google Drive-bestands-URL ontbreekt of is ongeldig.', 'itemCount' => 0];
    }

    $token = ce_drive_fetch_access_token($pdo, $sourceId);
    if ($token === null) {
        return ['ok' => false, 'message' => 'Google Drive nog niet gekoppeld.', 'itemCount' => 0];
    }

    $exported = ce_drive_export_file_text($token, $fileId);
    if ($exported === null) {
        return [
            'ok' => false,
            'message' => 'Bestand niet leesbaar — koppel Google Drive opnieuw en controleer of dit Google Doc/PDF is dat je met Content Engine hebt gedeeld.',
            'itemCount' => 0,
        ];
    }

    return [
        'ok' => true,
        'message' => 'Google Drive-bestand bereikbaar: ' . $exported['title'],
        'itemCount' => 1,
    ];
}

/** @return array{ok: bool, items: list<array<string, mixed>>, run: array<string, mixed>} */
function ce_run_drive_source(PDO $pdo, string $sourceId): array
{
    $stmt = $pdo->prepare('SELECT id, title, category, drive_file_url FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Source not found']];
    }
    if ((string) ($row['category'] ?? '') !== 'drive') {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Geen Drive-bron']];
    }

    $fileUrl = trim((string) ($row['drive_file_url'] ?? ''));
    $fileId = ce_drive_extract_file_id($fileUrl);
    $token = ce_drive_fetch_access_token($pdo, $sourceId);
    $sourceTitle = trim((string) ($row['title'] ?? 'Google Drive'));
    $items = [];

    if ($fileId !== null && $token !== null) {
        $exported = ce_drive_export_file_text($token, $fileId);
        if ($exported !== null) {
            $items[] = [
                'title' => $exported['title'],
                'url' => $fileUrl,
                'snippet' => $exported['snippet'],
                'source_id' => $sourceId,
                'source_title' => $sourceTitle !== '' ? $sourceTitle : 'Google Drive',
                'source_type' => 'drive',
            ];
        }
    }

    return [
        'ok' => true,
        'items' => $items,
        'run' => [
            'status' => $items === [] ? 'empty' : 'ok',
            'message' => $items === [] ? 'Geen Drive-inhoud gevonden.' : '1 Drive-bestand ingelezen.',
            'items_count' => count($items),
        ],
    ];
}
