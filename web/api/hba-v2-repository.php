<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/hba-branche-seed.php';
require_once __DIR__ . '/hba-contentflow-schema.php';
require_once __DIR__ . '/hba-v2-prompts.php';
require_once __DIR__ . '/hba-hint-verificatie.php';
require_once __DIR__ . '/llm-repository.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/editor-questions.php';

function ce_hba_v2_model(): string
{
    return ce_hba_model();
}

function ce_ensure_hba_v2_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    ce_ensure_hba_schema($pdo);

    if (!ce_table_exists($pdo, 'hba_theme_verdicts')) {
        $migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_hba_v2_meedenker.sql';
        if (is_readable($migration)) {
            try {
                $pdo->exec((string) file_get_contents($migration));
            } catch (Throwable) {
                // Geen CREATE-rechten.
            }
        }
    }

    if (!ce_table_exists($pdo, 'hba_research_verdicts')) {
        $researchMigration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_hba_research_verdicts.sql';
        if (is_readable($researchMigration)) {
            try {
                $pdo->exec((string) file_get_contents($researchMigration));
            } catch (Throwable) {
                // Geen CREATE-rechten.
            }
        }
        if (!ce_table_exists($pdo, 'hba_research_verdicts')) {
            try {
                $pdo->exec(
                    <<<'SQL'
CREATE TABLE IF NOT EXISTS hba_research_verdicts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    slot_id CHAR(36) NOT NULL,
    research_item_id CHAR(36) NOT NULL,
    label ENUM('versterkt', 'neutraal', 'verwatert') NOT NULL,
    toelichting TEXT NOT NULL,
    wat_ontbreekt TEXT NULL,
    judged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hba_research_verdicts_client (client_id),
    KEY idx_hba_research_verdicts_slot (slot_id),
    KEY idx_hba_research_verdicts_item (research_item_id),
    KEY idx_hba_research_verdicts_judged (judged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
                );
            } catch (Throwable) {
                // Geen CREATE-rechten.
            }
        }
    }

    $columnAlters = [
        ['brand_narratives', 'distinctiviteit', 'ALTER TABLE brand_narratives ADD COLUMN distinctiviteit TEXT NULL'],
        ['brand_narratives', 'branche_code', 'ALTER TABLE brand_narratives ADD COLUMN branche_code VARCHAR(64) NULL'],
        ['brand_narratives', 'wat_bewust_niet', 'ALTER TABLE brand_narratives ADD COLUMN wat_bewust_niet TEXT NULL'],
        ['brand_narratives', 'identity_intro', 'ALTER TABLE brand_narratives ADD COLUMN identity_intro TEXT NULL'],
        ['brand_narratives', 'aanspreekvorm', 'ALTER TABLE brand_narratives ADD COLUMN aanspreekvorm VARCHAR(8) NULL'],
        ['clients', 'hba_architecture_completed_at', 'ALTER TABLE clients ADD COLUMN hba_architecture_completed_at TIMESTAMP NULL'],
        ['clients', 'hba_pbs_revealed_at', 'ALTER TABLE clients ADD COLUMN hba_pbs_revealed_at TIMESTAMP NULL'],
        ['hba_article_hints', 'genegeerd', 'ALTER TABLE hba_article_hints ADD COLUMN genegeerd TINYINT(1) NOT NULL DEFAULT 0'],
        ['hba_article_hints', 'invulvelden', 'ALTER TABLE hba_article_hints ADD COLUMN invulvelden JSON NULL'],
        ['hba_article_hints', 'bewijsveld_bron', 'ALTER TABLE hba_article_hints ADD COLUMN bewijsveld_bron TEXT NULL'],
    ];

    foreach ($columnAlters as [$table, $column, $sql]) {
        if (ce_table_exists($pdo, $table) && !ce_table_has_column($pdo, $table, $column)) {
            try {
                $pdo->exec($sql);
                unset($GLOBALS['ce_table_columns'][$table]);
            } catch (Throwable) {
                // Kolom bestaat al.
            }
        }
    }

    ce_hba_seed_branche_library($pdo);

    if (ce_table_exists($pdo, 'branche_library')) {
        $brancheColumns = [
            'branche_definitie' => 'TEXT NULL',
            'bronnen_kwaliteit' => 'VARCHAR(16) NULL',
            'vakcontext' => 'JSON NULL',
            'differentiatie_assen' => 'JSON NULL',
            'waar_onderscheid_zit' => 'TEXT NULL',
        ];
        foreach ($brancheColumns as $column => $definition) {
            if (!ce_table_has_column($pdo, 'branche_library', $column)) {
                try {
                    $pdo->exec("ALTER TABLE branche_library ADD COLUMN `$column` $definition");
                    unset($GLOBALS['ce_table_columns']['branche_library']);
                } catch (Throwable) {
                    // Kolom bestaat al.
                }
            }
        }
    }

    ce_ensure_hba_contentflow_schema($pdo);
    ce_hba_ensure_verdict_model_columns($pdo);
    if (function_exists('ce_hba_migrate_seed_branches_to_records')) {
        ce_hba_migrate_seed_branches_to_records($pdo);
    } else {
        require_once __DIR__ . '/hba-branch-record-repository.php';
        ce_hba_migrate_seed_branches_to_records($pdo);
    }
}

/** Map legacy vrije-tekst archetype naar dichtstbijzijnde HBA-archetype. */
function ce_hba_v2_map_archetype(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    foreach (CE_HBA_ARCHETYPES as $archetype) {
        if (strcasecmp($raw, $archetype) === 0) {
            return $archetype;
        }
    }
    $lower = mb_strtolower($raw);
    $aliases = [
        'leider' => 'Leider',
        'leader' => 'Leider',
        'wijze' => 'Wijze',
        'wise' => 'Wijze',
        'maker' => 'Maker',
        'creator' => 'Maker',
        'rebel' => 'Rebel',
        'kampioen' => 'Kampioen',
        'hero' => 'Kampioen',
        'magier' => 'Magiër',
        'magiër' => 'Magiër',
        'magician' => 'Magiër',
        'tovenaar' => 'Magiër',
        'verzorger' => 'Verzorger',
        'caregiver' => 'Verzorger',
        'optimist' => 'Optimist',
        'jester' => 'Entertainer',
        'entertainer' => 'Entertainer',
        'buur' => 'Buur',
        'everyman' => 'Buur',
        'beminnaar' => 'Beminnaar',
        'lover' => 'Beminnaar',
        'ontdekker' => 'Ontdekker',
        'explorer' => 'Ontdekker',
    ];
    foreach ($aliases as $needle => $mapped) {
        if (str_contains($lower, $needle)) {
            return $mapped;
        }
    }

    return CE_HBA_ARCHETYPES[0];
}

/** @return array<string, string> */
function ce_hba_v2_architecture_context(PDO $pdo, string $clientId): array
{
    ce_ensure_hba_v2_schema($pdo);
    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$narrative) {
        $stmt = $pdo->prepare('SELECT archetype, adversary, brand_goal FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $client = $stmt->fetch() ?: [];

        return [
            'archetype' => ce_hba_v2_map_archetype((string) ($client['archetype'] ?? '')),
            'doel' => trim((string) ($client['brand_goal'] ?? '')),
            'foe' => trim((string) ($client['adversary'] ?? '')),
            'distinctiviteit' => '',
            'wat_bewust_niet' => '',
            'regels' => '',
            'mechanisme' => '',
            'verboden_logica' => '',
            'bewijsvelden' => [],
            'tone_of_behaviour' => '',
            'aanspreekvorm' => '',
            'categorietaal' => '',
            'branche_code' => '',
            'branche_label' => '',
        ];
    }

    $brancheCode = trim((string) ($narrative['branche_code'] ?? ''));
    $categorietaal = '';
    $brancheLabel = '';
    if ($brancheCode !== '' && ce_table_exists($pdo, 'branche_library')) {
        $bStmt = $pdo->prepare('SELECT branche_label, categorietaal FROM branche_library WHERE branche_code = :code LIMIT 1');
        $bStmt->execute(['code' => $brancheCode]);
        $branche = $bStmt->fetch();
        if ($branche) {
            $brancheLabel = (string) ($branche['branche_label'] ?? '');
            $categorietaal = implode(', ', ce_hba_decode_json_list($branche['categorietaal'] ?? []));
        }
    }

    $regels = trim((string) ($narrative['geloofwaardigheidstest'] ?? ''));
    $verboden = ce_hba_decode_json_list($narrative['verboden_logica'] ?? []);
    $watBewustNiet = trim((string) ($narrative['wat_bewust_niet'] ?? ''));
    if ($watBewustNiet === '' && $verboden !== []) {
        $watBewustNiet = implode('; ', $verboden);
    }

    return [
        'archetype' => ce_hba_v2_map_archetype((string) ($narrative['archetype'] ?? '')),
        'doel' => trim((string) ($narrative['doel'] ?? '')),
        'foe' => trim((string) ($narrative['foe'] ?? '')),
        'distinctiviteit' => trim((string) ($narrative['distinctiviteit'] ?? '')),
        'wat_bewust_niet' => $watBewustNiet,
        'regels' => $regels,
        'mechanisme' => trim((string) ($narrative['mechanisme'] ?? '')),
        'verboden_logica' => implode('; ', $verboden),
        'bewijsvelden' => ce_hba_decode_json_list($narrative['bewijsvelden'] ?? []),
        'tone_of_behaviour' => trim((string) ($narrative['tone_of_behaviour'] ?? '')),
        'aanspreekvorm' => ce_hba_normalize_aanspreekvorm((string) ($narrative['aanspreekvorm'] ?? '')),
        'categorietaal' => $categorietaal,
        'branche_code' => $brancheCode,
        'branche_label' => $brancheLabel,
    ];
}

function ce_hba_v2_architecture_ready(PDO $pdo, string $clientId): bool
{
    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);

    return $ctx['archetype'] !== '' && $ctx['foe'] !== '' && $ctx['doel'] !== '';
}

/** @param array<string, mixed> $row */
function ce_hba_v2_positioning_fingerprint(array $row): string
{
    return implode('|', [
        trim((string) ($row['archetype'] ?? '')),
        trim((string) ($row['doel'] ?? '')),
        trim((string) ($row['foe'] ?? '')),
    ]);
}

function ce_hba_v2_current_narrative_version(PDO $pdo, string $clientId): int
{
    $row = ce_fetch_current_brand_narrative($pdo, $clientId);

    return $row ? max(1, (int) ($row['version'] ?? 1)) : 0;
}

function ce_hba_v2_invalidate_positioning_verdicts(PDO $pdo, string $clientId): void
{
    ce_ensure_hba_v2_schema($pdo);
    ce_ensure_hba_contentflow_schema($pdo);
    foreach (['hba_theme_verdicts', 'hba_research_verdicts'] as $table) {
        if (!ce_table_exists($pdo, $table)) {
            continue;
        }
        $pdo->prepare("DELETE FROM {$table} WHERE client_id = :client_id")
            ->execute(['client_id' => $clientId]);
    }

    if (ce_table_exists($pdo, 'hba_article_hints')) {
        $pdo->prepare('DELETE FROM hba_article_hints WHERE client_id = :client_id')
            ->execute(['client_id' => $clientId]);
    }

    if (ce_table_exists($pdo, 'hba_scores') && ce_table_exists($pdo, 'articles') && ce_table_exists($pdo, 'content_slots')) {
        try {
            $pdo->prepare(
                'DELETE hs FROM hba_scores hs
                 INNER JOIN articles a ON a.id = hs.post_id
                 INNER JOIN content_slots cs ON cs.id = a.slot_id
                 WHERE cs.client_id = :client_id'
            )->execute(['client_id' => $clientId]);
        } catch (Throwable) {
            // ignore
        }
    }
}

function ce_hba_v2_score_row_matches_architecture(PDO $pdo, string $clientId, array $scoreRow): bool
{
    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$narrative) {
        return false;
    }
    $currentId = trim((string) ($narrative['id'] ?? ''));
    $currentVersion = max(1, (int) ($narrative['version'] ?? 1));
    $rowNarrativeId = trim((string) ($scoreRow['brand_narrative_id'] ?? ''));
    if ($currentId !== '' && $rowNarrativeId !== '' && $rowNarrativeId !== $currentId) {
        return false;
    }
    if (ce_table_has_column($pdo, 'hba_scores', 'architecture_version')) {
        $rowVersion = $scoreRow['architecture_version'] ?? null;
        if ($rowVersion === null) {
            return $currentVersion <= 1;
        }

        return (int) $rowVersion === $currentVersion;
    }

    return $currentVersion <= 1;
}

function ce_hba_v2_hint_row_matches_architecture(PDO $pdo, string $clientId, array $hintRow): bool
{
    $currentVersion = ce_hba_v2_current_narrative_version($pdo, $clientId);
    if (!ce_table_has_column($pdo, 'hba_article_hints', 'architecture_version')) {
        return $currentVersion <= 1;
    }
    $rowVersion = $hintRow['architecture_version'] ?? null;
    if ($rowVersion === null) {
        return $currentVersion <= 1;
    }

    return (int) $rowVersion === $currentVersion;
}

/** @return array<string, mixed> */
function ce_hba_v2_theme_positioning_meta(PDO $pdo, string $clientId): array
{
    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    $archetype = trim((string) ($ctx['archetype'] ?? ''));
    $doel = trim((string) ($ctx['doel'] ?? ''));

    return [
        // Intern (conflictcheck); niet tonen aan klant — A6 rij 2.
        'positioningArchetype' => $archetype !== '' ? $archetype : null,
        // Klantzichtbaar: doel uit brand narrative i.p.v. Jung-archetype.
        'positioningBrandGoal' => $doel !== '' ? $doel : null,
        'positioningNarrativeVersion' => ce_hba_v2_current_narrative_version($pdo, $clientId),
    ];
}

/** @return array<string, mixed>|null POST-body voor /hba/architecture-check uit huidige narrative */
function ce_hba_v2_architecture_check_body_from_narrative(PDO $pdo, string $clientId): ?array
{
    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$narrative) {
        return null;
    }

    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    if ($ctx['archetype'] === '' || $ctx['doel'] === '' || $ctx['foe'] === '') {
        return null;
    }

    $regels = $ctx['regels'] !== '' ? preg_split('/\R+/', $ctx['regels']) ?: [] : [];

    return [
        'archetype' => $ctx['archetype'],
        'doel' => $ctx['doel'],
        'foe' => $ctx['foe'],
        'distinctiviteit' => $ctx['distinctiviteit'],
        'watBewustNiet' => $ctx['wat_bewust_niet'],
        'regels' => array_values(array_filter(array_map('strval', $regels))),
        'toneOfBehaviour' => $ctx['tone_of_behaviour'],
        'goedeVoorbeelden' => ce_hba_decode_json_list($narrative['goede_voorbeelden'] ?? []),
        'zwakkeVoorbeelden' => ce_hba_decode_json_list($narrative['zwakke_voorbeelden'] ?? []),
        'brancheCode' => $ctx['branche_code'],
    ];
}

function ce_hba_v2_guardrail_check_count(PDO $pdo, string $clientId): int
{
    ce_ensure_hba_contentflow_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_guardrail_objects')) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM hba_guardrail_objects WHERE client_id = :id');
    $stmt->execute(['id' => $clientId]);

    return (int) $stmt->fetchColumn();
}

