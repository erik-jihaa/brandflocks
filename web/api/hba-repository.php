<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/llm-repository.php';
require_once __DIR__ . '/hba-prompt.php';
require_once __DIR__ . '/hba-model-config.php';
require_once __DIR__ . '/hba-hint-verificatie.php';

const CE_HBA_PILOT_CLIENT_ID = '016384af-b26e-4415-aa9c-abc46b804798';

/** §10 — content scoring draait op temperatuur 0 voor determinisme. */
const CE_HBA_CONTENT_SCORE_TEMPERATURE = 0.0;

const CE_HBA_ARCHETYPES = [
    'Leider',
    'Magiër',
    'Wijze',
    'Kampioen',
    'Ontdekker',
    'Maker',
    'Rebel',
    'Entertainer',
    'Beminnaar',
    'Verzorger',
    'Buur',
    'Optimist',
];

const CE_HBA_CONTEXT_PROFILES = [
    'nieuw_merk' => 'Nieuw merk',
    'herpositionering' => 'Herpositionering',
    'groeiend' => 'Groeiend',
    'volwassen' => 'Volwassen',
    'tijdelijke_salesdruk' => 'Tijdelijke salesdruk',
    'b2b_complex_sale' => 'B2B complex sale',
];

/** Archetypische Foe-hints (Core Logic) — UI mag dit tonen, geen volledige rubric. */
const CE_HBA_ARCHETYPE_FOE_HINTS = [
    'Leider' => 'Beslisonzekerheid; complexiteit, onduidelijke keuzes, gebrek aan betrouwbare normen.',
    'Magiër' => 'Fatalisme en beperkte verbeelding; cynisme over transformatie.',
    'Wijze' => 'Schijnkennis en verwarring; halve waarheden, expertjargon zonder begrip.',
    'Kampioen' => 'Machteloosheid en opgave; apathie, middelmaat als norm.',
    'Ontdekker' => 'Opsluiting en opgelegde routes; angst voor het onbekende.',
    'Maker' => 'Vormloosheid en lege abstractie; ideeën zonder uitvoering.',
    'Rebel' => 'Schijnzekerheid; conformiteit, dogma\'s, autoriteit zonder legitimiteit.',
    'Entertainer' => 'Krampachtige ernst; statusangst, overcontrole.',
    'Beminnaar' => 'Emotionele afstandelijkheid; puur functionele wereld.',
    'Verzorger' => 'Verwaarlozing; zorg als kostenpost, doorgeschoven verantwoordelijkheid.',
    'Buur' => 'Uitsluiting en pretentie; elitair taalgebruik, deelnamedrempels.',
    'Optimist' => 'Cynisme en besmette complexiteit; wantrouwen als standaard.',
];

const CE_HBA_NARRATIVE_FIELDS = [
    'archetype',
    'doel',
    'foe',
    'spanning',
    'mechanisme',
    'geloofwaardigheidstest',
    'bewijsvelden',
    'verboden_logica',
    'tone_of_behaviour',
    'woorden_die_passen',
    'woorden_die_niet_passen',
    'goede_voorbeelden',
    'zwakke_voorbeelden',
    'contextprofiel',
];

function ce_ensure_hba_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!ce_table_exists($pdo, 'brand_narratives')) {
        $migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_hba_brandlaag.sql';
        if (is_readable($migration)) {
            try {
                $pdo->exec((string) file_get_contents($migration));
            } catch (Throwable) {
                // Geen CREATE-rechten — scoring mag later falen met duidelijke fout.
            }
        }
    }

    if (ce_table_exists($pdo, 'articles') && !ce_table_has_column($pdo, 'articles', 'hba_contentrol_override')) {
        try {
            $pdo->exec('ALTER TABLE articles ADD COLUMN hba_contentrol_override VARCHAR(32) NULL');
            unset($GLOBALS['ce_table_columns']['articles']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }

    $v2ColumnAlters = [
        ['brand_narratives', 'distinctiviteit', 'ALTER TABLE brand_narratives ADD COLUMN distinctiviteit TEXT NULL'],
        ['brand_narratives', 'branche_code', 'ALTER TABLE brand_narratives ADD COLUMN branche_code VARCHAR(64) NULL'],
        ['brand_narratives', 'wat_bewust_niet', 'ALTER TABLE brand_narratives ADD COLUMN wat_bewust_niet TEXT NULL'],
        ['brand_narratives', 'identity_intro', 'ALTER TABLE brand_narratives ADD COLUMN identity_intro TEXT NULL'],
        ['brand_narratives', 'kernformuleringen', 'ALTER TABLE brand_narratives ADD COLUMN kernformuleringen JSON NULL'],
        ['brand_narratives', 'aanspreekvorm', 'ALTER TABLE brand_narratives ADD COLUMN aanspreekvorm VARCHAR(8) NULL'],
        ['hba_scores', 'kernformulering_hit', 'ALTER TABLE hba_scores ADD COLUMN kernformulering_hit TINYINT NULL'],
        ['hba_scores', 'hygiene_list_version', 'ALTER TABLE hba_scores ADD COLUMN hygiene_list_version INT NULL'],
        ['hba_scores', 'origin', 'ALTER TABLE hba_scores ADD COLUMN origin VARCHAR(16) NOT NULL DEFAULT \'app\''],
        ['hba_scores', 'linkedin_post_id', 'ALTER TABLE hba_scores ADD COLUMN linkedin_post_id CHAR(36) NULL'],
    ];
    foreach ($v2ColumnAlters as [$table, $column, $sql]) {
        if (ce_table_exists($pdo, $table) && !ce_table_has_column($pdo, $table, $column)) {
            try {
                $pdo->exec($sql);
                unset($GLOBALS['ce_table_columns'][$table]);
            } catch (Throwable) {
                // Kolom bestaat al.
            }
        }
    }
}

/** Mag HBA-scoring draaien? Centrale poort — later plan_allows_hba toevoegen. */
/** @return list<string> */
function ce_hba_contentrol_keys(): array
{
    return [
        'positionering',
        'bewijs',
        'educatie',
        'activatie',
        'community',
        'service',
        'employer_culture',
        'reactief',
    ];
}

/** Herken contentrol in vrije tekst (diagnose) wanneer het model het enum-veld leeg liet. */
function ce_hba_infer_contentrol_from_text(string $text): ?string
{
    $t = mb_strtolower(trim($text));
    if ($t === '') {
        return null;
    }

    $rules = [
        ['positioneringsstuk', 'positionering'],
        ['positionerend stuk', 'positionering'],
        ['positionerend', 'positionering'],
        ['employer culture', 'employer_culture'],
        ['employer_culture', 'employer_culture'],
        ['reactief', 'reactief'],
        ['activatie', 'activatie'],
        ['community', 'community'],
        ['service', 'service'],
        ['bewijs', 'bewijs'],
        ['educatie', 'educatie'],
        ['positionering', 'positionering'],
    ];
    foreach ($rules as [$needle, $rol]) {
        if (str_contains($t, $needle)) {
            return $rol;
        }
    }

    return null;
}

/** @param array<string, mixed> $tool @return array<string, mixed> */
function ce_hba_ensure_contentrol_on_tool(array $tool): array
{
    if (($tool['status'] ?? '') !== 'scored') {
        return $tool;
    }

    $rol = trim((string) ($tool['contentrol'] ?? ''));
    if ($rol !== '' && in_array($rol, ce_hba_contentrol_keys(), true)) {
        return $tool;
    }

    $inferred = ce_hba_infer_contentrol_from_text((string) ($tool['diagnose'] ?? ''))
        ?? ce_hba_infer_contentrol_from_text((string) ($tool['contentrol_motivation'] ?? ''));
    if ($inferred === null) {
        return $tool;
    }

    $tool['contentrol'] = $inferred;
    if (trim((string) ($tool['contentrol_motivation'] ?? '')) === '') {
        $tool['contentrol_motivation'] = 'Contentrol afgeleid uit de diagnose (model vulde het veld niet in).';
    }

    return $tool;
}

function ce_hba_on_strategy_bc_threshold(): float
{
    $env = getenv('CE_HBA_ON_STRATEGY_BC_THRESHOLD');
    if ($env !== false && $env !== '' && is_numeric($env)) {
        return (float) $env;
    }

    return 6.0;
}

/**
 * Enige toegangspoort voor content-scoring: narrative compleet + guardrail PASS.
 *
 * @return array{allowed: bool, status: string, message?: string, missingOrInconsistent?: list<string>}
 */
function ce_client_hba_scoring_gate(PDO $pdo, string $clientId): array
{
    $clientId = trim($clientId);
    if ($clientId === '') {
        return [
            'allowed' => false,
            'status' => 'disabled',
            'message' => 'Geen client voor scoring',
        ];
    }

    $narrativeRow = ce_fetch_current_brand_narrative($pdo, $clientId);
    $missing = $narrativeRow ? ce_brand_narrative_missing_fields($narrativeRow) : CE_HBA_NARRATIVE_FIELDS;
    if ($missing !== []) {
        return [
            'allowed' => false,
            'status' => 'narrative_incomplete',
            'message' => 'Merkarchitectuur nog niet compleet voor scoring',
            'missingOrInconsistent' => $missing,
        ];
    }

    require_once __DIR__ . '/hba-identity-gate.php';
    if (ce_hba_active_guardrail($pdo, $clientId) === null) {
        return [
            'allowed' => false,
            'status' => 'guardrail_inactive',
            'message' => 'Architectuurcheck staat nog niet op PASS — geen PBS-scoring.',
        ];
    }

    return ['allowed' => true, 'status' => 'ready'];
}

function ce_client_hba_scoring_allowed(PDO $pdo, string $clientId): bool
{
    return !empty(ce_client_hba_scoring_gate($pdo, $clientId)['allowed']);
}

/**
 * Presentatielaag: integrity = 10 − risk. Ruwe risicowaarde blijft in hba_scores.
 * Zie briefing/11-scoreweergave-implementatie.md §2.
 */
function ce_hba_architecture_integrity_from_risk(mixed $risk): ?float
{
    if ($risk === null || $risk === '' || !is_numeric($risk)) {
        return null;
    }

    return max(0.0, min(10.0, 10.0 - (float) $risk));
}

/** @return list<string> */
function ce_hba_decode_json_list(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $value)));
    }
    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return ce_hba_decode_json_list($decoded);
        }
    }

    return [];
}

/** @param array<string, mixed> $row */
function ce_brand_narrative_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'clientId' => (string) ($row['client_id'] ?? ''),
        'archetype' => (string) ($row['archetype'] ?? ''),
        'doel' => (string) ($row['doel'] ?? ''),
        'foe' => (string) ($row['foe'] ?? ''),
        'spanning' => (string) ($row['spanning'] ?? ''),
        'mechanisme' => (string) ($row['mechanisme'] ?? ''),
        'geloofwaardigheidstest' => (string) ($row['geloofwaardigheidstest'] ?? ''),
        'verbodenLogica' => ce_hba_decode_json_list($row['verboden_logica'] ?? []),
        'bewijsvelden' => ce_hba_decode_json_list($row['bewijsvelden'] ?? []),
        'toneOfBehaviour' => (string) ($row['tone_of_behaviour'] ?? ''),
        'woordenDiePassen' => ce_hba_decode_json_list($row['woorden_die_passen'] ?? []),
        'woordenDieNietPassen' => ce_hba_decode_json_list($row['woorden_die_niet_passen'] ?? []),
        'goedeVoorbeelden' => ce_hba_decode_json_list($row['goede_voorbeelden'] ?? []),
        'zwakkeVoorbeelden' => ce_hba_decode_json_list($row['zwakke_voorbeelden'] ?? []),
        'kernformuleringen' => ce_hba_decode_json_list($row['kernformuleringen'] ?? []),
        'contextprofiel' => (string) ($row['contextprofiel'] ?? ''),
        'distinctiviteit' => (string) ($row['distinctiviteit'] ?? ''),
        'watBewustNiet' => (string) ($row['wat_bewust_niet'] ?? ''),
        'brancheCode' => (string) ($row['branche_code'] ?? ''),
        'identityIntro' => (string) ($row['identity_intro'] ?? ''),
        'aanspreekvorm' => (string) ($row['aanspreekvorm'] ?? ''),
        'defaultPlatforms' => ce_hba_decode_json_list($row['default_platforms'] ?? []),
        'targetPostVolumePerMonth' => isset($row['target_post_volume_per_month'])
            ? (int) $row['target_post_volume_per_month']
            : null,
        'version' => (int) ($row['version'] ?? 1),
        'createdAt' => (string) ($row['created_at'] ?? ''),
    ];
}

