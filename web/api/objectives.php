<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/objectives-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    ce_json_response(ce_fetch_objectives_payload($pdo));
}

if ($method === 'POST') {
    $body = ce_read_json_body();
    try {
        $created = ce_create_objective($pdo, $body);
        ce_json_response(array_merge(ce_fetch_objectives_payload($pdo), $created));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

if ($method === 'PUT') {
    $body = ce_read_json_body();
    $items = $body['objectives'] ?? [];
    if (!is_array($items)) {
        ce_json_error('objectives array vereist', 400);
    }
    try {
        ce_json_response(ce_save_objectives($pdo, $items));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 400);
    }
}

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    if (!isset($body['order']) || !is_array($body['order'])) {
        ce_json_error('order array vereist voor bulk reorder', 400);
    }
    try {
        ce_json_response(ce_reorder_objectives($pdo, $body['order']));
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
