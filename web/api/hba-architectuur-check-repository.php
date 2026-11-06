<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/hba-wizard-rate-limit.php';

function ce_hba_architectuur_check_ensure_schema(PDO $pdo): void
{
    ce_ensure_hba_v2_schema($pdo);

    if (!ce_table_exists($pdo, 'branche_library')) {
        return;
    }

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
                // Kolom bestaat al of geen ALTER-rechten.
            }
        }
    }

    if (ce_table_exists($pdo, 'architectuur_check_result')) {
        return;
    }

    try {
        $pdo->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS architectuur_check_result (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    kracht_triangle_oordeel VARCHAR(32) NULL,
    kracht_triangle_toelichting TEXT NULL,
    kracht_triangle_verbetering TEXT NULL,
    relevantie_tegenkracht_oordeel VARCHAR(32) NULL,
    relevantie_tegenkracht_toelichting TEXT NULL,
    relevantie_tegenkracht_verbetering TEXT NULL,
    relevantie_rol_oordeel VARCHAR(32) NULL,
    relevantie_rol_toelichting TEXT NULL,
    relevantie_rol_verbetering TEXT NULL,
    scherpte_uniciteit_oordeel VARCHAR(32) NULL,
    scherpte_uniciteit_toelichting TEXT NULL,
    scherpte_uniciteit_verbetering TEXT NULL,
    strawman_gedetecteerd TINYINT(1) NULL,
    zit_in_hygienefactoren TINYINT(1) NULL,
    regels_oordeel VARCHAR(32) NULL,
    regels_toelichting TEXT NULL,
    regels_verbetering TEXT NULL,
    totaaloordeel VARCHAR(64) NULL,
    premium_laag_aangeraden TINYINT(1) NOT NULL DEFAULT 0,
    toon_suggesties JSON NULL,
    voorbeeld_suggesties JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_architectuur_check_client (client_id),
    KEY idx_architectuur_check_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
    } catch (Throwable) {
        // Geen CREATE-rechten.
    }
}

function ce_hba_branche_code_from_label(string $label): string
{
    $slug = strtolower(trim($label));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'branche_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    return substr($slug, 0, 60);
}

