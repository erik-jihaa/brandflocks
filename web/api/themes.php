<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    $week = isset($_GET['week']) ? (string) $_GET['week'] : null;
    ce_json_response(ce_fetch_themes($pdo, $week));
}

if ($method === 'POST') {
    $body = ce_read_json_body();
    try {
        ce_json_response(ce_create_theme($pdo, $body));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 400);
    }
}

ce_json_error('Method not allowed', 405);
