<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

function li_linkedin_company_actor_id(): string
{
    return 'apimaestro~linkedin-company-posts-batch-scraper-no-cookies';
}

function li_linkedin_school_actor_id(): string
{
    if (function_exists('li_config')) {
        $configured = trim((string) li_config('school_actor_id', ''));
        if ($configured !== '') {
            return str_replace('/', '~', $configured);
        }
    }

    return li_linkedin_company_actor_id();
}

function li_linkedin_is_company_url(string $raw): bool
{
    $page = li_linkedin_parse_page_url($raw);

    return ($page['type'] ?? null) === 'company';
}

function li_linkedin_is_supported_url(string $raw): bool
{
    return li_linkedin_parse_page_url($raw) !== null;
}

/**
 * @return array{type: 'company'|'school', slug: string, url: string}|null
 */
function li_linkedin_parse_page_url(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    if (preg_match('~linkedin\.com/company/([^/?#]+)~i', $raw, $matches)) {
        $slug = rawurldecode($matches[1]);
        if ($slug === '' || str_starts_with(strtolower($slug), 'admin')) {
            return null;
        }

        return [
            'type' => 'company',
            'slug' => $slug,
            'url' => li_linkedin_company_url($slug),
        ];
    }

    if (preg_match('~linkedin\.com/school/([^/?#]+)~i', $raw, $matches)) {
        $slug = rawurldecode($matches[1]);
        if ($slug === '') {
            return null;
        }

        return [
            'type' => 'school',
            'slug' => $slug,
            'url' => li_linkedin_school_url($slug),
        ];
    }

    return null;
}

/** @return list<string> */
function li_linkedin_company_slugs(string $raw): array
{
    $page = li_linkedin_parse_page_url($raw);
    if ($page !== null) {
        return [$page['slug']];
    }

    $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $out[] = ltrim($part, '@/');
    }

    return array_values(array_unique($out));
}

function li_linkedin_company_url(string $slug): string
{
    return 'https://www.linkedin.com/company/' . rawurlencode($slug) . '/';
}

function li_linkedin_school_url(string $slug): string
{
    return 'https://www.linkedin.com/school/' . rawurlencode($slug) . '/';
}

function li_linkedin_page_type_label(string $type): string
{
    return $type === 'school' ? 'schoolpagina' : 'bedrijfspagina';
}

function li_linkedin_page_type_from_url(string $url): string
{
    return str_contains(strtolower($url), '/school/') ? 'school' : 'company';
}

/**
 * @return array{ok: bool, http_status: int, json: array<string, mixed>|list<mixed>|null, raw: string, error: string|null}
 */
function li_apify_http_json(
    string $method,
    string $url,
    ?string $body = null,
    int $timeoutSec = 180,
    string $bearerToken = '',
): array {
    $headers = [
        'User-Agent: LinkedInAnalyse/1.0',
        'Accept: application/json',
    ];
    if ($bearerToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }
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

    return [
        'ok' => false,
        'http_status' => 0,
        'json' => null,
        'raw' => '',
        'error' => 'cURL ontbreekt',
    ];
}

/** @return array<string, mixed>|null */
function li_apify_unwrap_run(?array $decoded): ?array
{
    if (!is_array($decoded)) {
        return null;
    }

    if (isset($decoded['data']) && is_array($decoded['data'])) {
        return $decoded['data'];
    }

    return $decoded;
}

/** @return array<string, mixed>|null */
function li_apify_fetch_run(string $token, string $runId, int $timeoutSec = 60): ?array
{
    $runId = trim($runId);
    if ($runId === '') {
        return null;
    }

    $url = 'https://api.apify.com/v2/actor-runs/' . rawurlencode($runId);
    $response = li_apify_http_json('GET', $url, null, $timeoutSec, $token);
    if (!$response['ok'] || !is_array($response['json'])) {
        return null;
    }

    return li_apify_unwrap_run($response['json']);
}