/** @return array{decision: string, check_score: int|null}|null */
function ce_hba_v2_latest_guardrail_summary(PDO $pdo, string $clientId): ?array
{
    ce_ensure_hba_contentflow_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_guardrail_objects')) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT decision, check_score FROM hba_guardrail_objects
         WHERE client_id = :client_id
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    return [
        'decision' => (string) ($row['decision'] ?? ''),
        'check_score' => isset($row['check_score']) ? (int) $row['check_score'] : null,
    ];
}

/** @return array<string, mixed>|null */
function ce_hba_v2_latest_architectuur_check(PDO $pdo, string $clientId): ?array
{
    if (!ce_table_exists($pdo, 'architectuur_check_result')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT decision, total_score, hard_fail_reason, totaaloordeel, created_at
         FROM architectuur_check_result WHERE client_id = :id ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/** Eenmalig architectuurcheck draaien als wizard af is maar er nog nooit een check liep. */
function ce_hba_v2_try_bootstrap_guardrail(PDO $pdo, string $clientId): bool
{
    if (ce_hba_active_guardrail($pdo, $clientId) !== null) {
        return true;
    }
    if (ce_hba_v2_latest_architectuur_check($pdo, $clientId) !== null) {
        return false;
    }
    if (!ce_table_has_column($pdo, 'clients', 'hba_architecture_completed_at')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT hba_architecture_completed_at FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    if (trim((string) ($stmt->fetchColumn() ?: '')) === '') {
        return false;
    }

    $body = ce_hba_v2_architecture_check_body_from_narrative($pdo, $clientId);
    if ($body === null) {
        return false;
    }

    require_once __DIR__ . '/hba-architectuur-check-repository.php';
    try {
        ce_hba_v2_run_architecture_check($pdo, $clientId, $body);
    } catch (Throwable $e) {
        error_log('CE HBA guardrail bootstrap failed for ' . $clientId . ': ' . $e->getMessage());

        return false;
    }

    return ce_hba_active_guardrail($pdo, $clientId) !== null;
}

function ce_hba_v2_guardrail_inactive_footnote(PDO $pdo, string $clientId): string
{
    $archCheck = ce_hba_v2_latest_architectuur_check($pdo, $clientId);
    if ($archCheck !== null) {
        $decision = strtoupper(trim((string) ($archCheck['decision'] ?? '')));
        $reason = trim((string) ($archCheck['hard_fail_reason'] ?? ''));
        if ($decision === 'FAIL') {
            if ($reason !== '') {
                return 'De architectuurcheck is nog niet geslaagd. ' . $reason
                    . ' Pas je merkarchitectuur aan op BrandArchitecture (stap 7).';
            }

            return 'De architectuurcheck is nog niet geslaagd. '
                . 'Scherp je merkarchitectuur aan op BrandArchitecture (stap 7).';
        }
        if ($decision === 'REVIEW') {
            return 'De architectuurcheck staat op review — positioneringsadvies volgt zodra de check op PASS staat. '
                . 'Verfijn je merkarchitectuur op BrandArchitecture (stap 7) indien nodig.';
        }
    }

    $latest = ce_hba_v2_latest_guardrail_summary($pdo, $clientId);
    if ($latest !== null) {
        $decision = strtoupper(trim((string) ($latest['decision'] ?? '')));
        if ($decision === 'FAIL') {
            return 'De architectuurcheck is nog niet geslaagd. '
                . 'Scherp je merkarchitectuur aan op BrandArchitecture (stap 7) en laat de check opnieuw lopen.';
        }
        if ($decision === 'REVIEW') {
            return 'De architectuurcheck staat op review — positioneringsadvies volgt zodra de check op PASS staat. '
                . 'Verfijn je merkarchitectuur op BrandArchitecture (stap 7) indien nodig.';
        }
    }

    return 'Positioneringsadvies op onderzoek start na de architectuurcheck. '
        . 'Open BrandArchitecture (stap 7 — overzicht) om de check te laten lopen.';
}

/**
 * Onderzoek/thema-advies: volstaat ingevulde merkarchitectuur (geen architectuurcheck-PASS vereist).
 *
 * @return array<string, mixed>|null
 */
function ce_hba_v2_architecture_touchpoint_gate(PDO $pdo, string $clientId): ?array
{
    if (!ce_hba_v2_architecture_ready($pdo, $clientId)) {
        return [
            'ok' => false,
            'error' => 'Vul eerst je merkarchitectuur in',
            'code' => 'architecture_incomplete',
        ];
    }

    return null;
}

/**
 * Blokkeert HBA-ingrijppunten zolang er geen guardrail PASS is (briefing §2.13).
 *
 * @return array<string, mixed>|null Block-response, of null wanneer guardrail actief is.
 */
function ce_hba_v2_guardrail_gate(PDO $pdo, string $clientId): ?array
{
    if (!ce_hba_v2_architecture_ready($pdo, $clientId)) {
        return ['ok' => false, 'error' => 'Vul eerst je merkarchitectuur in', 'code' => 'architecture_incomplete'];
    }

    require_once __DIR__ . '/hba-identity-gate.php';
    if (ce_hba_active_guardrail($pdo, $clientId) !== null) {
        return null;
    }

    ce_hba_v2_try_bootstrap_guardrail($pdo, $clientId);
    if (ce_hba_active_guardrail($pdo, $clientId) !== null) {
        return null;
    }

    return [
        'ok' => true,
        'guardrailActive' => false,
        'code' => 'guardrail_inactive',
        'architectureFootnote' => ce_hba_v2_guardrail_inactive_footnote($pdo, $clientId),
    ];
}

/** @return array<string, mixed> */
function ce_hba_v2_call_json(string $systemPrompt, string $userPrompt, float $temperature = 0.2, int $maxTokens = 4096): array
{
    return ce_hba_v2_call_json_for(null, $systemPrompt, $userPrompt, $temperature, $maxTokens);
}

/** @return array{ok: false, error: string}|array{ok: true, data: array<string, mixed>, model: string} */
function ce_hba_v2_decode_json_llm_text(string $text, string $model): array
{
    $text = trim($text);
    if (str_starts_with($text, '```')) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
    }

    $data = json_decode($text, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'LLM gaf geen geldige JSON terug'];
    }

    return ['ok' => true, 'data' => $data, 'model' => $model];
}

function ce_hba_v2_gemini_model_id(): string
{
    ce_load_env();
    $model = trim((string) (getenv('HBA_GEMINI_MODEL') ?: ''));

    return $model !== '' ? $model : 'gemini-flash-latest';
}

function ce_hba_v2_should_try_gemini_fallback(string $anthropicError): bool
{
    ce_load_env();
    $flag = strtolower(trim((string) (getenv('CE_HBA_GEMINI_FALLBACK') ?: '1')));
    if (in_array($flag, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    $err = strtolower($anthropicError);

    return str_contains($err, 'x-api-key')
        || str_contains($err, 'authentication')
        || str_contains($err, 'anthropic_api_key ontbreekt');
}

/** @return array{ok: bool, data?: array<string, mixed>, model?: string, error?: string} */
function ce_hba_v2_call_json_gemini(
    string $systemPrompt,
    string $userPrompt,
    float $temperature = 0.2,
    int $maxTokens = 4096,
): array {
    $keys = ce_llm_visibility_api_keys();
    $geminiKey = trim((string) ($keys['gemini'] ?? ''));
    if ($geminiKey === '') {
        return ['ok' => false, 'error' => 'GOOGLE_GEMINI_API_KEY ontbreekt'];
    }

    $model = ce_hba_v2_gemini_model_id();
    $userFull = $userPrompt . "\n\nAntwoord uitsluitend met geldige JSON, zonder markdown fences.";
    $payload = json_encode([
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $userFull]]],
        ],
        'generationConfig' => [
            'maxOutputTokens' => max(256, min(8192, $maxTokens)),
            'temperature' => max(0.0, min(1.0, $temperature)),
        ],
    ], JSON_THROW_ON_ERROR);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model)
        . ':generateContent?key='
        . rawurlencode($geminiKey);

    $res = ce_llm_visibility_http_post($url, ['Content-Type: application/json'], $payload, 120);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error']];
    }

    $raw = json_decode($res['body'], true);
    if (!is_array($raw)) {
        return ['ok' => false, 'error' => 'Ongeldig Gemini-antwoord'];
    }

    $text = (string) ($raw['candidates'][0]['content']['parts'][0]['text'] ?? '');

    return ce_hba_v2_decode_json_llm_text($text, $model);
}

/** @return array{ok: bool, data?: array<string, mixed>, model?: string, error?: string} */
function ce_hba_v2_call_json_for(
    ?string $functionKey,
    string $systemPrompt,
    string $userPrompt,
    float $temperature = 0.2,
    int $maxTokens = 4096,
): array {
    ce_load_env();
    if (strtolower(trim((string) (getenv('CE_HBA_LLM_PROVIDER') ?: ''))) === 'gemini') {
        if (str_contains($systemPrompt, '{{') && !str_contains($userPrompt, '{{')) {
            $systemPrompt = $userPrompt;
            $userPrompt = 'Geef nu de gevraagde JSON-output.';
        }

        return ce_hba_v2_call_json_gemini($systemPrompt, $userPrompt, $temperature, $maxTokens);
    }

    $keys = ce_llm_visibility_api_keys();
    $key = trim((string) ($keys['anthropic'] ?? ''));
    if ($key === '') {
        if (ce_hba_v2_should_try_gemini_fallback('ANTHROPIC_API_KEY ontbreekt')) {
            if (str_contains($systemPrompt, '{{') && !str_contains($userPrompt, '{{')) {
                $systemPrompt = $userPrompt;
                $userPrompt = 'Geef nu de gevraagde JSON-output.';
            }

            return ce_hba_v2_call_json_gemini($systemPrompt, $userPrompt, $temperature, $maxTokens);
        }

        return ['ok' => false, 'error' => 'ANTHROPIC_API_KEY ontbreekt'];
    }

    if (str_contains($systemPrompt, '{{') && !str_contains($userPrompt, '{{')) {
        $systemPrompt = $userPrompt;
        $userPrompt = 'Geef nu de gevraagde JSON-output.';
    }

    $model = $functionKey !== null && $functionKey !== ''
        ? ce_hba_model_for($functionKey)
        : ce_hba_model();

    $request = [
        'max_tokens' => max(256, min(8192, $maxTokens)),
        'system' => $systemPrompt,
        'messages' => [
            [
                'role' => 'user',
                'content' => $userPrompt . "\n\nAntwoord uitsluitend met geldige JSON, zonder markdown fences.",
            ],
        ],
    ];
    if (!ce_hba_anthropic_model_omits_temperature($model)) {
        $request['temperature'] = max(0.0, min(1.0, $temperature));
    }

    $payload = json_encode(array_merge(['model' => $model], $request), JSON_THROW_ON_ERROR);

    $res = ce_llm_visibility_http_post(
        'https://api.anthropic.com/v1/messages',
        [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        $payload,
        120,
    );

    if (!$res['ok']) {
        if (ce_hba_v2_should_try_gemini_fallback($res['error'])) {
            return ce_hba_v2_call_json_gemini($systemPrompt, $userPrompt, $temperature, $maxTokens);
        }

        return ['ok' => false, 'error' => $res['error']];
    }

    $raw = json_decode($res['body'], true);
    if (!is_array($raw)) {
        return ['ok' => false, 'error' => 'Ongeldig Anthropic-antwoord'];
    }

    $text = '';
    foreach (($raw['content'] ?? []) as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'text') {
            $text .= (string) ($block['text'] ?? '');
        }
    }

    return ce_hba_v2_decode_json_llm_text($text, $model);
}

/** @return list<array<string, mixed>> */
function ce_hba_v2_fetch_branches(PDO $pdo): array
{
    ce_ensure_hba_v2_schema($pdo);
    if (!ce_table_exists($pdo, 'branche_library')) {
        return [];
    }
    $stmt = $pdo->query(
        'SELECT * FROM branche_library ORDER BY branche_label ASC'
    );

    return $stmt ? ($stmt->fetchAll() ?: []) : [];
}

/** @return array<string, mixed> */
function ce_hba_v2_derive_architecture(PDO $pdo, string $clientId, array $body): array
{
    ce_ensure_hba_v2_schema($pdo);

    $brancheCode = trim((string) ($body['brancheCode'] ?? ''));
    $brancheLabel = trim((string) ($body['brancheLabel'] ?? ''));
    $categorietaal = '';
    if ($brancheCode !== '' && ce_table_exists($pdo, 'branche_library')) {
        $bStmt = $pdo->prepare('SELECT branche_label, categorietaal FROM branche_library WHERE branche_code = :code LIMIT 1');
        $bStmt->execute(['code' => $brancheCode]);
        $branche = $bStmt->fetch();
        if ($branche) {
            $brancheLabel = (string) ($branche['branche_label'] ?? $brancheLabel);
            $categorietaal = implode(', ', ce_hba_decode_json_list($branche['categorietaal'] ?? []));
        }
    }

    $regelsList = is_array($body['regels'] ?? null) ? $body['regels'] : [];
    $regels = implode("\n", array_values(array_filter(array_map('strval', $regelsList))));

    require_once __DIR__ . '/hba-wizard-document-repository.php';
    $strategyDocument = ce_hba_wizard_document_extracted_text($clientId);
    if ($strategyDocument !== '') {
        $chunks = ce_strategy_document_text_chunks($strategyDocument);
        $strategyDocument = implode("\n\n---\n\n", $chunks);
        if (mb_strlen($strategyDocument) > 12000) {
            $strategyDocument = mb_substr($strategyDocument, 0, 12000) . '…';
        }
    } else {
        $strategyDocument = '(geen strategiedocument geüpload)';
    }

    $system = ce_hba_v2_derive_system_prompt();
    $user = ce_hba_v2_fill_prompt($system, [
        'branche_label' => $brancheLabel !== '' ? $brancheLabel : 'Onbekend',
        'categorietaal' => $categorietaal !== '' ? $categorietaal : 'Geen branche-context',
        'identity_intro' => trim((string) ($body['identityIntro'] ?? $body['identity_intro'] ?? '')),
        'doel' => trim((string) ($body['doel'] ?? '')),
        'distinctiviteit' => trim((string) ($body['distinctiviteit'] ?? '')),
        'wat_bewust_niet' => trim((string) ($body['watBewustNiet'] ?? $body['wat_bewust_niet'] ?? '')),
        'regels' => $regels,
        'strategy_document' => $strategyDocument,
    ]);

    $llm = ce_hba_v2_call_json_for(CE_HBA_FN_ARCHITECTUUR_CHECK, $user, 'Geef nu de gevraagde JSON-output.');
    if (!$llm['ok']) {
        return ['ok' => false, 'error' => $llm['error'] ?? 'Afleiding mislukt'];
    }

    return ['ok' => true, 'derivation' => $llm['data']];
}

/** @param list<array<string, mixed>> $themes */
function ce_hba_v2_format_themes_for_prompt(array $themes): string
{
    $lines = [];
    foreach ($themes as $theme) {
        $motivation = trim((string) ($theme['motivation'] ?? ''));
        if ($motivation === '') {
            $motivation = trim((string) ($theme['rationale'] ?? ''));
        }
        $lines[] = sprintf(
            '- id=%s | titel=%s | motivatie=%s',
            (string) ($theme['id'] ?? ''),
            (string) ($theme['title'] ?? ''),
            $motivation
        );
    }

    return $lines === [] ? '(geen thema\'s)' : implode("\n", $lines);
}

/** @return list<string> */
function ce_hba_v2_merge_theme_filter_ids(string $themeId, string $slotId): array
{
    $ids = [];
    if ($themeId !== '') {
        $ids[] = $themeId;
    }
    if ($slotId !== '' && !in_array($slotId, $ids, true)) {
        $ids[] = $slotId;
    }

    return $ids;
}

