<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/metrics-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

// Ops-endpoint over tenants heen: alleen met de service-token, niet met een tenantsessie.
if (!ce_reset_token_valid()) {
    ce_json_error('Reset-token vereist', 403);
}

$clientId = trim((string) ($_GET['client_id'] ?? ''));

if ($clientId !== '' && ($_GET['raw'] ?? '') === '1') {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        ce_json_error('Invalid client_id', 400);
    }

    $context = ce_analytics_api_context($pdo, $clientId);
    $source = ce_analytics_source_for_client($pdo, $clientId);
    $out = [
        'ok' => true,
        'clientId' => $clientId,
        'oauthConnected' => ce_analytics_oauth_connected_for_client($pdo, $clientId),
        'sourceFound' => $source !== null,
        'propertyId' => $source['propertyId'] ?? null,
        'contextResolved' => $context !== null,
    ];

    if ($context !== null) {
        $endDate = (new DateTimeImmutable('now'))->format('Y-m-d');
        $startDate = (new DateTimeImmutable('now'))->modify('-180 days')->format('Y-m-d');
        $body = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'screenPageViews']],
            'limit' => 10,
        ];
        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
            . rawurlencode($context['propertyId'])
            . ':runReport';
        $rawResponse = ce_analytics_http_post($url, json_encode($body, JSON_THROW_ON_ERROR), [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $context['accessToken'],
        ]);
        $out['ga4HttpStatus'] = $rawResponse['status'];
        $out['ga4RawBody'] = mb_substr($rawResponse['body'], 0, 1500);
    }

    ce_json_response($out);
}

if ($clientId !== '') {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        ce_json_error('Invalid client_id', 400);
    }

    $debug = filter_var($_GET['debug'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $result = $method === 'POST'
        ? ce_analytics_sync_article_views($pdo, $clientId, 180, $debug)
        : ['matched' => 0, 'checked' => 0, 'note' => 'GET toont alleen status, gebruik POST om te synchroniseren'];

    ce_json_response([
        'ok' => true,
        'clientId' => $clientId,
        'oauthConnected' => ce_analytics_oauth_connected_for_client($pdo, $clientId),
        'result' => $result,
    ]);
}

$clients = $pdo->query('SELECT id, company FROM clients ORDER BY company ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$results = [];
foreach ($clients as $row) {
    $id = (string) ($row['id'] ?? '');
    if ($id === '' || !ce_analytics_oauth_connected_for_client($pdo, $id)) {
        continue;
    }

    $entry = [
        'clientId' => $id,
        'company' => $row['company'] ?? '',
    ];

    if ($method === 'POST') {
        try {
            $entry['result'] = ce_analytics_sync_article_views($pdo, $id);
        } catch (Throwable $e) {
            $entry['error'] = $e->getMessage();
        }
    }

    $results[] = $entry;
}

ce_json_response([
    'ok' => true,
    'clients' => $results,
]);
