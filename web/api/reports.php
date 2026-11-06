<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/report-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    ce_json_response(ce_fetch_reports($pdo));
}

if ($method === 'POST') {
    try {
        ce_json_response(ce_generate_report($pdo, ce_read_json_body()));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 502);
    }
}

ce_json_error('Method not allowed', 405);
