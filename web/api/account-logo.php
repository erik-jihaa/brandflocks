<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/account-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo, trim((string) ($_POST['clientId'] ?? '')));
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 404);
}

if (!isset($_FILES['logo']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
    ce_json_error('Geen logo-bestand ontvangen', 400);
}

$file = $_FILES['logo'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    ce_json_error('Upload mislukt', 400);
}

$maxBytes = 2 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    ce_json_error('Logo mag maximaal 2 MB zijn', 400);
}

$originalName = (string) ($file['name'] ?? 'logo');
$ext = ce_logo_extension_from_upload((string) $file['tmp_name'], $originalName);
if ($ext === null) {
    if (ce_upload_looks_like_svg((string) $file['tmp_name'], $originalName)) {
        ce_json_error('SVG-logo\'s worden niet ondersteund. Upload een PNG met transparante achtergrond.', 400);
    }
    ce_json_error('Alleen PNG, JPG of WEBP. Voorkeur: PNG met transparante achtergrond.', 400);
}

$dir = ce_client_logo_dir();
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    ce_json_error('Uploadmap kon niet worden aangemaakt', 500);
}

ce_remove_client_logo_files($clientId);

$filename = $clientId . '.' . $ext;
$target = $dir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    ce_json_error('Logo opslaan mislukt', 500);
}

$logoUrl = '/uploads/logos/' . $filename;
ce_patch_account($pdo, ['clientId' => $clientId, 'logoUrl' => $logoUrl]);

ce_json_response(['ok' => true, 'logoUrl' => $logoUrl]);
