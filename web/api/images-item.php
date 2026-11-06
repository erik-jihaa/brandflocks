<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-images-repository.php';

$imageId = $_GET['image_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $imageId)) {
    ce_json_error('Invalid id', 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo);
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 404);
}

if ($method === 'DELETE') {
    if (!ce_delete_client_image($pdo, $clientId, $imageId)) {
        ce_json_error('Afbeelding niet gevonden', 404);
    }
    ce_json_response([
        'ok' => true,
        'images' => ce_fetch_client_images($pdo, $clientId),
    ]);
}

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    $updated = ce_patch_client_image($pdo, $clientId, $imageId, $body);
    if ($updated === null) {
        ce_json_error('Afbeelding niet gevonden', 404);
    }
    ce_json_response([
        'ok' => true,
        'image' => $updated,
        'images' => ce_fetch_client_images($pdo, $clientId),
    ]);
}

ce_json_error('Method not allowed', 405);
