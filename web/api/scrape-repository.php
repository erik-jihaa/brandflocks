<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/event-log-repository.php';

function ce_scrape_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function ce_ensure_scrape_runs_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'scrape_runs')) {
        try {
            $pdo->exec(
                <<<'SQL'
                CREATE TABLE IF NOT EXISTS scrape_runs (
                  id CHAR(36) NOT NULL PRIMARY KEY,
                  source_id CHAR(36) NOT NULL,
                  client_id CHAR(36) NULL,
                  theme_id CHAR(36) NULL,
                  slot_id CHAR(36) NULL,
                  status VARCHAR(30) NOT NULL DEFAULT 'ok',
                  units_used DECIMAL(10,4) NULL,
                  cost_eur DECIMAL(10,4) NULL,
                  items_count INT NULL,
                  apify_run_id VARCHAR(100) NULL,
                  message TEXT NULL,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  KEY idx_source_created (source_id, created_at),
                  KEY idx_client_created (client_id, created_at)
                )
                SQL
            );
        } catch (Throwable) {
            // Tabel bestaat al of rechten ontbreken.
        }
    }

    $columns = [
        'client_id' => 'CHAR(36) NULL',
        'theme_id' => 'CHAR(36) NULL',
        'slot_id' => 'CHAR(36) NULL',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'ok'",
        'units_used' => 'DECIMAL(10,4) NULL',
        'cost_eur' => 'DECIMAL(10,4) NULL',
        'items_count' => 'INT NULL',
        'apify_run_id' => 'VARCHAR(100) NULL',
        'message' => 'TEXT NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($columns as $column => $definition) {
        if (!ce_table_has_column($pdo, 'scrape_runs', $column)) {
            try {
                $pdo->exec("ALTER TABLE scrape_runs ADD COLUMN `$column` $definition");
                unset($GLOBALS['ce_table_columns']['scrape_runs']);
            } catch (Throwable) {
                // Kolom bestaat al of ALTER niet toegestaan.
            }
        }
    }

    // Productie had soms ENUM/korte VARCHAR → "Data truncated for column status"
    // (o.a. skipped_theme_phase, budget_exceeded). Breedte forceren zodat elke tenant kan syncen.
    try {
        $pdo->exec("ALTER TABLE scrape_runs MODIFY status VARCHAR(40) NOT NULL DEFAULT 'ok'");
    } catch (Throwable) {
        // Geen ALTER-rechten of al breed genoeg.
    }
}

function ce_apify_platform_token(): string
{
    ce_load_env();

    $token = trim((string) (getenv('APIFY_API_TOKEN') ?: ''));
    if ($token !== '') {
        return $token;
    }

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            return trim((string) ($config['apify_api_token'] ?? ''));
        }
    }

    return '';
}

function ce_apify_platform_configured(): bool
{
    return ce_apify_platform_token() !== '';
}

function ce_scraper_resolve_apify_token(?string $storedRef): string
{
    try {
        $stored = ce_credential_resolve_plaintext(null, (string) ($storedRef ?? ''));
    } catch (Throwable) {
        $stored = '';
    }

    if ($stored !== '') {
        return $stored;
    }

    return ce_apify_platform_token();
}

function ce_apify_probe_token(string $token): bool
{
    $token = trim($token);
    if ($token === '') {
        return false;
    }

    $url = 'https://api.apify.com/v2/users/me?token=' . rawurlencode($token);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "User-Agent: ContentEngine/1.0\r\nAccept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        return false;
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) && isset($decoded['data']['id']);
}

/** @return array{allowed: bool, reason: string|null} */
function ce_scraper_budget_allows(?float $maxUnitsPerRun): array
{
    if ($maxUnitsPerRun === null) {
        return ['allowed' => true, 'reason' => null];
    }

    if ($maxUnitsPerRun <= 0) {
        return ['allowed' => false, 'reason' => 'Budget uitgeschakeld (max units = 0).'];
    }

    return ['allowed' => true, 'reason' => null];
}

