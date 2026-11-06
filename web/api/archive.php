<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/archive-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$q = $query !== '' ? $query : null;
ce_json_response([
    'items' => ce_fetch_archive_items($pdo, $q),
    'reports' => ce_fetch_archive_reports($pdo, $q),
]);
