<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/source-folders-repository.php';
require_once __DIR__ . '/auth-guard.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

// Deze route draait vóór de centrale inlogcontrole in index.php.
ce_require_auth($pdo);

if ($method === 'GET') {
    ce_json_response(['folders' => ce_fetch_source_folders($pdo)]);
}

if ($method === 'POST') {
    $body = ce_read_json_body();
    try {
        $folder = ce_create_source_folder(
            $pdo,
            (string) ($body['name'] ?? ''),
            null,
            (string) ($body['objectiveId'] ?? $body['objective_id'] ?? ''),
        );
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 503);
    }

    ce_json_response($folder, 201);
}

ce_json_error('Method not allowed', 405);