function ce_scrape_log_run(
    PDO $pdo,
    string $sourceId,
    ?string $clientId,
    ?string $themeId,
    ?string $slotId,
    string $status,
    ?float $unitsUsed,
    ?float $costEur,
    ?int $itemsCount,
    ?string $apifyRunId,
    ?string $message,
): void {
    ce_ensure_scrape_runs_schema($pdo);

    $runId = ce_scrape_uuid_v4();
    $apifyRunIdStored = $apifyRunId !== null && strlen($apifyRunId) > 100 ? substr($apifyRunId, 0, 100) : $apifyRunId;

    $columns = [
        'id' => $runId,
        'source_id' => $sourceId,
        'client_id' => $clientId,
        'theme_id' => $themeId,
        'slot_id' => $slotId,
        'status' => $status,
        'units_used' => $unitsUsed,
        'cost_eur' => $costEur,
        'items_count' => $itemsCount,
        'apify_run_id' => $apifyRunIdStored,
        'message' => $message,
    ];

    if (ce_table_has_column($pdo, 'scrape_runs', 'estimated_cost')) {
        $columns['estimated_cost'] = $costEur;
    }
    if (ce_table_has_column($pdo, 'scrape_runs', 'units_returned')) {
        $columns['units_returned'] = $unitsUsed;
    }
    if (ce_table_has_column($pdo, 'scrape_runs', 'run_at')) {
        $columns['run_at'] = date('Y-m-d H:i:s');
    }

    $colNames = array_keys($columns);
    $placeholders = array_map(static fn (string $col): string => ':' . $col, $colNames);
    $sql = 'INSERT INTO scrape_runs (' . implode(', ', $colNames) . ') VALUES (' . implode(', ', $placeholders) . ')';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($columns);
    } catch (Throwable) {
        // Logging mag een succesvolle Apify-run nooit blokkeren (multi-tenant sync).
    }
}

/** @return string */
function ce_scraper_resolve_actor_id(?string $actorId, ?string $url): string
{
    $actorId = trim((string) $actorId);
    if ($actorId !== '') {
        return str_replace('/', '~', $actorId);
    }

    $url = trim((string) $url);
    if ($url !== '' && preg_match('#/acts/([^/?]+)#i', $url, $matches)) {
        return str_replace('/', '~', rawurldecode($matches[1]));
    }

    return '';
}

/** Apify POST-endpoint uit bron-URL of actor-id (zelfde patroon als n8n HTTP Request). */
function ce_scraper_apify_run_url(string $token, ?string $sourceUrl, ?string $actorId): string
{
    $sourceUrl = trim((string) $sourceUrl);
    if ($sourceUrl !== '' && preg_match('#^https?://api\.apify\.com/v2/acts/#i', $sourceUrl)) {
        $base = preg_replace('#([?&])token=[^&]*#i', '$1', $sourceUrl);
        $base = rtrim((string) $base, '?&');

        return $base . (str_contains($base, '?') ? '&' : '?') . 'token=' . rawurlencode($token);
    }

    $actorId = ce_scraper_resolve_actor_id($actorId, $sourceUrl);
    if ($actorId === '') {
        return '';
    }

    return 'https://api.apify.com/v2/acts/' . rawurlencode($actorId) . '/run-sync-get-dataset-items?token=' . rawurlencode($token);
}

/**
 * Start-run endpoint: wacht op finish en geeft run-object (id, stats, usageTotalUsd, defaultDatasetId).
 * Niet /run-sync — die endpoint retourneert alleen KV-store OUTPUT, geen run-metadata.
 */
function ce_scraper_apify_start_run_url(string $token, ?string $sourceUrl, ?string $actorId, int $waitForFinish = 60): string
{
    $url = ce_scraper_apify_run_url($token, $sourceUrl, $actorId);
    if ($url === '') {
        return '';
    }

    $url = preg_replace('#/run-sync-get-dataset-items#', '/runs', $url) ?? $url;
    $url = preg_replace('#/run-sync#', '/runs', $url) ?? $url;

    if (!preg_match('/[?&]waitForFinish=/', $url)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'waitForFinish=' . max(0, min(60, $waitForFinish));
    }

    return $url;
}

/** @return array<string, mixed>|null */
function ce_apify_fetch_run(string $token, string $runId, int $timeoutSec = 60): ?array
{
    $runId = trim($runId);
    if ($runId === '') {
        return null;
    }

    $url = 'https://api.apify.com/v2/actor-runs/' . rawurlencode($runId) . '?token=' . rawurlencode($token);
    $response = ce_apify_http_json('GET', $url, null, $timeoutSec);
    if (!$response['ok'] || !is_array($response['json'])) {
        return null;
    }

    return ce_apify_unwrap_run($response['json']);
}

/** @return array<string, mixed>|null */
function ce_apify_wait_for_terminal_run(string $token, string $runId, int $maxWaitSec = 240): ?array
{
    $terminal = ['SUCCEEDED', 'FAILED', 'ABORTED', 'TIMED-OUT'];
    $deadline = time() + max(1, $maxWaitSec);

    do {
        $run = ce_apify_fetch_run($token, $runId);
        if ($run === null) {
            return null;
        }

        $status = strtoupper(trim((string) ($run['status'] ?? '')));
        if ($status !== '' && in_array($status, $terminal, true)) {
            return $run;
        }

        if (time() >= $deadline) {
            return $run;
        }

        sleep(2);
    } while (true);
}