/** @return array<string, mixed>|null */
function ce_hba_fetch_branche_by_code(PDO $pdo, string $code): ?array
{
    ce_hba_architectuur_check_ensure_schema($pdo);
    if ($code === '' || !ce_table_exists($pdo, 'branche_library')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM branche_library WHERE branche_code = :code LIMIT 1');
    $stmt->execute(['code' => $code]);

    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @return array<string, mixed>|null */
function ce_hba_fetch_branche_by_label(PDO $pdo, string $label): ?array
{
    ce_hba_architectuur_check_ensure_schema($pdo);
    $label = trim($label);
    if ($label === '' || !ce_table_exists($pdo, 'branche_library')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM branche_library WHERE LOWER(branche_label) = LOWER(:label) LIMIT 1'
    );
    $stmt->execute(['label' => $label]);

    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function ce_hba_branche_unique_code(PDO $pdo, string $baseCode): string
{
    $code = ce_hba_branche_code_from_label($baseCode);
    if (!ce_table_exists($pdo, 'branche_library')) {
        return $code;
    }
    $candidate = $code;
    $suffix = 2;
    while (ce_hba_fetch_branche_by_code($pdo, $candidate) !== null) {
        $candidate = substr($code, 0, 55) . '_' . $suffix;
        $suffix++;
    }

    return $candidate;
}

/** @return array<string, string> */
function ce_hba_branche_prompt_context(?array $row): array
{
    if ($row === null) {
        return [
            'branche_definitie' => '(geen branche gedefinieerd)',
            'hygienefactoren' => '(geen)',
            'categorietaal' => '(geen)',
            'markt_archetype' => '(geen)',
            'differentiatie_assen' => '(geen)',
            'branche_bron' => 'geen',
            'branche_label' => '',
            'branche_code' => '',
        ];
    }

    return [
        'branche_definitie' => trim((string) ($row['branche_definitie'] ?? '')) !== ''
            ? (string) $row['branche_definitie']
            : (string) ($row['branche_label'] ?? ''),
        'hygienefactoren' => implode(', ', ce_hba_decode_json_list($row['hygienefactoren'] ?? [])),
        'categorietaal' => implode(', ', ce_hba_decode_json_list($row['categorietaal'] ?? [])),
        'markt_archetype' => (string) ($row['markt_archetype'] ?? ''),
        'differentiatie_assen' => implode(', ', ce_hba_decode_json_list($row['differentiatie_assen'] ?? [])),
        'branche_bron' => (string) ($row['bron'] ?? 'gecureerd'),
        'branche_label' => (string) ($row['branche_label'] ?? ''),
        'branche_code' => (string) ($row['branche_code'] ?? ''),
    ];
}

/** @param array<string, mixed> $data */
function ce_hba_store_derived_branche(PDO $pdo, string $label, array $data): array
{
    ce_hba_architectuur_check_ensure_schema($pdo);
    $code = ce_hba_branche_unique_code($pdo, $label);
    $id = ce_uuid();

    $hygienefactoren = $data['hygienefactoren'] ?? [];
    $categorietaal = $data['categorietaal'] ?? [];
    $vakcontext = $data['vakcontext'] ?? [];
    $differentiatie = $data['differentiatie_assen'] ?? [];

    $stmt = $pdo->prepare(
        'INSERT INTO branche_library (
            id, branche_code, branche_label, markt_archetype, hygienefactoren, categorietaal, bron,
            branche_definitie, bronnen_kwaliteit, vakcontext, differentiatie_assen, waar_onderscheid_zit
        ) VALUES (
            :id, :code, :label, :markt_archetype, :hygienefactoren, :categorietaal, :bron,
            :branche_definitie, :bronnen_kwaliteit, :vakcontext, :differentiatie_assen, :waar_onderscheid_zit
        )'
    );
    $stmt->execute([
        'id' => $id,
        'code' => $code,
        'label' => $label,
        'markt_archetype' => trim((string) ($data['markt_archetype'] ?? '')),
        'hygienefactoren' => json_encode(is_array($hygienefactoren) ? $hygienefactoren : [], JSON_UNESCAPED_UNICODE),
        'categorietaal' => json_encode(is_array($categorietaal) ? $categorietaal : [], JSON_UNESCAPED_UNICODE),
        'bron' => 'afgeleid',
        'branche_definitie' => trim((string) ($data['branche_definitie'] ?? '')),
        'bronnen_kwaliteit' => trim((string) ($data['bronnen_kwaliteit'] ?? 'mager')),
        'vakcontext' => json_encode(is_array($vakcontext) ? $vakcontext : [], JSON_UNESCAPED_UNICODE),
        'differentiatie_assen' => json_encode(is_array($differentiatie) ? $differentiatie : [], JSON_UNESCAPED_UNICODE),
        'waar_onderscheid_zit' => trim((string) ($data['waar_onderscheid_zit'] ?? '')),
    ]);

    $row = ce_hba_fetch_branche_by_code($pdo, $code);

    return ce_hba_branche_option_dto($row ?? []);
}

/** @param array<string, mixed> $row
 *  @return array<string, mixed>
 */
function ce_hba_branche_option_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'code' => (string) ($row['branche_code'] ?? ''),
        'label' => (string) ($row['branche_label'] ?? ''),
        'marktArchetype' => $row['markt_archetype'] ?? null,
        'bron' => (string) ($row['bron'] ?? 'gecureerd'),
        'brancheDefinitie' => (string) ($row['branche_definitie'] ?? ''),
        'bronnenKwaliteit' => (string) ($row['bronnen_kwaliteit'] ?? ''),
    ];
}

