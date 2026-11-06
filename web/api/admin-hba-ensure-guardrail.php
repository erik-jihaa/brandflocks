<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/hba-identity-gate.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$clientId = trim((string) ($_GET['client_id'] ?? ''));
if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('client_id verplicht (UUID)', 400);
}

$run = ($_GET['run'] ?? '') === '1';
$before = ce_hba_active_guardrail($pdo, $clientId) !== null;
$latestBefore = ce_hba_v2_latest_guardrail_summary($pdo, $clientId);
$bootstrapped = false;
$checkBody = ce_hba_v2_architecture_check_body_from_narrative($pdo, $clientId);
$checkResult = null;

if ($run && !$before && $checkBody !== null) {
    require_once __DIR__ . '/hba-architectuur-check-repository.php';
    try {
        $checkResult = ce_hba_v2_run_architecture_check($pdo, $clientId, $checkBody);
    } catch (Throwable $e) {
        $checkResult = ['ok' => false, 'error' => $e->getMessage()];
    }
    $bootstrapped = ce_hba_active_guardrail($pdo, $clientId) !== null;
}

$after = ce_hba_active_guardrail($pdo, $clientId) !== null;
$latestAfter = ce_hba_v2_latest_guardrail_summary($pdo, $clientId);

$lastArchitectuurCheck = null;
if (ce_table_exists($pdo, 'architectuur_check_result')) {
    $stmt = $pdo->prepare(
        'SELECT id, totaaloordeel, decision, total_score, hard_fail_reason, created_at
         FROM architectuur_check_result WHERE client_id = :id ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute(['id' => $clientId]);
    $lastArchitectuurCheck = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'run' => $run,
    'guardrailActiveBefore' => $before,
    'guardrailActiveAfter' => $after,
    'bootstrapped' => $bootstrapped,
    'guardrailCheckCount' => ce_hba_v2_guardrail_check_count($pdo, $clientId),
    'latestGuardrailBefore' => $latestBefore,
    'latestGuardrailAfter' => $latestAfter,
    'checkBodyPresent' => $checkBody !== null,
    'checkResult' => $checkResult,
    'lastArchitectuurCheck' => $lastArchitectuurCheck,
    'footnote' => $after ? null : ce_hba_v2_guardrail_inactive_footnote($pdo, $clientId),
]);
