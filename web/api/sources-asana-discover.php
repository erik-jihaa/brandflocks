<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/asana-helper.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$pat = trim((string) ($body['asanaPatRef'] ?? $body['pat'] ?? ''));
$sourceId = trim((string) ($body['sourceId'] ?? ''));
if ($pat === '' && preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    $pdo = ce_db();
    require_once __DIR__ . '/sources-repository.php';
    $clientId = ce_resolve_client_id($pdo);
    if ($clientId !== null) {
        $stmt = $pdo->prepare(
            'SELECT asana_pat_ref FROM sources WHERE id = :id AND client_id = :client_id AND category = :category LIMIT 1'
        );
        $stmt->execute(['id' => $sourceId, 'client_id' => $clientId, 'category' => 'asana']);
        $row = $stmt->fetch();
        if ($row) {
            $stored = ce_credential_try_decrypt((string) ($row['asana_pat_ref'] ?? ''));
            if ($stored !== null && $stored !== '') {
                $pat = $stored;
            }
        }
    }
}
if ($pat === '') {
    ce_json_error('Asana Personal Access Token is verplicht.', 400);
}

$workspaces = ce_asana_list_workspaces($pat);
$projects = [];
foreach ($workspaces as $workspace) {
    foreach (ce_asana_list_projects($pat, $workspace['gid']) as $project) {
        $projects[] = $project + ['workspaceGid' => $workspace['gid'], 'workspaceName' => $workspace['name']];
    }
}

ce_json_response([
    'ok' => true,
    'workspaces' => $workspaces,
    'projects' => $projects,
]);