/**
 * @return array{ok: bool, http_status: int, json: array<string, mixed>|null, raw: string, error: string|null}
 */
function ce_apify_http_json(string $method, string $url, ?string $body = null, int $timeoutSec = 180): array
{
    $headers = [
        'User-Agent: ContentEngine/1.0',
        'Accept: application/json',
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
        ]);
        $raw = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw)) {
            return [
                'ok' => false,
                'http_status' => $httpStatus,
                'json' => null,
                'raw' => '',
                'error' => $curlError !== '' ? $curlError : 'Apify request mislukt',
            ];
        }

        $decoded = json_decode($raw, true);

        return [
            'ok' => $httpStatus >= 200 && $httpStatus < 300,
            'http_status' => $httpStatus,
            'json' => is_array($decoded) ? $decoded : null,
            'raw' => $raw,
            'error' => null,
        ];
    }

    $headerStr = implode("\r\n", $headers) . "\r\n";
    if ($body !== null) {
        $headerStr .= 'Content-Length: ' . strlen($body) . "\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'timeout' => $timeoutSec,
            'ignore_errors' => true,
            'header' => $headerStr,
            'content' => $body ?? '',
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $httpStatus = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $matches)) {
        $httpStatus = (int) $matches[1];
    }

    if (!is_string($raw)) {
        return [
            'ok' => false,
            'http_status' => $httpStatus,
            'json' => null,
            'raw' => '',
            'error' => 'Apify request mislukt',
        ];
    }

    $decoded = json_decode($raw, true);

    return [
        'ok' => $httpStatus >= 200 && $httpStatus < 300,
        'http_status' => $httpStatus,
        'json' => is_array($decoded) ? $decoded : null,
        'raw' => $raw,
        'error' => null,
    ];
}

/** @return array<string, mixed>|null */
function ce_apify_unwrap_run(?array $decoded): ?array
{
    if (!is_array($decoded)) {
        return null;
    }

    if (isset($decoded['data']) && is_array($decoded['data'])) {
        return $decoded['data'];
    }

    return $decoded;
}

/**
 * @param array<string, mixed> $run
 *
 * @return array{
 *   apify_run_id: string|null,
 *   units_used: float|null,
 *   cost_usd: float|null,
 *   dataset_id: string|null,
 *   run_status: string|null,
 *   status_message: string|null
 * }
 */
function ce_apify_parse_run_metrics(array $run): array
{
    $runId = trim((string) ($run['id'] ?? ''));
    $datasetId = trim((string) ($run['defaultDatasetId'] ?? ''));
    $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
    $statusMessage = trim((string) ($run['statusMessage'] ?? ''));

    $unitsUsed = null;
    if (isset($run['usage']) && is_array($run['usage'])) {
        $usage = $run['usage'];
        if (isset($usage['ACTOR_COMPUTE_UNITS'])) {
            $unitsUsed = (float) $usage['ACTOR_COMPUTE_UNITS'];
        }
    }
    if ($unitsUsed === null && isset($run['stats']) && is_array($run['stats'])) {
        $stats = $run['stats'];
        if (isset($stats['computeUnits'])) {
            $unitsUsed = (float) $stats['computeUnits'];
        }
    }

    $costUsd = null;
    if (isset($run['usageTotalUsd']) && is_numeric($run['usageTotalUsd'])) {
        $costUsd = (float) $run['usageTotalUsd'];
    }

    return [
        'apify_run_id' => $runId !== '' ? $runId : null,
        'units_used' => $unitsUsed,
        'cost_usd' => $costUsd,
        'dataset_id' => $datasetId !== '' ? $datasetId : null,
        'run_status' => $runStatus !== '' ? $runStatus : null,
        'status_message' => $statusMessage !== '' ? $statusMessage : null,
    ];
}

/** @return array{ok: bool, items: array<int, array<string, mixed>>, error: string|null} */
function ce_apify_fetch_dataset_items(string $token, string $datasetId, int $timeoutSec = 120): array
{
    $url = 'https://api.apify.com/v2/datasets/' . rawurlencode($datasetId)
        . '/items?token=' . rawurlencode($token) . '&format=json&limit=1000&clean=1';

    $response = ce_apify_http_json('GET', $url, null, $timeoutSec);
    if (!$response['ok'] || !is_array($response['json'])) {
        $snippet = mb_substr(trim($response['raw']), 0, 200);

        return [
            'ok' => false,
            'items' => [],
            'error' => 'Dataset ophalen mislukt (HTTP ' . $response['http_status'] . '): ' . $snippet,
        ];
    }

    $json = $response['json'];
    if (array_is_list($json)) {
        return ['ok' => true, 'items' => $json, 'error' => null];
    }

    if (isset($json['items']) && is_array($json['items'])) {
        return ['ok' => true, 'items' => $json['items'], 'error' => null];
    }

    return ['ok' => true, 'items' => [], 'error' => null];
}