/** @return array<string, mixed>|null */
function li_apify_wait_for_terminal_run(string $token, string $runId, int $maxWaitSec = 240): ?array
{
    $terminal = ['SUCCEEDED', 'FAILED', 'ABORTED', 'TIMED-OUT'];
    $deadline = time() + max(1, $maxWaitSec);

    do {
        $run = li_apify_fetch_run($token, $runId);
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

function li_apify_abort_run(string $token, string $runId): void
{
    $runId = trim($runId);
    if ($token === '' || $runId === '') {
        return;
    }

    $url = 'https://api.apify.com/v2/actor-runs/' . rawurlencode($runId) . '/abort';
    li_apify_http_json('POST', $url, '{}', 30, $token);
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
function li_apify_parse_run_metrics(array $run): array
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
function li_apify_fetch_dataset_items(string $token, string $datasetId, int $timeoutSec = 120): array
{
    $url = 'https://api.apify.com/v2/datasets/' . rawurlencode($datasetId)
        . '/items?format=json&limit=1000&clean=1';

    $response = li_apify_http_json('GET', $url, null, $timeoutSec, $token);
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

function li_apify_start_run_url(
    string $actorId,
    int $waitForFinish = 0,
    ?int $timeoutSec = null,
    ?float $maxTotalChargeUsd = null,
): string {
    $actorId = str_replace('/', '~', trim($actorId));
    $url = 'https://api.apify.com/v2/acts/' . rawurlencode($actorId) . '/runs';

    // Run Actor query params — niet in de actor-invoer (Apify API v2).
    // Bron: https://docs.apify.com/api/v2/act-runs-post (timeout, maxTotalChargeUsd)
    $query = [
        'waitForFinish' => max(0, min(60, $waitForFinish)),
    ];
    if ($timeoutSec !== null && $timeoutSec > 0) {
        $query['timeout'] = $timeoutSec;
    }
    if ($maxTotalChargeUsd !== null && $maxTotalChargeUsd > 0) {
        $query['maxTotalChargeUsd'] = round($maxTotalChargeUsd, 4);
    }

    return $url . '?' . http_build_query($query);
}

/**
 * @param array<string, mixed> $input
 *
 * @return array{ok: bool, apify_run_id: string|null, message: string|null}
 */
function li_apify_start_actor_run(
    string $token,
    string $actorId,
    array $input,
    int $timeoutSec = 120,
    ?float $maxTotalChargeUsd = null,
): array {
    if ($token === '') {
        return ['ok' => false, 'apify_run_id' => null, 'message' => 'Apify API-token ontbreekt in config.local.php'];
    }

    $payload = json_encode($input, JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        $payload = '{}';
    }

    $url = li_apify_start_run_url($actorId, 0, max(1, $timeoutSec), $maxTotalChargeUsd);
    $runResponse = li_apify_http_json('POST', $url, $payload, 60, $token);

    if (!$runResponse['ok'] || !is_array($runResponse['json'])) {
        $snippet = mb_substr(trim($runResponse['raw']), 0, 200);
        $apiError = null;
        if (is_array($runResponse['json']['error'] ?? null)) {
            $apiError = (string) ($runResponse['json']['error']['message'] ?? '');
        }

        return [
            'ok' => false,
            'apify_run_id' => null,
            'message' => $apiError !== null && $apiError !== ''
                ? $apiError
                : 'Apify run start mislukt (HTTP ' . $runResponse['http_status'] . '): ' . $snippet,
        ];
    }

    if (array_is_list($runResponse['json'])) {
        return ['ok' => false, 'apify_run_id' => null, 'message' => 'Apify start gaf onverwachte dataset terug.'];
    }

    $run = li_apify_unwrap_run($runResponse['json']);
    $runId = trim((string) ($run['id'] ?? ''));
    if ($runId === '') {
        return ['ok' => false, 'apify_run_id' => null, 'message' => 'Apify run response ongeldig.'];
    }

    return ['ok' => true, 'apify_run_id' => $runId, 'message' => null];
}

/**
 * @return array{
 *   ok: bool,
 *   items: array<int, array<string, mixed>>,
 *   status: string,
 *   message: string|null,
 *   units_used: float|null,
 *   cost_usd: float|null,
 *   apify_run_id: string|null
 * }
 */
function li_apify_collect_actor_run(string $token, string $runId, int $maxWaitSec = 20): array
{
    $fail = static function (
        string $message,
        ?string $apifyRunId = null,
        ?float $unitsUsed = null,
        ?float $costUsd = null,
        string $status = 'error',
    ): array {
        return [
            'ok' => false,
            'items' => [],
            'status' => $status,
            'message' => $message,
            'units_used' => $unitsUsed,
            'cost_usd' => $costUsd,
            'apify_run_id' => $apifyRunId,
        ];
    };

    if ($token === '') {
        return $fail('Apify API-token ontbreekt in config.local.php');
    }

    $runId = trim($runId);
    if ($runId === '') {
        return $fail('Apify run-id ontbreekt.');
    }

    $run = li_apify_wait_for_terminal_run($token, $runId, max(5, min(120, $maxWaitSec)));
    if ($run === null) {
        return $fail('Apify run status ophalen mislukt.', $runId, null, null, 'running');
    }

    $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
    if ($runStatus === 'RUNNING' || $runStatus === 'READY') {
        return $fail('Apify run is nog bezig — even geduld.', $runId, null, null, 'running');
    }

    $freshRun = li_apify_fetch_run($token, $runId);
    if ($freshRun !== null) {
        $run = $freshRun;
        $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
    }

    $metrics = li_apify_parse_run_metrics($run);
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

    $dataset = li_apify_fetch_dataset_items($token, $datasetId, 60);
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
        'cost_usd' => $metrics['cost_usd'],
        'apify_run_id' => $metrics['apify_run_id'],
    ];
}

/**
 * @param array<string, mixed> $input
 *
 * @return array{
 *   ok: bool,
 *   items: array<int, array<string, mixed>>,
 *   status: string,
 *   message: string|null,
 *   units_used: float|null,
 *   cost_usd: float|null,
 *   apify_run_id: string|null
 * }
 */
function li_apify_execute_actor_run(
    string $token,
    string $actorId,
    array $input,
    int $timeoutSec = 120,
    ?float $maxTotalChargeUsd = null,
): array {
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
            'cost_usd' => $costUsd,
            'apify_run_id' => $apifyRunId,
        ];
    };

    if ($token === '') {
        return $fail('Apify API-token ontbreekt in config.local.php');
    }

    $payload = json_encode($input, JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        $payload = '{}';
    }

    $url = li_apify_start_run_url($actorId, 0, max(1, $timeoutSec), $maxTotalChargeUsd);
    $runResponse = li_apify_http_json('POST', $url, $payload, min(300, max(60, $timeoutSec)), $token);

    if (!$runResponse['ok'] || !is_array($runResponse['json'])) {
        $snippet = mb_substr(trim($runResponse['raw']), 0, 200);
        $apiError = null;
        if (is_array($runResponse['json']['error'] ?? null)) {
            $apiError = (string) ($runResponse['json']['error']['message'] ?? '');
        }

        return $fail(
            $apiError !== null && $apiError !== ''
                ? $apiError
                : 'Apify run start mislukt (HTTP ' . $runResponse['http_status'] . '): ' . $snippet,
        );
    }

    if (array_is_list($runResponse['json'])) {
        return [
            'ok' => true,
            'items' => $runResponse['json'],
            'status' => 'ok',
            'message' => null,
            'units_used' => null,
            'cost_usd' => null,
            'apify_run_id' => null,
        ];
    }

    $run = li_apify_unwrap_run($runResponse['json']);
    if ($run === null || !isset($run['id'])) {
        return $fail('Apify run response ongeldig.');
    }

    $runId = trim((string) $run['id']);
    $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
    if ($runStatus === 'RUNNING' || $runStatus === 'READY' || $runStatus === '') {
        $polled = li_apify_wait_for_terminal_run($token, $runId, $timeoutSec);
        if ($polled !== null) {
            $run = $polled;
            $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
        }
    }

    if ($runStatus === 'RUNNING' || $runStatus === 'READY') {
        li_apify_abort_run($token, $runId);
        $aborted = li_apify_fetch_run($token, $runId);
        if ($aborted !== null) {
            $run = $aborted;
            $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
        }
    }

    $freshRun = li_apify_fetch_run($token, $runId);
    if ($freshRun !== null) {
        $run = $freshRun;
        $runStatus = strtoupper(trim((string) ($run['status'] ?? '')));
    }

    $metrics = li_apify_parse_run_metrics($run);
    if ($runStatus !== '' && $runStatus !== 'SUCCEEDED') {
        $message = $metrics['status_message'] ?? ('Apify run status: ' . $runStatus);
        if ($runStatus === 'ABORTED' || $runStatus === 'TIMED-OUT') {
            $message = 'Apify run time-out (>' . $timeoutSec . ' s) — run gestopt.';
        }

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

    $dataset = li_apify_fetch_dataset_items($token, $datasetId);
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
        'cost_usd' => $metrics['cost_usd'],
        'apify_run_id' => $metrics['apify_run_id'],
    ];
}

/**
 * @return array{
 *   ok: bool,
 *   items: array<int, array<string, mixed>>,
 *   status: string,
 *   message: string|null,
 *   units_used: float|null,
 *   cost_usd: float|null,
 *   apify_run_id: string|null
 * }
 */
function li_apify_run_company_scan(
    string $token,
    string $companySlug,
    int $maxPosts,
    int $timeoutSec = 120,
    ?float $maxTotalChargeUsd = null,
): array {
    return li_apify_execute_actor_run($token, li_linkedin_company_actor_id(), [
        'company_names' => [$companySlug],
        'limit' => max(1, min(30, $maxPosts)),
    ], $timeoutSec, $maxTotalChargeUsd);
}

function li_apify_school_scan_input(string $schoolSlug, int $maxPosts): array
{
    $limit = max(1, min(30, $maxPosts));

    return [
        'company_names' => [li_linkedin_school_url($schoolSlug)],
        'limit' => $limit,
    ];
}

/**
 * @return array{
 *   ok: bool,
 *   items: array<int, array<string, mixed>>,
 *   status: string,
 *   message: string|null,
 *   units_used: float|null,
 *   cost_usd: float|null,
 *   apify_run_id: string|null
 * }
 */
function li_apify_run_school_scan(
    string $token,
    string $schoolSlug,
    int $maxPosts,
    int $timeoutSec = 120,
    ?float $maxTotalChargeUsd = null,
): array {
    return li_apify_run_company_scan($token, li_linkedin_school_url($schoolSlug), $maxPosts, $timeoutSec, $maxTotalChargeUsd);
}

/**
 * @return array{
 *   ok: bool,
 *   items: array<int, array<string, mixed>>,
 *   status: string,
 *   message: string|null,
 *   units_used: float|null,
 *   cost_usd: float|null,
 *   apify_run_id: string|null
 * }
 */
function li_apify_run_page_scan(
    string $token,
    string $pageType,
    string $slug,
    int $maxPosts,
    int $timeoutSec = 120,
    ?float $maxTotalChargeUsd = null,
): array {
    if ($pageType === 'school') {
        return li_apify_run_school_scan($token, $slug, $maxPosts, $timeoutSec, $maxTotalChargeUsd);
    }

    return li_apify_run_company_scan($token, $slug, $maxPosts, $timeoutSec, $maxTotalChargeUsd);
}

/**
 * @param array<string, mixed> $input
 *
 * @return array{ok: bool, apify_run_id: string|null, message: string|null}
 */
function li_apify_start_page_scan(
    string $token,
    string $pageType,
    string $slug,
    int $maxPosts,
    int $timeoutSec = 120,
    ?float $maxTotalChargeUsd = null,
): array {
    if ($token === '') {
        return ['ok' => false, 'apify_run_id' => null, 'message' => 'Apify API-token ontbreekt in config.local.php'];
    }

    if ($pageType === 'school') {
        return li_apify_start_actor_run(
            $token,
            li_linkedin_school_actor_id(),
            li_apify_school_scan_input($slug, $maxPosts),
            $timeoutSec,
            $maxTotalChargeUsd,
        );
    }

    return li_apify_start_actor_run($token, li_linkedin_company_actor_id(), [
        'company_names' => [$slug],
        'limit' => max(1, min(30, $maxPosts)),
    ], $timeoutSec, $maxTotalChargeUsd);
}

/** @return array<string, mixed> */
function li_apify_collect_page_scan(string $token, string $runId, int $maxWaitSec = 20): array
{
    return li_apify_collect_actor_run($token, $runId, $maxWaitSec);
}
