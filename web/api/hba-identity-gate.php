<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-contentflow-schema.php';
require_once __DIR__ . '/hba-branch-record-repository.php';

/** @return list<string> */
function ce_hba_goal_wrong_level_signals(): array
{
    return [
        'omzetgroei', 'omzet groei', 'marktaandeel', 'meer leads', 'leads',
        'hogere conversie', 'conversie', 'nummer één', 'nummer 1', 'nummer een',
        'winst', 'kpi', 'roi', 'omzet', 'groei van', 'sales target',
    ];
}

/** @return list<string> */
function ce_hba_foe_wrong_level_signals(): array
{
    return [
        'concurrent', 'concurrenten', 'overheid', 'regulator', 'acm',
        'belastingdienst', 'inspectie', 'autoriteit persoonsgegevens',
    ];
}

function ce_hba_text_has_signal(string $text, array $signals): bool
{
    $hay = mb_strtolower($text);
    foreach ($signals as $signal) {
        if ($signal !== '' && str_contains($hay, $signal)) {
            return true;
        }
    }

    return false;
}

function ce_hba_identity_gate_system_prompt(): string
{
    return <<<'PROMPT'
Je doet ALLEEN een typecheck op Goal en Foe, geen kwaliteitsscore.
Mogelijke uitkomsten per veld: wrong_level | identity_weak | identity_valid.

GOAL WRONG_LEVEL-signalen: omzetgroei, marktaandeel, meer leads, hogere conversie,
"nummer één worden", KPI's. Test: als het merk dit doel bereikt, weten we dan
wezenlijk beter wie het merk is? Zo niet → wrong_level.

FOE WRONG_LEVEL-signalen: concrete concurrent, overheid, regulator als tegenkracht zelf.
Test: als deze actor morgen verdwijnt, bestaat de tegenkracht dan nog?
Zo nee → wrong_level (opponent, geen identiteitsbepalende Foe).

identity_weak: het denkniveau klopt (verandering / kracht, geen KPI / actor),
maar de formulering is nog te algemeen of te zacht.

identity_valid: het veld staat op identiteitsniveau en is specifiek genoeg om verder te toetsen.

Feedback:
- wrong_level: leg uit welk denkniveau fout is (geen herhaling van de wizard-uitleg).
- identity_weak: alleen gerichte aanscherping, geen basisuitleg herhalen.

OUTPUT (JSON):
{
  "goal": { "level": "wrong_level|identity_weak|identity_valid", "feedback": "" },
  "foe": { "level": "wrong_level|identity_weak|identity_valid", "feedback": "" }
}
PROMPT;
}

function ce_hba_architecture_score_system_prompt(): string
{
    return <<<'PROMPT'
Je scoort een merkarchitectuur NÁ een geslaagde identity-level gate.
Score 0-2 per criterium, 9 criteria, max 18 punten.

Criteria:
1. hero_specificiteit
2. goal_betekenis
3. foe_echtheid
4. hero_goal_foe_causaliteit
5. constraint_uitsluiting
6. deductieve_kracht
7. marktcontrast
8. bewijsbaarheid
9. overdraagbaarheid

Hard fail (beslissing FAIL ongeacht rest) als één van deze 0 is:
goal_betekenis, foe_echtheid, hero_goal_foe_causaliteit, constraint_uitsluiting,
of als marktframe onbruikbaar is, of de architectuur vrijwel volledig uit hygiëne/categorietaal bestaat.

Beslislogica:
- PASS: 14-18, geen hard fail
- REVIEW: 11-13, geen hard fail
- FAIL: 0-10 of hard fail

Geef per criterium: score (0-2), toelichting, verbetering.
Geef hard_fail_reason (leeg als geen) en return_action (naar welke stap de gebruiker terug moet).

OUTPUT (JSON):
{
  "criteria": {
    "hero_specificiteit": { "score": 0, "toelichting": "", "verbetering": "" },
    "goal_betekenis": { "score": 0, "toelichting": "", "verbetering": "" },
    "foe_echtheid": { "score": 0, "toelichting": "", "verbetering": "" },
    "hero_goal_foe_causaliteit": { "score": 0, "toelichting": "", "verbetering": "" },
    "constraint_uitsluiting": { "score": 0, "toelichting": "", "verbetering": "" },
    "deductieve_kracht": { "score": 0, "toelichting": "", "verbetering": "" },
    "marktcontrast": { "score": 0, "toelichting": "", "verbetering": "" },
    "bewijsbaarheid": { "score": 0, "toelichting": "", "verbetering": "" },
    "overdraagbaarheid": { "score": 0, "toelichting": "", "verbetering": "" }
  },
  "total_score": 0,
  "hard_fail": false,
  "hard_fail_reason": "",
  "decision": "PASS|REVIEW|FAIL",
  "return_action": ""
}
PROMPT;
}