function ce_apify_usd_to_eur(?float $usd): ?float
{
    if ($usd === null || $usd <= 0) {
        return null;
    }

    return round($usd * 0.92, 4);
}

/** @return array{ok: bool, items: array<int, array<string, mixed>>, status: string, message: string|null, units_used: float|null, cost_eur: float|null, apify_run_id: string|null} */
function ce_scraper_fetch_via_apify_actor(string $token, ?string $sourceUrl, ?string $actorId, array $input): array
{
    $fail = static function (
        string $message,
        ?string $apifyRunId = null,
        ?float $unitsUsed = null,
        ?float $costUsd = null,
    ): array {
        return [
            'ok' => false,
            'items' => [],
            'status' => 'error',
            'message' => $message,
            'units_used' => $unitsUsed,
            'cost_eur' => ce_apify_usd_to_eur($costUsd),
            'apify_run_id' => $apifyRunId,
        ];
    };

    $url = ce_scraper_apify_start_run_url($token, $sourceUrl, $actorId);
    if ($url === '') {
        return $fail('Apify actor URL ontbreekt.');
    }

    $payload = json_encode($input, JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        $payload = '{}';
    }

    $runResponse = ce_apify_http_json('POST', $url, $payload, 300);
    if (!$runResponse['ok'] || !is_array($runResponse['json'])) {
        $snippet = mb_substr(trim($runResponse['raw']), 0, 200);
        $apiError = null;
        if (is_array($runResponse['json']['error'] ?? null)) {
            $apiError = (string) ($runResponse['json']['error']['message'] ?? '');
        } elseif (isset($runResponse['json']['error'])) {
            $apiError = (string) $runResponse['json']['error'];
        }

        return $fail(
            $apiError !== null && $apiError !== ''
                ? $apiError
                : 'Apify run start mislukt (HTTP ' . $runResponse['http_status'] . '): ' . $snippet,
        );
    }

    // Legacy: run-sync-get-dataset-items retourneert direct een items-array (geen run-metadata).
    if (array_is_list($runResponse['json'])) {
        return [
            'ok' => true,
            'items' => $runResponse['json'],
            'status' => 'ok',
            'message' => null,
            'units_used' => null,
            'cost_eur' => null,
            'apify_run_id' => null,
        ];
    }

    $run = ce_apify_unwrap_run($runResponse['json']);
    if ($run === null || !isset($run['id'])) {
        return $fail('Apify run response ongeldig.');
    }

    $runId = trim((string) $run['id']);
    $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
    if ($runStatus === 'RUNNING' || $runStatus === 'READY') {
        $polled = ce_apify_wait_for_terminal_run($token, $runId, 240);
        if ($polled !== null) {
            $run = $polled;
            $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
        }
    }

    $freshRun = ce_apify_fetch_run($token, $runId);
    if ($freshRun !== null) {
        $run = $freshRun;
        $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
    }

    $metrics = ce_apify_parse_run_metrics($run);
    if ($runStatus !== '' && $runStatus !== 'SUCCEEDED') {
        $message = $metrics['status_message'] ?? ('Apify run status: ' . $runStatus);

        return $fail($message, $metrics['apify_run_id'], $metrics['units_used'], $metrics['cost_usd']);
    }

    $datasetId = $metrics['dataset_id'];
    if ($datasetId === null || $datasetId === '') {
        return $fail(
            'Apify run zonder dataset-id.',
            $metrics['apify_run_id'],
            $metrics['units_used'],
            $metrics['cost_usd'],
        );
    }

    $dataset = ce_apify_fetch_dataset_items($token, $datasetId);
    if (!$dataset['ok']) {
        return $fail(
            (string) ($dataset['error'] ?? 'Dataset ophalen mislukt'),
            $metrics['apify_run_id'],
            $metrics['units_used'],
            $metrics['cost_usd'],
        );
    }

    return [
        'ok' => true,
        'items' => $dataset['items'],
        'status' => 'ok',
        'message' => null,
        'units_used' => $metrics['units_used'],
        'cost_eur' => ce_apify_usd_to_eur($metrics['cost_usd']),
        'apify_run_id' => $metrics['apify_run_id'],
    ];
}