/**
 * Koppel LLM-thema_id (uuid, index of titel) aan een echt theme-id.
 *
 * @param list<array<string, mixed>> $themes
 */
function ce_hba_v2_resolve_theme_id(array $themes, string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $byId = [];
    $byTitle = [];
    $byIndex = [];
    foreach ($themes as $index => $theme) {
        $id = trim((string) ($theme['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $byId[strtolower($id)] = $id;
        $byId[str_replace('-', '', strtolower($id))] = $id;
        $title = strtolower(trim((string) ($theme['title'] ?? '')));
        if ($title !== '') {
            $byTitle[$title] = $id;
        }
        $byIndex[(string) ($index + 1)] = $id;
    }

    $key = strtolower($raw);
    if (isset($byId[$key]) || isset($byId[str_replace('-', '', $key)])) {
        return $byId[$key] ?? $byId[str_replace('-', '', $key)];
    }
    if (isset($byTitle[$key])) {
        return $byTitle[$key];
    }
    if (preg_match('/^(?:thema|theme)\s*(\d+)$/i', $raw, $matches) && isset($byIndex[$matches[1]])) {
        return $byIndex[$matches[1]];
    }
    if (isset($byIndex[$raw])) {
        return $byIndex[$raw];
    }

    return '';
}

/**
 * @param list<string> $themeIds
 * @return list<array<string, mixed>>
 */
function ce_hba_v2_theme_rows_by_ids(PDO $pdo, array $themeIds): array
{
    $themeIds = array_values(array_unique(array_filter(array_map(
        static fn ($id) => trim((string) $id),
        $themeIds,
    ), static fn ($id) => $id !== '')));
    if ($themeIds === []) {
        return [];
    }

    $byId = [];
    if (ce_table_exists($pdo, 'themes')) {
        $cols = ['id', 'title'];
        if (ce_table_has_column($pdo, 'themes', 'motivation')) {
            $cols[] = 'motivation';
        }
        if (ce_table_has_column($pdo, 'themes', 'rationale')) {
            $cols[] = 'rationale';
        }
        $placeholders = implode(',', array_fill(0, count($themeIds), '?'));
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $cols) . " FROM themes WHERE id IN ({$placeholders})");
        $stmt->execute($themeIds);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $motivation = trim((string) ($row['motivation'] ?? ''));
            if ($motivation === '') {
                $motivation = trim((string) ($row['rationale'] ?? ''));
            }
            $byId[$id] = [
                'id' => $id,
                'title' => (string) ($row['title'] ?? ''),
                'motivation' => $motivation,
            ];
        }
    }

    $missing = array_values(array_filter($themeIds, static fn ($id) => !isset($byId[$id])));
    if ($missing !== [] && ce_table_exists($pdo, 'content_slots')) {
        require_once __DIR__ . '/theme-repository.php';
        foreach ($missing as $slotId) {
            if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
                continue;
            }
            $ctx = ce_resolve_slot_research_context($pdo, $slotId);
            $title = trim((string) ($ctx['slotTheme'] ?? ''));
            if ($title === '') {
                continue;
            }
            $linkedThemeId = trim((string) ($ctx['themeId'] ?? ''));
            $canonicalId = $linkedThemeId !== '' ? $linkedThemeId : $slotId;
            $row = [
                'id' => $canonicalId,
                'title' => $title,
                'motivation' => trim((string) ($ctx['themeRationale'] ?? '')),
            ];
            // Index onder het opgevraagde slot-id zodat slot_id-filters matchen.
            $byId[$slotId] = $row;
            if ($canonicalId !== $slotId) {
                $byId[$canonicalId] = $row;
            }
        }
    }

    $out = [];
    $seen = [];
    foreach ($themeIds as $id) {
        $row = $byId[$id] ?? null;
        if ($row === null) {
            foreach ($byId as $candidate) {
                if (($candidate['id'] ?? '') === $id) {
                    $row = $candidate;
                    break;
                }
            }
        }
        if ($row === null) {
            continue;
        }
        $canonical = (string) ($row['id'] ?? '');
        if ($canonical === '' || isset($seen[$canonical])) {
            continue;
        }
        $seen[$canonical] = true;
        $out[] = $row;
    }

    return $out;
}

/**
 * Breid slot_id-filters uit met gekoppeld theme_id (verdicts worden op theme_id opgeslagen).
 *
 * @param list<string> $themeIds
 * @return list<string>
 */
function ce_hba_v2_expand_theme_verdict_ids(PDO $pdo, array $themeIds): array
{
    $expanded = [];
    foreach ($themeIds as $raw) {
        $id = trim((string) $raw);
        if ($id === '') {
            continue;
        }
        $expanded[] = $id;
        if (!preg_match('/^[0-9a-f-]{36}$/i', $id) || !ce_table_exists($pdo, 'content_slots')) {
            continue;
        }
        require_once __DIR__ . '/theme-repository.php';
        $ctx = ce_resolve_slot_research_context($pdo, $id);
        $linked = trim((string) ($ctx['themeId'] ?? ''));
        if ($linked !== '' && $linked !== $id) {
            $expanded[] = $linked;
        }
        if (!ce_table_has_column($pdo, 'content_slots', 'theme_id')) {
            continue;
        }
        $slotStmt = $pdo->prepare(
            "SELECT id FROM content_slots
             WHERE theme_id = :theme_id
               AND COALESCE(status, '') NOT IN ('archived', 'archive', 'deleted')
             LIMIT 20"
        );
        $slotStmt->execute(['theme_id' => $id]);
        foreach ($slotStmt->fetchAll() ?: [] as $slotRow) {
            $sid = trim((string) ($slotRow['id'] ?? ''));
            if ($sid !== '') {
                $expanded[] = $sid;
            }
        }
    }

    return array_values(array_unique($expanded));
}

function ce_hba_v2_theme_verdicts_payload(array $cached): array
{
    return [
        'ok' => true,
        'verdicts' => $cached['verdicts'] ?? [],
        'watOntbreekt' => $cached['watOntbreekt'] ?? null,
    ];
}

/** @return array<string, mixed> */
function ce_hba_v2_load_theme_verdicts(PDO $pdo, string $clientId, ?string $week = null, array $themeIds = []): array
{
    ce_ensure_hba_v2_schema($pdo);
    $gate = $themeIds !== []
        ? ce_hba_v2_architecture_touchpoint_gate($pdo, $clientId)
        : ce_hba_v2_guardrail_gate($pdo, $clientId);
    if ($gate !== null) {
        return array_merge($gate, ['verdicts' => [], 'watOntbreekt' => null]);
    }

    $themes = [];
    if ($themeIds === []) {
        $themesPayload = ce_fetch_themes($pdo, $week);
        $themes = is_array($themesPayload['themes'] ?? null) ? $themesPayload['themes'] : [];
        foreach ($themes as $theme) {
            $id = trim((string) ($theme['id'] ?? ''));
            if ($id !== '') {
                $themeIds[] = $id;
            }
        }
    } else {
        $themes = ce_hba_v2_theme_rows_by_ids($pdo, $themeIds);
    }

    $queryIds = ce_hba_v2_expand_theme_verdict_ids($pdo, $themeIds);
    $cached = ce_hba_v2_fetch_theme_verdicts($pdo, $clientId, $queryIds);

    return array_merge(
        ce_hba_v2_theme_verdicts_payload($cached),
        ce_hba_v2_theme_positioning_meta($pdo, $clientId),
        ['themeCount' => count($themes)],
    );
}

/** @return array<string, mixed> */
function ce_hba_v2_run_theme_verdicts(PDO $pdo, string $clientId, ?string $week = null, array $themeIdsFilter = []): array
{
    ce_ensure_hba_v2_schema($pdo);
    $gate = $themeIdsFilter !== []
        ? ce_hba_v2_architecture_touchpoint_gate($pdo, $clientId)
        : ce_hba_v2_guardrail_gate($pdo, $clientId);
    if ($gate !== null) {
        return array_merge($gate, ['verdicts' => [], 'watOntbreekt' => null]);
    }

    $themes = [];
    if ($themeIdsFilter !== []) {
        $themes = ce_hba_v2_theme_rows_by_ids($pdo, $themeIdsFilter);
    } else {
        $themesPayload = ce_fetch_themes($pdo, $week);
        $themes = is_array($themesPayload['themes'] ?? null) ? $themesPayload['themes'] : [];
    }
    if ($themes === []) {
        return ['ok' => true, 'verdicts' => [], 'watOntbreekt' => null];
    }

    $recentLines = [];
    if (ce_table_exists($pdo, 'content_slots')) {
        try {
            $dateCol = ce_table_has_column($pdo, 'content_slots', 'publish_date')
                ? 'publish_date'
                : (ce_table_has_column($pdo, 'content_slots', 'created_at') ? 'created_at' : null);
            $selectDate = $dateCol !== null ? ", cs.{$dateCol} AS recent_date" : '';
            $whereDate = $dateCol !== null
                ? " AND cs.{$dateCol} >= DATE_SUB(CURDATE(), INTERVAL 42 DAY)"
                : '';
            $orderDate = $dateCol !== null ? " ORDER BY cs.{$dateCol} DESC" : '';
            $recentStmt = $pdo->prepare(
                "SELECT DISTINCT cs.theme_id, cs.theme{$selectDate}
                 FROM content_slots cs
                 WHERE cs.client_id = :client_id
                   AND cs.theme_id IS NOT NULL AND TRIM(cs.theme_id) <> ''
                   {$whereDate}
                 {$orderDate}
                 LIMIT 20"
            );
            $recentStmt->execute(['client_id' => $clientId]);
            foreach ($recentStmt->fetchAll() ?: [] as $row) {
                $recentLines[] = sprintf('- %s (%s)', (string) ($row['theme'] ?? ''), (string) ($row['recent_date'] ?? ''));
            }
        } catch (Throwable) {
            $recentLines = [];
        }
    }

    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    require_once __DIR__ . '/hba-prompt.php';
    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId) ?: [];
    $t4 = ce_hba_archetype_market_match_block($pdo, $narrative);
    $bewijslast = '';
    if ($t4 !== '') {
        $bewijslast = 'ZWAARDERE BEWIJSLAST: ' . $t4
            . ' Voor themavoorstellen: geef "versterkt" alleen als het thema een concreet '
            . 'element van het mechanisme of de regel laat zien (handeling, getal of keuze '
            . 'die geld of groei kost). Een zorgzaam, warm of menselijk thema zonder dat '
            . 'element is hoogstens "neutraal".';
    }
    $system = ce_hba_v2_theme_system_prompt();
    $filled = ce_hba_v2_fill_prompt($system, [
        'archetype' => $ctx['archetype'],
        'doel' => $ctx['doel'],
        'foe' => $ctx['foe'],
        'distinctiviteit' => $ctx['distinctiviteit'] !== '' ? $ctx['distinctiviteit'] : '(niet ingevuld)',
        'wat_bewust_niet' => $ctx['wat_bewust_niet'] !== '' ? $ctx['wat_bewust_niet'] : '(niet ingevuld)',
        'regels' => $ctx['regels'] !== '' ? $ctx['regels'] : '(niet ingevuld)',
        'mechanisme' => ($ctx['mechanisme'] ?? '') !== '' ? $ctx['mechanisme'] : '(niet ingevuld)',
        'categorietaal' => $ctx['categorietaal'] !== '' ? $ctx['categorietaal'] : '(geen branche)',
        'archetype_bewijslast_block' => $bewijslast,
        'themas' => ce_hba_v2_format_themes_for_prompt($themes),
        'recente_themas' => $recentLines === [] ? '(geen recente thema\'s)' : implode("\n", $recentLines),
    ]);

    $themeIds = [];
    foreach ($themes as $theme) {
        $id = trim((string) ($theme['id'] ?? ''));
        if ($id !== '') {
            $themeIds[] = $id;
        }
    }
    $cached = ce_hba_v2_fetch_theme_verdicts(
        $pdo,
        $clientId,
        ce_hba_v2_expand_theme_verdict_ids($pdo, array_values(array_unique(array_merge($themeIds, $themeIdsFilter)))),
    );

    $llm = ce_hba_v2_call_json_for(CE_HBA_FN_THEMA_VERDICT, $filled, 'Geef nu de gevraagde JSON-output.');
    if (!$llm['ok']) {
        if (($cached['verdicts'] ?? []) !== []) {
            return array_merge(
                ce_hba_v2_theme_verdicts_payload($cached),
                [
                    'error' => $llm['error'] ?? 'Thema-advies mislukt',
                ],
                ce_hba_v2_theme_positioning_meta($pdo, $clientId),
            );
        }

        return array_merge(
            ['ok' => false, 'error' => $llm['error'] ?? 'Thema-advies mislukt'],
            ce_hba_v2_theme_positioning_meta($pdo, $clientId),
        );
    }

    $data = is_array($llm['data'] ?? null) ? $llm['data'] : [];
    $watOntbreekt = trim((string) ($data['wat_ontbreekt'] ?? ''));
    $oordelen = is_array($data['thema_oordelen'] ?? null) ? $data['thema_oordelen'] : [];
    $saved = [];
    $canPersist = ce_table_exists($pdo, 'hba_theme_verdicts');
    $modelUsed = trim((string) ($llm['model'] ?? ce_hba_model_for(CE_HBA_FN_THEMA_VERDICT)));
    $logModel = ce_hba_table_has_model_column($pdo, 'hba_theme_verdicts') ? $modelUsed : null;
    $narrativeVersion = ce_hba_v2_current_narrative_version($pdo, $clientId);
    $hasArchVersion = ce_table_has_column($pdo, 'hba_theme_verdicts', 'architecture_version');

    foreach ($oordelen as $item) {
        if (!is_array($item)) {
            continue;
        }
        $themeId = ce_hba_v2_resolve_theme_id(
            $themes,
            trim((string) ($item['thema_id'] ?? $item['theme_id'] ?? $item['id'] ?? '')),
        );
        $label = ce_hba_v2_normalize_theme_verdict_label((string) ($item['label'] ?? ''));
        $regelBotsing = ce_hba_v2_theme_verdict_bool($item['regel_botsing'] ?? $item['regelBotsing'] ?? false);
        if ($regelBotsing) {
            $label = 'verwatert';
        }
        if ($themeId === '' || !in_array($label, ['versterkt', 'neutraal', 'verwatert'], true)) {
            continue;
        }
        $toelichting = ce_hba_v2_compose_theme_fit_toelichting(
            trim((string) ($item['toelichting'] ?? '')),
            $regelBotsing,
            trim((string) ($item['regel'] ?? '')),
            trim((string) ($item['invalshoek'] ?? '')),
        );
        $id = ce_uuid();
        if ($canPersist) {
            try {
                if ($logModel !== null && $hasArchVersion) {
                    $pdo->prepare(
                        'INSERT INTO hba_theme_verdicts (id, client_id, theme_id, label, toelichting, wat_ontbreekt, model, architecture_version, judged_at)
                         VALUES (:id, :client_id, :theme_id, :label, :toelichting, :wat_ontbreekt, :model, :architecture_version, NOW())'
                    )->execute([
                        'id' => $id,
                        'client_id' => $clientId,
                        'theme_id' => $themeId,
                        'label' => $label,
                        'toelichting' => $toelichting,
                        'wat_ontbreekt' => $watOntbreekt !== '' ? $watOntbreekt : null,
                        'model' => $logModel,
                        'architecture_version' => $narrativeVersion,
                    ]);
                } elseif ($logModel !== null) {
                    $pdo->prepare(
                        'INSERT INTO hba_theme_verdicts (id, client_id, theme_id, label, toelichting, wat_ontbreekt, model, judged_at)
                         VALUES (:id, :client_id, :theme_id, :label, :toelichting, :wat_ontbreekt, :model, NOW())'
                    )->execute([
                        'id' => $id,
                        'client_id' => $clientId,
                        'theme_id' => $themeId,
                        'label' => $label,
                        'toelichting' => $toelichting,
                        'wat_ontbreekt' => $watOntbreekt !== '' ? $watOntbreekt : null,
                        'model' => $logModel,
                    ]);
                } elseif ($hasArchVersion) {
                    $pdo->prepare(
                        'INSERT INTO hba_theme_verdicts (id, client_id, theme_id, label, toelichting, wat_ontbreekt, architecture_version, judged_at)
                         VALUES (:id, :client_id, :theme_id, :label, :toelichting, :wat_ontbreekt, :architecture_version, NOW())'
                    )->execute([
                        'id' => $id,
                        'client_id' => $clientId,
                        'theme_id' => $themeId,
                        'label' => $label,
                        'toelichting' => $toelichting,
                        'wat_ontbreekt' => $watOntbreekt !== '' ? $watOntbreekt : null,
                        'architecture_version' => $narrativeVersion,
                    ]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO hba_theme_verdicts (id, client_id, theme_id, label, toelichting, wat_ontbreekt, judged_at)
                         VALUES (:id, :client_id, :theme_id, :label, :toelichting, :wat_ontbreekt, NOW())'
                    )->execute([
                        'id' => $id,
                        'client_id' => $clientId,
                        'theme_id' => $themeId,
                        'label' => $label,
                        'toelichting' => $toelichting,
                        'wat_ontbreekt' => $watOntbreekt !== '' ? $watOntbreekt : null,
                    ]);
                }
                ce_hba_persist_outcome_meta($pdo, 'hba_theme_verdicts', $id, $logModel ?? $modelUsed);
            } catch (Throwable) {
                // Toon het oordeel ook als opslaan faalt.
            }
        }
        $saved[] = [
            'id' => $id,
            'themeId' => $themeId,
            'label' => $label,
            'toelichting' => $toelichting,
        ];
    }

    if ($saved === [] && ($cached['verdicts'] ?? []) !== []) {
        return array_merge(ce_hba_v2_theme_verdicts_payload($cached), [
            'error' => 'Thema-advies gaf geen herkenbare oordelen',
        ], ce_hba_v2_theme_positioning_meta($pdo, $clientId));
    }

    return array_merge(
        [
            'ok' => true,
            'verdicts' => $saved,
            'watOntbreekt' => $watOntbreekt !== '' ? $watOntbreekt : ($cached['watOntbreekt'] ?? null),
        ],
        ce_hba_v2_theme_positioning_meta($pdo, $clientId),
    );
}

