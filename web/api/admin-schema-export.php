<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];

$lines = [
    '-- Content Engine — schema export from production MySQL',
    '-- Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC',
    '-- Source: GET /api/admin/schema-export (SHOW CREATE TABLE via PDO on server)',
    '-- Regenerate: ./scripts/export-production-schema.sh',
    '',
    'SET NAMES utf8mb4;',
    'SET FOREIGN_KEY_CHECKS=0;',
    '',
];

foreach ($tables as $table) {
    $table = (string) $table;
    if ($table === '') {
        continue;
    }
    $stmt = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if (!$row) {
        continue;
    }
    $create = $row['Create Table'] ?? $row['Create View'] ?? null;
    if (!is_string($create) || $create === '') {
        continue;
    }
    $lines[] = 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`;';
    $lines[] = $create . ';';
    $lines[] = '';
}

$lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
$lines[] = '';

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/session-repository.php';
ce_close_session_if_active();

echo implode("\n", $lines);
exit;