/** @return array{goal: array{level: string, feedback: string}, foe: array{level: string, feedback: string}, pass: bool} */
function ce_hba_run_identity_gate(string $goal, string $foe): array
{
    $goal = trim($goal);
    $foe = trim($foe);

    if ($foe === '') {
        return [
            'goal' => ce_hba_run_identity_gate_goal_only($goal),
            'foe' => [
                'level' => 'missing',
                'feedback' => 'Je hebt nog geen tegenkracht ingevuld — dat beoordelen we apart in de check.',
            ],
            'pass' => true,
        ];
    }

    $goalLevel = ce_hba_text_has_signal($goal, ce_hba_goal_wrong_level_signals()) ? 'wrong_level' : '';
    $foeLevel = ce_hba_text_has_signal($foe, ce_hba_foe_wrong_level_signals()) ? 'wrong_level' : '';

    $llm = ce_hba_v2_call_json(ce_hba_identity_gate_system_prompt(), "Goal: {$goal}\nFoe: {$foe}");
    $goalFeedback = '';
    $foeFeedback = '';
    if ($llm['ok']) {
        $g = is_array($llm['data']['goal'] ?? null) ? $llm['data']['goal'] : [];
        $f = is_array($llm['data']['foe'] ?? null) ? $llm['data']['foe'] : [];
        $llmGoal = (string) ($g['level'] ?? '');
        $llmFoe = (string) ($f['level'] ?? '');
        $goalFeedback = (string) ($g['feedback'] ?? '');
        $foeFeedback = (string) ($f['feedback'] ?? '');
        if ($goalLevel !== 'wrong_level' && in_array($llmGoal, ['wrong_level', 'identity_weak', 'identity_valid'], true)) {
            $goalLevel = $llmGoal;
        }
        if ($foeLevel !== 'wrong_level' && in_array($llmFoe, ['wrong_level', 'identity_weak', 'identity_valid'], true)) {
            $foeLevel = $llmFoe;
        }
    }

    if ($goalLevel === '') {
        $goalLevel = 'identity_weak';
        $goalFeedback = $goalFeedback !== '' ? $goalFeedback : 'Het doel is nog te algemeen om te toetsen of het het merk definieert.';
    }
    if ($foeLevel === '') {
        $foeLevel = 'identity_weak';
        $foeFeedback = $foeFeedback !== '' ? $foeFeedback : 'De tegenkracht is nog te algemeen om te toetsen of hij het merk vormt.';
    }
    if ($goalLevel === 'wrong_level' && $goalFeedback === '') {
        $goalFeedback = 'Dit klinkt als een bedrijfsdoel (groei, leads, nummer één). Een merkonderdeel zegt wie je bent als dat doel is bereikt.';
    }
    if ($foeLevel === 'wrong_level' && $foeFeedback === '') {
        $foeFeedback = 'Dit is een opponent (concurrent of instantie), geen tegenkracht. Als die actor verdwijnt, blijft de kracht die jullie bestrijden dan bestaan?';
    }

    return [
        'goal' => ['level' => $goalLevel, 'feedback' => $goalFeedback],
        'foe' => ['level' => $foeLevel, 'feedback' => $foeFeedback],
        'pass' => $goalLevel !== 'wrong_level' && $foeLevel !== 'wrong_level',
    ];
}

/** @return array{level: string, feedback: string} */
function ce_hba_run_identity_gate_goal_only(string $goal): array
{
    $goalLevel = $goal === ''
        ? 'missing'
        : (ce_hba_text_has_signal($goal, ce_hba_goal_wrong_level_signals()) ? 'wrong_level' : '');

    if ($goalLevel === 'missing') {
        return [
            'level' => 'missing',
            'feedback' => 'Je hebt nog geen doel ingevuld.',
        ];
    }

    $llm = ce_hba_v2_call_json(ce_hba_identity_gate_system_prompt(), "Goal: {$goal}\nFoe: (niet ingevuld)");
    $goalFeedback = '';
    if ($llm['ok']) {
        $g = is_array($llm['data']['goal'] ?? null) ? $llm['data']['goal'] : [];
        $llmGoal = (string) ($g['level'] ?? '');
        $goalFeedback = (string) ($g['feedback'] ?? '');
        if ($goalLevel !== 'wrong_level' && in_array($llmGoal, ['wrong_level', 'identity_weak', 'identity_valid'], true)) {
            $goalLevel = $llmGoal;
        }
    }
    if ($goalLevel === '') {
        $goalLevel = 'identity_weak';
        $goalFeedback = $goalFeedback !== '' ? $goalFeedback : 'Het doel is nog te algemeen om te toetsen of het het merk definieert.';
    }
    if ($goalLevel === 'wrong_level' && $goalFeedback === '') {
        $goalFeedback = 'Dit klinkt als een bedrijfsdoel (groei, leads, nummer één). Een merkonderdeel zegt wie je bent als dat doel is bereikt.';
    }

    return ['level' => $goalLevel, 'feedback' => $goalFeedback];
}