/** @return array<int, array<string, mixed>> */
function ce_scraper_normalize_items(array $rawItems, string $sourceId, string $sourceTitle, string $sourceType = 'scraper'): array
{
    $out = [];
    foreach ($rawItems as $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = trim((string) (
            $row['title']
            ?? $row['name']
            ?? $row['headline']
            ?? $row['postText']
            ?? $row['commentary']
            ?? $row['text']
            ?? $row['content']
            ?? ''
        ));
        if ($title === '' && isset($row['author']) && is_array($row['author'])) {
            $title = trim((string) ($row['author']['name'] ?? ''));
        }
        if ($title === '') {
            continue;
        }
        $title = mb_substr(preg_replace('/\s+/u', ' ', $title) ?? $title, 0, 200);
        $url = trim((string) (
            $row['url']
            ?? $row['link']
            ?? $row['pageUrl']
            ?? $row['postUrl']
            ?? $row['linkedinUrl']
            ?? $row['postLink']
            ?? ''
        ));
        $snippet = trim((string) (
            $row['contentSnippet']
            ?? $row['snippet']
            ?? $row['description']
            ?? $row['postText']
            ?? $row['commentary']
            ?? $row['text']
            ?? $row['content']
            ?? ''
        ));
        if ($snippet === $title) {
            $snippet = '';
        }
        $out[] = [
            'title' => $title,
            'url' => $url,
            'contentSnippet' => mb_substr($snippet, 0, 500),
            'source_id' => $sourceId,
            'source_title' => $sourceTitle,
            'source_type' => $sourceType,
        ];
    }

    return $out;
}

/** @return array{ok: bool, items: array<int, array<string, mixed>>, status: string, message: string|null, units_used: float|null, cost_eur: float|null, apify_run_id: string|null} */
function ce_scraper_fetch_via_webhook(string $webhookUrl, string $token): array
{
    $webhookUrl = trim($webhookUrl);
    if ($webhookUrl === '') {
        return ['ok' => false, 'items' => [], 'status' => 'error', 'message' => 'Webhook URL ontbreekt.', 'units_used' => null, 'cost_eur' => null, 'apify_run_id' => null];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 120,
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'User-Agent: ContentEngine/1.0',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ]) . "\r\n",
        ],
    ]);

    $raw = @file_get_contents($webhookUrl, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        return ['ok' => false, 'items' => [], 'status' => 'error', 'message' => 'Scraper webhook gaf geen response.', 'units_used' => null, 'cost_eur' => null, 'apify_run_id' => null];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'items' => [], 'status' => 'error', 'message' => 'Scraper webhook response is geen JSON.', 'units_used' => null, 'cost_eur' => null, 'apify_run_id' => null];
    }

    $items = [];
    if (isset($decoded['items']) && is_array($decoded['items'])) {
        $items = $decoded['items'];
    } elseif (array_is_list($decoded)) {
        $items = $decoded;
    }

    return [
        'ok' => true,
        'items' => $items,
        'status' => 'ok',
        'message' => null,
        'units_used' => isset($decoded['unitsUsed']) ? (float) $decoded['unitsUsed'] : null,
        'cost_eur' => null,
        'apify_run_id' => isset($decoded['runId']) ? (string) $decoded['runId'] : null,
    ];
}

function ce_scraper_linkedin_profile_actor_id(): string
{
    return 'apimaestro~linkedin-batch-profile-posts-scraper';
}

function ce_scraper_linkedin_company_actor_id(): string
{
    return 'apimaestro~linkedin-company-posts-batch-scraper-no-cookies';
}

function ce_scraper_linkedin_is_company_profile(string $raw): bool
{
    return (bool) preg_match('~linkedin\.com/company/~i', trim($raw));
}

/** @return list<string> */
function ce_scraper_linkedin_usernames(string $raw): array
{
    $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('~linkedin\.com/in/([^/?#]+)~i', $part, $matches)) {
            $out[] = $matches[1];
            continue;
        }
        $out[] = ltrim($part, '@/');
    }

    return array_values(array_unique($out));
}

/** Bedrijfspagina-slug, numeriek ID of publieke URL (admin-pad wordt genegeerd). @return list<string> */
function ce_scraper_linkedin_company_names(string $raw): array
{
    $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('~linkedin\.com/company/([^/?#]+)~i', $part, $matches)) {
            $slug = rawurldecode($matches[1]);
            if ($slug !== '' && !str_starts_with(strtolower($slug), 'admin')) {
                $out[] = $slug;
                continue;
            }
        }
        $out[] = ltrim($part, '@/');
    }

    return array_values(array_unique($out));
}

