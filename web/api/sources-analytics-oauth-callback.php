<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/analytics-oauth-helper.php';
require_once __DIR__ . '/calendar-oauth-helper.php';
require_once __DIR__ . '/drive-oauth-helper.php';

$code = trim((string) ($_GET['code'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

ce_session_start();
$pdo = ce_db();
$flow = (string) ($_SESSION['google_oauth_flow'] ?? '');
$calendarSourceId = (string) ($_SESSION['calendar_oauth_source_id'] ?? '');
$analyticsSourceId = (string) ($_SESSION['analytics_oauth_source_id'] ?? '');
$driveSourceId = (string) ($_SESSION['drive_oauth_source_id'] ?? '');
$isDrive = $flow === 'drive' || ($flow === '' && $driveSourceId !== '' && $calendarSourceId === '' && $analyticsSourceId === '');
$isCalendar = !$isDrive && ($flow === 'calendar' || ($flow === '' && $calendarSourceId !== '' && $analyticsSourceId === ''));
$sourceId = $isDrive ? $driveSourceId : ($isCalendar ? $calendarSourceId : $analyticsSourceId);
$redirect = static fn (string $id, string $query = ''): string => ce_google_oauth_frontend_redirect($id, $query);

if ($error !== '') {
    ce_google_oauth_restore_client_from_session($pdo);
    header('Location: ' . $redirect($sourceId, 'oauth_error=' . rawurlencode($error)));
    exit;
}

if ($code === '' || $state === '') {
    ce_json_error('OAuth callback onvolledig', 400);
}

try {
    $oauthClientId = ce_google_oauth_restore_client_from_session($pdo);
    $connectedId = $isDrive
        ? ce_drive_oauth_handle_callback($pdo, $code, $state)
        : ($isCalendar
            ? ce_calendar_oauth_handle_callback($pdo, $code, $state)
            : ce_analytics_oauth_handle_callback($pdo, $code, $state));
    $source = ce_fetch_source($pdo, $connectedId, $oauthClientId);
    if (!$source) {
        throw new RuntimeException('Bron niet gevonden na koppeling — log opnieuw in en probeer opnieuw.');
    }
    unset($_SESSION['google_oauth_flow'], $_SESSION['google_oauth_client_id']);
    header('Location: ' . $redirect((string) $source['id'], 'connected=1'));
    exit;
} catch (Throwable $e) {
    ce_google_oauth_restore_client_from_session($pdo);
    unset($_SESSION['google_oauth_flow'], $_SESSION['google_oauth_client_id']);
    $id = $sourceId !== '' ? $sourceId : '';
    header('Location: ' . $redirect($id, 'oauth_error=' . rawurlencode($e->getMessage())));
    exit;
}
