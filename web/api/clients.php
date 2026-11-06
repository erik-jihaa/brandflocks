<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/account-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$query = isset($_GET['q']) ? (string) $_GET['q'] : null;
ce_json_response(ce_search_clients($pdo, $query));