/**
 * Kies Apify-endpoint op basis van persoonlijk vs bedrijfspagina-profiel.
 *
 * @param array<string, mixed> $row
 *
 * @return array{sourceUrl: string, actorId: string, isCompany: bool}
 */
function ce_scraper_resolve_linkedin_apify_target(array $row): array
{
    $profile = trim((string) ($row['scraper_linkedin_profile'] ?? ''));
    $isCompany = $profile !== '' && ce_scraper_linkedin_is_company_profile($profile);
    $sourceUrl = trim((string) ($row['url'] ?? ''));
    $actorId = ce_scraper_resolve_actor_id(
        (string) ($row['scraper_actor_or_task_id'] ?? ''),
        $sourceUrl,
    );

    if (!$isCompany) {
        return [
            'sourceUrl' => $sourceUrl,
            'actorId' => $actorId,
            'isCompany' => false,
        ];
    }

    $companyActor = ce_scraper_linkedin_company_actor_id();

    return [
        'sourceUrl' => 'https://api.apify.com/v2/acts/' . rawurlencode($companyActor) . '/run-sync-get-dataset-items',
        'actorId' => $companyActor,
        'isCompany' => true,
    ];
}

function ce_scraper_is_linkedin_actor(?string $sourceUrl, ?string $actorId): bool
{
    $haystack = strtolower(trim((string) $sourceUrl) . ' ' . trim((string) $actorId));

    return str_contains($haystack, 'linkedin');
}

/** @param array<string, mixed> $row */
function ce_scraper_build_apify_input(PDO $pdo, array $row, ?string $searchLabel): array
{
    $input = [];
    $sourceUrl = (string) ($row['url'] ?? '');
    $actorId = ce_scraper_resolve_actor_id(
        (string) ($row['scraper_actor_or_task_id'] ?? ''),
        $sourceUrl,
    );
    $isLinkedIn = ce_scraper_is_linkedin_actor($sourceUrl, $actorId);
    $profile = trim((string) ($row['scraper_linkedin_profile'] ?? ''));

    if ($isLinkedIn && $profile !== '') {
        if (ce_scraper_linkedin_is_company_profile($profile)) {
            $input['company_names'] = ce_scraper_linkedin_company_names($profile);
            $input['limit'] = 100;
        } else {
            $input['usernames'] = ce_scraper_linkedin_usernames($profile);
            $input['total_posts'] = 100;
            $input['limit'] = 100;
        }
    }

    if ($isLinkedIn) {
        try {
            $password = ce_credential_resolve_plaintext(null, (string) ($row['scraper_linkedin_password_ref'] ?? ''));
        } catch (Throwable) {
            $password = '';
        }
        if ($password !== '') {
            $input['password'] = $password;
            $input['linkedinPassword'] = $password;
            if (str_contains($profile, '@')) {
                $input['email'] = $profile;
                $input['username'] = $profile;
            }
        }
    }

    if (!$isLinkedIn && $searchLabel !== null && trim($searchLabel) !== '') {
        $input['search'] = trim($searchLabel);
        $input['query'] = trim($searchLabel);
    }

    return $input;
}

