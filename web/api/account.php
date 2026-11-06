<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/account-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    $clientId = isset($_GET['client_id']) ? (string) $_GET['client_id'] : null;
    ce_json_response(ce_fetch_account($pdo, $clientId));
}

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    try {
        ce_json_response(ce_patch_account($pdo, $body));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
