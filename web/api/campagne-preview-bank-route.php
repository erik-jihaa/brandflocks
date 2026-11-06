<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/campagne-preview-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$pdo = ce_db();
$clientId = ce_default_client_id($pdo);
if ($clientId === null || $clientId === '') {
    if ($method === 'GET') {
        ce_json_response(['vragen' => [], 'feiten' => [], 'merkOnderdelen' => CE_CAMPAGNE_MERK_ONDERDELEN]);
    }
    ce_json_error('Geen klantcontext', 400);
}

if ($method === 'GET') {
    $nameStmt = $pdo->prepare('SELECT name FROM clients WHERE id = :id LIMIT 1');
    $nameStmt->execute(['id' => $clientId]);
    $clientName = strtolower((string) ($nameStmt->fetchColumn() ?: ''));
    if (str_contains($clientName, 'brandpunt')) {
        ce_campagne_bank_seed_brandpunt($pdo, $clientId);
    }

    ce_json_response([
        'vragen' => ce_campagne_bank_list_vragen($pdo, $clientId),
        'feiten' => ce_campagne_bank_list_feiten($pdo, $clientId),
        'merkOnderdelen' => CE_CAMPAGNE_MERK_ONDERDELEN,
    ]);
}

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    try {
        $saved = ce_campagne_bank_save($pdo, $clientId, is_array($body) ? $body : []);
        ce_json_response([
            'ok' => true,
            'vragen' => $saved['vragen'],
            'feiten' => $saved['feiten'],
            'idMap' => $saved['idMap'] ?? [],
            'merkOnderdelen' => CE_CAMPAGNE_MERK_ONDERDELEN,
        ]);
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    }
}

ce_json_error('Method not allowed', 405);