/** @return array{verdicts: list<array<string, mixed>>, watOntbreekt: ?string} */
function ce_hba_v2_fetch_theme_verdicts(PDO $pdo, string $clientId, array $themeIds): array
{
    ce_ensure_hba_v2_schema($pdo);
    if ($themeIds === [] || !ce_table_exists($pdo, 'hba_theme_verdicts')) {
        return ['verdicts' => [], 'watOntbreekt' => null];
    }

    $currentVersion = ce_hba_v2_current_narrative_version($pdo, $clientId);
    $hasArchVersion = ce_table_has_column($pdo, 'hba_theme_verdicts', 'architecture_version');
    $currentArchetype = trim((string) (ce_hba_v2_architecture_context($pdo, $clientId)['archetype'] ?? ''));

    $placeholders = implode(',', array_fill(0, count($themeIds), '?'));
    $archSelect = $hasArchVersion ? ', v.architecture_version' : '';
    $stmt = $pdo->prepare(
        "SELECT v.theme_id, v.label, v.toelichting, v.wat_ontbreekt{$archSelect}
         FROM hba_theme_verdicts v
         INNER JOIN (
           SELECT theme_id, MAX(judged_at) AS max_judged
           FROM hba_theme_verdicts
           WHERE client_id = ?
             AND theme_id IS NOT NULL
           GROUP BY theme_id
         ) latest ON latest.theme_id <=> v.theme_id AND latest.max_judged <=> v.judged_at
         WHERE v.client_id = ? AND v.theme_id IS NOT NULL AND v.theme_id IN ($placeholders)"
    );
    $params = [$clientId, $clientId, ...$themeIds];
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];
    $out = [];
    $watOntbreekt = null;
    foreach ($rows as $row) {
        if ($hasArchVersion && $currentVersion > 0) {
            $rowVersion = $row['architecture_version'] ?? null;
            if ($rowVersion === null) {
                if ($currentVersion > 1) {
                    continue;
                }
            } elseif ((int) $rowVersion !== $currentVersion) {
                continue;
            }
        }
        $toelichting = (string) ($row['toelichting'] ?? '');
        if (ce_hba_v2_toelichting_conflicts_with_archetype($toelichting, $currentArchetype)) {
            continue;
        }
        if ($watOntbreekt === null && !empty($row['wat_ontbreekt'])) {
            $rowWat = (string) $row['wat_ontbreekt'];
            if (!ce_hba_v2_toelichting_conflicts_with_archetype($rowWat, $currentArchetype)) {
                $watOntbreekt = $rowWat;
            }
        }
        $out[] = [
            'themeId' => (string) ($row['theme_id'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'toelichting' => $toelichting,
        ];
    }

    return ['verdicts' => $out, 'watOntbreekt' => $watOntbreekt];
}

function ce_hba_v2_toelichting_conflicts_with_archetype(string $toelichting, string $currentArchetype): bool
{
    if ($currentArchetype === '' || trim($toelichting) === '') {
        return false;
    }
    foreach (CE_HBA_ARCHETYPES as $archetype) {
        if (strcasecmp($archetype, $currentArchetype) === 0) {
            continue;
        }
        if (stripos($toelichting, $archetype) !== false) {
            return true;
        }
        if (stripos($toelichting, $archetype . '-archetype') !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $items
 */
function ce_hba_v2_format_research_for_prompt(array $items): string
{
    $lines = [];
    foreach ($items as $item) {
        $snippet = trim((string) ($item['snippet'] ?? ''));
        if (mb_strlen($snippet) > 280) {
            $snippet = mb_substr($snippet, 0, 277) . '…';
        }
        $lines[] = sprintf(
            '- id=%s | titel=%s | snippet=%s',
            (string) ($item['id'] ?? ''),
            (string) ($item['title'] ?? ''),
            $snippet
        );
    }

    return $lines === [] ? '(geen research)' : implode("\n", $lines);
}

/**
 * @param list<array<string, mixed>> $items
 */
function ce_hba_v2_resolve_research_item_id(array $items, string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $byId = [];
    $byTitle = [];
    $byIndex = [];
    foreach ($items as $index => $item) {
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $byId[strtolower($id)] = $id;
        $byId[str_replace('-', '', strtolower($id))] = $id;
        $title = strtolower(trim((string) ($item['title'] ?? '')));
        if ($title !== '') {
            $byTitle[$title] = $id;
        }
        $byIndex[(string) ($index + 1)] = $id;
    }

    $key = strtolower($raw);
    if (isset($byId[$key]) || isset($byId[str_replace('-', '', $key)])) {
        return $byId[$key] ?? $byId[str_replace('-', '', $key)];
    }
    if (isset($byTitle[$key])) {
        return $byTitle[$key];
    }
    if (preg_match('/^(?:item|bron|research)\s*(\d+)$/i', $raw, $matches) && isset($byIndex[$matches[1]])) {
        return $byIndex[$matches[1]];
    }
    if (isset($byIndex[$raw])) {
        return $byIndex[$raw];
    }

    return '';
}

function ce_hba_v2_research_verdicts_payload(array $cached): array
{
    return [
        'ok' => true,
        'verdicts' => $cached['verdicts'] ?? [],
        'watOntbreekt' => $cached['watOntbreekt'] ?? null,
    ];
}

/**
 * @param list<array<string, mixed>> $saved
 * @param array{verdicts: list<array<string, mixed>>, watOntbreekt: ?string} $cached
 * @return array{verdicts: list<array<string, mixed>>, watOntbreekt: ?string}
 */
function ce_hba_v2_merge_research_verdict_sets(array $saved, array $cached): array
{
    $byItem = [];
    foreach ($cached['verdicts'] ?? [] as $row) {
        $itemId = trim((string) ($row['itemId'] ?? ''));
        if ($itemId !== '') {
            $byItem[$itemId] = $row;
        }
    }
    foreach ($saved as $row) {
        $itemId = trim((string) ($row['itemId'] ?? ''));
        if ($itemId !== '') {
            $byItem[$itemId] = $row;
        }
    }

    return [
        'verdicts' => array_values($byItem),
        'watOntbreekt' => $cached['watOntbreekt'] ?? null,
    ];
}

/** @return array<string, mixed> */
function ce_hba_v2_load_research_group(PDO $pdo, string $clientId, string $slotId): array
{
    require_once __DIR__ . '/client-context.php';
    ce_assert_slot_client_access($pdo, $slotId, $clientId);

    $group = ce_fetch_research_theme_group($pdo, $slotId, 10);
    $items = is_array($group['items'] ?? null) ? $group['items'] : [];

    return [
        'slotId' => $slotId,
        'themeTitle' => (string) ($group['themeTitle'] ?? ''),
        'themeRationale' => (string) ($group['themeRationale'] ?? ''),
        'items' => $items,
    ];
}

/** @return array<string, mixed> */
function ce_hba_v2_load_research_verdicts(PDO $pdo, string $clientId, string $slotId): array
{
    ce_ensure_hba_v2_schema($pdo);
    $gate = ce_hba_v2_architecture_touchpoint_gate($pdo, $clientId);
    if ($gate !== null) {
        return array_merge($gate, ['verdicts' => [], 'watOntbreekt' => null, 'itemCount' => 0]);
    }

    $slotId = trim($slotId);
    if ($slotId === '') {
        return ['ok' => true, 'verdicts' => [], 'watOntbreekt' => null, 'itemCount' => 0];
    }

    $group = ce_hba_v2_load_research_group($pdo, $clientId, $slotId);
    $itemIds = [];
    foreach ($group['items'] as $item) {
        $id = trim((string) ($item['id'] ?? ''));
        if ($id !== '') {
            $itemIds[] = $id;
        }
    }
    $cached = ce_hba_v2_fetch_research_verdicts($pdo, $clientId, $slotId, $itemIds);

    return array_merge(
        ce_hba_v2_research_verdicts_payload($cached),
        ce_hba_v2_theme_positioning_meta($pdo, $clientId),
        ['itemCount' => count($group['items'])],
    );
}

/** @return array<string, mixed> */
function ce_hba_v2_run_research_verdicts(PDO $pdo, string $clientId, string $slotId): array
{
    ce_ensure_hba_v2_schema($pdo);
    $gate = ce_hba_v2_architecture_touchpoint_gate($pdo, $clientId);
    if ($gate !== null) {
        return array_merge($gate, ['verdicts' => [], 'watOntbreekt' => null]);
    }

    $slotId = trim($slotId);
    if ($slotId === '') {
        return ['ok' => true, 'verdicts' => [], 'watOntbreekt' => null];
    }

    $group = ce_hba_v2_load_research_group($pdo, $clientId, $slotId);
    $items = $group['items'];
    if ($items === []) {
        return ['ok' => true, 'verdicts' => [], 'watOntbreekt' => null];
    }

    $itemIds = [];
    foreach ($items as $item) {
        $id = trim((string) ($item['id'] ?? ''));
        if ($id !== '') {
            $itemIds[] = $id;
        }
    }
    $cached = ce_hba_v2_fetch_research_verdicts($pdo, $clientId, $slotId, $itemIds);

    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    $system = ce_hba_v2_research_system_prompt();
    $filled = ce_hba_v2_fill_prompt($system, [
        'archetype' => $ctx['archetype'],
        'doel' => $ctx['doel'],
        'foe' => $ctx['foe'],
        'distinctiviteit' => $ctx['distinctiviteit'] !== '' ? $ctx['distinctiviteit'] : '(niet ingevuld)',
        'wat_bewust_niet' => $ctx['wat_bewust_niet'] !== '' ? $ctx['wat_bewust_niet'] : '(niet ingevuld)',
        'regels' => $ctx['regels'] !== '' ? $ctx['regels'] : '(niet ingevuld)',
        'categorietaal' => $ctx['categorietaal'] !== '' ? $ctx['categorietaal'] : '(geen branche)',
        'theme_title' => $group['themeTitle'] !== '' ? $group['themeTitle'] : '(geen thema)',
        'theme_rationale' => $group['themeRationale'] !== '' ? $group['themeRationale'] : '(geen toelichting)',
        'items' => ce_hba_v2_format_research_for_prompt($items),
    ]);

    $llm = ce_hba_v2_call_json_for(CE_HBA_FN_THEMA_VERDICT, $filled, 'Geef nu de gevraagde JSON-output.');
    if (!$llm['ok']) {
        if (($cached['verdicts'] ?? []) !== []) {
            return array_merge(
                ce_hba_v2_research_verdicts_payload($cached),
                [
                    'error' => $llm['error'] ?? 'Research-advies mislukt',
                ],
                ce_hba_v2_theme_positioning_meta($pdo, $clientId),
            );
        }

        return array_merge(
            ['ok' => false, 'error' => $llm['error'] ?? 'Research-advies mislukt'],
            ce_hba_v2_theme_positioning_meta($pdo, $clientId),
        );
    }

    $data = is_array($llm['data'] ?? null) ? $llm['data'] : [];
    $watOntbreekt = trim((string) ($data['wat_ontbreekt'] ?? ''));
    $oordelen = is_array($data['research_oordelen'] ?? null) ? $data['research_oordelen'] : [];
    if ($oordelen === [] && is_array($data['thema_oordelen'] ?? null)) {
        $oordelen = $data['thema_oordelen'];
    }
    $saved = [];
    $canPersist = ce_table_exists($pdo, 'hba_research_verdicts');
    $modelUsed = trim((string) ($llm['model'] ?? ce_hba_model_for(CE_HBA_FN_THEMA_VERDICT)));
    $logModel = ce_hba_table_has_model_column($pdo, 'hba_research_verdicts') ? $modelUsed : null;
    $narrativeVersion = ce_hba_v2_current_narrative_version($pdo, $clientId);
    $hasArchVersion = ce_table_has_column($pdo, 'hba_research_verdicts', 'architecture_version');

    foreach ($oordelen as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = ce_hba_v2_resolve_research_item_id(
            $items,
            trim((string) ($item['item_id'] ?? $item['research_item_id'] ?? $item['id'] ?? $item['thema_id'] ?? '')),
        );
        $label = strtolower(trim((string) ($item['label'] ?? '')));
        if ($itemId === '' || !in_array($label, ['versterkt', 'neutraal', 'verwatert'], true)) {
            continue;
        }
        $toelichting = trim((string) ($item['toelichting'] ?? ''));
        $id = ce_uuid();
        if ($canPersist) {
            try {
                if ($logModel !== null && $hasArchVersion) {
                    $pdo->prepare(
                        'INSERT INTO hba_research_verdicts (id, client_id, slot_id, research_item_id, label, toelichting, wat_ontbreekt, model, architecture_version, judged_at)
                         VALUES (:id, :client_id, :slot_id, :research_item_id, :label, :toelichting, :wat_ontbreekt, :model, :architecture_version, NOW())'
                    )->execute([
                        'id' => $id,
                        'client_id' => $clientId,
                        'slot_id' => $slotId,
                        'research_item_id' => $itemId,
                        'label' => $label,
                        'toelichting' => $toelichting,
                        'wat_ontbreekt' => $watOntbreekt !== '' ? $watOntbreekt : null,
                        'model' => $logModel,
                        'architecture_version' => $narrativeVersion,
                    ]);
                } elseif ($logModel !== null) {
                    $pdo->prepare(
                        'INSERT INTO hba_research_verdicts (id, client_id, slot_id, research_item_id, label, toelichting, wat_ontbreekt, model, judged_at)
                         VALUES (:id, :client_id, :slot_id, :research_item_id, :label, :toelichting, :wat_ontbreekt, :model, NOW())'
                    )->execute([
                        'id' => $id,
                        'client_id' => $clientId,
                        'slot_id' => $slotId,
                        'research_item_id' => $itemId,
                        'label' => $label,
                        'toelichting' => $toelichting,
                        'wat_ontbreekt' => $watOntbreekt !== '' ? $watOntbreekt : null,
                        'model' => $logModel,
                    ]);
                } elseif ($hasArchVersion) {
                    $pdo->prepare(
                        'INSERT INTO hba_research_verdicts (id, client_id, slot_id, research_item_id, label, toelichting, wat_ontbreekt, architecture_version, judged_at)
                         VALUES (:id, :client_id, :slot_id, :research_item_id, :label, :toelichting, :wat_ontbreekt, :architecture_version, NOW())'
                    )->execute([
                        'id' => $id,
                        'client_id' => $clientId,
                        'slot_id' => $slotId,
                        'research_item_id' => $itemId,
                        'label' => $label,
                        'toelichting' => $toelichting,
                        'wat_ontbreekt' => $watOntbreekt !== '' ? $watOntbreekt : null,
                        'architecture_version' => $narrativeVersion,
                    ]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO hba_research_verdicts (id, client_id, slot_id, research_item_id, label, toelichting, wat_ontbreekt, judged_at)
                         VALUES (:id, :client_id, :slot_id, :research_item_id, :label, :toelichting, :wat_ontbreekt, NOW())'
                    )->execute([
                        'id' => $id,
                        'client_id' => $clientId,
                        'slot_id' => $slotId,
                        'research_item_id' => $itemId,
                        'label' => $label,
                        'toelichting' => $toelichting,
                        'wat_ontbreekt' => $watOntbreekt !== '' ? $watOntbreekt : null,
                    ]);
                }
                ce_hba_persist_outcome_meta($pdo, 'hba_research_verdicts', $id, $logModel ?? $modelUsed);
            } catch (Throwable) {
                // Toon het oordeel ook als opslaan faalt.
            }
        }
        $saved[] = [
            'id' => $id,
            'itemId' => $itemId,
            'label' => $label,
            'toelichting' => $toelichting,
        ];
    }

    $fresh = ce_hba_v2_fetch_research_verdicts($pdo, $clientId, $slotId, $itemIds);
    $merged = ce_hba_v2_merge_research_verdict_sets($saved, $fresh);
    $mergedWat = $watOntbreekt !== '' ? $watOntbreekt : ($fresh['watOntbreekt'] ?? $cached['watOntbreekt'] ?? null);

    if ($saved === [] && ($merged['verdicts'] ?? []) === []) {
        if (($cached['verdicts'] ?? []) !== []) {
            return array_merge(
                ce_hba_v2_research_verdicts_payload($cached),
                ce_hba_v2_theme_positioning_meta($pdo, $clientId),
                ['error' => 'Research-advies gaf geen herkenbare oordelen'],
            );
        }

        return array_merge(
            [
                'ok' => false,
                'verdicts' => [],
                'watOntbreekt' => null,
                'error' => 'Research-advies gaf geen herkenbare oordelen',
            ],
            ce_hba_v2_theme_positioning_meta($pdo, $clientId),
        );
    }

    return array_merge(
        [
            'ok' => true,
            'verdicts' => $merged['verdicts'],
            'watOntbreekt' => $mergedWat,
        ],
        ce_hba_v2_theme_positioning_meta($pdo, $clientId),
    );
}

/**
 * @param list<string> $itemIds
 * @return array{verdicts: list<array<string, mixed>>, watOntbreekt: ?string}
 */
function ce_hba_v2_fetch_research_verdicts(PDO $pdo, string $clientId, string $slotId, array $itemIds): array
{
    ce_ensure_hba_v2_schema($pdo);
    if ($itemIds === [] || !ce_table_exists($pdo, 'hba_research_verdicts')) {
        return ['verdicts' => [], 'watOntbreekt' => null];
    }

    $currentVersion = ce_hba_v2_current_narrative_version($pdo, $clientId);
    $hasArchVersion = ce_table_has_column($pdo, 'hba_research_verdicts', 'architecture_version');
    $currentArchetype = trim((string) (ce_hba_v2_architecture_context($pdo, $clientId)['archetype'] ?? ''));

    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $archSelect = $hasArchVersion ? ', v.architecture_version' : '';
    $stmt = $pdo->prepare(
        "SELECT v.research_item_id, v.label, v.toelichting, v.wat_ontbreekt{$archSelect}
         FROM hba_research_verdicts v
         INNER JOIN (
           SELECT research_item_id, MAX(judged_at) AS max_judged
           FROM hba_research_verdicts
           WHERE client_id = ? AND slot_id <=> ?
             AND research_item_id IS NOT NULL
           GROUP BY research_item_id
         ) latest ON latest.research_item_id <=> v.research_item_id AND latest.max_judged <=> v.judged_at
         WHERE v.client_id = ? AND v.slot_id <=> ? AND v.research_item_id IS NOT NULL AND v.research_item_id IN ($placeholders)"
    );
    $params = array_merge([$clientId, $slotId, $clientId, $slotId], $itemIds);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];
    $out = [];
    $watOntbreekt = null;
    foreach ($rows as $row) {
        if ($hasArchVersion && $currentVersion > 0) {
            $rowVersion = $row['architecture_version'] ?? null;
            if ($rowVersion === null) {
                if ($currentVersion > 1) {
                    continue;
                }
            } elseif ((int) $rowVersion !== $currentVersion) {
                continue;
            }
        }
        $toelichting = (string) ($row['toelichting'] ?? '');
        if (ce_hba_v2_toelichting_conflicts_with_archetype($toelichting, $currentArchetype)) {
            continue;
        }
        if ($watOntbreekt === null && !empty($row['wat_ontbreekt'])) {
            $rowWat = (string) $row['wat_ontbreekt'];
            if (!ce_hba_v2_toelichting_conflicts_with_archetype($rowWat, $currentArchetype)) {
                $watOntbreekt = $rowWat;
            }
        }
        $out[] = [
            'itemId' => (string) ($row['research_item_id'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'toelichting' => $toelichting,
        ];
    }

    return ['verdicts' => $out, 'watOntbreekt' => $watOntbreekt];
}


/**
 * @param array<string, mixed> $draft
 * @return array<string, mixed>
 */
function ce_hba_v2_run_article_hint(PDO $pdo, string $clientId, string $articleId, array $draft = []): array
{
    ce_ensure_hba_v2_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_article_hints')) {
        return ['ok' => false, 'error' => 'HBA-aanwijzingen-tabel ontbreekt op deze omgeving'];
    }
    $gate = ce_hba_v2_guardrail_gate($pdo, $clientId);
    if ($gate !== null) {
        if (($gate['code'] ?? '') === 'guardrail_inactive') {
            return array_merge($gate, ['hint' => null]);
        }

        return $gate;
    }

    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $articleId]);
    $article = $stmt->fetch();
    if (!$article) {
        return ['ok' => false, 'error' => 'Artikel niet gevonden'];
    }

    $articleUi = ce_status_to_ui($article['status'] ?? null);
    if (in_array($articleUi, ['published', 'archived', 'approved'], true)) {
        return ['ok' => false, 'error' => 'Aanwijzing is alleen voor concepten', 'code' => 'article_closed'];
    }

    $headline = trim((string) ($draft['headline'] ?? ''));
    if ($headline === '') {
        $headline = ce_article_headline($article);
    }
    $body = ce_strip_editor_questions_block(trim((string) ($draft['content'] ?? '')));
    if ($body === '') {
        $body = trim((string) ($article['content_human'] ?? ''));
    }
    if ($body === '') {
        $body = trim((string) ($article['content_ai'] ?? ''));
    }
    $body = ce_strip_editor_questions_block($body);
    if ($body === '') {
        return ['ok' => false, 'error' => 'Nog geen concept om te beoordelen'];
    }

    $force = filter_var($draft['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($force) {
        ce_hba_v2_supersede_open_hints($pdo, $articleId);
    } else {
        $pending = ce_hba_v2_fetch_pending_hint($pdo, $articleId, $clientId);
        if ($pending !== null) {
            return ['ok' => true, 'hint' => $pending];
        }
    }

    $concept = "HEADLINE:\n{$headline}\n\nBODY:\n{$body}";
    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    $bewijsvelden = is_array($ctx['bewijsvelden'] ?? null) ? $ctx['bewijsvelden'] : [];
    $bewijsText = function_exists('ce_hba_format_list')
        ? ce_hba_format_list($bewijsvelden)
        : implode("\n", array_map(static fn ($v) => '- ' . $v, $bewijsvelden));
    $researchText = ce_hba_v2_research_summary_for_article($pdo, $article);
    $system = ce_hba_v2_article_system_prompt();
    $filled = ce_hba_v2_fill_prompt($system, [
        'archetype' => $ctx['archetype'],
        'doel' => $ctx['doel'],
        'foe' => $ctx['foe'],
        'distinctiviteit' => $ctx['distinctiviteit'] !== '' ? $ctx['distinctiviteit'] : '(niet ingevuld)',
        'wat_bewust_niet' => $ctx['wat_bewust_niet'] !== '' ? $ctx['wat_bewust_niet'] : '(niet ingevuld)',
        'regels' => $ctx['regels'] !== '' ? $ctx['regels'] : '(niet ingevuld)',
        'verboden_logica' => $ctx['verboden_logica'] !== '' ? $ctx['verboden_logica'] : '(niet ingevuld)',
        'bewijsvelden' => $bewijsText !== '' ? $bewijsText : '(niet ingevuld)',
        'regelbreuk_definitie' => ce_hba_regelbreuk_definitie(),
        'archetype_bewijslast_block' => ce_hba_archetype_market_match_block(
            $pdo,
            is_array($narrative = ce_fetch_current_brand_narrative($pdo, $clientId)) ? $narrative : [],
        ),
        'tone_of_behaviour' => $ctx['tone_of_behaviour'] !== '' ? $ctx['tone_of_behaviour'] : '(niet ingevuld)',
        'categorietaal' => $ctx['categorietaal'] !== '' ? $ctx['categorietaal'] : '(geen branche)',
        'research' => $researchText,
        'concept' => $concept,
    ]);

    $llm = ce_hba_v2_call_json_for(CE_HBA_FN_ARTICLE_HINT, $filled, 'Geef nu de gevraagde JSON-output.');
    if (!$llm['ok']) {
        return ['ok' => false, 'error' => $llm['error'] ?? 'Aanwijzing mislukt'];
    }

    $articleText = trim($headline . ($body !== '' ? "\n\n" . $body : ''));
    $aanspreekvorm = (string) ($ctx['aanspreekvorm'] ?? '');
    $prepared = ce_hba_v2_prepare_article_hint_data(
        is_array($llm['data'] ?? null) ? $llm['data'] : [],
        $bewijsvelden
    );
    $check = ce_hba_validate_article_hint($prepared['data'], $articleText, $aanspreekvorm);
    if (!$check['ok']) {
        ce_hba_log_hint_verificatie($pdo, $articleId, $check['violations'], 1);
        $retryUser = ce_hba_hint_verificatie_retry_message($check['violations'], $aanspreekvorm);
        $retry = ce_hba_v2_call_json_for(CE_HBA_FN_ARTICLE_HINT, $filled, $retryUser);
        if (($retry['ok'] ?? false) && is_array($retry['data'] ?? null)) {
            $llm = $retry;
            $prepared = ce_hba_v2_prepare_article_hint_data($retry['data'], $bewijsvelden);
            $check = ce_hba_validate_article_hint($prepared['data'], $articleText, $aanspreekvorm);
        }
        if (!$check['ok']) {
            ce_hba_log_hint_verificatie($pdo, $articleId, $check['violations'], 2);

            return ['ok' => false, 'error' => 'Geen aanwijzing ontvangen — probeer opnieuw'];
        }
    }
    $data = $prepared['data'];
    $aanwijzing = trim((string) ($data['aanwijzing'] ?? ''));
    if ($aanwijzing === '') {
        return ['ok' => false, 'error' => 'Geen aanwijzing ontvangen — probeer opnieuw'];
    }
    $data['aanwijzing'] = $aanwijzing;
    $hintId = ce_uuid();
    $modelUsed = trim((string) ($llm['model'] ?? ce_hba_model_for(CE_HBA_FN_ARTICLE_HINT)));
    $logModel = ce_hba_table_has_model_column($pdo, 'hba_article_hints') ? $modelUsed : null;
    $narrativeVersion = ce_hba_v2_current_narrative_version($pdo, $clientId);
    $hasArchVersion = ce_table_has_column($pdo, 'hba_article_hints', 'architecture_version');
    if ($logModel !== null && $hasArchVersion) {
        $pdo->prepare(
            'INSERT INTO hba_article_hints (
                id, client_id, article_id, aanwijzing, passage_voor, passage_na,
                headline_voor, headline_na, cta_voor, cta_na, regel_overtreden, model, architecture_version, toegepast, judged_at
             ) VALUES (
                :id, :client_id, :article_id, :aanwijzing, :passage_voor, :passage_na,
                :headline_voor, :headline_na, :cta_voor, :cta_na, :regel_overtreden, :model, :architecture_version, 0, NOW()
             )'
        )->execute([
            'id' => $hintId,
            'client_id' => $clientId,
            'article_id' => $articleId,
            'aanwijzing' => trim((string) ($data['aanwijzing'] ?? '')),
            'passage_voor' => ce_hba_v2_nullable_string($data['passage_voor'] ?? null),
            'passage_na' => ce_hba_v2_nullable_string($data['passage_na'] ?? null),
            'headline_voor' => ce_hba_v2_nullable_string($data['headline_voor'] ?? null),
            'headline_na' => ce_hba_v2_nullable_string($data['headline_na'] ?? null),
            'cta_voor' => ce_hba_v2_nullable_string($data['cta_voor'] ?? null),
            'cta_na' => ce_hba_v2_nullable_string($data['cta_na'] ?? null),
            'regel_overtreden' => ce_hba_v2_nullable_string($data['regel_overtreden'] ?? null),
            'model' => $logModel,
            'architecture_version' => $narrativeVersion,
        ]);
    } elseif ($logModel !== null) {
        $pdo->prepare(
            'INSERT INTO hba_article_hints (
                id, client_id, article_id, aanwijzing, passage_voor, passage_na,
                headline_voor, headline_na, cta_voor, cta_na, regel_overtreden, model, toegepast, judged_at
             ) VALUES (
                :id, :client_id, :article_id, :aanwijzing, :passage_voor, :passage_na,
                :headline_voor, :headline_na, :cta_voor, :cta_na, :regel_overtreden, :model, 0, NOW()
             )'
        )->execute([
            'id' => $hintId,
            'client_id' => $clientId,
            'article_id' => $articleId,
            'aanwijzing' => trim((string) ($data['aanwijzing'] ?? '')),
            'passage_voor' => ce_hba_v2_nullable_string($data['passage_voor'] ?? null),
            'passage_na' => ce_hba_v2_nullable_string($data['passage_na'] ?? null),
            'headline_voor' => ce_hba_v2_nullable_string($data['headline_voor'] ?? null),
            'headline_na' => ce_hba_v2_nullable_string($data['headline_na'] ?? null),
            'cta_voor' => ce_hba_v2_nullable_string($data['cta_voor'] ?? null),
            'cta_na' => ce_hba_v2_nullable_string($data['cta_na'] ?? null),
            'regel_overtreden' => ce_hba_v2_nullable_string($data['regel_overtreden'] ?? null),
            'model' => $logModel,
        ]);
    } elseif ($hasArchVersion) {
        $pdo->prepare(
            'INSERT INTO hba_article_hints (
                id, client_id, article_id, aanwijzing, passage_voor, passage_na,
                headline_voor, headline_na, cta_voor, cta_na, regel_overtreden, architecture_version, toegepast, judged_at
             ) VALUES (
                :id, :client_id, :article_id, :aanwijzing, :passage_voor, :passage_na,
                :headline_voor, :headline_na, :cta_voor, :cta_na, :regel_overtreden, :architecture_version, 0, NOW()
             )'
        )->execute([
            'id' => $hintId,
            'client_id' => $clientId,
            'article_id' => $articleId,
            'aanwijzing' => trim((string) ($data['aanwijzing'] ?? '')),
            'passage_voor' => ce_hba_v2_nullable_string($data['passage_voor'] ?? null),
            'passage_na' => ce_hba_v2_nullable_string($data['passage_na'] ?? null),
            'headline_voor' => ce_hba_v2_nullable_string($data['headline_voor'] ?? null),
            'headline_na' => ce_hba_v2_nullable_string($data['headline_na'] ?? null),
            'cta_voor' => ce_hba_v2_nullable_string($data['cta_voor'] ?? null),
            'cta_na' => ce_hba_v2_nullable_string($data['cta_na'] ?? null),
            'regel_overtreden' => ce_hba_v2_nullable_string($data['regel_overtreden'] ?? null),
            'architecture_version' => $narrativeVersion,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO hba_article_hints (
                id, client_id, article_id, aanwijzing, passage_voor, passage_na,
                headline_voor, headline_na, cta_voor, cta_na, regel_overtreden, toegepast, judged_at
             ) VALUES (
                :id, :client_id, :article_id, :aanwijzing, :passage_voor, :passage_na,
                :headline_voor, :headline_na, :cta_voor, :cta_na, :regel_overtreden, 0, NOW()
             )'
        )->execute([
            'id' => $hintId,
            'client_id' => $clientId,
            'article_id' => $articleId,
            'aanwijzing' => trim((string) ($data['aanwijzing'] ?? '')),
            'passage_voor' => ce_hba_v2_nullable_string($data['passage_voor'] ?? null),
            'passage_na' => ce_hba_v2_nullable_string($data['passage_na'] ?? null),
            'headline_voor' => ce_hba_v2_nullable_string($data['headline_voor'] ?? null),
            'headline_na' => ce_hba_v2_nullable_string($data['headline_na'] ?? null),
            'cta_voor' => ce_hba_v2_nullable_string($data['cta_voor'] ?? null),
            'cta_na' => ce_hba_v2_nullable_string($data['cta_na'] ?? null),
            'regel_overtreden' => ce_hba_v2_nullable_string($data['regel_overtreden'] ?? null),
        ]);
    }

    ce_hba_persist_outcome_meta($pdo, 'hba_article_hints', $hintId, $logModel ?? $modelUsed);
    ce_hba_v2_persist_hint_invulvelden($pdo, $hintId, $data);

    return [
        'ok' => true,
        'hint' => ce_hba_v2_hint_dto($hintId, $data, false),
    ];
}

function ce_hba_v2_nullable_string(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $s = trim((string) $value);
    if ($s === '' || strtolower($s) === 'null') {
        return null;
    }

    return $s;
}

function ce_hba_open_placeholder_message(): string
{
    return 'Er staat nog een stukje tussen haken dat je zelf moet invullen.';
}

function ce_hba_self_review_unsupported_assumption_message(): string
{
    return 'De schrijver heeft een aanname of merkbewering gemarkeerd die niet in je bronnen staat. Corrigeer die eerst voordat je indient of publiceert.';
}

function ce_hba_article_self_review_blocks_flow(mixed $selfReviewJson): bool
{
    require_once __DIR__ . '/mappers.php';
    $parsed = ce_parse_self_review($selfReviewJson);
    if ($parsed === null) {
        return false;
    }
    require_once __DIR__ . '/self-review-confirm-repository.php';
    if (ce_self_review_unconfirmed_geen_bron_count($parsed) > 0) {
        return true;
    }
    $assumption = trim((string) ($parsed['unsupportedAssumption'] ?? ''));
    if ($assumption === '') {
        return false;
    }
    $lower = mb_strtolower($assumption);
    if (preg_match('/^(geen|n\.?v\.?t\.?|none)\b/u', $lower)) {
        return false;
    }

    return true;
}

/** @return list<string> */
function ce_hba_extract_invulvelden(string $text): array
{
    if (!preg_match_all('/\[([^\[\]]{3,200})\]/u', $text, $matches)) {
        return [];
    }
    $out = [];
    foreach ($matches[1] as $inner) {
        $t = trim((string) $inner);
        if ($t === '') {
            continue;
        }
        $out[$t] = $t;
    }

    return array_values($out);
}

function ce_hba_text_has_open_placeholders(string $text): bool
{
    return ce_hba_extract_invulvelden($text) !== [];
}

/**
 * @param array<string, mixed> $articleRow
 */
function ce_hba_v2_research_summary_for_article(PDO $pdo, array $articleRow): string
{
    $slotId = trim((string) ($articleRow['slot_id'] ?? ''));
    if ($slotId === '') {
        return 'Geen research gekoppeld.';
    }
    require_once __DIR__ . '/theme-repository.php';
    try {
        $pack = ce_fetch_slot_research($pdo, $slotId);
    } catch (Throwable) {
        return 'Geen research gekoppeld.';
    }
    $items = is_array($pack['items'] ?? null) ? $pack['items'] : [];
    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string) ($item['title'] ?? ''));
        $snip = trim((string) ($item['snippet'] ?? $item['item_rationale'] ?? $item['rationale'] ?? ''));
        if ($title === '' && $snip === '') {
            continue;
        }
        $lines[] = $title !== '' ? ($snip !== '' ? $title . ': ' . $snip : $title) : $snip;
        if (count($lines) >= 8) {
            break;
        }
    }

    return $lines !== [] ? implode("\n", $lines) : 'Geen research gekoppeld.';
}

