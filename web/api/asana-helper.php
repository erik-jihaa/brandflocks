<?php

declare(strict_types=1);

require_once __DIR__ . '/credential-crypto.php';

const CE_ASANA_API_BASE = 'https://app.asana.com/api/1.0';

/** @return array{ok: bool, status: int, json: mixed, error: string|null} */
function ce_asana_http(string $method, string $path, string $pat, ?array $query = null, ?array $body = null): array
{
    $pat = trim($pat);
    if ($pat === '') {
        return ['ok' => false, 'status' => 0, 'json' => null, 'error' => 'Asana token ontbreekt.'];
    }

    $url = CE_ASANA_API_BASE . '/' . ltrim($path, '/');
    if ($query !== null && $query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Authorization: Bearer ' . $pat,
        'Accept: application/json',
        'User-Agent: ContentEngine/1.0',
    ];
    $payload = null;
    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $payload ?? '',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    if (!is_string($raw) || $raw === '') {
        return ['ok' => false, 'status' => $status, 'json' => null, 'error' => 'Asana antwoord leeg.'];
    }

    $json = json_decode($raw, true);
    if ($status >= 400) {
        $msg = is_array($json) ? (string) ($json['errors'][0]['message'] ?? $json['message'] ?? 'Asana-fout') : 'Asana-fout';

        return ['ok' => false, 'status' => $status, 'json' => $json, 'error' => $msg];
    }

    return ['ok' => true, 'status' => $status, 'json' => $json, 'error' => null];
}

function ce_asana_resolve_pat(PDO $pdo, string $sourceId, ?string $plainPat = null): string
{
    if ($plainPat !== null && trim($plainPat) !== '') {
        return trim($plainPat);
    }

    $stmt = $pdo->prepare('SELECT asana_pat_ref FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return '';
    }

    try {
        return ce_credential_resolve_plaintext(null, (string) ($row['asana_pat_ref'] ?? ''));
    } catch (Throwable) {
        return '';
    }
}

/** @return list<array{gid: string, name: string}> */
function ce_asana_list_workspaces(string $pat): array
{
    $res = ce_asana_http('GET', '/workspaces', $pat, ['limit' => 100]);
    if (!$res['ok'] || !is_array($res['json'])) {
        return [];
    }
    $data = $res['json']['data'] ?? [];
    if (!is_array($data)) {
        return [];
    }

    $out = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $gid = trim((string) ($row['gid'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($gid !== '' && $name !== '') {
            $out[] = ['gid' => $gid, 'name' => $name];
        }
    }

    return $out;
}

/** @return list<array{gid: string, name: string}> */
function ce_asana_list_projects(string $pat, string $workspaceGid): array
{
    $workspaceGid = trim($workspaceGid);
    if ($workspaceGid === '') {
        return [];
    }

    $res = ce_asana_http('GET', '/projects', $pat, [
        'workspace' => $workspaceGid,
        'limit' => 100,
        'archived' => 'false',
    ]);
    if (!$res['ok'] || !is_array($res['json'])) {
        return [];
    }
    $data = $res['json']['data'] ?? [];
    if (!is_array($data)) {
        return [];
    }

    $out = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $gid = trim((string) ($row['gid'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($gid !== '' && $name !== '') {
            $out[] = ['gid' => $gid, 'name' => $name];
        }
    }

    return $out;
}

/** @return list<array{title: string, url: string, snippet: string}> */
function ce_asana_fetch_project_tasks(string $pat, string $projectGid, int $max = 24): array
{
    $projectGid = trim($projectGid);
    if ($projectGid === '') {
        return [];
    }

    $res = ce_asana_http('GET', '/tasks', $pat, [
        'project' => $projectGid,
        'limit' => min(100, max(1, $max)),
        'opt_fields' => 'name,notes,permalink_url,completed',
    ]);
    if (!$res['ok'] || !is_array($res['json'])) {
        return [];
    }
    $data = $res['json']['data'] ?? [];
    if (!is_array($data)) {
        return [];
    }

    $items = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!empty($row['completed'])) {
            continue;
        }
        $title = trim((string) ($row['name'] ?? ''));
        if ($title === '') {
            continue;
        }
        $url = trim((string) ($row['permalink_url'] ?? ''));
        $snippet = trim((string) ($row['notes'] ?? ''));
        $items[] = [
            'title' => mb_substr($title, 0, 500),
            'url' => $url,
            'snippet' => mb_substr($snippet !== '' ? $snippet : 'Asana-taak', 0, 2000),
        ];
        if (count($items) >= $max) {
            break;
        }
    }

    return $items;
}

/** @return array{ok: bool, message: string, itemCount: int} */
function ce_probe_asana_source(PDO $pdo, string $sourceId, ?string $plainPat = null): array
{
    $stmt = $pdo->prepare(
        'SELECT asana_project_gid, asana_workspace_gid FROM sources WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'message' => 'Bron niet gevonden.', 'itemCount' => 0];
    }

    $projectGid = trim((string) ($row['asana_project_gid'] ?? ''));
    if ($projectGid === '') {
        return ['ok' => false, 'message' => 'Asana-project ontbreekt.', 'itemCount' => 0];
    }

    $pat = ce_asana_resolve_pat($pdo, $sourceId, $plainPat);
    if ($pat === '') {
        return ['ok' => false, 'message' => 'Asana Personal Access Token ontbreekt.', 'itemCount' => 0];
    }

    $tasks = ce_asana_fetch_project_tasks($pat, $projectGid, 24);
    if ($tasks === []) {
        return [
            'ok' => false,
            'message' => 'Asana-project bereikbaar, maar geen open taken gevonden.',
            'itemCount' => 0,
        ];
    }

    $count = count($tasks);

    return [
        'ok' => true,
        'message' => $count === 1 ? '1 Asana-taak gevonden.' : "{$count} Asana-taken gevonden.",
        'itemCount' => $count,
    ];
}

/** @return array{ok: bool, items: list<array<string, mixed>>, run: array<string, mixed>} */
function ce_run_asana_source(PDO $pdo, string $sourceId): array
{
    $stmt = $pdo->prepare('SELECT id, title, category, asana_project_gid FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Source not found']];
    }
    if ((string) ($row['category'] ?? '') !== 'asana') {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Geen Asana-bron']];
    }

    $pat = ce_asana_resolve_pat($pdo, $sourceId, null);
    $projectGid = trim((string) ($row['asana_project_gid'] ?? ''));
    $sourceTitle = trim((string) ($row['title'] ?? 'Asana'));
    $rawItems = ce_asana_fetch_project_tasks($pat, $projectGid);
    $items = [];
    foreach ($rawItems as $item) {
        $items[] = [
            'title' => $item['title'],
            'url' => $item['url'],
            'snippet' => $item['snippet'],
            'source_id' => $sourceId,
            'source_title' => $sourceTitle !== '' ? $sourceTitle : 'Asana',
            'source_type' => 'asana',
        ];
    }

    return [
        'ok' => true,
        'items' => $items,
        'run' => [
            'status' => $items === [] ? 'empty' : 'ok',
            'message' => $items === [] ? 'Geen Asana-taken gevonden.' : count($items) . ' Asana-taken ingelezen.',
            'items_count' => count($items),
        ],
    ];
}
