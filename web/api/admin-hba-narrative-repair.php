<?php

declare(strict_types=1);

/**
 * Repareert een incomplete of verkeerd-gecalibreerde brand_narratives-rij
 * op basis van clients-strategievelden. Alleen via CE_RESET_TOKEN.
 *
 * GET  — preview (dry-run)
 * POST — toepassen ({ "clientId": "...", "dryRun": false, "force": false })
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

/** @return array{id: string, name: string}|null */
function ce_hba_repair_resolve_client(PDO $pdo, string $clientId, string $clientSearch): ?array
{
    if ($clientId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        $stmt = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ['id' => (string) $row['id'], 'name' => (string) ($row['name'] ?? '')] : null;
    }

    $search = trim($clientSearch);
    if ($search === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, name FROM clients WHERE LOWER(name) LIKE :q ORDER BY name ASC LIMIT 1"
    );
    $stmt->execute(['q' => '%' . strtolower($search) . '%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? ['id' => (string) $row['id'], 'name' => (string) ($row['name'] ?? '')] : null;
}

if ($method === 'GET') {
    $client = ce_hba_repair_resolve_client(
        $pdo,
        trim((string) ($_GET['clientId'] ?? $_GET['client_id'] ?? '')),
        trim((string) ($_GET['clientSearch'] ?? $_GET['client_search'] ?? '')),
    );
    if (!$client) {
        ce_json_error('Client niet gevonden', 404);
    }

    $result = ce_hba_repair_brand_narrative_from_client($pdo, $client['id'], false, true);
    ce_json_response(array_merge(['client' => $client], $result));
}

if ($method !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$client = ce_hba_repair_resolve_client(
    $pdo,
    trim((string) ($body['clientId'] ?? $body['client_id'] ?? '')),
    trim((string) ($body['clientSearch'] ?? $body['client_search'] ?? '')),
);
if (!$client) {
    ce_json_error('Client niet gevonden', 404);
}

$dryRun = (bool) ($body['dryRun'] ?? $body['dry_run'] ?? false);
$force = (bool) ($body['force'] ?? false);

$result = ce_hba_repair_brand_narrative_from_client($pdo, $client['id'], $force, $dryRun);
if (!($result['ok'] ?? false)) {
    ce_json_response(array_merge(['error' => (string) ($result['error'] ?? 'Repair mislukt')], $result), 400);
}

ce_json_response(array_merge(['client' => $client], $result));