/**
 * @param array<string, mixed> $data
 * @param list<string> $bewijsvelden
 * @return array<string, mixed>
 */
/**
 * @param array<string, mixed> $data
 * @param list<string> $bewijsvelden
 * @return array{data: array<string, mixed>}
 */
function ce_hba_v2_prepare_article_hint_data(array $data, array $bewijsvelden): array
{
    $data['aanwijzing'] = trim((string) ($data['aanwijzing'] ?? ''));
    $data = ce_hba_v2_collapse_duplicate_rewrites($data);
    $data = ce_hba_v2_enrich_hint_invulvelden($data, $bewijsvelden);
    $data = ce_hba_move_placeholders_out_of_running_text($data);

    return ['data' => $data];
}

function ce_hba_v2_enrich_hint_invulvelden(array $data, array $bewijsvelden): array
{
    $fromLlm = [];
    $raw = $data['invulvelden'] ?? null;
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [$raw];
    }
    if (is_array($raw)) {
        foreach ($raw as $item) {
            $t = trim((string) $item);
            if ($t !== '') {
                $fromLlm[] = $t;
            }
        }
    }
    $fromText = array_merge(
        ce_hba_extract_invulvelden((string) ($data['passage_na'] ?? '')),
        ce_hba_extract_invulvelden((string) ($data['cta_na'] ?? '')),
        ce_hba_extract_invulvelden((string) ($data['headline_na'] ?? '')),
    );
    $merged = [];
    foreach (array_merge($fromLlm, $fromText) as $item) {
        $merged[$item] = $item;
        if (count($merged) >= 2) {
            break;
        }
    }
    $data['invulvelden'] = array_values($merged);

    $bron = ce_hba_v2_nullable_string($data['bewijsveld_bron'] ?? null);
    if ($bron !== null && $bewijsvelden !== []) {
        $matched = null;
        foreach ($bewijsvelden as $field) {
            if (mb_stripos($field, $bron) !== false || mb_stripos($bron, $field) !== false) {
                $matched = $field;
                break;
            }
        }
        $bron = $matched;
    }
    $data['bewijsveld_bron'] = $bron;

    return $data;
}