/** Posttekst voor HBA-scoring — headline + body (editor-vragenblok eruit, redactie-wrapper mag blijven voor LLM). */
function ce_hba_article_post_text(array $articleRow): string
{
    $formatted = ce_format_article_row($articleRow);
    $headline = ce_article_headline($articleRow);
    $bodyText = trim((string) ($formatted['content'] ?? $formatted['body'] ?? ''));
    if ($bodyText === '') {
        $raw = trim((string) ($articleRow['content_human'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($articleRow['content_ai'] ?? ''));
        }
        if ($raw !== '') {
            require_once __DIR__ . '/editor-questions.php';
            $bodyText = trim(ce_strip_editor_questions_block($raw));
        }
    }

    $text = trim($headline . ($bodyText !== '' ? "\n\n" . $bodyText : ''));
    $tags = trim((string) ($articleRow['hashtags_final'] ?? $articleRow['hashtags_suggested'] ?? ''));
    if ($tags !== '') {
        $text = trim($text . "\n\n" . $tags);
    }

    return $text;
}

function ce_fetch_current_brand_narrative(PDO $pdo, string $clientId): ?array
{
    ce_ensure_hba_schema($pdo);
    if (!ce_table_exists($pdo, 'brand_narratives')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM brand_narratives WHERE client_id = :client_id AND is_current = 1 ORDER BY version DESC LIMIT 1'
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @return array{complete: bool, filled: int, total: int, missing: list<string>} */
function ce_brand_narrative_completeness(PDO $pdo, string $clientId): array
{
    $row = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$row) {
        return [
            'complete' => false,
            'filled' => 0,
            'total' => count(CE_HBA_NARRATIVE_FIELDS),
            'missing' => CE_HBA_NARRATIVE_FIELDS,
        ];
    }

    $missing = ce_brand_narrative_missing_fields($row);

    return [
        'complete' => $missing === [],
        'filled' => count(CE_HBA_NARRATIVE_FIELDS) - count($missing),
        'total' => count(CE_HBA_NARRATIVE_FIELDS),
        'missing' => $missing,
    ];
}

function ce_brand_narrative_is_complete(PDO $pdo, string $clientId): bool
{
    return ce_brand_narrative_completeness($pdo, $clientId)['complete'];
}

function ce_brand_narrative_strip_wizard_template_noise(string $text): string
{
    $text = preg_replace('/\.?\s*Schrijf hieronder in je eigen woorden[^.!?]*[.!?]?/iu', '', $text) ?? $text;
    $text = preg_replace('/\.?\s*Schrijf hieronder[^.!?]*[.!?]?/iu', '', $text) ?? $text;

    return trim(preg_replace('/\s{2,}/u', ' ', $text) ?? $text);
}

/** @param array<string, mixed> $narrative */
function ce_brand_narrative_enrich_scoring_fields(array &$narrative): void
{
    $doel = trim((string) ($narrative['doel'] ?? ''));
    $foe = trim((string) ($narrative['foe'] ?? ''));
    $distinct = trim((string) ($narrative['distinctiviteit'] ?? ''));
    $spanning = ce_brand_narrative_strip_wizard_template_noise((string) ($narrative['spanning'] ?? ''));
    if ($spanning === '' || ($foe !== '' && $spanning === $foe) || mb_strlen($spanning) < 20) {
        $combo = 'De inzet: ' . $doel . ' — zolang ' . $foe . ' onopgelost blijft, blijft groei kwetsbaar.';
        $fallback = $doel . ' — versus ' . $foe;
        $candidate = mb_strlen($combo) >= 20 ? $combo : $fallback;
        if (mb_strlen($candidate) >= 20) {
            $narrative['spanning'] = mb_substr($candidate, 0, 600);
        }
    } else {
        $narrative['spanning'] = $spanning;
    }

    $mechanisme = ce_brand_narrative_strip_wizard_template_noise((string) ($narrative['mechanisme'] ?? ''));
    if ($mechanisme === '' || ($distinct !== '' && $mechanisme === $distinct) || mb_strlen($mechanisme) < 20) {
        $cleanDistinct = ce_brand_narrative_strip_wizard_template_noise($distinct);
        if (mb_strlen($cleanDistinct) >= 20) {
            $narrative['mechanisme'] = $cleanDistinct;
        }
    } else {
        $narrative['mechanisme'] = $mechanisme;
    }

    $mechFinal = trim((string) ($narrative['mechanisme'] ?? ''));
    $bewijs = ce_hba_decode_json_list($narrative['bewijsvelden'] ?? []);
    $bewijs = array_values(array_filter(array_map(
        static fn (string $item): string => ce_brand_narrative_strip_wizard_template_noise($item),
        $bewijs,
    )));
    $bewijs = array_values(array_filter($bewijs, static fn (string $item): bool => $item !== '' && $item !== $mechFinal));
    if ($bewijs === []) {
        $regels = preg_split('/\r\n|\r|\n/u', (string) ($narrative['geloofwaardigheidstest'] ?? '')) ?: [];
        $kern = ce_hba_decode_json_list($narrative['kernformuleringen'] ?? []);
        $candidates = array_values(array_unique(array_filter([
            ce_brand_narrative_strip_wizard_template_noise($distinct),
            ...array_map(static fn (string $r): string => ce_brand_narrative_strip_wizard_template_noise(trim($r)), $regels),
            ...array_map(
                static fn (string $k): string => ce_brand_narrative_strip_wizard_template_noise($k),
                $kern,
            ),
        ], static fn (string $item): bool => $item !== '' && $item !== $mechFinal)));
        $bewijs = array_slice($candidates, 0, 5);
    }
    $narrative['bewijsvelden'] = $bewijs;
}

/** @param array<string, mixed> $row @return list<string> */
function ce_brand_narrative_missing_fields(array $row): array
{
    $missing = [];

    foreach (CE_HBA_NARRATIVE_FIELDS as $field) {
        if ($field === 'archetype') {
            $value = trim((string) ($row['archetype'] ?? ''));
            if ($value === '' || !in_array($value, CE_HBA_ARCHETYPES, true)) {
                $missing[] = 'archetype';
            }
            continue;
        }
        if ($field === 'contextprofiel') {
            $value = trim((string) ($row['contextprofiel'] ?? ''));
            if ($value === '' || !array_key_exists($value, CE_HBA_CONTEXT_PROFILES)) {
                $missing[] = 'contextprofiel';
            }
            continue;
        }
        if (in_array($field, ['bewijsvelden', 'verboden_logica', 'woorden_die_passen', 'woorden_die_niet_passen'], true)) {
            $min = in_array($field, ['woorden_die_passen', 'woorden_die_niet_passen'], true) ? 3 : 1;
            if (count(ce_hba_decode_json_list($row[$field] ?? [])) < $min) {
                $missing[] = $field;
            }
            continue;
        }
        if (in_array($field, ['goede_voorbeelden', 'zwakke_voorbeelden'], true)) {
            $items = ce_hba_decode_json_list($row[$field] ?? []);
            if (count($items) !== 3 || count(array_filter($items)) !== 3) {
                $missing[] = $field;
            }
            continue;
        }
        $value = trim((string) ($row[$field] ?? ''));
        $minLen = match ($field) {
            'doel', 'foe' => 20,
            'spanning' => 20,
            'mechanisme', 'geloofwaardigheidstest' => 20,
            'tone_of_behaviour' => 20,
            default => 1,
        };
        if (mb_strlen($value) < $minLen) {
            $missing[] = $field;
        }
    }

    return $missing;
}

/** @param array<string, mixed> $body */
function ce_brand_narrative_from_request(array $body): array
{
    $pickList = static function (string $camel, string $snake) use ($body): array {
        if (array_key_exists($camel, $body)) {
            return ce_hba_decode_json_list($body[$camel]);
        }
        if (array_key_exists($snake, $body)) {
            return ce_hba_decode_json_list($body[$snake]);
        }

        return [];
    };

    return [
        'archetype' => trim((string) ($body['archetype'] ?? '')),
        'doel' => trim((string) ($body['doel'] ?? '')),
        'foe' => trim((string) ($body['foe'] ?? $body['adversary'] ?? '')),
        'spanning' => trim((string) ($body['spanning'] ?? '')),
        'mechanisme' => trim((string) ($body['mechanisme'] ?? '')),
        'geloofwaardigheidstest' => trim((string) ($body['geloofwaardigheidstest'] ?? '')),
        'verboden_logica' => $pickList('verbodenLogica', 'verboden_logica'),
        'bewijsvelden' => $pickList('bewijsvelden', 'bewijsvelden'),
        'tone_of_behaviour' => trim((string) ($body['toneOfBehaviour'] ?? $body['tone_of_behaviour'] ?? '')),
        'woorden_die_passen' => $pickList('woordenDiePassen', 'woorden_die_passen'),
        'woorden_die_niet_passen' => $pickList('woordenDieNietPassen', 'woorden_die_niet_passen'),
        'goede_voorbeelden' => array_slice($pickList('goedeVoorbeelden', 'goede_voorbeelden'), 0, 3),
        'zwakke_voorbeelden' => array_slice($pickList('zwakkeVoorbeelden', 'zwakke_voorbeelden'), 0, 3),
        'contextprofiel' => trim((string) ($body['contextprofiel'] ?? '')),
        'distinctiviteit' => trim((string) ($body['distinctiviteit'] ?? '')),
        'wat_bewust_niet' => trim((string) ($body['watBewustNiet'] ?? $body['wat_bewust_niet'] ?? '')),
        'branche_code' => trim((string) ($body['brancheCode'] ?? $body['branche_code'] ?? '')),
        'identity_intro' => trim((string) ($body['identityIntro'] ?? $body['identity_intro'] ?? '')),
        'default_platforms' => $pickList('defaultPlatforms', 'default_platforms'),
        'target_post_volume_per_month' => isset($body['targetPostVolumePerMonth'])
            ? (int) $body['targetPostVolumePerMonth']
            : null,
        'kernformuleringen' => array_slice($pickList('kernformuleringen', 'kernformuleringen'), 0, 5),
        'aanspreekvorm' => ce_hba_normalize_aanspreekvorm(
            (string) ($body['aanspreekvorm'] ?? $body['aanspreekVorm'] ?? '')
        ),
    ];
}

/** @param array<string, mixed> $narrative @return array<string, mixed> */
function ce_save_brand_narrative(PDO $pdo, string $clientId, array $narrative, string $createdBy): array
{
    ce_ensure_hba_schema($pdo);
    if (!ce_table_exists($pdo, 'brand_narratives')) {
        throw new RuntimeException('brand_narratives-tabel ontbreekt');
    }

    ce_brand_narrative_enrich_scoring_fields($narrative);

    $missing = ce_brand_narrative_missing_fields($narrative);
    $isComplete = $missing === [];

    $current = ce_fetch_current_brand_narrative($pdo, $clientId);
    $positioningChanged = false;
    if ($current) {
        require_once __DIR__ . '/hba-v2-repository.php';
        $positioningChanged = ce_hba_v2_positioning_fingerprint($current)
            !== ce_hba_v2_positioning_fingerprint($narrative);
    }
    $nextVersion = $current ? ((int) ($current['version'] ?? 0)) : 0;

    $pdo->beginTransaction();
    try {
        if ($current && !$isComplete) {
            $id = (string) $current['id'];
            $stmt = $pdo->prepare(
                'UPDATE brand_narratives SET
                    archetype = :archetype, doel = :doel, foe = :foe, spanning = :spanning,
                    mechanisme = :mechanisme, geloofwaardigheidstest = :geloofwaardigheidstest,
                    verboden_logica = :verboden_logica, bewijsvelden = :bewijsvelden,
                    tone_of_behaviour = :tone_of_behaviour, woorden_die_passen = :woorden_die_passen,
                    woorden_die_niet_passen = :woorden_die_niet_passen, goede_voorbeelden = :goede_voorbeelden,
                    zwakke_voorbeelden = :zwakke_voorbeelden, contextprofiel = :contextprofiel,
                    distinctiviteit = :distinctiviteit, wat_bewust_niet = :wat_bewust_niet, branche_code = :branche_code,
                    identity_intro = :identity_intro,
                    default_platforms = :default_platforms, target_post_volume_per_month = :target_post_volume_per_month,
                    kernformuleringen = :kernformuleringen
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'archetype' => $narrative['archetype'],
                'doel' => $narrative['doel'],
                'foe' => $narrative['foe'],
                'spanning' => $narrative['spanning'],
                'mechanisme' => $narrative['mechanisme'],
                'geloofwaardigheidstest' => $narrative['geloofwaardigheidstest'],
                'verboden_logica' => json_encode($narrative['verboden_logica'], JSON_UNESCAPED_UNICODE),
                'bewijsvelden' => json_encode($narrative['bewijsvelden'], JSON_UNESCAPED_UNICODE),
                'tone_of_behaviour' => $narrative['tone_of_behaviour'],
                'woorden_die_passen' => json_encode($narrative['woorden_die_passen'], JSON_UNESCAPED_UNICODE),
                'woorden_die_niet_passen' => json_encode($narrative['woorden_die_niet_passen'], JSON_UNESCAPED_UNICODE),
                'goede_voorbeelden' => json_encode($narrative['goede_voorbeelden'], JSON_UNESCAPED_UNICODE),
                'zwakke_voorbeelden' => json_encode($narrative['zwakke_voorbeelden'], JSON_UNESCAPED_UNICODE),
                'contextprofiel' => $narrative['contextprofiel'],
                'distinctiviteit' => $narrative['distinctiviteit'] ?? '',
                'wat_bewust_niet' => $narrative['wat_bewust_niet'] ?? '',
                'branche_code' => $narrative['branche_code'] ?? '',
                'identity_intro' => $narrative['identity_intro'] ?? '',
                'default_platforms' => json_encode($narrative['default_platforms'], JSON_UNESCAPED_UNICODE),
                'target_post_volume_per_month' => $narrative['target_post_volume_per_month'],
                'kernformuleringen' => json_encode($narrative['kernformuleringen'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        } else {
            if ($current) {
                $pdo->prepare('UPDATE brand_narratives SET is_current = 0 WHERE client_id = :client_id AND is_current = 1')
                    ->execute(['client_id' => $clientId]);
            }
            $nextVersion = $current ? ((int) ($current['version'] ?? 0)) + 1 : 1;
            $id = sprintf(
                '%s-%s-%s-%s-%s',
                bin2hex(random_bytes(4)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(6)),
            );

            $stmt = $pdo->prepare(
                'INSERT INTO brand_narratives (
                    id, client_id, archetype, doel, foe, spanning, mechanisme, geloofwaardigheidstest,
                    verboden_logica, bewijsvelden, tone_of_behaviour, woorden_die_passen, woorden_die_niet_passen,
                    goede_voorbeelden, zwakke_voorbeelden, contextprofiel, distinctiviteit, wat_bewust_niet, branche_code,
                    identity_intro,
                    default_platforms, target_post_volume_per_month, kernformuleringen, version, is_current, created_by
                ) VALUES (
                    :id, :client_id, :archetype, :doel, :foe, :spanning, :mechanisme, :geloofwaardigheidstest,
                    :verboden_logica, :bewijsvelden, :tone_of_behaviour, :woorden_die_passen, :woorden_die_niet_passen,
                    :goede_voorbeelden, :zwakke_voorbeelden, :contextprofiel, :distinctiviteit, :wat_bewust_niet, :branche_code,
                    :identity_intro,
                    :default_platforms, :target_post_volume_per_month, :kernformuleringen, :version, 1, :created_by
                )'
            );
            $stmt->execute([
                'id' => $id,
                'client_id' => $clientId,
                'archetype' => $narrative['archetype'],
                'doel' => $narrative['doel'],
                'foe' => $narrative['foe'],
                'spanning' => $narrative['spanning'],
                'mechanisme' => $narrative['mechanisme'],
                'geloofwaardigheidstest' => $narrative['geloofwaardigheidstest'],
                'verboden_logica' => json_encode($narrative['verboden_logica'], JSON_UNESCAPED_UNICODE),
                'bewijsvelden' => json_encode($narrative['bewijsvelden'], JSON_UNESCAPED_UNICODE),
                'tone_of_behaviour' => $narrative['tone_of_behaviour'],
                'woorden_die_passen' => json_encode($narrative['woorden_die_passen'], JSON_UNESCAPED_UNICODE),
                'woorden_die_niet_passen' => json_encode($narrative['woorden_die_niet_passen'], JSON_UNESCAPED_UNICODE),
                'goede_voorbeelden' => json_encode($narrative['goede_voorbeelden'], JSON_UNESCAPED_UNICODE),
                'zwakke_voorbeelden' => json_encode($narrative['zwakke_voorbeelden'], JSON_UNESCAPED_UNICODE),
                'contextprofiel' => $narrative['contextprofiel'],
                'distinctiviteit' => $narrative['distinctiviteit'] ?? '',
                'wat_bewust_niet' => $narrative['wat_bewust_niet'] ?? '',
                'branche_code' => $narrative['branche_code'] ?? '',
                'identity_intro' => $narrative['identity_intro'] ?? '',
                'default_platforms' => json_encode($narrative['default_platforms'], JSON_UNESCAPED_UNICODE),
                'target_post_volume_per_month' => $narrative['target_post_volume_per_month'],
                'kernformuleringen' => json_encode($narrative['kernformuleringen'] ?? [], JSON_UNESCAPED_UNICODE),
                'version' => $nextVersion,
                'created_by' => $createdBy !== '' ? $createdBy : $clientId,
            ]);
        }

        if ($isComplete) {
            ce_hba_write_through_clients($pdo, $clientId, $narrative);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $saved = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$saved) {
        throw new RuntimeException('Narrative opslaan mislukt');
    }
    ce_hba_persist_aanspreekvorm($pdo, (string) ($saved['id'] ?? ''), $narrative['aanspreekvorm'] ?? '');
    $saved = ce_fetch_current_brand_narrative($pdo, $clientId) ?: $saved;

    if ($positioningChanged) {
        require_once __DIR__ . '/hba-v2-repository.php';
        ce_hba_v2_invalidate_positioning_verdicts($pdo, $clientId);
    }

    $dto = ce_brand_narrative_dto($saved);
    $dto['completeness'] = ce_brand_narrative_completeness($pdo, $clientId);

    return $dto;
}

/** @param array<string, mixed> $narrative */
function ce_hba_write_through_clients(PDO $pdo, string $clientId, array $narrative): void
{
    ce_ensure_account_columns($pdo);
    $sets = [];
    $params = ['id' => $clientId];

    if (ce_table_has_column($pdo, 'clients', 'archetype')) {
        $sets[] = 'archetype = :archetype';
        $params['archetype'] = (string) ($narrative['archetype'] ?? '');
    }
    if (ce_table_has_column($pdo, 'clients', 'adversary')) {
        $sets[] = 'adversary = :adversary';
        $params['adversary'] = (string) ($narrative['foe'] ?? '');
    }
    if (ce_table_has_column($pdo, 'clients', 'brand_goal')) {
        $sets[] = 'brand_goal = :brand_goal';
        $params['brand_goal'] = (string) ($narrative['doel'] ?? '');
    }

    if ($sets === []) {
        return;
    }

    $pdo->prepare('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
}

/**
 * @param list<string>|null $terms null = algemene verbodslijst (StoryBrand e.d.)
 * @return list<string>
 */
function ce_hba_forbidden_terms_in_text(string $text, ?array $terms = null): array
{
    $hits = [];
    $lower = mb_strtolower($text);
    foreach ($terms ?? ce_hba_forbidden_terms() as $term) {
        $needle = mb_strtolower(trim((string) $term));
        if ($needle !== '' && str_contains($lower, $needle)) {
            $hits[] = trim((string) $term);
        }
    }

    return array_values(array_unique($hits));
}

/**
 * Algemene verboden termen in diagnose+advies, plus V5-klanttermen alleen in verbeteradvies.
 *
 * @return array{hits: list<string>, adviesHits: list<string>}
 */
function ce_hba_score_output_forbidden_hits(array $tool): array
{
    $diagnose = trim((string) ($tool['diagnose'] ?? ''));
    $advies = trim((string) ($tool['verbeteradvies'] ?? ''));
    $general = ce_hba_forbidden_terms_in_text($diagnose . ' ' . $advies);
    $adviesHits = ce_hba_forbidden_terms_in_text($advies, ce_hba_verbeteradvies_forbidden_terms());

    return [
        'hits' => array_values(array_unique(array_merge($general, $adviesHits))),
        'adviesHits' => $adviesHits,
    ];
}

function ce_hba_anthropic_model_omits_temperature(string $model): bool
{
    return preg_match('/claude-(sonnet|opus|fable)-5(\b|$)/i', $model) === 1;
}

/** @param array<string, mixed> $extra */
function ce_hba_anthropic_messages_payload(string $model, array $extra, ?float $temperature = null): array
{
    $payload = array_merge(['model' => $model, 'max_tokens' => 4096], $extra);
    $temp = $temperature ?? CE_HBA_CONTENT_SCORE_TEMPERATURE;
    if (!ce_hba_anthropic_model_omits_temperature($model)) {
        $payload['temperature'] = max(0.0, min(1.0, $temp));
    }

    return $payload;
}

/** @return array{ok: bool, tool: ?array<string, mixed>, raw: array<string, mixed>, input_tokens: ?int, output_tokens: ?int, model: string, error: string} */
function ce_hba_call_llm_with_model(string $prompt, string $model, bool $retryAfterForbidden = false): array
{
    $keys = ce_llm_visibility_api_keys();
    $key = trim((string) ($keys['anthropic'] ?? ''));
    if ($key === '') {
        return ['ok' => false, 'tool' => null, 'raw' => [], 'input_tokens' => null, 'output_tokens' => null, 'model' => $model, 'error' => 'ANTHROPIC_API_KEY ontbreekt'];
    }

    $model = ce_hba_model_reject_fable(trim($model), 'explicit model override');

    $userContent = $prompt;
    if ($retryAfterForbidden) {
        $userContent .= "\n\nCORRECTIE: je vorige output bevatte verboden termen. Herformuleer diagnose zonder StoryBrand-/merkjargon uit de verbodslijst. "
            . "Herformuleer verbeteradvies in klanttaal: gebruik nooit mechanisme, tegenkracht, geloofwaardigheidstest, "
            . "archetype, merklogica, branchenorm, categorietaal of hoofdoordeel.";
    }

    $payload = json_encode(ce_hba_anthropic_messages_payload($model, [
        'tools' => [ce_hba_score_tool_schema()],
        'tool_choice' => ['type' => 'tool', 'name' => 'hba_score'],
        'messages' => [['role' => 'user', 'content' => $userContent]],
    ]), JSON_THROW_ON_ERROR);

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
        return ['ok' => false, 'tool' => null, 'raw' => [], 'input_tokens' => null, 'output_tokens' => null, 'model' => $model, 'error' => $res['error']];
    }

    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        return ['ok' => false, 'tool' => null, 'raw' => [], 'input_tokens' => null, 'output_tokens' => null, 'model' => $model, 'error' => 'Ongeldig Anthropic-antwoord'];
    }

    $tool = null;
    foreach (($data['content'] ?? []) as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === 'hba_score') {
            $input = $block['input'] ?? null;
            if (is_array($input)) {
                $tool = $input;
            }
        }
    }

    if ($tool === null) {
        return ['ok' => false, 'tool' => null, 'raw' => $data, 'input_tokens' => null, 'output_tokens' => null, 'model' => $model, 'error' => 'Geen hba_score tool output'];
    }

    return [
        'ok' => true,
        'tool' => $tool,
        'raw' => $data,
        'input_tokens' => isset($data['usage']['input_tokens']) ? (int) $data['usage']['input_tokens'] : null,
        'output_tokens' => isset($data['usage']['output_tokens']) ? (int) $data['usage']['output_tokens'] : null,
        'model' => $model,
        'error' => '',
    ];
}

/** @return array{ok: bool, tool: ?array<string, mixed>, raw: array<string, mixed>, input_tokens: ?int, output_tokens: ?int, model: string, error: string} */
function ce_hba_call_llm(string $prompt, bool $retryAfterForbidden = false): array
{
    return ce_hba_call_llm_with_model($prompt, ce_hba_model_for(CE_HBA_FN_CONTENT_SCORE), $retryAfterForbidden);
}

/**
 * Tweede korte call: houdt constraint_breach alleen als de zin een verboden handeling is.
 *
 * @return array{ok: bool, ja: ?bool, reden: string, input_tokens: ?int, output_tokens: ?int, error: string}
 */
function ce_hba_call_constraint_breach_verification(
    string $geloofwaardigheidstest,
    string $citaat,
    string $uitleg,
    string $verbodenLogica = '',
): array {
    $keys = ce_llm_visibility_api_keys();
    $key = trim((string) ($keys['anthropic'] ?? ''));
    $model = ce_hba_model_for(CE_HBA_FN_CONTENT_SCORE);
    if ($key === '') {
        return ['ok' => false, 'ja' => null, 'reden' => '', 'input_tokens' => null, 'output_tokens' => null, 'error' => 'ANTHROPIC_API_KEY ontbreekt'];
    }

    $payload = ce_hba_anthropic_messages_payload($model, [
        'max_tokens' => 256,
        'tools' => [ce_hba_constraint_breach_verification_tool_schema()],
        'tool_choice' => ['type' => 'tool', 'name' => 'constraint_breach_verificatie'],
        'messages' => [[
            'role' => 'user',
            'content' => ce_hba_constraint_breach_verification_prompt($geloofwaardigheidstest, $citaat, $uitleg, $verbodenLogica),
        ]],
    ], 0.0);
    $payload['max_tokens'] = 256;

    $res = ce_llm_visibility_http_post(
        'https://api.anthropic.com/v1/messages',
        [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        json_encode($payload, JSON_THROW_ON_ERROR),
        45,
    );
    if (!$res['ok']) {
        return ['ok' => false, 'ja' => null, 'reden' => '', 'input_tokens' => null, 'output_tokens' => null, 'error' => $res['error']];
    }

    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        return ['ok' => false, 'ja' => null, 'reden' => '', 'input_tokens' => null, 'output_tokens' => null, 'error' => 'Ongeldig Anthropic-antwoord'];
    }

    $parsed = null;
    foreach (($data['content'] ?? []) as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === 'constraint_breach_verificatie') {
            $input = $block['input'] ?? null;
            if (is_array($input)) {
                $parsed = ce_hba_parse_constraint_breach_verification_tool($input);
            }
        }
    }
    if ($parsed === null) {
        return ['ok' => false, 'ja' => null, 'reden' => '', 'input_tokens' => null, 'output_tokens' => null, 'error' => 'Geen verificatie-tooloutput'];
    }

    return [
        'ok' => true,
        'ja' => $parsed['ja'],
        'reden' => $parsed['reden'],
        'input_tokens' => isset($data['usage']['input_tokens']) ? (int) $data['usage']['input_tokens'] : null,
        'output_tokens' => isset($data['usage']['output_tokens']) ? (int) $data['usage']['output_tokens'] : null,
        'error' => '',
    ];
}

/**
 * @return array{
 *   tool: array<string, mixed>,
 *   flags_verwijderd: list<array{flag: string, reason: string}>,
 *   verificatie: ?array{ja: bool, reden: string, kept: bool},
 *   input_tokens: ?int,
 *   output_tokens: ?int
 * }
 */
function ce_hba_apply_constraint_breach_verification(array $tool, string $geloofwaardigheidstest, string $verbodenLogica = ''): array
{
    $empty = [
        'tool' => $tool,
        'flags_verwijderd' => [],
        'verificatie' => null,
        'input_tokens' => null,
        'output_tokens' => null,
    ];
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : null;
    $flags = is_array($scores['architecture_risk']['flags'] ?? null) ? $scores['architecture_risk']['flags'] : [];
    if (!in_array('constraint_breach', $flags, true)) {
        return $empty;
    }

    $citaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    $citaat = trim((string) ($citaten['constraint_breach'] ?? ''));
    $uitleg = trim((string) ($citaten['constraint_breach_uitleg'] ?? ''));
    $regel = trim($geloofwaardigheidstest);
    if ($citaat === '' || $uitleg === '' || $regel === '') {
        return $empty;
    }

    $call = ce_hba_call_constraint_breach_verification($regel, $citaat, $uitleg, $verbodenLogica);
    if (!$call['ok'] || $call['ja'] === null) {
        // Call mislukt: vlag laten staan (echte breuken niet weggooien).
        return [
            'tool' => $tool,
            'flags_verwijderd' => [],
            'verificatie' => [
                'ja' => true,
                'reden' => $call['error'] !== '' ? ('verificatie overgeslagen: ' . $call['error']) : 'verificatie overgeslagen',
                'kept' => true,
                'skipped' => true,
            ],
            'input_tokens' => $call['input_tokens'],
            'output_tokens' => $call['output_tokens'],
        ];
    }

    if ($call['ja'] === true) {
        return [
            'tool' => $tool,
            'flags_verwijderd' => [],
            'verificatie' => [
                'ja' => true,
                'reden' => $call['reden'],
                'kept' => true,
            ],
            'input_tokens' => $call['input_tokens'],
            'output_tokens' => $call['output_tokens'],
        ];
    }

    $tool = ce_hba_drop_constraint_breach_flag($tool);

    return [
        'tool' => $tool,
        'flags_verwijderd' => [[
            'flag' => 'constraint_breach',
            'reason' => 'verificatie=nee: ' . $call['reden'],
        ]],
        'verificatie' => [
            'ja' => false,
            'reden' => $call['reden'],
            'kept' => false,
        ],
        'input_tokens' => $call['input_tokens'],
        'output_tokens' => $call['output_tokens'],
    ];
}

/**
 * Citaatfilter → uitleg-eis → verificatie → hoofdoordeel uit flags (v1.11).
 *
 * @return array{
 *   tool: array<string, mixed>,
 *   flags_verwijderd: list<array{flag: string, reason: string}>,
 *   hoofdoordeel_gecorrigeerd: ?string,
 *   hoofdoordeel_volgt_uit_flags: bool,
 *   verificatie: ?array<string, mixed>,
 *   input_tokens: ?int,
 *   output_tokens: ?int
 * }
 */
function ce_hba_finalize_scored_tool(
    array $tool,
    string $postText,
    string $geloofwaardigheidstest,
    string $verbodenLogica = '',
    ?string $headline = null,
): array {
    $citationFilter = ce_hba_apply_flag_citation_filter($tool, $postText);
    $tool = $citationFilter['tool'];
    $uitlegFilter = ce_hba_apply_constraint_breach_uitleg_filter($tool);
    $tool = $uitlegFilter['tool'];
    $verify = ce_hba_apply_constraint_breach_verification($tool, $geloofwaardigheidstest, $verbodenLogica);
    $tool = $verify['tool'];
    $foeDrop = ce_hba_drop_foe_vervaging_tegen_tegenkracht($tool);
    $tool = $foeDrop['tool'];
    $bcCap = ce_hba_apply_brand_contribution_kernbewering_cap($tool, $postText, $headline);
    $tool = $bcCap['tool'];
    $earlyCap = ce_hba_apply_brand_contribution_proof_gap_early_cap($tool, $postText);
    $tool = $earlyCap['tool'];
    $citaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    $foeCitaat = trim((string) ($citaten['foe_vervaging'] ?? ''));
    $zones = ce_hba_resolve_kop_en_openingszin($postText, $headline);
    $foeOnKern = $foeCitaat !== ''
        && ce_hba_flag_citation_on_kernbewering($foeCitaat, $zones['kop'], $zones['opening']);
    $hoofdFilter = ce_hba_apply_hoofdoordeel_filter($tool, [
        'foe_on_kern' => $foeOnKern,
    ]);

    return [
        'tool' => $hoofdFilter['tool'],
        'flags_verwijderd' => array_merge(
            $citationFilter['flags_verwijderd'],
            $uitlegFilter['flags_verwijderd'],
            $verify['flags_verwijderd'],
            $foeDrop['flags_verwijderd'],
        ),
        'hoofdoordeel_gecorrigeerd' => null,
        'hoofdoordeel_volgt_uit_flags' => (bool) ($hoofdFilter['hoofdoordeel_volgt_uit_flags'] ?? true),
        'foe_on_kern' => $foeOnKern,
        'verificatie' => $verify['verificatie'],
        'brand_contribution_kernbewering_cap' => $bcCap['cap'],
        'brand_contribution_proof_gap_early_cap' => $earlyCap['cap'],
        'input_tokens' => $verify['input_tokens'],
        'output_tokens' => $verify['output_tokens'],
    ];
}

function ce_hba_first_sentence(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (preg_match('/^(.+?[.!?])(?:\s|$)/u', $text, $matches)) {
        return trim($matches[1]);
    }

    return mb_substr($text, 0, 240);
}

/**
 * Kop (headlineveld) en openingszin (eerste zin body) voor merkbijdrage-cap v1.10.
 *
 * @return array{kop: string, opening: string}
 */
function ce_hba_resolve_kop_en_openingszin(string $postText, ?string $headline = null): array
{
    $postText = trim($postText);
    $headline = trim($headline ?? '');
    $body = $postText;

    if (str_contains($postText, "\n\n")) {
        $parts = preg_split("/\n\n/u", $postText, 2);
        $body = trim($parts[1] ?? $postText);
        if ($headline === '') {
            $headline = trim($parts[0] ?? '');
        }
    }

    $kop = $headline;
    if ($kop === '' || preg_match('/^HBA-test\s*:/iu', $kop)) {
        $kop = ce_hba_first_sentence($body !== '' ? $body : $postText);
    }

    if ($kop !== '' && $body !== '') {
        $bodyTrim = ltrim($body);
        $kopNorm = ce_hba_normalize_text_for_citation_match($kop);
        $firstNorm = ce_hba_normalize_text_for_citation_match(ce_hba_first_sentence($bodyTrim));
        if ($kopNorm !== '' && $kopNorm === $firstNorm) {
            $firstLen = mb_strlen(ce_hba_first_sentence($bodyTrim));
            $body = trim(mb_substr($bodyTrim, $firstLen));
            $body = ltrim($body, " \n\t");
        }
    }

    $opening = ce_hba_first_sentence($body !== '' ? $body : $postText);

    return ['kop' => $kop, 'opening' => $opening];
}

function ce_hba_flag_citation_on_kernbewering(string $citaat, string $kop, string $opening): bool
{
    $cNorm = ce_hba_normalize_text_for_citation_match($citaat);
    if ($cNorm === '') {
        return false;
    }
    foreach ([$kop, $opening] as $target) {
        $target = trim($target);
        if ($target === '') {
            continue;
        }
        $tNorm = ce_hba_normalize_text_for_citation_match($target);
        if ($tNorm === '') {
            continue;
        }
        if (str_contains($cNorm, $tNorm) || str_contains($tNorm, $cNorm)) {
            return true;
        }
    }

    return false;
}

/**
 * v1.10: proof_gap of foe_vervaging op kop/openingszin → brand_contribution ≤ 5 (PHP, niet prompt).
 *
 * @return array{tool: array<string, mixed>, cap: ?array<string, mixed>}
 */
function ce_hba_apply_brand_contribution_kernbewering_cap(
    array $tool,
    string $postText,
    ?string $headline = null,
): array {
    if (($tool['status'] ?? '') !== 'scored') {
        return ['tool' => $tool, 'cap' => null];
    }
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : null;
    if ($scores === null) {
        return ['tool' => $tool, 'cap' => null];
    }
    $risk = is_array($scores['architecture_risk'] ?? null) ? $scores['architecture_risk'] : null;
    $flags = is_array($risk['flags'] ?? null) ? $risk['flags'] : [];
    $citaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    $zones = ce_hba_resolve_kop_en_openingszin($postText, $headline);

    $triggerFlag = null;
    $triggerCitaat = '';
    foreach (['proof_gap', 'foe_vervaging'] as $flagKey) {
        if (!in_array($flagKey, $flags, true)) {
            continue;
        }
        $citaat = trim((string) ($citaten[$flagKey] ?? ''));
        if ($citaat === '') {
            continue;
        }
        if (ce_hba_flag_citation_on_kernbewering($citaat, $zones['kop'], $zones['opening'])) {
            $triggerFlag = $flagKey;
            $triggerCitaat = $citaat;
            break;
        }
    }

    if ($triggerFlag === null) {
        return ['tool' => $tool, 'cap' => null];
    }

    $bc = is_array($scores['brand_contribution'] ?? null) ? $scores['brand_contribution'] : null;
    if ($bc === null) {
        return ['tool' => $tool, 'cap' => null];
    }
    $before = isset($bc['score']) && is_numeric($bc['score']) ? (float) $bc['score'] : null;
    if ($before === null || $before <= 5.0) {
        return [
            'tool' => $tool,
            'cap' => [
                'flag' => $triggerFlag,
                'citaat' => $triggerCitaat,
                'kop' => $zones['kop'],
                'opening' => $zones['opening'],
                'from' => $before,
                'to' => $before,
                'applied' => false,
            ],
        ];
    }

    $bc['score'] = 5.0;
    $scores['brand_contribution'] = $bc;
    $tool['scores'] = $scores;

    return [
        'tool' => $tool,
        'cap' => [
            'flag' => $triggerFlag,
            'citaat' => $triggerCitaat,
            'kop' => $zones['kop'],
            'opening' => $zones['opening'],
            'from' => $before,
            'to' => 5.0,
            'applied' => true,
        ],
    ];
}

function ce_hba_citation_in_first_fraction(string $postText, string $citaat, float $fraction): bool
{
    $postText = trim($postText);
    $citaat = trim($citaat);
    if ($postText === '' || $citaat === '') {
        return false;
    }
    $postNorm = ce_hba_normalize_text_for_citation_match($postText);
    $cNorm = ce_hba_normalize_text_for_citation_match($citaat);
    if ($cNorm === '') {
        return false;
    }
    $pos = mb_strpos($postNorm, $cNorm);
    if ($pos === false) {
        return false;
    }
    $len = mb_strlen($postNorm);
    if ($len === 0) {
        return false;
    }

    return ($pos / $len) <= max(0.05, min(0.95, $fraction));
}

/**
 * v1.10.1: proof_gap in vroege kern (langere posts) → brand_contribution ≤ 6; korte posts (bijv. post 1) uitgezonderd.
 *
 * @return array{tool: array<string, mixed>, cap: ?array<string, mixed>}
 */
function ce_hba_apply_brand_contribution_proof_gap_early_cap(array $tool, string $postText): array
{
    if (($tool['status'] ?? '') !== 'scored') {
        return ['tool' => $tool, 'cap' => null];
    }
    $postText = trim($postText);
    if (mb_strlen($postText) < 350) {
        return ['tool' => $tool, 'cap' => null];
    }
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : null;
    if ($scores === null) {
        return ['tool' => $tool, 'cap' => null];
    }
    $risk = is_array($scores['architecture_risk'] ?? null) ? $scores['architecture_risk'] : null;
    $flags = is_array($risk['flags'] ?? null) ? $risk['flags'] : [];
    if (!in_array('proof_gap', $flags, true)) {
        return ['tool' => $tool, 'cap' => null];
    }
    $citaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    $citaat = trim((string) ($citaten['proof_gap'] ?? ''));
    if ($citaat === '' || !ce_hba_citation_in_first_fraction($postText, $citaat, 0.55)) {
        return ['tool' => $tool, 'cap' => null];
    }

    $bc = is_array($scores['brand_contribution'] ?? null) ? $scores['brand_contribution'] : null;
    if ($bc === null) {
        return ['tool' => $tool, 'cap' => null];
    }
    $before = isset($bc['score']) && is_numeric($bc['score']) ? (float) $bc['score'] : null;
    if ($before === null || $before <= 6.0) {
        return [
            'tool' => $tool,
            'cap' => [
                'flag' => 'proof_gap',
                'citaat' => $citaat,
                'from' => $before,
                'to' => $before,
                'applied' => false,
            ],
        ];
    }

    $bc['score'] = 6.0;
    $scores['brand_contribution'] = $bc;
    $tool['scores'] = $scores;

    return [
        'tool' => $tool,
        'cap' => [
            'flag' => 'proof_gap',
            'citaat' => $citaat,
            'from' => $before,
            'to' => 6.0,
            'applied' => true,
        ],
    ];
}

/** Detecteert verkeerde calibration-data (bv. salade/Buur bij een tech-klant). */
function ce_hba_narrative_looks_corrupted(array $narrative, array $clientRow): bool
{
    $narrativeBlob = mb_strtolower(implode(' ', array_filter([
        (string) ($narrative['doel'] ?? ''),
        (string) ($narrative['foe'] ?? ''),
        (string) ($narrative['mechanisme'] ?? ''),
        (string) ($narrative['distinctiviteit'] ?? ''),
        (string) ($narrative['geloofwaardigheidstest'] ?? ''),
        (string) ($narrative['wat_bewust_niet'] ?? ''),
        json_encode(ce_hba_decode_json_list($narrative['bewijsvelden'] ?? []), JSON_UNESCAPED_UNICODE),
    ])));

    $foodMarkers = ['salade', 'groente', 'gezond', 'bakker', 'broodje', 'healthy'];
    $hasFoodNarrative = false;
    foreach ($foodMarkers as $marker) {
        if (str_contains($narrativeBlob, $marker)) {
            $hasFoodNarrative = true;
            break;
        }
    }
    if (!$hasFoodNarrative) {
        return false;
    }

    $clientBlob = mb_strtolower(implode(' ', [
        (string) ($clientRow['name'] ?? ''),
        (string) ($clientRow['brand_goal'] ?? ''),
        (string) ($clientRow['archetype'] ?? ''),
        (string) ($clientRow['adversary'] ?? ''),
    ]));
    foreach ($foodMarkers as $marker) {
        if (str_contains($clientBlob, $marker)) {
            return false;
        }
    }

    return true;
}

function ce_hba_infer_archetype_from_client_text(string $raw): string
{
    require_once __DIR__ . '/hba-v2-repository.php';
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
    if (str_contains($lower, 'begrijpt') && (str_contains($lower, 'organisatie') || str_contains($lower, 'kennis'))) {
        return 'Wijze';
    }

    return ce_hba_v2_map_archetype($raw);
}

/** @param array<string, mixed>|null $current @return array<string, mixed> */
function ce_hba_build_narrative_repair_from_client(PDO $pdo, string $clientId, ?array $current = null): array
{
    $stmt = $pdo->prepare(
        'SELECT name, archetype, brand_goal, adversary, writing_guide, default_platform
         FROM clients WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($client)) {
        throw new RuntimeException('Client niet gevonden');
    }

    $current = $current ?? ce_fetch_current_brand_narrative($pdo, $clientId) ?? [];

    $clientArchetype = trim((string) ($client['archetype'] ?? ''));
    $brandGoal = trim((string) ($client['brand_goal'] ?? ''));
    $adversary = trim((string) ($client['adversary'] ?? ''));
    $writingGuide = trim((string) ($client['writing_guide'] ?? ''));

    $doel = ce_hba_first_sentence($brandGoal);
    if (
        mb_strlen($doel) < 20
        || preg_match('/\b(zag|zagen|patroon vanuit|invalshoek|ontstaan)\b/ui', $doel)
    ) {
        $doel = 'Organisaties laten groeien zonder kennis, keuzes en identiteit in enkelen te laten — door eerst identiteit, kennis en werkwijze expliciet te maken vóór AI en content.';
    }

    $foe = $adversary !== '' ? ce_hba_first_sentence($adversary) : '';
    if (mb_strlen($foe) < 20) {
        $foe = $adversary;
    }
    if (mb_strlen($foe) < 20) {
        $foe = 'Organisaties die groeien terwijl kennis, keuzes en identiteit in de hoofden van een paar mensen blijven zitten.';
    }

    $spanning = 'Groei maakt zichtbaar wat niet is vastgelegd: kennis, keuzes en identiteit die nog in enkelen zitten, raken onder druk naarmate de organisatie groter wordt.';

    $mechanisme = 'Eerst identiteit, kennis en werkwijze van de organisatie expliciet vastleggen; pas daarna AI en content die herkenbaar blijft bij groei.';
    $distinctiviteit = ce_hba_first_sentence($clientArchetype);
    if (mb_strlen($distinctiviteit) < 20) {
        $distinctiviteit = 'Begrijpen vóór bouwen: identiteit, kennis en werkwijze zijn het vertrekpunt — niet de technologie.';
    }

    $isRelevantExample = static fn (string $text): bool => preg_match('/organisatie|identiteit|kennis|AI|herkenbaar/i', $text) === 1;

    $goede = array_values(array_filter(
        ce_hba_decode_json_list($current['goede_voorbeelden'] ?? []),
        static fn ($item) => is_string($item) && trim($item) !== '' && $isRelevantExample($item),
    ));
    $zwakke = array_values(array_filter(
        ce_hba_decode_json_list($current['zwakke_voorbeelden'] ?? []),
        static fn ($item) => is_string($item) && trim($item) !== '' && $isRelevantExample($item),
    ));

    $defaultGoede = [
        'Voordat AI iets doet, leggen we vast wie jullie zijn, hoe jullie denken en wat jullie weten. Zodat de organisatie kan groeien zonder zichzelf te verliezen.',
        'Voordat AI iets doet, leggen we vast wie jullie zijn, hoe jullie denken en wat jullie weten. Zodat groei niet betekent dat die kennis verdwijnt.',
        'Voordat AI iets doet in jouw organisatie, leggen we vast wie jullie zijn, hoe jullie denken en wat jullie weten. Zodat groei niet ten koste gaat van herkenbaarheid.',
    ];
    $defaultZwakke = [
        'Wij helpen organisaties slimmer werken met AI. Meer efficiency, minder gedoe — zodat jij je kunt focussen op wat echt belangrijk is.',
        'Wij helpen organisaties slimmer werken met AI en zetten jouw bedrijf klaar voor de toekomst.',
        'Wij helpen organisaties slimmer werken met AI-oplossingen die aansluiten bij jouw bedrijfsdoelen en processen.',
    ];
    while (count($goede) < 3) {
        $goede[] = $defaultGoede[count($goede) % 3];
    }
    while (count($zwakke) < 3) {
        $zwakke[] = $defaultZwakke[count($zwakke) % 3];
    }
    $goede = array_slice($goede, 0, 3);
    $zwakke = array_slice($zwakke, 0, 3);

    $tone = $writingGuide !== ''
        ? $writingGuide
        : 'Kort, direct, geen jargon. Begin bij het probleem dat de lezer herkent, niet bij de oplossing.';

    $defaultPlatforms = ce_hba_decode_json_list($current['default_platforms'] ?? []);
    if ($defaultPlatforms === []) {
        $platform = trim((string) ($client['default_platform'] ?? 'linkedin'));
        $defaultPlatforms = $platform !== '' ? [$platform] : ['linkedin'];
    }

    return [
        'archetype' => ce_hba_infer_archetype_from_client_text($clientArchetype),
        'doel' => $doel,
        'foe' => $foe,
        'spanning' => $spanning,
        'mechanisme' => $mechanisme,
        'distinctiviteit' => $distinctiviteit,
        'geloofwaardigheidstest' => 'Zeggen we nee tegen AI zonder merkfundament? Vermijden we generieke efficiency-beloftes? Is de keten identiteit → doel → tegenkracht zichtbaar?',
        'verboden_logica' => [
            'Meer AI-output zonder herkenbaar merk',
            'Efficiency-first zonder identiteitsfundament',
            'Generieke slimmer-werken-met-AI claims',
        ],
        'bewijsvelden' => [
            'Identiteit en kennis expliciet vóór automatisering',
            'Brandflocks als fundament-laag voor herkenbare content',
            'Groei zonder verlies van herkenbaarheid',
        ],
        'tone_of_behaviour' => $tone,
        'woorden_die_passen' => ['identiteit', 'kennis', 'fundament', 'herkenbaar', 'organisatie'],
        'woorden_die_niet_passen' => ['slimmer werken', 'oplossingen op maat', 'de toekomst', 'efficiency'],
        'goede_voorbeelden' => $goede,
        'zwakke_voorbeelden' => $zwakke,
        'contextprofiel' => 'b2b_complex_sale',
        'branche_code' => 'tech_saas',
        'identity_intro' => ce_hba_first_sentence($clientArchetype),
        'wat_bewust_niet' => 'AI en content zonder eerst identiteit, kennis en werkwijze expliciet te maken',
        'default_platforms' => $defaultPlatforms,
        'target_post_volume_per_month' => isset($current['target_post_volume_per_month'])
            ? (int) $current['target_post_volume_per_month']
            : null,
    ];
}

/** @return array<string, mixed> */
function ce_hba_repair_brand_narrative_from_client(
    PDO $pdo,
    string $clientId,
    bool $force = false,
    bool $dryRun = false,
): array {
    $stmt = $pdo->prepare(
        'SELECT name, archetype, brand_goal, adversary, writing_guide FROM clients WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($client)) {
        return ['ok' => false, 'error' => 'Client niet gevonden'];
    }

    $current = ce_fetch_current_brand_narrative($pdo, $clientId);
    $corrupted = is_array($current) && ce_hba_narrative_looks_corrupted($current, $client);
    $missing = is_array($current) ? ce_brand_narrative_missing_fields($current) : CE_HBA_NARRATIVE_FIELDS;
    $needsRepair = !is_array($current) || $corrupted || $missing !== [];

    if (!$needsRepair && !$force) {
        return [
            'ok' => true,
            'repaired' => false,
            'reason' => 'already_complete',
            'corrupted' => false,
            'missing' => [],
            'completeness' => ce_brand_narrative_completeness($pdo, $clientId),
        ];
    }

    try {
        $proposed = ce_hba_build_narrative_repair_from_client($pdo, $clientId, $current);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $stillMissing = ce_brand_narrative_missing_fields($proposed);
    if ($stillMissing !== []) {
        return [
            'ok' => false,
            'error' => 'Repair levert nog incomplete narrative op',
            'missing' => $stillMissing,
            'proposed' => $proposed,
        ];
    }

    $beforeSummary = is_array($current) ? [
        'archetype' => (string) ($current['archetype'] ?? ''),
        'doel' => (string) ($current['doel'] ?? ''),
        'foe' => mb_substr((string) ($current['foe'] ?? ''), 0, 120),
        'branche_code' => (string) ($current['branche_code'] ?? ''),
        'missing' => $missing,
        'corrupted' => $corrupted,
    ] : null;

    if ($dryRun) {
        return [
            'ok' => true,
            'repaired' => false,
            'dryRun' => true,
            'corrupted' => $corrupted,
            'missing' => $missing,
            'before' => $beforeSummary,
            'proposed' => $proposed,
            'completenessAfter' => [
                'complete' => true,
                'filled' => count(CE_HBA_NARRATIVE_FIELDS),
                'total' => count(CE_HBA_NARRATIVE_FIELDS),
                'missing' => [],
            ],
        ];
    }

    $saved = ce_save_brand_narrative($pdo, $clientId, $proposed, 'admin-hba-narrative-repair');

    return [
        'ok' => true,
        'repaired' => true,
        'corrupted' => $corrupted,
        'missingBefore' => $missing,
        'before' => $beforeSummary,
        'after' => [
            'id' => (string) ($saved['id'] ?? ''),
            'version' => (int) ($saved['version'] ?? 0),
            'archetype' => (string) ($proposed['archetype'] ?? ''),
            'doel' => (string) ($proposed['doel'] ?? ''),
            'foe' => mb_substr((string) ($proposed['foe'] ?? ''), 0, 120),
            'branche_code' => (string) ($proposed['branche_code'] ?? ''),
        ],
        'completeness' => ce_brand_narrative_completeness($pdo, $clientId),
    ];
}

/** Bouwt narrative voor scoring — volledige rij, of synthetisch uit architecture context (A/B-test). */
function ce_hba_resolve_narrative_for_scoring(PDO $pdo, string $clientId): ?array
{
    require_once __DIR__ . '/hba-v2-repository.php';

    $row = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (is_array($row) && ce_brand_narrative_missing_fields($row) === []) {
        return $row;
    }

    if (!ce_hba_v2_architecture_ready($pdo, $clientId)) {
        return null;
    }

    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    $base = is_array($row) ? $row : [];

    $verboden = ce_hba_decode_json_list($base['verboden_logica'] ?? []);
    $watBewustNiet = trim((string) ($base['wat_bewust_niet'] ?? $ctx['wat_bewust_niet'] ?? ''));
    if ($verboden === [] && $watBewustNiet !== '') {
        $verboden = [$watBewustNiet];
    }

    $regels = trim((string) ($base['geloofwaardigheidstest'] ?? $ctx['regels'] ?? ''));
    $distinct = trim((string) ($base['distinctiviteit'] ?? $ctx['distinctiviteit'] ?? ''));

    return array_merge($base, [
        'archetype' => trim((string) ($base['archetype'] ?? $ctx['archetype'] ?? '')),
        'doel' => trim((string) ($base['doel'] ?? $ctx['doel'] ?? '')),
        'foe' => trim((string) ($base['foe'] ?? $ctx['foe'] ?? '')),
        'spanning' => trim((string) ($base['spanning'] ?? $ctx['foe'] ?? '')),
        'mechanisme' => trim((string) ($base['mechanisme'] ?? $distinct)),
        'distinctiviteit' => $distinct,
        'wat_bewust_niet' => $watBewustNiet,
        'geloofwaardigheidstest' => $regels,
        'verboden_logica' => $verboden,
        'bewijsvelden' => ce_hba_decode_json_list($base['bewijsvelden'] ?? []) ?: ($distinct !== '' ? [$distinct] : []),
        'tone_of_behaviour' => trim((string) ($base['tone_of_behaviour'] ?? $ctx['tone_of_behaviour'] ?? '')),
        'woorden_die_passen' => ce_hba_decode_json_list($base['woorden_die_passen'] ?? []),
        'woorden_die_niet_passen' => ce_hba_decode_json_list($base['woorden_die_niet_passen'] ?? []),
        'goede_voorbeelden' => ce_hba_decode_json_list($base['goede_voorbeelden'] ?? []),
        'zwakke_voorbeelden' => ce_hba_decode_json_list($base['zwakke_voorbeelden'] ?? []),
        'contextprofiel' => trim((string) ($base['contextprofiel'] ?? 'groeiend')),
    ]);
}

/** @return array<string, mixed>|null */
function ce_hba_fetch_score_for_article(PDO $pdo, string $articleId, string $narrativeId): ?array
{
    ce_ensure_hba_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_scores')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM hba_scores WHERE post_id = :post_id AND brand_narrative_id = :narrative_id ORDER BY scored_at DESC LIMIT 1'
    );
    $stmt->execute(['post_id' => $articleId, 'narrative_id' => $narrativeId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @param array<string, mixed> $row */
function ce_hba_score_dto(array $row): array
{
    $contentrol = trim((string) ($row['contentrol'] ?? ''));
    if ($contentrol === '' && trim((string) ($row['diagnose'] ?? '')) !== '') {
        $inferred = ce_hba_infer_contentrol_from_text((string) ($row['diagnose'] ?? ''));
        if ($inferred !== null) {
            $contentrol = $inferred;
        }
    }

    return [
        'id' => (string) ($row['id'] ?? ''),
        'articleId' => (string) ($row['post_id'] ?? ''),
        'brandNarrativeId' => (string) ($row['brand_narrative_id'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'contentrol' => $contentrol !== '' ? $contentrol : null,
        'contentrolMotivation' => $row['contentrol_motivation'] ?? null,
        'brandContribution' => isset($row['brand_contribution_score']) ? (float) $row['brand_contribution_score'] : null,
        'brandContributionDimensions' => [
            'archetypeFit' => isset($row['bc_archetype_fit']) ? (float) $row['bc_archetype_fit'] : null,
            'doelFit' => isset($row['bc_doel_fit']) ? (float) $row['bc_doel_fit'] : null,
            'tegenkrachtFit' => isset($row['bc_tegenkracht_fit']) ? (float) $row['bc_tegenkracht_fit'] : null,
            'spanning' => isset($row['bc_spanning']) ? (float) $row['bc_spanning'] : null,
            'mechanismeProof' => isset($row['bc_mechanisme_proof']) ? (float) $row['bc_mechanisme_proof'] : null,
            'distinctiviteit' => isset($row['bc_distinctiviteit']) ? (float) $row['bc_distinctiviteit'] : null,
        ],
        'brandConsistency' => isset($row['brand_consistency_score']) ? (float) $row['brand_consistency_score'] : null,
        'brandConsistencyNotes' => $row['brand_consistency_notes'] ?? null,
        'architectureRisk' => isset($row['architecture_risk_score']) ? (float) $row['architecture_risk_score'] : null,
        'architectureIntegrity' => ce_hba_architecture_integrity_from_risk($row['architecture_risk_score'] ?? null),
        'architectureRiskFlags' => ce_hba_decode_json_list($row['architecture_risk_flags'] ?? []),
        'longTermValue' => isset($row['long_term_value']) ? (float) $row['long_term_value'] : null,
        'shortTermValue' => isset($row['short_term_value']) ? (float) $row['short_term_value'] : null,
        'diagnose' => $row['diagnose'] ?? null,
        'verbeteradvies' => $row['verbeteradvies'] ?? null,
        'needsReview' => !empty($row['needs_review']),
        'kernformuleringHit' => isset($row['kernformulering_hit']) ? (int) $row['kernformulering_hit'] : null,
        'scoredAt' => (string) ($row['scored_at'] ?? ''),
        'modelUsed' => (string) ($row['model_used'] ?? ''),
        'promptVersion' => (string) ($row['prompt_version'] ?? ''),
        ...ce_hba_hoofdoordeel_dto_fields($row['raw_llm_response'] ?? null),
    ];
}

/** A8: klant-API — geen HBA-contentrol; alleen planningsrol (angle) in de UI. */
function ce_hba_score_dto_for_api(PDO $pdo, array $row): array
{
    $dto = ce_hba_score_dto($row);
    if (!ce_request_is_tenant_scoped($pdo)) {
        return $dto;
    }
    unset($dto['contentrol'], $dto['contentrolMotivation']);

    return $dto;
}

/** @return array{hoofdoordeel: ?string, hoofdoordeelKlantlabel: ?string, hoofdoordeelKlantzin: ?string, hoofdoordeelGecorrigeerd: ?string, hoofdoordeelVolgtUitFlags: ?bool, kernCategorietaal: ?string, constraintBreachVerificatie: ?array<string, mixed>} */
function ce_hba_hoofdoordeel_dto_fields(mixed $raw): array
{
    require_once __DIR__ . '/hba-prompt.php';
    $meta = ce_hba_hoofdoordeel_from_raw($raw);
    $hoofd = $meta['hoofdoordeel'];
    $verificatie = $meta['constraintBreachVerificatie'] ?? null;
    if ($hoofd === null || $hoofd === '') {
        return [
            'hoofdoordeel' => null,
            'hoofdoordeelKlantlabel' => null,
            'hoofdoordeelKlantzin' => null,
            'hoofdoordeelGecorrigeerd' => null,
            'hoofdoordeelVolgtUitFlags' => $meta['hoofdoordeelVolgtUitFlags'] ?? null,
            'kernCategorietaal' => $meta['kernCategorietaal'] ?? null,
            'constraintBreachVerificatie' => $verificatie,
        ];
    }

    return [
        'hoofdoordeel' => $hoofd,
        'hoofdoordeelKlantlabel' => ce_hba_hoofdoordeel_klantlabel($hoofd),
        'hoofdoordeelKlantzin' => ce_hba_hoofdoordeel_klantzin($hoofd),
        'hoofdoordeelGecorrigeerd' => null,
        'hoofdoordeelVolgtUitFlags' => $meta['hoofdoordeelVolgtUitFlags'] ?? true,
        'kernCategorietaal' => $meta['kernCategorietaal'] ?? null,
        'constraintBreachVerificatie' => $verificatie,
    ];
}

/** @param array<string, mixed> $articleRow @return array<string, mixed> */
function ce_hba_article_api_extras(PDO $pdo, array $articleRow): array
{
    ce_ensure_hba_schema($pdo);

    $clientId = trim((string) ($articleRow['client_id'] ?? ''));
    if ($clientId === '') {
        $slotId = trim((string) ($articleRow['slot_id'] ?? ''));
        if ($slotId !== '') {
            $clientId = ce_client_id_for_slot($pdo, $slotId);
        }
    }

    $override = trim((string) ($articleRow['hba_contentrol_override'] ?? ''));
    $tenantScoped = ce_request_is_tenant_scoped($pdo);

    $extras = [
        'hbaContentrolOverride' => !$tenantScoped && $override !== '' ? $override : null,
        'hideHbaContentrol' => $tenantScoped,
        'hbaScore' => null,
        'brandNarrativeCompleteness' => null,
        'hbaScoreDisplayMode' => 'band',
    ];

    if ($clientId === '') {
        return $extras;
    }

    $extras['brandNarrativeCompleteness'] = ce_brand_narrative_completeness($pdo, $clientId);
    require_once __DIR__ . '/hba-determinism-repository.php';
    $extras['hbaScoreDisplayMode'] = ce_hba_client_score_display_mode($pdo, $clientId);

    $articleId = trim((string) ($articleRow['id'] ?? ''));
    $narrativeRow = ce_fetch_current_brand_narrative($pdo, $clientId);
    if ($articleId === '' || !$narrativeRow) {
        return $extras;
    }

    require_once __DIR__ . '/hba-v2-repository.php';

    $existing = ce_hba_fetch_score_for_article($pdo, $articleId, (string) ($narrativeRow['id'] ?? ''));
    if ($existing && ce_hba_v2_score_row_matches_architecture($pdo, $clientId, $existing)) {
        $scoreDto = ce_hba_score_dto_for_api($pdo, $existing);
        $narrativeComplete = ce_brand_narrative_completeness($pdo, $clientId)['complete'] ?? false;
        $scoreStatus = (string) ($scoreDto['status'] ?? '');
        if (!$narrativeComplete || $scoreStatus !== 'narrative_incomplete') {
            $extras['hbaScore'] = $scoreDto;
        }
    }

    $hint = ce_hba_v2_fetch_latest_hint($pdo, $articleId, $clientId);
    if ($hint) {
        $extras['hbaHint'] = $hint;
    }

    $extras = array_merge($extras, ce_hba_v2_theme_positioning_meta($pdo, $clientId));

    return $extras;
}

/**
 * Normaliseer LLM-tooloutput naar het nested scores-formaat (kit §6).
 *
 * @param array<string, mixed> $tool
 * @return array<string, mixed>
 */
function ce_hba_normalize_score_tool(array $tool): array
{
    if (!is_array($tool['scores'] ?? null)) {
        return $tool;
    }

    $scores = $tool['scores'];

    foreach (['brand_contribution', 'brand_consistency', 'architecture_risk'] as $key) {
        $value = $scores[$key] ?? null;
        if (is_numeric($value)) {
            if ($key === 'brand_contribution') {
                $scores[$key] = ['score' => (float) $value, 'dimensions' => []];
            } elseif ($key === 'architecture_risk') {
                $scores[$key] = ['score' => (float) $value, 'flags' => []];
            } else {
                $scores[$key] = ['score' => (float) $value, 'notes' => ''];
            }
        }
    }

    $tool['scores'] = $scores;

    return $tool;
}

/** @param array<string, mixed> $tool @param array<string, mixed> $raw */
function ce_hba_insert_score(
    PDO $pdo,
    string $articleId,
    string $narrativeId,
    array $tool,
    array $raw,
    ?int $inputTokens,
    ?int $outputTokens,
    bool $needsReview,
    ?string $modelUsed = null,
    ?int $hygieneListVersion = null,
    string $origin = 'app',
    ?string $linkedinPostId = null,
    ?int $architectureVersion = null,
): array {
    ce_ensure_hba_schema($pdo);
    ce_ensure_hba_contentflow_schema($pdo);
    $id = sprintf(
        '%s-%s-%s-%s-%s',
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(6)),
    );

    $status = (string) ($tool['status'] ?? 'narrative_incomplete');
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : null;
    $bc = is_array($scores['brand_contribution'] ?? null) ? $scores['brand_contribution'] : null;
    $dims = is_array($bc['dimensions'] ?? null) ? $bc['dimensions'] : [];
    $consistency = is_array($scores['brand_consistency'] ?? null) ? $scores['brand_consistency'] : null;
    $risk = is_array($scores['architecture_risk'] ?? null) ? $scores['architecture_risk'] : null;

    $kernHit = $tool['kernformulering_hit'] ?? null;
    if ($kernHit !== null) {
        $kernHit = (int) $kernHit;
        if ($kernHit <= 0) {
            $kernHit = null;
        }
    }

    $hasKernColumn = ce_table_has_column($pdo, 'hba_scores', 'kernformulering_hit');
    $hasHygieneColumn = ce_table_has_column($pdo, 'hba_scores', 'hygiene_list_version');
    $hasOriginColumn = ce_table_has_column($pdo, 'hba_scores', 'origin');
    $hasLinkedinColumn = ce_table_has_column($pdo, 'hba_scores', 'linkedin_post_id');
    $kernSql = $hasKernColumn ? ', kernformulering_hit' : '';
    $kernVal = $hasKernColumn ? ', :kernformulering_hit' : '';
    $hygieneSql = $hasHygieneColumn ? ', hygiene_list_version' : '';
    $hygieneVal = $hasHygieneColumn ? ', :hygiene_list_version' : '';
    $originSql = $hasOriginColumn ? ', origin' : '';
    $originVal = $hasOriginColumn ? ', :origin' : '';
    $linkedinSql = $hasLinkedinColumn ? ', linkedin_post_id' : '';
    $linkedinVal = $hasLinkedinColumn ? ', :linkedin_post_id' : '';
    $hasArchVersion = ce_table_has_column($pdo, 'hba_scores', 'architecture_version');
    $archSql = $hasArchVersion ? ', architecture_version' : '';
    $archVal = $hasArchVersion ? ', :architecture_version' : '';

    $stmt = $pdo->prepare(
        'INSERT INTO hba_scores (
            id, post_id, brand_narrative_id, status, contentrol, contentrol_motivation,
            bc_archetype_fit, bc_doel_fit, bc_tegenkracht_fit, bc_spanning, bc_mechanisme_proof, bc_distinctiviteit,
            brand_contribution_score, brand_consistency_score, brand_consistency_notes,
            architecture_risk_score, architecture_risk_flags, long_term_value, short_term_value,
            diagnose, verbeteradvies, raw_llm_response, input_tokens, output_tokens,
            model_used, prompt_version, needs_review' . $kernSql . $hygieneSql . $originSql . $linkedinSql . $archSql . '
        ) VALUES (
            :id, :post_id, :brand_narrative_id, :status, :contentrol, :contentrol_motivation,
            :bc_archetype_fit, :bc_doel_fit, :bc_tegenkracht_fit, :bc_spanning, :bc_mechanisme_proof, :bc_distinctiviteit,
            :brand_contribution_score, :brand_consistency_score, :brand_consistency_notes,
            :architecture_risk_score, :architecture_risk_flags, :long_term_value, :short_term_value,
            :diagnose, :verbeteradvies, :raw_llm_response, :input_tokens, :output_tokens,
            :model_used, :prompt_version, :needs_review' . $kernVal . $hygieneVal . $originVal . $linkedinVal . $archVal . '
        )'
    );
    $params = [
        'id' => $id,
        'post_id' => $articleId,
        'brand_narrative_id' => $narrativeId,
        'status' => $status,
        'contentrol' => $tool['contentrol'] ?? null,
        'contentrol_motivation' => $tool['contentrol_motivation'] ?? null,
        'bc_archetype_fit' => $dims['archetype_fit'] ?? null,
        'bc_doel_fit' => $dims['doel_fit'] ?? null,
        'bc_tegenkracht_fit' => $dims['tegenkracht_fit'] ?? null,
        'bc_spanning' => $dims['spanning'] ?? null,
        'bc_mechanisme_proof' => $dims['mechanisme_proof'] ?? null,
        'bc_distinctiviteit' => $dims['distinctiviteit'] ?? null,
        'brand_contribution_score' => $bc['score'] ?? null,
        'brand_consistency_score' => $consistency['score'] ?? null,
        'brand_consistency_notes' => $consistency['notes'] ?? null,
        'architecture_risk_score' => $risk['score'] ?? null,
        'architecture_risk_flags' => json_encode($risk['flags'] ?? [], JSON_UNESCAPED_UNICODE),
        'long_term_value' => $scores['long_term_value'] ?? null,
        'short_term_value' => $scores['short_term_value'] ?? null,
        'diagnose' => $tool['diagnose'] ?? null,
        'verbeteradvies' => $tool['verbeteradvies'] ?? null,
        'raw_llm_response' => json_encode($raw, JSON_UNESCAPED_UNICODE),
        'input_tokens' => $inputTokens,
        'output_tokens' => $outputTokens,
        'model_used' => $modelUsed !== null && $modelUsed !== ''
            ? $modelUsed
            : ce_hba_model_for(CE_HBA_FN_CONTENT_SCORE),
        'prompt_version' => CE_HBA_PROMPT_VERSION,
        'needs_review' => $needsReview ? 1 : 0,
    ];
    if ($hasKernColumn) {
        $params['kernformulering_hit'] = $kernHit;
    }
    if ($hasHygieneColumn) {
        $params['hygiene_list_version'] = $hygieneListVersion;
    }
    if ($hasOriginColumn) {
        $params['origin'] = $origin !== '' ? $origin : 'app';
    }
    if ($hasLinkedinColumn) {
        $params['linkedin_post_id'] = $linkedinPostId;
    }
    if ($hasArchVersion) {
        $params['architecture_version'] = $architectureVersion !== null && $architectureVersion > 0
            ? $architectureVersion
            : 1;
    }
    $stmt->execute($params);

    $fetch = $pdo->prepare('SELECT * FROM hba_scores WHERE id = :id LIMIT 1');
    $fetch->execute(['id' => $id]);
    $row = $fetch->fetch();

    return ce_hba_score_dto_for_api($pdo, is_array($row) ? $row : []);
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_hba_score_article(PDO $pdo, array $body): array
{
    ce_ensure_hba_schema($pdo);

    $articleId = trim((string) ($body['article_id'] ?? $body['articleId'] ?? ''));
    $force = !empty($body['force']);
    if ($articleId === '') {
        throw new InvalidArgumentException('article_id is verplicht');
    }

    $article = ce_fetch_article_by_id($pdo, $articleId);
    if (!$article) {
        throw new RuntimeException('Artikel niet gevonden');
    }

    $slotId = (string) ($article['slot_id'] ?? '');
    $clientId = ce_client_id_for_slot($pdo, $slotId);
    if ($clientId === '') {
        throw new RuntimeException('Client niet gevonden voor artikel');
    }

    $gate = ce_client_hba_scoring_gate($pdo, $clientId);
    if (empty($gate['allowed'])) {
        return [
            'ok' => true,
            'status' => (string) ($gate['status'] ?? 'disabled'),
            'message' => $gate['message'] ?? null,
            'missingOrInconsistent' => $gate['missingOrInconsistent'] ?? null,
            'scores' => null,
            'score' => null,
        ];
    }

    $narrativeRow = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!is_array($narrativeRow)) {
        return [
            'ok' => true,
            'status' => 'narrative_incomplete',
            'message' => 'Merkarchitectuur nog niet compleet voor scoring',
            'missingOrInconsistent' => CE_HBA_NARRATIVE_FIELDS,
            'scores' => null,
            'score' => null,
        ];
    }
    $narrativeForPrompt = $narrativeRow;
    ce_brand_narrative_enrich_scoring_fields($narrativeForPrompt);
    $narrativeRow = $narrativeForPrompt;
    $narrativeId = (string) ($narrativeRow['id'] ?? '');
    require_once __DIR__ . '/hba-v2-repository.php';
    if (!$force) {
        $existing = ce_hba_fetch_score_for_article($pdo, $articleId, $narrativeId);
        if ($existing && ce_hba_v2_score_row_matches_architecture($pdo, $clientId, $existing)) {
            $existingStatus = (string) ($existing['status'] ?? 'scored');
            $narrativeComplete = ce_brand_narrative_is_complete($pdo, $clientId);
            if (!$narrativeComplete || $existingStatus !== 'narrative_incomplete') {
                return [
                    'ok' => true,
                    'status' => $existingStatus,
                    'cached' => true,
                    'score' => ce_hba_score_dto_for_api($pdo, $existing),
                ];
            }
        }
    }

    $postText = ce_hba_article_post_text($article);
    if ($postText === '') {
        throw new RuntimeException('Geen posttekst om te beoordelen');
    }
    $scoreHeadline = ce_article_headline($article);

    $platform = strtolower(trim((string) ($article['platform_final'] ?? $article['platform'] ?? 'website_blog')));
    $contentrolOverride = trim((string) ($article['hba_contentrol_override'] ?? ''));

    require_once __DIR__ . '/hygiene-repository.php';
    $hygieneContext = ce_hygiene_scoring_context(
        $pdo,
        trim((string) ($narrativeRow['contextprofiel'] ?? '')),
    );
    $prompt = ce_hba_build_system_prompt($narrativeRow, $platform, 'article', $postText, $hygieneContext, $pdo);
    if ($contentrolOverride !== '') {
        $prompt .= "\n\nContentrol override (gebruik als startpunt): " . $contentrolOverride;
    }

    $llm = ce_hba_call_llm($prompt, false);
    require_once __DIR__ . '/hba-determinism-repository.php';
    if (!ce_hba_determinism_measurement_active() && ce_hba_client_score_averaging_enabled($pdo, $clientId)) {
        $llmSecond = ce_hba_call_llm($prompt, false);
        if ($llm['ok'] && $llm['tool'] !== null && $llmSecond['ok'] && $llmSecond['tool'] !== null) {
            $llm['tool'] = ce_hba_average_normalized_tools(
                ce_hba_normalize_score_tool($llm['tool']),
                ce_hba_normalize_score_tool($llmSecond['tool']),
            );
            $llm['input_tokens'] = (int) (($llm['input_tokens'] ?? 0) + ($llmSecond['input_tokens'] ?? 0));
            $llm['output_tokens'] = (int) (($llm['output_tokens'] ?? 0) + ($llmSecond['output_tokens'] ?? 0));
        }
    }
    $needsReview = false;
    $forbiddenAdviesHits = [];
    if ($llm['ok'] && $llm['tool'] !== null) {
        $forbiddenCheck = ce_hba_score_output_forbidden_hits($llm['tool']);
        if ($forbiddenCheck['hits'] !== []) {
            $retry = ce_hba_call_llm($prompt, true);
            if ($retry['ok'] && $retry['tool'] !== null) {
                $llm = $retry;
                $forbiddenCheck = ce_hba_score_output_forbidden_hits($llm['tool']);
            }
            if ($forbiddenCheck['hits'] !== []) {
                $needsReview = true;
            }
        }
        $forbiddenAdviesHits = $forbiddenCheck['adviesHits'];
    }

    if (!$llm['ok'] || $llm['tool'] === null) {
        throw new RuntimeException($llm['error'] !== '' ? $llm['error'] : 'HBA scoring mislukt');
    }

    $tool = ce_hba_ensure_contentrol_on_tool(ce_hba_normalize_score_tool($llm['tool']));
    if (($tool['status'] ?? '') === 'scored') {
        $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : null;
        $bcScore = is_array($scores['brand_contribution'] ?? null) ? ($scores['brand_contribution']['score'] ?? null) : null;
        if ($bcScore === null) {
            throw new RuntimeException('HBA scoring onvolledig — numerieke scores ontbreken in LLM-antwoord');
        }
    }

    $finalize = ce_hba_finalize_scored_tool(
        $tool,
        $postText,
        (string) ($narrativeRow['geloofwaardigheidstest'] ?? ''),
        ce_hba_format_list($narrativeRow['verboden_logica'] ?? []),
        $scoreHeadline !== '' ? $scoreHeadline : null,
    );
    $tool = $finalize['tool'];
    if ($finalize['input_tokens'] !== null) {
        $llm['input_tokens'] = (int) (($llm['input_tokens'] ?? 0) + $finalize['input_tokens']);
    }
    if ($finalize['output_tokens'] !== null) {
        $llm['output_tokens'] = (int) (($llm['output_tokens'] ?? 0) + $finalize['output_tokens']);
    }
    // Na filter opnieuw: verbeteradvies mag vaktermen bevatten ondanks model-hoofdoordeel.
    $postFilterForbidden = ce_hba_score_output_forbidden_hits($tool);
    if ($postFilterForbidden['adviesHits'] !== []) {
        $forbiddenAdviesHits = $postFilterForbidden['adviesHits'];
        $needsReview = true;
    }
    $rawForStore = is_array($llm['raw']) ? $llm['raw'] : [];
    if ($finalize['flags_verwijderd'] !== []) {
        $rawForStore['flags_verwijderd'] = $finalize['flags_verwijderd'];
    }
    if (isset($tool['hoofdoordeel'])) {
        $rawForStore['hoofdoordeel'] = $tool['hoofdoordeel'];
    }
    unset($rawForStore['hoofdoordeel_gecorrigeerd']);
    $rawForStore['hoofdoordeel_volgt_uit_flags'] = (bool) ($finalize['hoofdoordeel_volgt_uit_flags'] ?? true);
    $rawForStore['hoofdoordeel_context'] = [
        'foe_on_kern' => (bool) ($finalize['foe_on_kern'] ?? false),
    ];
    if (isset($tool['merkbewering'])) {
        $rawForStore['merkbewering'] = $tool['merkbewering'];
    }
    if (array_key_exists('kern_categorietaal', $tool)) {
        $rawForStore['kern_categorietaal'] = $tool['kern_categorietaal'];
    }
    if ($finalize['verificatie'] !== null) {
        $rawForStore['constraint_breach_verificatie'] = $finalize['verificatie'];
    }
    if (($finalize['brand_contribution_kernbewering_cap'] ?? null) !== null) {
        $rawForStore['brand_contribution_kernbewering_cap'] = $finalize['brand_contribution_kernbewering_cap'];
    }
    if (($finalize['brand_contribution_proof_gap_early_cap'] ?? null) !== null) {
        $rawForStore['brand_contribution_proof_gap_early_cap'] = $finalize['brand_contribution_proof_gap_early_cap'];
    }
    if ($forbiddenAdviesHits !== []) {
        $rawForStore['verbeteradvies_verboden_termen'] = $forbiddenAdviesHits;
        require_once __DIR__ . '/event-log-repository.php';
        ce_log_event(
            $pdo,
            'warn',
            'hba',
            'verbeteradvies_verboden_termen',
            'Verbeteradvies bevat verboden klanttaal-termen',
            [
                'articleId' => $articleId,
                'clientId' => $clientId,
                'terms' => $forbiddenAdviesHits,
                'promptVersion' => CE_HBA_PROMPT_VERSION,
            ],
        );
    }
    $flagCitaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    if ($flagCitaten !== []) {
        $rawForStore['flag_citaten'] = $flagCitaten;
    }

    $score = ce_hba_insert_score(
        $pdo,
        $articleId,
        $narrativeId,
        $tool,
        $rawForStore,
        $llm['input_tokens'],
        $llm['output_tokens'],
        $needsReview,
        isset($llm['model']) ? (string) $llm['model'] : null,
        is_array($hygieneContext) ? ($hygieneContext['version'] ?? null) : null,
        'app',
        null,
        max(1, (int) ($narrativeRow['version'] ?? 1)),
    );

    return [
        'ok' => true,
        'status' => (string) ($tool['status'] ?? 'scored'),
        'missingOrInconsistent' => ce_hba_decode_json_list($tool['narrative_check']['missing_or_inconsistent'] ?? []),
        'score' => $score,
    ];
}

function ce_fetch_article_by_id(PDO $pdo, string $articleId): ?array
{
    if (!ce_table_exists($pdo, 'articles')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $articleId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function ce_hba_strategy_extras(PDO $pdo, string $clientId): array
{
    $row = ce_fetch_current_brand_narrative($pdo, $clientId);
    $completeness = ce_brand_narrative_completeness($pdo, $clientId);

    return [
        'brandNarrative' => $row ? ce_brand_narrative_dto($row) : null,
        'brandNarrativeCompleteness' => $completeness,
        'archetypeHints' => CE_HBA_ARCHETYPE_FOE_HINTS,
    ];
}