/** @return array{ok: bool, items: array<int, array<string, mixed>>, run: array<string, mixed>} */
function ce_run_scraper_source(PDO $pdo, string $sourceId, ?string $themeId, ?string $slotId, ?string $searchLabel = null): array
{
    ce_ensure_scrape_runs_schema($pdo);

    $stmt = $pdo->prepare(
        'SELECT id, client_id, category, title, url, scraper_api_token_ref, scraper_actor_or_task_id,
                scraper_webhook_url, scraper_linkedin_profile, scraper_linkedin_password_ref,
                budget_per_unit, max_units_per_run
         FROM sources WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Source not found']];
    }

    if ((string) ($row['category'] ?? '') !== 'scraper' && (string) ($row['category'] ?? '') !== 'linkedin') {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Geen scraper-bron']];
    }

    $themeId = trim((string) ($themeId ?? ''));
    $slotId = trim((string) ($slotId ?? ''));
    if ($themeId !== '' && $slotId === '') {
        $skipMessage = 'LinkedIn/scraper niet tijdens Theme — voorkom content-loop';
        ce_scrape_log_run(
            $pdo,
            $sourceId,
            (string) ($row['client_id'] ?? ''),
            $themeId,
            null,
            'skipped_theme_phase',
            null,
            null,
            0,
            null,
            $skipMessage,
        );

        return ['ok' => true, 'items' => [], 'run' => ['status' => 'skipped_theme_phase', 'message' => $skipMessage]];
    }

    $clientId = (string) ($row['client_id'] ?? '');
    $sourceTitle = (string) ($row['title'] ?? 'Scraper');
    $sourceCategory = (string) ($row['category'] ?? '');
    $budgetPerUnit = $row['budget_per_unit'] !== null ? (float) $row['budget_per_unit'] : null;
    $maxUnits = $row['max_units_per_run'] !== null ? (float) $row['max_units_per_run'] : null;
    if ($maxUnits === null && $sourceCategory === 'linkedin') {
        $maxUnits = 1.0;
    }

    $budget = ce_scraper_budget_allows($maxUnits);
    if (!$budget['allowed']) {
        ce_scrape_log_run($pdo, $sourceId, $clientId, $themeId, $slotId, 'skipped_budget', null, null, 0, null, $budget['reason']);
        return ['ok' => true, 'items' => [], 'run' => ['status' => 'skipped_budget', 'message' => $budget['reason']]];
    }

    try {
        $token = ce_scraper_resolve_apify_token((string) ($row['scraper_api_token_ref'] ?? ''));
    } catch (Throwable $e) {
        ce_scrape_log_run($pdo, $sourceId, $clientId, $themeId, $slotId, 'error', null, null, 0, null, 'Token decrypt mislukt');
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Apify-token kon niet worden gelezen']];
    }

    if ($token === '') {
        ce_scrape_log_run($pdo, $sourceId, $clientId, $themeId, $slotId, 'error', null, null, 0, null, 'Apify-token ontbreekt');
        ce_log_event($pdo, 'error', 'scraper', 'scraper_token_missing', 'Platform APIFY_API_TOKEN niet geconfigureerd', [
            'clientId' => $clientId,
            'sourceId' => $sourceId,
        ]);

        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Apify-token ontbreekt (platform APIFY_API_TOKEN niet geconfigureerd)']];
    }

    $webhook = trim((string) ($row['scraper_webhook_url'] ?? ''));
    $actorId = ce_scraper_resolve_actor_id(
        (string) ($row['scraper_actor_or_task_id'] ?? ''),
        (string) ($row['url'] ?? ''),
    );
    $isLinkedInActor = ce_scraper_is_linkedin_actor(
        (string) ($row['url'] ?? ''),
        $actorId !== '' ? $actorId : (string) ($row['scraper_actor_or_task_id'] ?? ''),
    );
    $profile = trim((string) ($row['scraper_linkedin_profile'] ?? ''));
    if ($isLinkedInActor && $profile === '') {
        ce_scrape_log_run($pdo, $sourceId, $clientId, $themeId, $slotId, 'skipped_config', null, null, 0, null, 'LinkedIn profiel ontbreekt');
        return ['ok' => true, 'items' => [], 'run' => ['status' => 'skipped_config', 'message' => 'LinkedIn profiel ontbreekt']];
    }

    if ($webhook !== '') {
        $fetch = ce_scraper_fetch_via_webhook($webhook, $token);
    } elseif ($actorId !== '' || trim((string) ($row['url'] ?? '')) !== '') {
        $apifyTarget = $isLinkedInActor
            ? ce_scraper_resolve_linkedin_apify_target($row)
            : [
                'sourceUrl' => (string) ($row['url'] ?? ''),
                'actorId' => $actorId,
                'isCompany' => false,
            ];
        $apifyInput = ce_scraper_build_apify_input($pdo, $row, $searchLabel);
        $fetch = ce_scraper_fetch_via_apify_actor(
            $token,
            $apifyTarget['sourceUrl'],
            $apifyTarget['actorId'],
            $apifyInput,
        );
    } else {
        ce_scrape_log_run($pdo, $sourceId, $clientId, $themeId, $slotId, 'skipped_config', null, null, 0, null, 'Geen actor of webhook geconfigureerd');
        return ['ok' => true, 'items' => [], 'run' => ['status' => 'skipped_config', 'message' => 'Geen actor of webhook geconfigureerd']];
    }

    if (!$fetch['ok']) {
        ce_scrape_log_run(
            $pdo,
            $sourceId,
            $clientId,
            $themeId,
            $slotId,
            'error',
            $fetch['units_used'],
            $fetch['cost_eur'],
            0,
            $fetch['apify_run_id'],
            $fetch['message'],
        );
        ce_log_event($pdo, 'error', 'scraper', 'scraper_failed', (string) ($fetch['message'] ?? ''), [
            'clientId' => $clientId,
            'sourceId' => $sourceId,
            'apifyRunId' => $fetch['apify_run_id'] ?? null,
        ]);

        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => $fetch['message']]];
    }

    $unitsUsed = $fetch['units_used'];
    $status = 'ok';
    $message = $fetch['message'];

    if ($maxUnits !== null && $unitsUsed !== null && $unitsUsed > $maxUnits) {
        $status = 'budget_exceeded';
        $message = sprintf('Run gebruikte %.4f units (max %.4f).', $unitsUsed, $maxUnits);
        ce_log_event($pdo, 'warn', 'scraper', 'scraper_budget_exceeded', $message, [
            'clientId' => $clientId,
            'sourceId' => $sourceId,
            'unitsUsed' => $unitsUsed,
            'maxUnits' => $maxUnits,
        ]);
    }

    $costEur = $fetch['cost_eur'];
    if ($costEur === null && $unitsUsed !== null && $budgetPerUnit !== null) {
        $costEur = $unitsUsed * $budgetPerUnit;
    }

    $isLinkedIn = ce_scraper_is_linkedin_actor(
        (string) ($row['url'] ?? ''),
        $actorId !== '' ? $actorId : (string) ($row['scraper_actor_or_task_id'] ?? ''),
    );
    $linkedinIngest = ['ingested' => 0, 'syncedArticles' => 0];
    if ($isLinkedIn && $clientId !== '') {
        require_once __DIR__ . '/linkedin-engagement-repository.php';
        $linkedinIngest = ce_linkedin_ingest_apify_items($pdo, $sourceId, $clientId, $fetch['items']);
    }

    $normalized = ce_scraper_normalize_items(
        $fetch['items'],
        $sourceId,
        $sourceTitle,
        (string) ($row['category'] ?? '') === 'linkedin' ? 'linkedin' : 'scraper',
    );

    require_once __DIR__ . '/mappers.php';
    if ($slotId !== '' && ce_source_is_own_linkedin_page($pdo, $row)) {
        $normalized = [];
    }

    ce_scrape_log_run(
        $pdo,
        $sourceId,
        $clientId,
        $themeId,
        $slotId,
        $status,
        $unitsUsed,
        $costEur,
        count($normalized),
        $fetch['apify_run_id'],
        $message,
    );

    return [
        'ok' => true,
        'items' => $normalized,
        'run' => [
            'status' => $status,
            'message' => $message,
            'units_used' => $unitsUsed,
            'cost_eur' => $costEur,
            'items_count' => count($normalized),
            'apify_run_id' => $fetch['apify_run_id'],
            'linkedin_ingested' => $linkedinIngest['ingested'],
            'linkedin_synced_articles' => $linkedinIngest['syncedArticles'],
        ],
    ];
}

