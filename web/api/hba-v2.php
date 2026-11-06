<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/hba-architectuur-check-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!isset($path) || !is_string($path)) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = preg_replace('#^/api#', '', $path) ?: '/';
}

if ($path === '/hba/branches' && $method === 'GET') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    $rows = ce_hba_v2_fetch_branches($pdo);
    $branches = array_map(static fn (array $row): array => [
        'id' => (string) ($row['id'] ?? ''),
        'code' => (string) ($row['branche_code'] ?? ''),
        'label' => (string) ($row['branche_label'] ?? ''),
        'marktArchetype' => $row['markt_archetype'] ?? null,
        'bron' => (string) ($row['bron'] ?? ''),
        'brancheDefinitie' => (string) ($row['branche_definitie'] ?? ''),
        'bronnenKwaliteit' => (string) ($row['bronnen_kwaliteit'] ?? ''),
        'confidenceTotal' => isset($row['confidence_total']) ? (int) $row['confidence_total'] : null,
        'status' => (string) ($row['status'] ?? 'active'),
    ], $rows);
    ce_json_response(['branches' => $branches]);
}

if ($path === '/hba/derive-architecture' && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    ce_json_response(ce_hba_v2_derive_architecture($pdo, $clientId, ce_read_json_body()));
}

if ($path === '/hba/derive-branche' && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    if ($clientId === null) {
        ce_json_error('Geen client gevonden', 400);
    }
    $result = ce_hba_v2_resolve_branche($pdo, $clientId, ce_read_json_body());
    if (!($result['ok'] ?? false) && ($result['code'] ?? '') === 'rate_limit') {
        ce_json_error((string) ($result['error'] ?? 'Daglimiet bereikt'), 429);
    }
    ce_json_response($result);
}

if ($path === '/hba/architecture-check' && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    if ($clientId === null) {
        ce_json_error('Geen client gevonden', 400);
    }
    $result = ce_hba_v2_run_architecture_check($pdo, $clientId, ce_read_json_body());
    if (!($result['ok'] ?? false) && ($result['code'] ?? '') === 'rate_limit') {
        ce_json_error((string) ($result['error'] ?? 'Daglimiet bereikt'), 429);
    }
    ce_json_response($result);
}

if ($path === '/hba/wizard-document' && $method === 'GET') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    if ($clientId === null) {
        ce_json_error('Geen client gevonden', 400);
    }
    require_once __DIR__ . '/hba-wizard-document-repository.php';
    ce_json_response(ce_hba_wizard_document_status($pdo, $clientId));
}

if ($path === '/hba/wizard-document' && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    if ($clientId === null) {
        ce_json_error('Geen client gevonden', 400);
    }
    require_once __DIR__ . '/hba-wizard-document-repository.php';
    if (!isset($_FILES['document']) || !is_array($_FILES['document'])) {
        ce_json_error('Document ontbreekt', 400);
    }
    $result = ce_hba_wizard_document_store_upload($pdo, $clientId, $_FILES['document']);
    if (!($result['ok'] ?? false)) {
        $status = !empty($result['noTextFound']) ? 422 : (!empty($result['code']) && $result['code'] === 'rate_limit' ? 429 : 400);
        ce_json_error((string) ($result['error'] ?? 'Upload mislukt'), $status);
    }
    ce_json_response($result);
}

if ($path === '/hba/calibration-pairs' && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    ce_json_response(ce_hba_v2_generate_calibration_pair($pdo, $clientId, ce_read_json_body()));
}

if ($path === '/hba/theme-verdicts' && $method === 'GET') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    $week = isset($_GET['week']) ? (string) $_GET['week'] : null;
    $themeId = trim((string) ($_GET['theme_id'] ?? $_GET['themeId'] ?? ''));
    $slotId = trim((string) ($_GET['slot_id'] ?? $_GET['slotId'] ?? ''));
    $themeIds = $themeId !== '' ? [$themeId] : ($slotId !== '' ? [$slotId] : []);
    ce_json_response(ce_hba_v2_load_theme_verdicts($pdo, $clientId, $week !== '' ? $week : null, $themeIds));
}

if ($path === '/hba/theme-verdicts' && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    $body = ce_read_json_body();
    $week = isset($body['week']) ? (string) $body['week'] : null;
    $themeId = trim((string) ($body['theme_id'] ?? $body['themeId'] ?? ''));
    $slotId = trim((string) ($body['slot_id'] ?? $body['slotId'] ?? ''));
    $themeIds = $themeId !== '' ? [$themeId] : ($slotId !== '' ? [$slotId] : []);
    ce_json_response(ce_hba_v2_run_theme_verdicts($pdo, $clientId, $week, $themeIds));
}

if ($path === '/hba/research-verdicts' && $method === 'GET') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    $slot = isset($_GET['slot']) ? (string) $_GET['slot'] : '';
    try {
        ce_json_response(ce_hba_v2_load_research_verdicts($pdo, $clientId, $slot));
    } catch (Throwable $e) {
        ce_json_response(['ok' => false, 'error' => $e->getMessage()]);
    }
}

if ($path === '/hba/research-verdicts' && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    $body = ce_read_json_body();
    $slot = isset($body['slot']) ? (string) $body['slot'] : (isset($_GET['slot']) ? (string) $_GET['slot'] : '');
    try {
        ce_json_response(ce_hba_v2_run_research_verdicts($pdo, $clientId, $slot));
    } catch (Throwable $e) {
        ce_json_response(['ok' => false, 'error' => $e->getMessage()]);
    }
}

if ($path === '/hba/progress' && $method === 'GET') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    ce_json_response(ce_hba_v2_progress($pdo, $clientId));
}

if ($path === '/hba/architecture/complete' && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    ce_hba_v2_mark_architecture_complete($pdo, $clientId);
    ce_json_response(['ok' => true]);
}

if (preg_match('#^/hba/article-hints/([0-9a-f-]{36})$#i', $path, $m) && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    ce_json_response(ce_hba_v2_run_article_hint($pdo, $clientId, $m[1], ce_read_json_body()));
}

if (preg_match('#^/hba/article-hints/([0-9a-f-]{36})/apply$#i', $path, $m) && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    ce_json_response(ce_hba_v2_apply_hint($pdo, $clientId, $m[1], ce_read_json_body()));
}

if (preg_match('#^/hba/article-hints/([0-9a-f-]{36})/ignore$#i', $path, $m) && $method === 'POST') {
    $pdo = ce_db();
    $clientId = ce_resolve_client_id($pdo);
    ce_json_response(ce_hba_v2_ignore_hint($pdo, $clientId, $m[1]));
}

ce_json_error('Not found', 404);