/** @param array<string, mixed> $data */
function ce_hba_v2_persist_hint_invulvelden(PDO $pdo, string $hintId, array $data): void
{
    if (!ce_table_exists($pdo, 'hba_article_hints')) {
        return;
    }
    $sets = [];
    $params = ['id' => $hintId];
    if (ce_table_has_column($pdo, 'hba_article_hints', 'invulvelden')) {
        $sets[] = 'invulvelden = :invulvelden';
        $params['invulvelden'] = json_encode(
            is_array($data['invulvelden'] ?? null) ? array_values($data['invulvelden']) : [],
            JSON_UNESCAPED_UNICODE,
        );
    }
    if (ce_table_has_column($pdo, 'hba_article_hints', 'bewijsveld_bron')) {
        $sets[] = 'bewijsveld_bron = :bewijsveld_bron';
        $params['bewijsveld_bron'] = ce_hba_v2_nullable_string($data['bewijsveld_bron'] ?? null);
    }
    if ($sets === []) {
        return;
    }
    $pdo->prepare('UPDATE hba_article_hints SET ' . implode(', ', $sets) . ' WHERE id = :id')
        ->execute($params);
}

/** @param array<string, mixed> $data */
function ce_hba_v2_normalize_compare(string $value): string
{
    $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

    return mb_strtolower($collapsed);
}

/** LLM zet dezelfde CTA vaak in passage_* én cta_* — bewaar één veldgroep. */
function ce_hba_v2_collapse_duplicate_rewrites(array $data): array
{
    $passageNa = ce_hba_v2_nullable_string($data['passage_na'] ?? null);
    $ctaNa = ce_hba_v2_nullable_string($data['cta_na'] ?? null);
    if (
        $passageNa !== null
        && $ctaNa !== null
        && ce_hba_v2_normalize_compare($passageNa) === ce_hba_v2_normalize_compare($ctaNa)
    ) {
        $passageVoor = ce_hba_v2_nullable_string($data['passage_voor'] ?? null);
        $ctaVoor = ce_hba_v2_nullable_string($data['cta_voor'] ?? null);
        if ($ctaVoor === null && $passageVoor !== null) {
            $data['cta_voor'] = $passageVoor;
        }
        $data['passage_voor'] = null;
        $data['passage_na'] = null;
    }

    return $data;
}

function ce_hba_v2_hint_dto(string $id, array $data, bool $toegepast): array
{
    $data = ce_hba_v2_collapse_duplicate_rewrites($data);

    return [
        'id' => $id,
        'aanwijzing' => trim((string) ($data['aanwijzing'] ?? '')),
        'passageVoor' => ce_hba_v2_nullable_string($data['passage_voor'] ?? null),
        'passageNa' => ce_hba_v2_nullable_string($data['passage_na'] ?? null),
        'headlineVoor' => ce_hba_v2_nullable_string($data['headline_voor'] ?? null),
        'headlineNa' => ce_hba_v2_nullable_string($data['headline_na'] ?? null),
        'ctaVoor' => ce_hba_v2_nullable_string($data['cta_voor'] ?? null),
        'ctaNa' => ce_hba_v2_nullable_string($data['cta_na'] ?? null),
        'regelOvertreden' => ce_hba_v2_nullable_string($data['regel_overtreden'] ?? null),
        'invulvelden' => ce_hba_decode_json_list($data['invulvelden'] ?? []),
        'bewijsveldBron' => ce_hba_v2_nullable_string($data['bewijsveld_bron'] ?? null),
        'toegepast' => $toegepast,
        'genegeerd' => (bool) ((int) ($data['genegeerd'] ?? 0)),
    ];
}

/** Openstaande aanwijzingen afsluiten vóór een geforceerde nieuwe LLM-run. */
function ce_hba_v2_supersede_open_hints(PDO $pdo, string $articleId): void
{
    ce_ensure_hba_v2_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_article_hints')) {
        return;
    }
    if (ce_table_has_column($pdo, 'hba_article_hints', 'genegeerd')) {
        $pdo->prepare(
            'UPDATE hba_article_hints SET genegeerd = 1
             WHERE article_id = :article_id AND toegepast = 0 AND genegeerd = 0',
        )->execute(['article_id' => $articleId]);

        return;
    }
    $pdo->prepare('DELETE FROM hba_article_hints WHERE article_id = :article_id AND toegepast = 0')
        ->execute(['article_id' => $articleId]);
}

/** @return array<string, mixed>|null */
function ce_hba_v2_fetch_pending_hint(PDO $pdo, string $articleId, ?string $clientId = null): ?array
{
    ce_ensure_hba_v2_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_article_hints')) {
        return null;
    }

    $genegeerdFilter = ce_table_has_column($pdo, 'hba_article_hints', 'genegeerd')
        ? ' AND genegeerd = 0'
        : '';

    $stmt = $pdo->prepare(
        'SELECT * FROM hba_article_hints
         WHERE article_id = :article_id
           AND toegepast = 0' . $genegeerdFilter . '
         ORDER BY judged_at DESC
         LIMIT 1'
    );
    $stmt->execute(['article_id' => $articleId]);
    $row = $stmt->fetch();
    if (!$row || trim((string) ($row['aanwijzing'] ?? '')) === '') {
        return null;
    }
    if ($clientId !== null && $clientId !== '' && !ce_hba_v2_hint_row_matches_architecture($pdo, $clientId, $row)) {
        return null;
    }

    $dto = ce_hba_v2_hint_dto(
        (string) ($row['id'] ?? ''),
        $row,
        false,
    );
    // Oude hints na stap 2a: placeholders gestript → passage_na == copy. Opnieuw genereren.
    if (ce_hba_hint_noop_rewrite_violations([
        'passage_voor' => $dto['passageVoor'] ?? '',
        'passage_na' => $dto['passageNa'] ?? '',
        'cta_voor' => $dto['ctaVoor'] ?? '',
        'cta_na' => $dto['ctaNa'] ?? '',
        'headline_voor' => $dto['headlineVoor'] ?? '',
        'headline_na' => $dto['headlineNa'] ?? '',
    ]) !== []) {
        ce_hba_v2_supersede_open_hints($pdo, $articleId);

        return null;
    }
    $dto['judgedAt'] = (string) ($row['judged_at'] ?? '');

    return $dto;
}

