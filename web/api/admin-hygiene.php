<?php

declare(strict_types=1);

/**
 * Admin — hygiënefactoren per contextprofiel (§6, server-side only).
 * GET  ?contextprofiel=b2b_complex_sale           — status
 * POST { contextprofiel, branche_code?, activate } — seed uit branche-bibliotheek
 * POST { list_id, action: "activate" }             — lijst activeren
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hygiene-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $contextprofiel = trim((string) ($_GET['contextprofiel'] ?? 'b2b_complex_sale'));
    if (!ce_hygiene_contextprofiel_valid($contextprofiel)) {
        ce_json_error('Onbekend contextprofiel', 400);
    }

    ce_json_response(ce_hygiene_admin_status($pdo, $contextprofiel));
}

if ($method !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$action = trim((string) ($body['action'] ?? ''));

if ($action === 'activate') {
    $listId = trim((string) ($body['list_id'] ?? $body['listId'] ?? ''));
    if ($listId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $listId)) {
        ce_json_error('list_id ontbreekt of is ongeldig', 400);
    }
    $result = ce_hygiene_activate_list($pdo, $listId);
    if (empty($result['ok'])) {
        ce_json_error((string) ($result['error'] ?? 'Activeren mislukt'), 400);
    }
    ce_json_response($result);
}

$contextprofiel = trim((string) ($body['contextprofiel'] ?? 'b2b_complex_sale'));
if (!ce_hygiene_contextprofiel_valid($contextprofiel)) {
    ce_json_error('Onbekend contextprofiel', 400);
}

$brancheCode = trim((string) ($body['branche_code'] ?? $body['brancheCode'] ?? ''));
if ($brancheCode === '') {
    $brancheCode = CE_HYGIENE_PROFILE_BRANCH_DEFAULTS[$contextprofiel] ?? '';
}
if ($brancheCode === '') {
    ce_json_error('branche_code is verplicht voor dit contextprofiel', 400);
}

$activate = !empty($body['activate']);
$result = ce_hygiene_seed_list_from_branche($pdo, $contextprofiel, $brancheCode, $activate);
if (empty($result['ok'])) {
    ce_json_error((string) ($result['error'] ?? 'Seed mislukt'), 400);
}

ce_json_response($result);
