<?php

declare(strict_types=1);

ini_set('display_errors', '0');

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!is_array($err) || !in_array((int) ($err['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $file = basename((string) ($err['file'] ?? ''));
    $line = (int) ($err['line'] ?? 0);
    echo json_encode([
        'error' => trim((string) ($err['message'] ?? 'Broninspiratie crashte')) . ($file !== '' ? " @{$file}:{$line}" : ''),
    ], JSON_UNESCAPED_UNICODE);
});

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';

try {
    require_once __DIR__ . '/sources-inspiration-repository.php';
} catch (Throwable $e) {
    ce_json_error($e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), 500);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$searchScope = ce_sources_inspiration_normalize_scope($body['searchScope'] ?? 50);
$claimContext = trim((string) ($body['claimContext'] ?? $body['claim'] ?? ''));
$requestedClientId = trim((string) ($body['clientId'] ?? $body['client_id'] ?? ''));
if ($requestedClientId !== '') {
    // Zelfde poort als de rest: een andere client_id wordt 403, de sessie wordt niet omgezet.
    ce_resolve_client_id(ce_db(), $requestedClientId);
}

try {
    if (function_exists('set_time_limit')) {
        set_time_limit(120);
    }
    $result = $claimContext !== ''
        ? ce_sources_inspiration_run_for_claim(ce_db(), $claimContext)
        : ce_sources_inspiration_run(ce_db(), $searchScope);
} catch (Throwable $e) {
    ce_json_error($e->getMessage() !== '' ? $e->getMessage() : 'Broninspiratie mislukt', 500);
}

if (!$result['ok']) {
    ce_json_error((string) ($result['error'] ?? 'Broninspiratie mislukt'), 503);
}

ce_json_response([
    'ok' => true,
    'suggestions' => $result['suggestions'] ?? [],
    'searchScope' => $result['searchScope'] ?? $searchScope,
    'droppedProbeFailed' => (int) ($result['droppedProbeFailed'] ?? 0),
]);