/** @return array<string, mixed> */
function ce_hba_v2_resolve_branche(PDO $pdo, string $clientId, array $body): array
{
    ce_hba_architectuur_check_ensure_schema($pdo);

    $brancheNaam = trim((string) ($body['brancheNaam'] ?? $body['brancheLabel'] ?? $body['branche_label'] ?? ''));
    if ($brancheNaam === '') {
        return ['ok' => false, 'error' => 'Vul een branche in'];
    }

    require_once __DIR__ . '/hba-branch-record-repository.php';

    return ce_hba_resolve_or_derive_branch($pdo, $clientId, $body);
}

/** @return array<string, mixed> */
function ce_hba_v2_architecture_check_from_body(array $body): array
{
    $regelsList = is_array($body['regels'] ?? null) ? $body['regels'] : [];
    $regels = implode("\n", array_values(array_filter(array_map('strval', $regelsList))));

    $goede = is_array($body['goedeVoorbeelden'] ?? null) ? $body['goedeVoorbeelden'] : [];
    $zwakke = is_array($body['zwakkeVoorbeelden'] ?? null) ? $body['zwakkeVoorbeelden'] : [];

    return [
        'archetype' => trim((string) ($body['archetype'] ?? '')),
        'doel' => trim((string) ($body['doel'] ?? '')),
        'foe' => trim((string) ($body['foe'] ?? '')),
        'distinctiviteit' => trim((string) ($body['distinctiviteit'] ?? '')),
        'wat_bewust_niet' => trim((string) ($body['watBewustNiet'] ?? $body['wat_bewust_niet'] ?? '')),
        'regels' => $regels,
        'tone_of_behaviour' => trim((string) ($body['toneOfBehaviour'] ?? $body['tone_of_behaviour'] ?? '')),
        'goede_voorbeelden' => implode("\n", array_values(array_filter(array_map('strval', $goede)))),
        'zwakke_voorbeelden' => implode("\n", array_values(array_filter(array_map('strval', $zwakke)))),
        'branche_code' => trim((string) ($body['brancheCode'] ?? $body['branche_code'] ?? '')),
    ];
}

/** @param array<string, mixed> $hardData
 *  @param array<string, mixed>|null $softData
 *  @return array<string, mixed>
 */
function ce_hba_architecture_check_dto(string $id, array $hardData, ?array $softData): array
{
    $dim = is_array($hardData['dimensies'] ?? null) ? $hardData['dimensies'] : [];
    $softData ??= [];

    return [
        'id' => $id,
        'dimensies' => [
            'krachtTriangle' => ce_hba_dimension_dto($dim['kracht_triangle'] ?? []),
            'relevantieTegenkracht' => ce_hba_dimension_dto($dim['relevantie_tegenkracht'] ?? []),
            'relevantieRol' => ce_hba_dimension_dto($dim['relevantie_rol'] ?? []),
            'scherpteEnUniciteit' => ce_hba_dimension_dto(
                is_array($dim['scherpte_en_uniciteit'] ?? null) ? $dim['scherpte_en_uniciteit'] : [],
                true,
            ),
        ],
        'regelsOordeel' => ce_hba_dimension_dto(
            is_array($hardData['regels_oordeel'] ?? null) ? $hardData['regels_oordeel'] : []
        ),
        'totaaloordeel' => (string) ($hardData['totaaloordeel'] ?? ''),
        'signalen' => is_array($hardData['signalen'] ?? null) ? $hardData['signalen'] : [],
        'signaalAdvies' => is_array($hardData['signaalAdvies'] ?? null) ? $hardData['signaalAdvies'] : [],
        'meetlatStatus' => (string) ($hardData['meetlatStatus'] ?? ''),
        'premiumLaagAangeraden' => (bool) ($hardData['premium_laag_aangeraden'] ?? false),
        'toonSuggesties' => is_array($softData['toon_suggesties'] ?? null) ? $softData['toon_suggesties'] : [],
        'voorbeeldSuggesties' => is_array($softData['voorbeeld_suggesties'] ?? null)
            ? array_values(array_map(static function ($item): array {
                if (!is_array($item)) {
                    return ['type' => 'goed', 'tekst' => ''];
                }

                return [
                    'type' => (string) ($item['type'] ?? 'goed'),
                    'tekst' => (string) ($item['tekst'] ?? ''),
                ];
            }, $softData['voorbeeld_suggesties']))
            : [],
    ];
}

