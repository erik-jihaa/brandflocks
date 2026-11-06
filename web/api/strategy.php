<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    ce_json_response(ce_fetch_strategy($pdo));
}

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    try {
        ce_json_response(ce_patch_strategy($pdo, $body));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