/** @return array{ok: bool, error?: string} */
function ce_hba_v2_ignore_hint(PDO $pdo, string $clientId, string $hintId): array
{
    ce_ensure_hba_v2_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_article_hints')) {
        return ['ok' => false, 'error' => 'HBA-aanwijzingen-tabel ontbreekt op deze omgeving'];
    }

    require_once __DIR__ . '/hba-pilot-alert-repository.php';

    $titleCols = ce_hba_pilot_article_title_sql_columns($pdo);
    $titleSql = implode(', ', $titleCols);
    $stmt = $pdo->prepare(
        "SELECT h.id, h.article_id, cs.id AS slot_id, c.name AS client_name, {$titleSql}
         FROM hba_article_hints h
         INNER JOIN articles a ON a.id = h.article_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         INNER JOIN clients c ON c.id = cs.client_id
         WHERE h.id = :id AND cs.client_id = :client_id
         LIMIT 1"
    );
    $stmt->execute(['id' => $hintId, 'client_id' => $clientId]);
    $hintRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hintRow) {
        return ['ok' => false, 'error' => 'Aanwijzing niet gevonden'];
    }

    if (ce_table_has_column($pdo, 'hba_article_hints', 'genegeerd')) {
        $pdo->prepare('UPDATE hba_article_hints SET genegeerd = 1 WHERE id = :id')
            ->execute(['id' => $hintId]);
    } else {
        $pdo->prepare('DELETE FROM hba_article_hints WHERE id = :id')
            ->execute(['id' => $hintId]);
    }

    $postTitel = ce_article_headline($hintRow);
    if ($postTitel === '') {
        $postTitel = '(zonder titel)';
    }

    ce_hba_pilot_after_hint_ignored(
        $pdo,
        $clientId,
        $hintId,
        (string) ($hintRow['article_id'] ?? ''),
        (string) ($hintRow['slot_id'] ?? ''),
        (string) ($hintRow['client_name'] ?? ''),
        $postTitel,
    );

    return ['ok' => true];
}

/** @return array<string, mixed>|null */
function ce_hba_v2_fetch_latest_hint(PDO $pdo, string $articleId, ?string $clientId = null): ?array
{
    ce_ensure_hba_v2_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_article_hints')) {
        return null;
    }

    $genegeerdFilter = ce_table_has_column($pdo, 'hba_article_hints', 'genegeerd')
        ? ' AND genegeerd = 0'
        : '';

    $stmt = $pdo->prepare(
        'SELECT * FROM hba_article_hints
         WHERE article_id = :article_id' . $genegeerdFilter . '
         ORDER BY judged_at DESC
         LIMIT 1'
    );
    $stmt->execute(['article_id' => $articleId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if (trim((string) ($row['aanwijzing'] ?? '')) === '') {
        return null;
    }
    if ($clientId !== null && $clientId !== '' && !ce_hba_v2_hint_row_matches_architecture($pdo, $clientId, $row)) {
        return null;
    }

    $dto = ce_hba_v2_hint_dto(
        (string) ($row['id'] ?? ''),
        $row,
        (bool) ((int) ($row['toegepast'] ?? 0)),
    );
    $dto['judgedAt'] = (string) ($row['judged_at'] ?? '');

    return $dto;
}

/** @return array{ok: bool, error?: string, hint?: array<string, mixed>, code?: string} */
function ce_hba_v2_apply_hint(PDO $pdo, string $clientId, string $hintId, array $draft = []): array
{
    ce_ensure_hba_v2_schema($pdo);
    $stmt = $pdo->prepare(
        'SELECT h.*, a.id AS article_row_id, a.content_ai, a.content_human, a.headline, a.headline_human
         FROM hba_article_hints h
         INNER JOIN articles a ON a.id = h.article_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE h.id = :id AND cs.client_id = :client_id
         LIMIT 1'
    );
    $stmt->execute(['id' => $hintId, 'client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Aanwijzing niet gevonden'];
    }

    $draftContent = trim((string) ($draft['content'] ?? ''));
    $draftHeadline = trim((string) ($draft['headline'] ?? ''));

    if ($draftContent !== '') {
        $content = $draftContent;
        $headline = $draftHeadline !== '' ? $draftHeadline : ce_article_headline($row);
    } else {
        $content = trim((string) ($row['content_human'] ?? ''));
        if ($content === '') {
            $content = (string) ($row['content_ai'] ?? '');
        }

        $editorBlock = '';
        $articleBody = $content;
        $editorPos = strpos($content, CE_EDITOR_BLOCK_MARKER);
        if ($editorPos !== false) {
            $articleBody = rtrim(substr($content, 0, $editorPos));
            $editorBlock = substr($content, $editorPos);
        }

        $changed = false;
        $passageVoor = (string) ($row['passage_voor'] ?? '');
        $passageNa = (string) ($row['passage_na'] ?? '');
        if ($passageVoor !== '' && $passageNa !== '') {
            $replaced = ce_hba_v2_replace_first($articleBody, $passageVoor, $passageNa);
            if ($replaced['changed']) {
                $articleBody = $replaced['text'];
                $changed = true;
            }
        }

        $ctaVoor = (string) ($row['cta_voor'] ?? '');
        $ctaNa = (string) ($row['cta_na'] ?? '');
        if ($ctaVoor !== '' && $ctaNa !== '') {
            $replaced = ce_hba_v2_replace_first($articleBody, $ctaVoor, $ctaNa);
            if ($replaced['changed']) {
                $articleBody = $replaced['text'];
                $changed = true;
            }
        }

        $headline = ce_article_headline($row);
        $headlineNa = trim((string) ($row['headline_na'] ?? ''));
        if ($headlineNa !== '') {
            $headline = $headlineNa;
            $changed = true;
        }

        $content = $articleBody . $editorBlock;
        if (!$changed) {
            return [
                'ok' => false,
                'error' => 'De tekst in de editor is gewijzigd sinds deze aanwijzing. Sla op en vraag een nieuwe aanwijzing aan, of pas handmatig aan.',
                'code' => 'hint_stale',
            ];
        }
    }

    $sets = ['content_human = :content'];
    $params = ['content' => $content, 'id' => (string) ($row['article_row_id'] ?? '')];
    if (ce_table_has_column($pdo, 'articles', 'headline_human')) {
        $sets[] = 'headline_human = :headline_human';
        $params['headline_human'] = $headline;
    }

    $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    $openPlaceholders = ce_hba_text_has_open_placeholders($content . "\n" . $headline);
    if (!$openPlaceholders) {
        $pdo->prepare('UPDATE hba_article_hints SET toegepast = 1 WHERE id = :id')->execute(['id' => $hintId]);
    }

    return [
        'ok' => true,
        'hint' => [
            'id' => $hintId,
            'toegepast' => !$openPlaceholders,
            'content' => $content,
            'headline' => $headline,
            'openPlaceholders' => $openPlaceholders,
        ],
    ];
}

/** @return array{text: string, changed: bool} */
function ce_hba_v2_replace_first(string $text, string $before, string $after): array
{
    $needle = trim($before);
    $replacement = trim($after);
    if ($needle === '' || $replacement === '') {
        return ['text' => $text, 'changed' => false];
    }
    if (str_contains($text, $needle)) {
        return ['text' => str_replace($needle, $replacement, $text), 'changed' => true];
    }
    $pattern = '/\s+/u';
    $escaped = preg_quote($needle, '/');
    $escaped = (string) preg_replace($pattern, '\\s+', $escaped);
    $replaced = (string) preg_replace('/' . $escaped . '/u', $replacement, $text, 1, $count);

    return ['text' => $replaced, 'changed' => $count > 0];
}

function ce_hba_v2_count_published_scored_posts(PDO $pdo, string $clientId): int
{
    if (!ce_table_exists($pdo, 'hba_scores') || !ce_table_exists($pdo, 'articles')) {
        return 0;
    }
    $originSelect = ce_table_has_column($pdo, 'hba_scores', 'origin')
        ? 'COALESCE(hs.origin, \'app\')'
        : '\'app\'';
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT hs.post_id) AS cnt
         FROM hba_scores hs
         INNER JOIN articles a ON a.id = hs.post_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND (
             {$originSelect} = 'baseline'
             OR ({$originSelect} = 'app' AND a.status IN ('published', 'archived'))
           )"
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();

    return (int) ($row['cnt'] ?? 0);
}

/** Positionering per post (0–100) uit brand contribution, consistency en architecture risk. */
function ce_hba_v2_post_positioning_score(array $row): ?float
{
    $bc = isset($row['brand_contribution_score']) && $row['brand_contribution_score'] !== null
        ? (float) $row['brand_contribution_score']
        : null;
    $consistency = isset($row['brand_consistency_score']) && $row['brand_consistency_score'] !== null
        ? (float) $row['brand_consistency_score']
        : null;
    if ($bc === null && $consistency === null) {
        return null;
    }
    $bc = $bc ?? 0.0;
    $consistency = $consistency ?? 0.0;
    $risk = isset($row['architecture_risk_score']) && $row['architecture_risk_score'] !== null
        ? (float) $row['architecture_risk_score']
        : 0.0;
    $base = (($bc + $consistency) / 2.0) / 10.0 * 100.0;
    $deduction = ($risk / 10.0) * 20.0;

    return max(0.0, min(100.0, round($base - $deduction, 2)));
}