/** @param array<string, mixed> $row */
function ce_hba_dimension_dto(array $row, bool $withFlags = false): array
{
    $dto = [
        'oordeel' => (string) ($row['oordeel'] ?? ''),
        'toelichting' => (string) ($row['toelichting'] ?? ''),
        'verbetering' => (string) ($row['verbetering'] ?? ''),
    ];
    if ($withFlags) {
        $dto['strawmanGedetecteerd'] = (bool) ($row['strawman_gedetecteerd'] ?? false);
        $dto['zitInHygienefactoren'] = (bool) ($row['zit_in_hygienefactoren'] ?? false);
    }

    return $dto;
}

/** @return array<string, mixed> */
function ce_hba_v2_run_architecture_check(PDO $pdo, string $clientId, array $body): array
{
    ce_hba_architectuur_check_ensure_schema($pdo);

    require_once __DIR__ . '/hba-identity-gate.php';
    require_once __DIR__ . '/hba-architecture-poort.php';
    require_once __DIR__ . '/hba-architecture-verbodslijst.php';

    $input = ce_hba_v2_architecture_check_from_body($body);
    $identityGate = ce_hba_run_identity_gate($input['doel'], $input['foe']);

    $brancheRow = $input['branche_code'] !== ''
        ? ce_hba_fetch_branche_by_code($pdo, $input['branche_code'])
        : null;
    $branchUse = $brancheRow !== null ? ce_hba_branch_confidence_use($brancheRow) : ['usable' => false, 'indicative' => false, 'blocked' => true, 'reason' => 'geen branche'];
    $brancheCtx = ce_hba_branche_prompt_context($brancheRow);
    if ($branchUse['blocked'] || $branchUse['indicative']) {
        $brancheCtx['branche_definitie'] .= ' [marktcontext onvoldoende — uniciteit niet hard beoordelen]';
    }

    if (!ce_hba_wizard_rate_limit_exempt($pdo, $clientId)) {
        $rateLimit = ce_hba_wizard_rate_limit_check($clientId, 'wizard_check');
        if (!$rateLimit['ok']) {
            return $rateLimit;
        }
    }

    $hardPrompt = ce_hba_v2_fill_prompt(ce_hba_v2_architecture_check_system_prompt(), array_merge(
        $brancheCtx,
        [
            'archetype' => ce_hba_architecture_prompt_field($input['archetype'], 'persoonlijkheid'),
            'doel' => ce_hba_architecture_prompt_field($input['doel'], 'doel'),
            'foe' => ce_hba_architecture_prompt_field($input['foe'], 'tegenkracht'),
            'distinctiviteit' => ce_hba_architecture_prompt_field($input['distinctiviteit'], 'onderscheid'),
            'wat_bewust_niet' => ce_hba_architecture_prompt_field($input['wat_bewust_niet'], 'wat jullie bewust niet doen'),
            'regels' => $input['regels'] !== '' ? $input['regels'] : '(niet ingevuld — regel)',
        ],
    ));
    $hardUser = 'Geef nu de gevraagde JSON-output.';
    $hardLlm = ce_hba_v2_call_json_for(CE_HBA_FN_ARCHITECTUUR_CHECK, $hardPrompt, $hardUser);
    if (!$hardLlm['ok']) {
        return ['ok' => false, 'error' => $hardLlm['error'] ?? 'Architectuur-check mislukt'];
    }

    if (!ce_hba_wizard_rate_limit_exempt($pdo, $clientId)) {
        ce_hba_wizard_rate_limit_increment($clientId, 'wizard_check');
    }

    $softPrompt = ce_hba_v2_fill_prompt(ce_hba_v2_architecture_soft_suggestions_system_prompt(), [
        'archetype' => $input['archetype'],
        'doel' => $input['doel'],
        'foe' => $input['foe'],
        'distinctiviteit' => $input['distinctiviteit'],
        'markt_archetype' => $brancheCtx['markt_archetype'],
        'categorietaal_om_te_vermijden' => $brancheCtx['categorietaal'],
        'tone_of_behaviour' => $input['tone_of_behaviour'] !== '' ? $input['tone_of_behaviour'] : '(nog leeg)',
        'voorbeelden_goed' => $input['goede_voorbeelden'] !== '' ? $input['goede_voorbeelden'] : '(nog niet compleet)',
        'voorbeelden_zwak' => $input['zwakke_voorbeelden'] !== '' ? $input['zwakke_voorbeelden'] : '(nog niet compleet)',
    ]);
    $softUser = 'Geef nu de gevraagde JSON-output.';
    $softLlm = ce_hba_v2_call_json_for(CE_HBA_FN_ARCHITECTUUR_CHECK, $softPrompt, $softUser, 0.3, 2048);
    $softData = $softLlm['ok'] && is_array($softLlm['data'] ?? null)
        ? $softLlm['data']
        : ['toon_suggesties' => [], 'voorbeeld_suggesties' => []];

    $id = ce_uuid();
    $hard = ce_hba_architecture_apply_poort(
        is_array($hardLlm['data']) ? $hardLlm['data'] : [],
        $input,
        $identityGate,
    );

    $verbodFirst = ce_hba_architecture_scan_client_forbidden($hard, $softData);
    $verbodStripped = [];
    if ($verbodFirst !== []) {
        $correction = ce_hba_architecture_forbidden_correction_suffix();
        $hardRetry = ce_hba_v2_call_json_for(
            CE_HBA_FN_ARCHITECTUUR_CHECK,
            $hardPrompt,
            $hardUser . $correction,
        );
        if ($hardRetry['ok'] && is_array($hardRetry['data'] ?? null)) {
            $hardLlm = $hardRetry;
            $hard = ce_hba_architecture_apply_poort($hardRetry['data'], $input, $identityGate);
        }
        $softRetry = ce_hba_v2_call_json_for(
            CE_HBA_FN_ARCHITECTUUR_CHECK,
            $softPrompt,
            $softUser . $correction,
            0.3,
            2048,
        );
        if ($softRetry['ok'] && is_array($softRetry['data'] ?? null)) {
            $softData = $softRetry['data'];
        }
        $verbodAfter = ce_hba_architecture_scan_client_forbidden($hard, $softData);
        if ($verbodAfter !== []) {
            $stripped = ce_hba_architecture_strip_forbidden_client_text($hard, $softData, $verbodAfter);
            $hard = $stripped['hard'];
            $softData = $stripped['soft'];
            $verbodStripped = $stripped['stripped'];
        }
        ce_hba_architecture_log_forbidden_hits(
            $pdo,
            $clientId,
            $id,
            $verbodFirst,
            true,
            $verbodStripped,
        );
    }

    $hard['meetlatStatus'] = ce_hba_architecture_meetlat_status($input, $hard);
    $dim = is_array($hard['dimensies'] ?? null) ? $hard['dimensies'] : [];
    $scherpte = is_array($dim['scherpte_en_uniciteit'] ?? null) ? $dim['scherpte_en_uniciteit'] : [];
    $regels = is_array($hard['regels_oordeel'] ?? null) ? $hard['regels_oordeel'] : [];

    if (ce_table_exists($pdo, 'architectuur_check_result')) {
        $stmt = $pdo->prepare(
            <<<'SQL'
INSERT INTO architectuur_check_result (
    id, client_id,
    kracht_triangle_oordeel, kracht_triangle_toelichting, kracht_triangle_verbetering,
    relevantie_tegenkracht_oordeel, relevantie_tegenkracht_toelichting, relevantie_tegenkracht_verbetering,
    relevantie_rol_oordeel, relevantie_rol_toelichting, relevantie_rol_verbetering,
    scherpte_uniciteit_oordeel, scherpte_uniciteit_toelichting, scherpte_uniciteit_verbetering,
    strawman_gedetecteerd, zit_in_hygienefactoren,
    regels_oordeel, regels_toelichting, regels_verbetering,
    totaaloordeel, premium_laag_aangeraden, toon_suggesties, voorbeeld_suggesties
) VALUES (
    :id, :client_id,
    :kt_o, :kt_t, :kt_v,
    :rt_o, :rt_t, :rt_v,
    :rr_o, :rr_t, :rr_v,
    :su_o, :su_t, :su_v,
    :strawman, :hygiene,
    :reg_o, :reg_t, :reg_v,
    :totaal, :premium, :toon, :voorbeelden
)
SQL
        );
        $kt = is_array($dim['kracht_triangle'] ?? null) ? $dim['kracht_triangle'] : [];
        $rt = is_array($dim['relevantie_tegenkracht'] ?? null) ? $dim['relevantie_tegenkracht'] : [];
        $rr = is_array($dim['relevantie_rol'] ?? null) ? $dim['relevantie_rol'] : [];
        $stmt->execute([
            'id' => $id,
            'client_id' => $clientId,
            'kt_o' => (string) ($kt['oordeel'] ?? ''),
            'kt_t' => (string) ($kt['toelichting'] ?? ''),
            'kt_v' => (string) ($kt['verbetering'] ?? ''),
            'rt_o' => (string) ($rt['oordeel'] ?? ''),
            'rt_t' => (string) ($rt['toelichting'] ?? ''),
            'rt_v' => (string) ($rt['verbetering'] ?? ''),
            'rr_o' => (string) ($rr['oordeel'] ?? ''),
            'rr_t' => (string) ($rr['toelichting'] ?? ''),
            'rr_v' => (string) ($rr['verbetering'] ?? ''),
            'su_o' => (string) ($scherpte['oordeel'] ?? ''),
            'su_t' => (string) ($scherpte['toelichting'] ?? ''),
            'su_v' => (string) ($scherpte['verbetering'] ?? ''),
            'strawman' => !empty($scherpte['strawman_gedetecteerd']) ? 1 : 0,
            'hygiene' => !empty($scherpte['zit_in_hygienefactoren']) ? 1 : 0,
            'reg_o' => (string) ($regels['oordeel'] ?? ''),
            'reg_t' => (string) ($regels['toelichting'] ?? ''),
            'reg_v' => (string) ($regels['verbetering'] ?? ''),
            'totaal' => (string) ($hard['totaaloordeel'] ?? ''),
            'premium' => !empty($hard['premium_laag_aangeraden']) ? 1 : 0,
            'toon' => json_encode($softData['toon_suggesties'] ?? [], JSON_UNESCAPED_UNICODE),
            'voorbeelden' => json_encode($softData['voorbeeld_suggesties'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        $archModel = trim((string) ($hardLlm['model'] ?? ce_hba_model_for(CE_HBA_FN_ARCHITECTUUR_CHECK)));
        ce_hba_persist_outcome_meta($pdo, 'architectuur_check_result', $id, $archModel);
    }

    $scorePrompt = ce_hba_architecture_score_system_prompt();
    $scoreUser = "Hero/archetype: {$input['archetype']}\nGoal: {$input['doel']}\nFoe: {$input['foe']}\n"
        . "Constraint: {$input['wat_bewust_niet']}\nRegels: {$input['regels']}\n"
        . "Branche: {$brancheCtx['branche_definitie']}\nHygiëne: {$brancheCtx['hygienefactoren']}\n"
        . "Categorietaal: {$brancheCtx['categorietaal']}\n"
        . "Marktframe bruikbaar: " . ($branchUse['usable'] ? 'ja' : 'nee');
    $scoreLlm = ce_hba_v2_call_json_for(CE_HBA_FN_ARCHITECTUUR_CHECK, $scorePrompt, $scoreUser, 0.2, 3072);
    $hygieneCollapse = !empty($scherpte['zit_in_hygienefactoren']);
    $scored = ce_hba_apply_score_decision(
        $scoreLlm['ok'] ? $scoreLlm['data'] : [],
        !empty($branchUse['blocked']),
        $hygieneCollapse,
    );

    $guardrailId = null;
    $archVersion = 1;
    $branchVersion = $brancheRow !== null ? (int) ($brancheRow['version'] ?? 1) : null;
    $branchId = $brancheRow !== null ? (string) ($brancheRow['id'] ?? '') : null;
    $meetlatActief = ($hard['meetlatStatus'] ?? '') === 'actief';
    if ($meetlatActief) {
        $guardrail = ce_hba_build_guardrail_object(
            [
                'archetype' => $input['archetype'],
                'doel' => $input['doel'],
                'foe' => $input['foe'],
                'wat_bewust_niet' => $input['wat_bewust_niet'],
                'regels' => $input['regels'] !== '' ? preg_split('/\n+/', $input['regels']) : [],
            ],
            $brancheRow,
            $scored,
            $archVersion,
        );
        $guardrailId = ce_hba_store_guardrail(
            $pdo,
            $clientId,
            $guardrail,
            'PASS',
            $scored['total_score'],
            $branchId !== '' ? $branchId : null,
            $branchVersion,
            $archVersion,
        );
    }

    if (ce_table_exists($pdo, 'architectuur_check_result')) {
        try {
            $pdo->prepare(
                'UPDATE architectuur_check_result SET
                    goal_identity_level = :goal_level,
                    foe_identity_level = :foe_level,
                    identity_gate_pass = :identity_gate_pass,
                    criterion_scores = :scores,
                    total_score = :total,
                    decision = :decision,
                    hard_fail_reason = :reason,
                    return_action = :return_action,
                    guardrail_id = :guardrail_id,
                    architecture_version = :arch_version,
                    branch_id = :branch_id,
                    branch_version = :branch_version
                 WHERE id = :id'
            )->execute([
                'goal_level' => $identityGate['goal']['level'],
                'foe_level' => $identityGate['foe']['level'],
                'identity_gate_pass' => $identityGate['pass'] ? 1 : 0,
                'scores' => json_encode($scored['criteria'], JSON_UNESCAPED_UNICODE),
                'total' => $scored['total_score'],
                'decision' => $meetlatActief ? $scored['decision'] : 'FAIL',
                'reason' => $meetlatActief ? $scored['hard_fail_reason'] : 'Meetlat geblokkeerd — architectuur nog niet volledig of scherp genoeg.',
                'return_action' => $scored['return_action'],
                'guardrail_id' => $guardrailId,
                'arch_version' => $archVersion,
                'branch_id' => $branchId !== '' ? $branchId : null,
                'branch_version' => $branchVersion,
                'id' => $id,
            ]);
        } catch (Throwable) {
            // Nieuwe kolommen ontbreken nog.
        }
    }

    $dto = ce_hba_architecture_check_dto($id, $hard, $softData);
    $dto['identityGate'] = $identityGate;
    $dto['identityGatePass'] = $identityGate['pass'];
    $dto['decision'] = $meetlatActief ? $scored['decision'] : 'FAIL';
    $dto['hardFailReason'] = $meetlatActief
        ? $scored['hard_fail_reason']
        : 'De meetlat staat nog niet aan: vul ontbrekende velden aan of scherp de architectuur verder aan.';
    $dto['returnAction'] = $identityGate['pass']
        ? $scored['return_action']
        : 'Formuleer doel en tegenkracht op identiteitsniveau — geen KPI, concurrent of toezichthouder als tegenkracht.';
    $dto['criterionScores'] = $scored['criteria'];
    $dto['totalScore'] = $scored['total_score'];
    $dto['guardrailActive'] = $meetlatActief;
    $dto['marktcontextOnvoldoende'] = !empty($branchUse['blocked']) || !empty($branchUse['indicative']);

    return [
        'ok' => true,
        'check' => $dto,
    ];
}
