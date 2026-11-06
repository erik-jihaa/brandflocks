<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/linkedin-oauth-helper.php';
require_once __DIR__ . '/publish-settings-repository.php';

$code = trim((string) ($_GET['code'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

if ($error !== '') {
    $description = trim((string) ($_GET['error_description'] ?? ''));
    $message = $description !== '' ? $error . ': ' . $description : $error;
    header('Location: ' . ce_linkedin_oauth_frontend_redirect('linkedin_oauth_error=' . rawurlencode($message)));
    exit;
}

if ($code === '' || $state === '') {
    ce_json_error('OAuth callback onvolledig', 400);
}

try {
    $pdo = ce_db();
    $clientId = ce_linkedin_oauth_handle_callback($pdo, $code, $state);
    ce_linkedin_persist_publish_extra_row($pdo, $clientId);
    header('Location: ' . ce_linkedin_oauth_frontend_redirect('linkedin_connected=1'));
    exit;
} catch (Throwable $e) {
    header('Location: ' . ce_linkedin_oauth_frontend_redirect('linkedin_oauth_error=' . rawurlencode($e->getMessage())));
    exit;
}