function ce_probe_scraper_source(PDO $pdo, string $sourceId): array
{
    $stmt = $pdo->prepare(
        'SELECT scraper_api_token_ref, scraper_actor_or_task_id, scraper_webhook_url, url,
                scraper_linkedin_profile, scraper_linkedin_password_ref
         FROM sources WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['reachable' => false, 'message' => 'Bron niet gevonden.'];
    }

    try {
        $token = ce_scraper_resolve_apify_token((string) ($row['scraper_api_token_ref'] ?? ''));
    } catch (Throwable) {
        return ['reachable' => false, 'message' => 'Apify-token kon niet worden gelezen.'];
    }

    if ($token === '') {
        return ['reachable' => false, 'message' => 'Apify-token ontbreekt — stel APIFY_API_TOKEN in op de server.'];
    }

    if (!ce_apify_probe_token($token)) {
        return ['reachable' => false, 'message' => 'Apify-token ongeldig of API niet bereikbaar.'];
    }

    $actor = ce_scraper_resolve_actor_id(
        (string) ($row['scraper_actor_or_task_id'] ?? ''),
        (string) ($row['url'] ?? ''),
    );
    $webhook = trim((string) ($row['scraper_webhook_url'] ?? ''));
    $isLinkedIn = ce_scraper_is_linkedin_actor((string) ($row['url'] ?? ''), $actor);
    $profile = trim((string) ($row['scraper_linkedin_profile'] ?? ''));

    if ($isLinkedIn && $profile === '') {
        return ['reachable' => false, 'message' => 'Vul LinkedIn profielnaam in — geen posts ophaalbaar.'];
    }

    if ($actor === '' && $webhook === '') {
        return ['reachable' => false, 'message' => 'Configureer actor of webhook — geen data ophaalbaar.'];
    }

    return ['reachable' => true, 'message' => null];
}
