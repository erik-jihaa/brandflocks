<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/campagne-preview-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();
$clientId = ce_default_client_id($pdo);

if ($method === 'GET') {
    ce_json_response([
        'campaigns' => ce_campagne_preview_list($pdo, $clientId),
    ]);
}

if ($method === 'POST') {
    $body = ce_read_json_body();
    try {
        ce_json_response(ce_campagne_preview_create($pdo, $body));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