/** @param array<string, mixed> $scores */
function ce_hba_apply_score_decision(array $scores, bool $marketUnusable, bool $hygieneCollapse): array
{
    $criteria = is_array($scores['criteria'] ?? null) ? $scores['criteria'] : [];
    $total = 0;
    $mapped = [];
    foreach ([
        'hero_specificiteit', 'goal_betekenis', 'foe_echtheid', 'hero_goal_foe_causaliteit',
        'constraint_uitsluiting', 'deductieve_kracht', 'marktcontrast', 'bewijsbaarheid', 'overdraagbaarheid',
    ] as $key) {
        $row = is_array($criteria[$key] ?? null) ? $criteria[$key] : [];
        $score = max(0, min(2, (int) ($row['score'] ?? 0)));
        $mapped[$key] = [
            'score' => $score,
            'toelichting' => (string) ($row['toelichting'] ?? ''),
            'verbetering' => (string) ($row['verbetering'] ?? ''),
        ];
        $total += $score;
    }

    $hardKeys = ['goal_betekenis', 'foe_echtheid', 'hero_goal_foe_causaliteit', 'constraint_uitsluiting'];
    $hardFail = $marketUnusable || $hygieneCollapse;
    $hardReason = (string) ($scores['hard_fail_reason'] ?? '');
    foreach ($hardKeys as $key) {
        if (($mapped[$key]['score'] ?? 0) === 0) {
            $hardFail = true;
            if ($hardReason === '') {
                $hardReason = 'Hard fail op ' . $key;
            }
        }
    }
    if ($marketUnusable && $hardReason === '') {
        $hardReason = 'Marktframe onbruikbaar — uniciteit kan niet hard beoordeeld worden.';
    }
    if ($hygieneCollapse && $hardReason === '') {
        $hardReason = 'De architectuur valt samen met hygiënefactoren of categorietaal.';
    }

    if ($hardFail || $total <= 10) {
        $decision = 'FAIL';
    } elseif ($total <= 13) {
        $decision = 'REVIEW';
    } else {
        $decision = 'PASS';
    }

    return [
        'criteria' => $mapped,
        'total_score' => $total,
        'hard_fail' => $hardFail,
        'hard_fail_reason' => $hardReason,
        'decision' => $decision,
        'return_action' => (string) ($scores['return_action'] ?? ''),
    ];
}

/** @param array<string, mixed> $narrative @param array<string, mixed>|null $branch */
function ce_hba_build_guardrail_object(array $narrative, ?array $branch, array $score, int $version): array
{
    $axes = [];
    if (is_array($branch)) {
        $rawAxes = $branch['differentiatie_assen'] ?? [];
        if (is_string($rawAxes)) {
            $decoded = json_decode($rawAxes, true);
            $rawAxes = is_array($decoded) ? $decoded : [];
        }
        $axes = is_array($rawAxes) ? $rawAxes : [];
    }

    return [
        'hero_role' => (string) ($narrative['archetype'] ?? ''),
        'goal_change' => (string) ($narrative['doel'] ?? ''),
        'foe_force' => (string) ($narrative['foe'] ?? ''),
        'constraint_never' => (string) ($narrative['wat_bewust_niet'] ?? ''),
        'derived_principles' => array_values(array_filter(array_map('strval', is_array($narrative['regels'] ?? null) ? $narrative['regels'] : []))),
        'positive_signals' => [],
        'negative_signals' => is_array($branch) ? ce_hba_decode_json_list($branch['categorietaal'] ?? []) : [],
        'branch_hygiene_factors' => is_array($branch) ? ce_hba_decode_json_list($branch['hygienefactoren'] ?? []) : [],
        'branch_category_language' => is_array($branch) ? ce_hba_decode_json_list($branch['categorietaal'] ?? []) : [],
        'branch_market_archetype' => is_array($branch) ? (string) ($branch['markt_archetype'] ?? '') : '',
        'branch_differentiation_axes' => $axes,
        'architecture_check_score' => (int) ($score['total_score'] ?? 0),
        'version' => $version,
    ];
}

function ce_hba_store_guardrail(PDO $pdo, string $clientId, array $payload, string $decision, ?int $score, ?string $branchId, ?int $branchVersion, int $archVersion): string
{
    ce_ensure_hba_contentflow_schema($pdo);
    $id = ce_uuid();
    $stmt = $pdo->prepare(
        'INSERT INTO hba_guardrail_objects (
            id, client_id, architecture_version, branch_id, branch_version, decision, check_score, payload
        ) VALUES (
            :id, :client_id, :architecture_version, :branch_id, :branch_version, :decision, :check_score, :payload
        )'
    );
    $stmt->execute([
        'id' => $id,
        'client_id' => $clientId,
        'architecture_version' => $archVersion,
        'branch_id' => $branchId,
        'branch_version' => $branchVersion,
        'decision' => $decision,
        'check_score' => $score,
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    if ($decision === 'PASS') {
        require_once __DIR__ . '/hba-baseline-repository.php';
        ce_hba_baseline_schedule($pdo, $clientId);
    }

    return $id;
}

/** @return array<string, mixed>|null */
function ce_hba_active_guardrail(PDO $pdo, string $clientId): ?array
{
    ce_ensure_hba_contentflow_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_guardrail_objects')) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT * FROM hba_guardrail_objects
         WHERE client_id = :client_id AND decision = 'PASS'
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $payload = json_decode((string) ($row['payload'] ?? ''), true);

    return is_array($payload) ? $payload : null;
}
