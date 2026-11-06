<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/source-folders-repository.php';
require_once __DIR__ . '/auth-guard.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$folderId = trim((string) ($_GET['folder_id'] ?? ''));

if (!preg_match('/^[0-9a-f-]{36}$/i', $folderId)) {
    ce_json_error('Invalid folder_id', 400);
}

$pdo = ce_db();

// Deze route draait vóór de centrale inlogcontrole in index.php.
ce_require_auth($pdo);

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    try {
        $folder = ce_update_source_folder($pdo, $folderId, $body);
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    }

    if ($folder === null) {
        ce_json_error('Map niet gevonden', 404);
    }

    ce_json_response($folder);
}

if ($method === 'DELETE') {
    if (!ce_delete_source_folder($pdo, $folderId)) {
        ce_json_error('Map niet gevonden', 404);
    }

    ce_json_response(['ok' => true]);
}

ce_json_error('Method not allowed', 405);
