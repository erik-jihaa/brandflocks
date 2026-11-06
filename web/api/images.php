<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-images-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo);
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 404);
}

if ($method === 'GET') {
    ce_json_response(['images' => ce_fetch_client_images($pdo, $clientId)]);
}

if ($method === 'POST') {
    try {
        $image = ce_upload_client_image($pdo, $clientId);
        ce_json_response([
            'ok' => true,
            'image' => $image,
            'images' => ce_fetch_client_images($pdo, $clientId),
        ]);
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 500);
    }
}

ce_json_error('Method not allowed', 405);
