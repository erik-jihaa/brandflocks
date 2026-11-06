<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/hba-identity-gate.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();

$tenantA = trim((string) ($_GET['client_id'] ?? '016384af-b26e-4415-aa9c-abc46b804798'));
$tenantB = trim((string) ($_GET['isolation_client_id'] ?? 'c01b93ca-cd5a-2675-c461-96faae6ded9a'));
$runLlm = ($_GET['run'] ?? '') === '1';

/** @param array<string, mixed> $row */
function ce_hba_phase1_tenant_summary(PDO $pdo, string $clientId): array
{
    $stmt = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $client = $stmt->fetch();
    if (!$client) {
        return ['ok' => false, 'error' => 'Client niet gevonden', 'clientId' => $clientId];
    }

    $gate = ce_hba_v2_guardrail_gate($pdo, $clientId);
    $guardrail = ce_hba_active_guardrail($pdo, $clientId);

    return [
        'ok' => true,
        'clientId' => $clientId,
        'name' => (string) ($client['name'] ?? ''),
        'guardrailActive' => $guardrail !== null,
        'gate' => $gate,
    ];
}

/** @return array<string, mixed> */
function ce_hba_phase1_theme_check(PDO $pdo, string $clientId, bool $runLlm): array
{
    ce_set_active_client_id($clientId);
    $result = $runLlm
        ? ce_hba_v2_run_theme_verdicts($pdo, $clientId, null)
        : ce_hba_v2_load_theme_verdicts($pdo, $clientId, null);

    $code = (string) ($result['code'] ?? '');
    $verdicts = is_array($result['verdicts'] ?? null) ? $result['verdicts'] : [];
    $labels = [];
    foreach ($verdicts as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    $pass = ($result['ok'] ?? false) === true
        && $code !== 'guardrail_inactive'
        && $code !== 'architecture_incomplete';

    return [
        'pass' => $pass,
        'mode' => $runLlm ? 'run' : 'load',
        'code' => $code !== '' ? $code : null,
        'footnote' => $result['architectureFootnote'] ?? null,
        'verdictCount' => count($verdicts),
        'labels' => array_values(array_unique($labels)),
        'watOntbreekt' => $result['watOntbreekt'] ?? null,
        'themeCount' => $result['themeCount'] ?? null,
        'error' => $result['error'] ?? null,
    ];
}

/** @return array<string, mixed>|null */
function ce_hba_phase1_pick_research_slot(PDO $pdo, string $clientId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT cs.id AS slot_id, cs.theme, COUNT(ri.id) AS item_count
         FROM content_slots cs
         INNER JOIN research_items ri ON ri.slot_id = cs.id
         WHERE cs.client_id = :client_id
         GROUP BY cs.id, cs.theme
         HAVING item_count > 0
         ORDER BY item_count DESC, cs.created_at DESC
         LIMIT 1"
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/** @return array<string, mixed> */
function ce_hba_phase1_research_check(PDO $pdo, string $clientId, bool $runLlm): array
{
    $slot = ce_hba_phase1_pick_research_slot($pdo, $clientId);
    if ($slot === null) {
        return [
            'pass' => true,
            'skipped' => true,
            'reason' => 'Geen slot met research-items — gate-only check',
            'gateOnly' => ce_hba_v2_guardrail_gate($pdo, $clientId),
        ];
    }

    $slotId = (string) ($slot['slot_id'] ?? '');
    ce_set_active_client_id($clientId);
    $result = $runLlm
        ? ce_hba_v2_run_research_verdicts($pdo, $clientId, $slotId)
        : ce_hba_v2_load_research_verdicts($pdo, $clientId, $slotId);

    $code = (string) ($result['code'] ?? '');
    $verdicts = is_array($result['verdicts'] ?? null) ? $result['verdicts'] : [];
    $labels = [];
    foreach ($verdicts as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    $pass = ($result['ok'] ?? false) === true
        && $code !== 'guardrail_inactive'
        && $code !== 'architecture_incomplete';

    return [
        'pass' => $pass,
        'mode' => $runLlm ? 'run' : 'load',
        'slotId' => $slotId,
        'theme' => (string) ($slot['theme'] ?? ''),
        'code' => $code !== '' ? $code : null,
        'footnote' => $result['architectureFootnote'] ?? null,
        'verdictCount' => count($verdicts),
        'labels' => array_values(array_unique($labels)),
        'error' => $result['error'] ?? null,
    ];
}

/** @return array<string, mixed>|null */
function ce_hba_phase1_pick_draft_article(PDO $pdo, string $clientId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT a.id, a.status,
                COALESCE(NULLIF(TRIM(a.content_human), ''), NULLIF(TRIM(a.content_ai), '')) AS body
         FROM articles a
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND a.status IN ('draft', 'writing', 'review')
         HAVING body IS NOT NULL AND TRIM(body) <> ''
         ORDER BY a.id DESC
         LIMIT 1"
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/** @return array<string, mixed> */
function ce_hba_phase1_article_check(PDO $pdo, string $clientId, bool $runLlm): array
{
    if (!$runLlm) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM hba_article_hints WHERE client_id = :client_id');
        $countStmt->execute(['client_id' => $clientId]);
        $hintCount = (int) $countStmt->fetchColumn();
        $gate = ce_hba_v2_guardrail_gate($pdo, $clientId);

        return [
            'pass' => $gate === null,
            'mode' => 'gate-only',
            'storedHintCount' => $hintCount,
            'code' => is_array($gate) ? ($gate['code'] ?? null) : null,
            'footnote' => is_array($gate) ? ($gate['architectureFootnote'] ?? null) : null,
            'note' => 'Gebruik run=1 voor live article-hint LLM-call',
        ];
    }

    $article = ce_hba_phase1_pick_draft_article($pdo, $clientId);
    if ($article === null) {
        return [
            'pass' => true,
            'skipped' => true,
            'reason' => 'Geen concept-artikel met body — gate-only',
            'gateOnly' => ce_hba_v2_guardrail_gate($pdo, $clientId),
        ];
    }

    $articleId = (string) ($article['id'] ?? '');
    ce_set_active_client_id($clientId);
    $result = ce_hba_v2_run_article_hint($pdo, $clientId, $articleId, []);

    $code = (string) ($result['code'] ?? '');
    $hint = is_array($result['hint'] ?? null) ? $result['hint'] : null;
    $pass = ($result['ok'] ?? false) === true
        && $code !== 'guardrail_inactive'
        && $code !== 'architecture_incomplete';

    return [
        'pass' => $pass,
        'mode' => 'run',
        'articleId' => $articleId,
        'code' => $code !== '' ? $code : null,
        'footnote' => $result['architectureFootnote'] ?? null,
        'hasHint' => $hint !== null,
        'hintHeadline' => is_array($hint) ? ($hint['kop'] ?? $hint['headline'] ?? null) : null,
        'error' => $result['error'] ?? null,
    ];
}

/** @return array<string, mixed> */
function ce_hba_phase1_isolation_check(PDO $pdo, string $clientId): array
{
    ce_set_active_client_id($clientId);
    $theme = ce_hba_v2_load_theme_verdicts($pdo, $clientId, null);
    $research = ce_hba_v2_load_research_verdicts($pdo, $clientId, '00000000-0000-0000-0000-000000000000');

    $themeBlocked = ($theme['ok'] ?? true) === false;
    $researchBlocked = ($research['ok'] ?? true) === false;

    return [
        'pass' => $themeBlocked && $researchBlocked,
        'theme' => [
            'ok' => $theme['ok'] ?? null,
            'error' => $theme['error'] ?? null,
            'code' => $theme['code'] ?? null,
        ],
        'research' => [
            'ok' => $research['ok'] ?? null,
            'error' => $research['error'] ?? null,
            'code' => $research['code'] ?? null,
        ],
    ];
}

$summaryA = ce_hba_phase1_tenant_summary($pdo, $tenantA);
$summaryB = ce_hba_phase1_tenant_summary($pdo, $tenantB);

$themeA = ce_hba_phase1_theme_check($pdo, $tenantA, $runLlm);
$researchA = ce_hba_phase1_research_check($pdo, $tenantA, $runLlm);
$articleA = ce_hba_phase1_article_check($pdo, $tenantA, $runLlm);
$isolationB = ce_hba_phase1_isolation_check($pdo, $tenantB);

$checks = [
    'guardrailActive' => ($summaryA['guardrailActive'] ?? false) === true,
    'themeMoment' => ($themeA['pass'] ?? false) === true,
    'researchMoment' => ($researchA['pass'] ?? false) === true,
    'articleMoment' => ($articleA['pass'] ?? false) === true,
    'isolationB' => ($isolationB['pass'] ?? false) === true,
];

$allPass = !in_array(false, $checks, true);

ce_json_response([
    'ok' => true,
    'phase' => 1,
    'runLlm' => $runLlm,
    'allPass' => $allPass,
    'checks' => $checks,
    'tenantA' => [
        'summary' => $summaryA,
        'theme' => $themeA,
        'research' => $researchA,
        'article' => $articleA,
    ],
    'tenantB' => [
        'summary' => $summaryB,
        'isolation' => $isolationB,
    ],
]);
