<?php

declare(strict_types=1);

require_once __DIR__ . '/hygiene-repository.php';
require_once __DIR__ . '/hygiene-scrape-repository.php';
require_once __DIR__ . '/hygiene-distill-repository.php';

/** @param array<string, mixed> $body */
function ce_platform_hygiene_handle(PDO $pdo, string $path, string $method, array $body): void
{
    if ($path === '/platform/hygiene' && $method === 'GET') {
        $contextprofiel = trim((string) ($_GET['contextprofiel'] ?? 'b2b_complex_sale'));
        if (!ce_hygiene_contextprofiel_valid($contextprofiel)) {
            ce_json_error('Onbekend contextprofiel', 400);
        }
        ce_json_response(['ok' => true, 'detail' => ce_hygiene_platform_detail($pdo, $contextprofiel)]);
    }

    if ($path === '/platform/hygiene/lists' && $method === 'POST') {
        $contextprofiel = trim((string) ($body['contextprofiel'] ?? 'b2b_complex_sale'));
        $result = ce_hygiene_create_draft_list($pdo, $contextprofiel);
        if (empty($result['ok'])) {
            ce_json_error((string) ($result['error'] ?? 'Draft aanmaken mislukt'), 400);
        }
        ce_json_response($result);
    }

    if (preg_match('#^/platform/hygiene/lists/([0-9a-f-]{36})/competitors$#i', $path, $m) && $method === 'POST') {
        $competitors = $body['competitors'] ?? [];
        if (!is_array($competitors)) {
            ce_json_error('competitors moet een array zijn', 400);
        }
        $result = ce_hygiene_set_competitors($pdo, $m[1], $competitors);
        if (empty($result['ok'])) {
            ce_json_error((string) ($result['error'] ?? 'Opslaan mislukt'), 400);
        }
        ce_json_response($result);
    }

    if (preg_match('#^/platform/hygiene/lists/([0-9a-f-]{36})/scrape$#i', $path, $m) && $method === 'POST') {
        $result = ce_hygiene_scrape_list($pdo, $m[1]);
        if (empty($result['ok'])) {
            ce_json_error((string) ($result['error'] ?? 'Scrape mislukt'), 400);
        }
        ce_json_response($result);
    }

    if (preg_match('#^/platform/hygiene/lists/([0-9a-f-]{36})/distill$#i', $path, $m) && $method === 'POST') {
        $result = ce_hygiene_distill_list($pdo, $m[1]);
        if (empty($result['ok'])) {
            ce_json_error((string) ($result['error'] ?? 'Distillatie mislukt'), 400);
        }
        ce_json_response($result);
    }

    if (preg_match('#^/platform/hygiene/lists/([0-9a-f-]{36})/activate$#i', $path, $m) && $method === 'POST') {
        $result = ce_hygiene_activate_list($pdo, $m[1]);
        if (empty($result['ok'])) {
            ce_json_error((string) ($result['error'] ?? 'Activeren mislukt'), 400);
        }
        ce_json_response($result);
    }

    if (preg_match('#^/platform/hygiene/lists/([0-9a-f-]{36})/category-factors$#i', $path, $m) && $method === 'POST') {
        $phrase = trim((string) ($body['phrase'] ?? ''));
        $result = ce_hygiene_add_category_factor($pdo, $m[1], $phrase, 'platform-strateeg');
        if (empty($result['ok'])) {
            ce_json_error((string) ($result['error'] ?? 'Toevoegen mislukt'), 400);
        }
        ce_json_response($result);
    }

    if (preg_match('#^/platform/hygiene/factors/([0-9a-f-]{36})$#i', $path, $m) && $method === 'PATCH') {
        $result = ce_hygiene_update_factor($pdo, $m[1], $body);
        if (empty($result['ok'])) {
            ce_json_error((string) ($result['error'] ?? 'Bijwerken mislukt'), 400);
        }
        ce_json_response($result);
    }

    ce_json_error('Not found', 404);
}