/** @return list<array<string, mixed>> */
function ce_hba_v2_fetch_published_score_rows(PDO $pdo, string $clientId): array
{
    if (!ce_table_exists($pdo, 'hba_scores') || !ce_table_exists($pdo, 'articles')) {
        return [];
    }
    $originSelect = ce_table_has_column($pdo, 'hba_scores', 'origin')
        ? 'COALESCE(hs.origin, \'app\')'
        : '\'app\'';
    $stmt = $pdo->prepare(
        "SELECT hs.brand_contribution_score, hs.brand_consistency_score, hs.architecture_risk_score,
                hs.content_weight, hs.scored_at, a.published_at, a.status, {$originSelect} AS score_origin
         FROM hba_scores hs
         INNER JOIN articles a ON a.id = hs.post_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND {$originSelect} = 'app'
           AND COALESCE(cs.origin, '') <> 'baseline'
           AND a.status IN ('published', 'archived')
           AND hs.brand_contribution_score IS NOT NULL
         ORDER BY COALESCE(a.published_at, hs.scored_at) ASC"
    );
    $stmt->execute(['client_id' => $clientId]);
    $appRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($appRows !== []) {
        return $appRows;
    }

    $stmt = $pdo->prepare(
        "SELECT hs.brand_contribution_score, hs.brand_consistency_score, hs.architecture_risk_score,
                hs.content_weight, hs.scored_at, a.published_at, a.status, {$originSelect} AS score_origin
         FROM hba_scores hs
         INNER JOIN articles a ON a.id = hs.post_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND {$originSelect} = 'baseline'
           AND hs.brand_contribution_score IS NOT NULL
         ORDER BY COALESCE(a.published_at, hs.scored_at) ASC"
    );
    $stmt->execute(['client_id' => $clientId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ce_hba_v2_round_pbs_display(float $score, bool $indicative): int
{
    if ($indicative) {
        return (int) (round($score / 5.0) * 5.0);
    }

    return (int) round($score);
}

function ce_hba_v2_pbs_headline(int $displayScore): string
{
    if ($displayScore >= 75) {
        return 'Je merk staat scherp';
    }
    if ($displayScore >= 55) {
        return 'Je merk staat op koers';
    }

    return 'Je merk heeft aandacht nodig';
}

/** @return array<string, mixed> */
function ce_hba_v2_compute_pbs_metrics(PDO $pdo, string $clientId, string $phase): array
{
    $rows = ce_hba_v2_fetch_published_score_rows($pdo, $clientId);
    if ($rows === []) {
        return [
            'pbsScore' => null,
            'pbsDisplayScore' => null,
            'pbsLabel' => null,
            'pbsTrend' => null,
            'pbsTrendDelta' => null,
            'pbsHistory' => [],
            'erosionAlert' => null,
            'idleMessage' => null,
        ];
    }

    $postScores = [];
    $monthBuckets = [];
    foreach ($rows as $row) {
        $score = ce_hba_v2_post_positioning_score($row);
        if ($score === null) {
            continue;
        }
        $weight = isset($row['content_weight']) ? max(0.25, (float) $row['content_weight']) : 1.0;
        $postScores[] = ['score' => $score, 'weight' => $weight];

        $ts = trim((string) ($row['published_at'] ?? $row['scored_at'] ?? ''));
        if ($ts !== '') {
            $monthKey = substr($ts, 0, 7);
            if (!isset($monthBuckets[$monthKey])) {
                $monthBuckets[$monthKey] = [];
            }
            $monthBuckets[$monthKey][] = $score;
        }
    }

    if ($postScores === []) {
        return [
            'pbsScore' => null,
            'pbsDisplayScore' => null,
            'pbsLabel' => null,
            'pbsTrend' => null,
            'pbsTrendDelta' => null,
            'pbsHistory' => [],
            'erosionAlert' => null,
            'idleMessage' => null,
        ];
    }

    $weightedSum = 0.0;
    $weightTotal = 0.0;
    foreach ($postScores as $entry) {
        $weightedSum += $entry['score'] * $entry['weight'];
        $weightTotal += $entry['weight'];
    }
    $pbsScore = $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null;

    ksort($monthBuckets);
    $pbsHistory = [];
    foreach ($monthBuckets as $month => $scores) {
        $avg = array_sum($scores) / count($scores);
        $pbsHistory[] = [
            'month' => $month,
            'score' => round($avg, 1),
        ];
    }

    $pbsTrend = null;
    $pbsTrendDelta = null;
    $historyCount = count($pbsHistory);
    if ($historyCount >= 2) {
        $current = $pbsHistory[$historyCount - 1]['score'];
        $previous = $pbsHistory[$historyCount - 2]['score'];
        $pbsTrendDelta = round($current - $previous, 1);
        if ($pbsTrendDelta > 1) {
            $pbsTrend = 'up';
        } elseif ($pbsTrendDelta < -1) {
            $pbsTrend = 'down';
        } else {
            $pbsTrend = 'flat';
        }
    }

    $indicative = $phase === 'B';
    $pbsDisplayScore = $pbsScore !== null ? ce_hba_v2_round_pbs_display($pbsScore, $indicative) : null;
    $pbsLabel = $phase === 'B' ? 'Indicatief' : ($phase === 'C' ? 'Stabiel' : null);

    $erosionAlert = null;
    if ($historyCount >= 3 && $phase === 'C') {
        $recent = $pbsHistory[$historyCount - 1]['score'];
        $prior = array_slice($pbsHistory, -4, 3);
        $priorAvg = array_sum(array_column($prior, 'score')) / count($prior);
        if ($recent < $priorAvg - 10) {
            $erosionAlert = [
                'message' => 'Je positionering zakt structureel — kijk welke thema\'s je de laatste weken koos.',
                'linkPath' => '/theme',
            ];
        }
    }

    $idleMessage = null;
    if (ce_table_exists($pdo, 'articles')) {
        $idleStmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt
             FROM articles a
             INNER JOIN content_slots cs ON cs.id = a.slot_id
             WHERE cs.client_id = :client_id
               AND a.status IN ('published', 'archived')
               AND a.published_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );
        $idleStmt->execute(['client_id' => $clientId]);
        $publishedThisMonth = (int) (($idleStmt->fetch()['cnt'] ?? 0));
        if ($publishedThisMonth === 0 && count($postScores) > 0) {
            $idleMessage = 'Er zijn deze maand geen nieuwe artikelen bijgekomen — de meting staat stil.';
        }
    }

    return [
        'pbsScore' => $pbsScore,
        'pbsDisplayScore' => $pbsDisplayScore,
        'pbsLabel' => $pbsLabel,
        'pbsHeadline' => $pbsDisplayScore !== null ? ce_hba_v2_pbs_headline($pbsDisplayScore) : null,
        'pbsTrend' => $pbsTrend,
        'pbsTrendDelta' => $pbsTrendDelta,
        'pbsHistory' => $pbsHistory,
        'erosionAlert' => $erosionAlert,
        'idleMessage' => $idleMessage,
    ];
}

/** @return array<string, mixed> */
function ce_hba_v2_tracker_details(PDO $pdo, string $clientId): array
{
    if (!ce_table_exists($pdo, 'hba_scores')) {
        return ['contentRoles' => [], 'storyTriangle' => [], 'averages' => []];
    }
    $originSelect = ce_table_has_column($pdo, 'hba_scores', 'origin')
        ? 'COALESCE(hs.origin, \'app\')'
        : '\'app\'';
    $slotOriginSql = ce_table_has_column($pdo, 'content_slots', 'origin')
        ? "AND COALESCE(cs.origin, '') <> 'baseline'"
        : '';
    $stmt = $pdo->prepare(
        "SELECT hs.contentrol, hs.bc_archetype_fit, hs.bc_doel_fit, hs.bc_tegenkracht_fit,
                hs.bc_spanning, hs.bc_mechanisme_proof, hs.brand_contribution_score,
                hs.brand_consistency_score, hs.architecture_risk_score
         FROM hba_scores hs
         INNER JOIN articles a ON a.id = hs.post_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND a.status IN ('published', 'archived')
           AND {$originSelect} <> 'baseline'
           {$slotOriginSql}"
    );
    $stmt->execute(['client_id' => $clientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === []) {
        return ['contentRoles' => [], 'storyTriangle' => [], 'averages' => []];
    }

    $roleCounts = [];
    $dims = ['archetype' => [], 'doel' => [], 'tegenkracht' => [], 'spanning' => [], 'mechanisme' => []];
    $bc = [];
    $consistency = [];
    $integrity = [];
    foreach ($rows as $row) {
        $role = trim((string) ($row['contentrol'] ?? ''));
        if ($role !== '') {
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
        }
        foreach ([
            'archetype' => 'bc_archetype_fit',
            'doel' => 'bc_doel_fit',
            'tegenkracht' => 'bc_tegenkracht_fit',
            'spanning' => 'bc_spanning',
            'mechanisme' => 'bc_mechanisme_proof',
        ] as $key => $col) {
            if ($row[$col] !== null) {
                $dims[$key][] = (float) $row[$col];
            }
        }
        if ($row['brand_contribution_score'] !== null) {
            $bc[] = (float) $row['brand_contribution_score'];
        }
        if ($row['brand_consistency_score'] !== null) {
            $consistency[] = (float) $row['brand_consistency_score'];
        }
        $integrityValue = ce_hba_architecture_integrity_from_risk($row['architecture_risk_score'] ?? null);
        if ($integrityValue !== null) {
            $integrity[] = $integrityValue;
        }
    }

    $avg = static fn (array $vals): ?float => $vals === [] ? null : round(array_sum($vals) / count($vals), 1);
    arsort($roleCounts);
    $contentRoles = [];
    foreach ($roleCounts as $role => $count) {
        $contentRoles[] = ['role' => $role, 'count' => $count];
    }

    return [
        'contentRoles' => $contentRoles,
        'storyTriangle' => [
            ['label' => 'Persoonlijkheid', 'avg' => $avg($dims['archetype'])],
            ['label' => 'Doel', 'avg' => $avg($dims['doel'])],
            ['label' => 'Tegenkracht', 'avg' => $avg($dims['tegenkracht'])],
            ['label' => 'Spanning', 'avg' => $avg($dims['spanning'])],
            ['label' => 'Mechanisme', 'avg' => $avg($dims['mechanisme'])],
        ],
        'averages' => [
            ['label' => 'Positionering per post', 'avg' => $avg($bc)],
            ['label' => 'Consistentie', 'avg' => $avg($consistency)],
            ['label' => 'Architectuurbestendigheid', 'avg' => $avg($integrity)],
        ],
    ];
}

function ce_hba_v2_architecture_footnote(PDO $pdo, string $clientId): ?string
{
    if (!ce_table_exists($pdo, 'brand_narratives')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT MAX(version) AS max_version, COUNT(*) AS total
         FROM brand_narratives WHERE client_id = :client_id'
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch() ?: [];
    $maxVersion = (int) ($row['max_version'] ?? 0);
    $total = (int) ($row['total'] ?? 0);
    if ($maxVersion <= 1 || $total <= 1) {
        return null;
    }
    if (!ce_table_exists($pdo, 'hba_scores')) {
        return null;
    }
    $scoreStmt = $pdo->prepare(
        "SELECT COUNT(*) AS cnt FROM hba_scores hs
         INNER JOIN articles a ON a.id = hs.post_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id"
    );
    $scoreStmt->execute(['client_id' => $clientId]);
    if ((int) (($scoreStmt->fetch()['cnt'] ?? 0)) === 0) {
        return null;
    }

    return 'Je merkarchitectuur is aangepast — de meting daarvoor gebruikte de oude versie.';
}

/** @return list<string> */
function ce_hba_v2_fetch_linkedin_seed_sentences(PDO $pdo, string $clientId): array
{
    if (!ce_table_exists($pdo, 'linkedin_posts')) {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT text_preview FROM linkedin_posts
         WHERE client_id = :client_id AND text_preview IS NOT NULL AND TRIM(text_preview) <> ''
         ORDER BY posted_at DESC LIMIT 8"
    );
    $stmt->execute(['client_id' => $clientId]);
    $lines = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $text = trim((string) ($row['text_preview'] ?? ''));
        if ($text === '') {
            continue;
        }
        foreach (preg_split('/\n+/u', $text) as $line) {
            $line = trim($line);
            if (mb_strlen($line) >= 20 && mb_strlen($line) <= 220) {
                $lines[] = $line;
            }
        }
    }

    return array_values(array_unique($lines));
}

/** @return array<string, mixed> */
function ce_hba_v2_generate_calibration_pair(PDO $pdo, string $clientId, array $body): array
{
    ce_ensure_hba_v2_schema($pdo);

    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    foreach (['archetype', 'doel', 'foe', 'distinctiviteit', 'wat_bewust_niet', 'regels'] as $key) {
        $overlay = trim((string) ($body[$key] ?? ''));
        if ($overlay !== '') {
            $ctx[$key] = $overlay;
        }
    }
    $round = max(0, (int) ($body['round'] ?? 0));
    $seeds = ce_hba_v2_fetch_linkedin_seed_sentences($pdo, $clientId);
    $seedLine = $seeds[$round % max(1, count($seeds))] ?? '';
    if ($seeds !== [] && $round < count($seeds)) {
        $seedLine = $seeds[$round];
    }

    $system = ce_hba_v2_calibration_system_prompt();
    $user = ce_hba_v2_fill_prompt($system, [
        'archetype' => $ctx['archetype'] !== '' ? $ctx['archetype'] : '(niet ingevuld)',
        'doel' => $ctx['doel'] !== '' ? $ctx['doel'] : '(niet ingevuld)',
        'foe' => $ctx['foe'] !== '' ? $ctx['foe'] : '(niet ingevuld)',
        'distinctiviteit' => $ctx['distinctiviteit'] !== '' ? $ctx['distinctiviteit'] : '(niet ingevuld)',
        'wat_bewust_niet' => $ctx['wat_bewust_niet'] !== '' ? $ctx['wat_bewust_niet'] : '(niet ingevuld)',
        'regels' => $ctx['regels'] !== '' ? $ctx['regels'] : '(niet ingevuld)',
        'categorietaal' => $ctx['categorietaal'] !== '' ? $ctx['categorietaal'] : '(geen branche-context)',
        'seed_zin' => $seedLine !== '' ? $seedLine : '(geen echte zin beschikbaar — verzin beide)',
    ]);

    $llm = ce_hba_v2_call_json_for(CE_HBA_FN_WIZARD_HINTS, $user, 'Geef nu de gevraagde JSON-output.');
    if (!$llm['ok'] || !is_array($llm['data'])) {
        return ['ok' => false, 'error' => $llm['error'] ?? 'Genereren mislukt'];
    }

    $strong = trim((string) ($llm['data']['sterke_zin'] ?? ''));
    $weak = trim((string) ($llm['data']['zwakke_zin'] ?? ''));
    if ($strong === '' || $weak === '') {
        return ['ok' => false, 'error' => 'Onvolledig paar van LLM'];
    }

    return [
        'ok' => true,
        'optionA' => $strong,
        'optionB' => $weak,
        'seedUsed' => $seedLine !== '' ? $seedLine : null,
        'round' => $round,
    ];
}

/** @return array<string, mixed> */
function ce_hba_v2_progress(PDO $pdo, string $clientId): array
{
    ce_ensure_hba_v2_schema($pdo);

    $scoredPosts = ce_hba_v2_count_published_scored_posts($pdo, $clientId);
    $phase = 'A';
    if ($scoredPosts >= 20) {
        $phase = 'C';
    } elseif ($scoredPosts >= 10) {
        $phase = 'B';
    }

    $architectureComplete = ce_hba_v2_architecture_ready($pdo, $clientId);
    $stmt = $pdo->prepare('SELECT hba_architecture_completed_at, hba_pbs_revealed_at FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $clientRow = $stmt->fetch() ?: [];
    if (!empty($clientRow['hba_architecture_completed_at'])) {
        $architectureComplete = true;
    }

    $themesChosen = 0;
    $themesReinforcing = 0;
    if (ce_table_exists($pdo, 'content_slots') && ce_table_exists($pdo, 'hba_theme_verdicts') && ce_table_exists($pdo, 'themes')) {
        $chosenStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT cs.theme_id) AS cnt
             FROM content_slots cs
             INNER JOIN themes t ON t.id = cs.theme_id
             WHERE cs.client_id = :client_id
               AND cs.theme_id IS NOT NULL AND TRIM(cs.theme_id) <> ''
               AND t.cycle_week IS NOT NULL AND TRIM(t.cycle_week) <> ''"
        );
        $chosenStmt->execute(['client_id' => $clientId]);
        $themesChosen = (int) (($chosenStmt->fetch()['cnt'] ?? 0));

        $reinStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT v.theme_id) AS cnt
             FROM hba_theme_verdicts v
             INNER JOIN content_slots cs ON cs.theme_id = v.theme_id AND cs.client_id = v.client_id
             INNER JOIN themes t ON t.id = cs.theme_id
             WHERE v.client_id = :client_id AND v.label = 'versterkt'
               AND t.cycle_week IS NOT NULL AND TRIM(t.cycle_week) <> ''"
        );
        $reinStmt->execute(['client_id' => $clientId]);
        $themesReinforcing = (int) (($reinStmt->fetch()['cnt'] ?? 0));
    }

    $hintsApplied = 0;
    if (ce_table_exists($pdo, 'hba_article_hints')) {
        $hStmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt FROM hba_article_hints WHERE client_id = :client_id AND toegepast = 1'
        );
        $hStmt->execute(['client_id' => $clientId]);
        $hintsApplied = (int) (($hStmt->fetch()['cnt'] ?? 0));
    }

    $pbsRevealed = !empty($clientRow['hba_pbs_revealed_at']);
    if ($phase === 'C' && !$pbsRevealed) {
        $pdo->prepare('UPDATE clients SET hba_pbs_revealed_at = NOW() WHERE id = :id')->execute(['id' => $clientId]);
        $pbsRevealed = true;
        $showRevealMessage = true;
    } else {
        $showRevealMessage = false;
    }

    $pbs = ce_hba_v2_compute_pbs_metrics($pdo, $clientId, $phase);
    if ($scoredPosts < 20) {
        $pbs['pbsDisplayScore'] = null;
        $pbs['pbsLabel'] = null;
        $pbs['pbsHeadline'] = null;
    }
    $trackerDetails = ce_hba_v2_tracker_details($pdo, $clientId);
    $architectureFootnote = ce_hba_v2_architecture_footnote($pdo, $clientId);

    require_once __DIR__ . '/voice-profile-repository.php';
    require_once __DIR__ . '/hba-identity-gate.php';
    require_once __DIR__ . '/hba-baseline-repository.php';
    ce_hba_baseline_invalidate_on_prompt_change($pdo, $clientId);
    $baselinePayload = ce_hba_baseline_progress_payload($pdo, $clientId, $scoredPosts);
    require_once __DIR__ . '/hba-determinism-repository.php';
    $determinismSettings = ce_hba_determinism_client_settings($pdo, $clientId);
    if ($determinismSettings['displayMode'] !== 'decimal') {
        $pbs['pbsDisplayScore'] = null;
        $pbs['pbsLabel'] = $pbs['pbsLabel'] ?? 'Indicatief';
    }
    $publishedPosts = ce_count_published_posts($pdo, $clientId);
    $voiceThreshold = ce_voice_profile_threshold();
    $guardrail = ce_hba_active_guardrail($pdo, $clientId);
    if ($guardrail === null) {
        $architectureFootnote = $architectureFootnote !== '' && $architectureFootnote !== null
            ? $architectureFootnote
            : ce_hba_v2_guardrail_inactive_footnote($pdo, $clientId);
    }

    return array_merge([
        'ok' => true,
        'scoredPosts' => $scoredPosts,
        'publishedPosts' => $publishedPosts,
        'voiceActive' => $publishedPosts >= $voiceThreshold,
        'phase' => $phase,
        'architectureComplete' => $architectureComplete,
        'themesChosen' => $themesChosen,
        'themesReinforcing' => $themesReinforcing,
        'hintsApplied' => $hintsApplied,
        'pbsRevealed' => $pbsRevealed,
        'showRevealMessage' => $showRevealMessage,
        'targetPosts' => $voiceThreshold,
        'architectureFootnote' => $architectureFootnote,
        'trackerDetails' => $trackerDetails,
        'guardrailActive' => $guardrail !== null,
        'scoreDisplayMode' => $determinismSettings['displayMode'],
        'determinismStatus' => $determinismSettings['status'],
        'scoreAveraging' => $determinismSettings['averaging'],
    ], $pbs, $baselinePayload);
}

/** Score bij publiceren — §9.4.2 */
function ce_hba_v2_score_on_publish(PDO $pdo, string $articleId, string $clientId): void
{
    require_once __DIR__ . '/hba-identity-gate.php';
    if (ce_hba_active_guardrail($pdo, $clientId) === null) {
        return;
    }
    if (!ce_table_exists($pdo, 'hba_scores')) {
        return;
    }
    $existingStmt = $pdo->prepare('SELECT id FROM hba_scores WHERE post_id = :post_id LIMIT 1');
    $existingStmt->execute(['post_id' => $articleId]);
    if ($existingStmt->fetch()) {
        return;
    }

    try {
        ce_hba_score_article($pdo, ['articleId' => $articleId, 'clientId' => $clientId]);
    } catch (Throwable $e) {
        error_log('CE HBA score on publish failed: ' . $e->getMessage());
    }
}

/** Markeer merkarchitectuur-flow afgerond. */
function ce_hba_v2_mark_architecture_complete(PDO $pdo, string $clientId): void
{
    ce_ensure_hba_v2_schema($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'hba_architecture_completed_at')) {
        return;
    }
    $pdo->prepare(
        'UPDATE clients SET hba_architecture_completed_at = COALESCE(hba_architecture_completed_at, NOW()) WHERE id = :id'
    )->execute(['id' => $clientId]);

    require_once __DIR__ . '/content-rhythm-repository.php';
    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    ce_content_rhythm_ensure_proposed($pdo, $clientId, $ctx['archetype'] ?? null, $ctx['branche_code'] ?? null);

    require_once __DIR__ . '/hba-baseline-repository.php';
    ce_hba_baseline_schedule($pdo, $clientId);

    ce_hba_v2_try_bootstrap_guardrail($pdo, $clientId);
}
