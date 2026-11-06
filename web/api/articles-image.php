<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/article-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$variant = trim((string) ($_POST['variant'] ?? $_GET['variant'] ?? ''));
$galleryImageId = trim((string) ($_POST['galleryImageId'] ?? ''));
$pdo = ce_db();

try {
    if ($method === 'DELETE') {
        ce_json_response(ce_delete_article_image($pdo, $slotId, $variant));
    }

    if ($method !== 'POST') {
        ce_json_error('Method not allowed', 405);
    }

    if ($galleryImageId !== '') {
        ce_json_response(ce_attach_gallery_image_to_article($pdo, $slotId, $variant, $galleryImageId));
    }

    ce_json_response(ce_upload_article_image($pdo, $slotId, $variant));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 500);
}
