<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/hba-identity-gate.php';
require_once __DIR__ . '/hba-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$clientId = trim((string) ($_GET['client_id'] ?? ''));

if (($_GET['list_hba'] ?? '') === '1') {
    $rows = $pdo->query('SELECT id, name FROM clients ORDER BY name ASC')->fetchAll() ?: [];
    $out = [];
    foreach ($rows as $row) {
        $id = (string) ($row['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $guardrail = ce_hba_active_guardrail($pdo, $id);
        $out[] = [
            'id' => $id,
            'name' => (string) ($row['name'] ?? ''),
            'architectureReady' => ce_hba_v2_architecture_ready($pdo, $id),
            'guardrailActive' => $guardrail !== null,
        ];
    }
    ce_json_response(['ok' => true, 'clients' => $out]);
}

if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('client_id verplicht (UUID) of list_hba=1', 400);
}

$stmt = $pdo->prepare('SELECT id, name, hba_architecture_completed_at, hba_pbs_revealed_at FROM clients WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $clientId]);
$client = $stmt->fetch();
if (!$client) {
    ce_json_error('Client niet gevonden', 404);
}

$ctx = ce_hba_v2_architecture_context($pdo, $clientId);
$guardrail = ce_hba_active_guardrail($pdo, $clientId);

$narrative = null;
if (ce_table_exists($pdo, 'brand_narratives')) {
    $nStmt = $pdo->prepare('SELECT id, archetype, branche_code, is_current FROM brand_narratives WHERE client_id = :id AND is_current = 1 LIMIT 1');
    $nStmt->execute(['id' => $clientId]);
    $narrative = $nStmt->fetch() ?: null;
}

$guardrailRow = null;
if (ce_table_exists($pdo, 'hba_guardrail_objects')) {
    $gStmt = $pdo->prepare(
        "SELECT id, decision, check_score, created_at FROM hba_guardrail_objects
         WHERE client_id = :id ORDER BY created_at DESC LIMIT 1"
    );
    $gStmt->execute(['id' => $clientId]);
    $guardrailRow = $gStmt->fetch() ?: null;
}

$tables = [
    'clients',
    'sources',
    'themes',
    'content_slots',
    'research_items',
    'articles',
    'objectives',
    'brand_narratives',
    'event_log',
    'hba_scores',
    'hba_theme_verdicts',
    'hba_research_verdicts',
    'hba_article_hints',
    'hba_guardrail_objects',
    'hba_branch_evidence',
    'content_rhythms',
    'branche_library',
];
$tableStatus = [];
foreach ($tables as $table) {
    $tableStatus[$table] = ce_table_exists($pdo, $table);
}

ce_json_response([
    'ok' => true,
    'client' => [
        'id' => $clientId,
        'name' => (string) ($client['name'] ?? ''),
        'architectureCompletedAt' => $client['hba_architecture_completed_at'] ?? null,
    ],
    'architectureReady' => ce_hba_v2_architecture_ready($pdo, $clientId),
    'brandNarrativeCompleteness' => ce_brand_narrative_completeness($pdo, $clientId),
    'scoringGate' => ce_client_hba_scoring_gate($pdo, $clientId),
    'guardrailActive' => $guardrail !== null,
    'latestGuardrail' => $guardrailRow,
    'narrative' => $narrative ? [
        'id' => (string) ($narrative['id'] ?? ''),
        'archetype' => (string) ($narrative['archetype'] ?? ''),
        'brancheCode' => (string) ($narrative['branche_code'] ?? ''),
    ] : null,
    'architectureContext' => [
        'archetype' => (string) ($ctx['archetype'] ?? ''),
        'doel' => (string) ($ctx['doel'] ?? ''),
        'foe' => (string) ($ctx['foe'] ?? ''),
        'brancheCode' => (string) ($ctx['branche_code'] ?? ''),
    ],
    'tables' => $tableStatus,
]);
