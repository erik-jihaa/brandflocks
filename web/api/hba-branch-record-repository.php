<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-contentflow-schema.php';
require_once __DIR__ . '/hba-architectuur-check-repository.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/llm-repository.php';

/** @return list<array<string, mixed>> */
function ce_hba_branch_record_seed_enrichment(): array
{
    return [
        'zakelijke_dienstverlening' => [
            'market_definition' => 'Professionele advies- en begeleidingsdiensten voor organisaties: diagnose, richting en uitvoering zonder zelf het eindproduct te maken.',
            'audience_scope' => 'Beslissers en opdrachtgevers in MKB en corporates',
            'geography' => 'NL/BE',
            'language' => 'nl',
            'inclusion_exclusion' => 'Inclusief: consultancy, interimabestuur, transformatie. Exclusief: puur uitzenden, softwareproducten, juridische procesvertegenwoordiging.',
            'market_archetype_rationale' => 'De categorie presenteert zich als gids die complexiteit duidt en de klant ontzorgt — dat is de Wijze, geen Rebel of Maker.',
            'differentiation' => [
                ['name' => 'Diepte vs breedte', 'pole_a' => 'Specialist in één domein', 'pole_b' => 'Generalist over de keten', 'rationale' => 'Klassiek spanningsveld tussen niche-autoriteit en full-service partner.'],
                ['name' => 'Advies vs uitvoering', 'pole_a' => 'Alleen richting', 'pole_b' => 'Hands-on implementatie', 'rationale' => 'Waar de belofte stopt: denken of ook doen.'],
            ],
            'evidence' => [
                ['source' => 'Categorieobservatie startset', 'claim' => 'Bijna elke speler claimt ontzorgen, strategisch partner en op maat.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Minimumverwachting: deskundigheid, discretie, aantoonbare resultaten.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Thought leadership en bewezen aanpak zijn standaardtaal, geen onderscheid.'],
            ],
        ],
        'supply_chain_logistiek' => [
            'market_definition' => 'Verplaatsen, opslaan en zichtbaar maken van goederenstromen zodat beloofde levertijden haalbaar blijven.',
            'audience_scope' => 'Supply-chain- en operations-beslissers',
            'geography' => 'NL/BE',
            'language' => 'nl',
            'inclusion_exclusion' => 'Inclusief: 3PL, warehousing, transportregie. Exclusief: puur software zonder uitvoering, postpakket-consumentenmerken.',
            'market_archetype_rationale' => 'De markt belooft zorgdragen voor de keten — Verzorger is de gedeelde categoriecode.',
            'differentiation' => [
                ['name' => 'Zicht vs grip', 'pole_a' => 'Realtime inzicht', 'pole_b' => 'Operationele ontzorging', 'rationale' => 'Spelers kiezen tussen dashboard-belofte en uitvoering uit handen nemen.'],
                ['name' => 'Schaal vs maatwerk', 'pole_a' => 'Netwerkschaal', 'pole_b' => 'Klantspecifieke inrichting', 'rationale' => 'Grote netwerken versus niche-ketens.'],
            ],
            'evidence' => [
                ['source' => 'Categorieobservatie startset', 'claim' => 'End-to-end, seamless en realtime inzicht zijn standaardclaims.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Hygiëne: tracking, veiligheid, leverbetrouwbaarheid.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Betrouwbare partner is categoriegeneriek.'],
            ],
        ],
        'bouw_techniek' => [
            'market_definition' => 'Ontwerpen, bouwen, installeren of onderhouden van fysieke objecten en installaties onder afspraak, veiligheid en oplevergarantie.',
            'audience_scope' => 'Opdrachtgevers, aannemers en vastgoedeigenaren',
            'geography' => 'NL/BE',
            'language' => 'nl',
            'inclusion_exclusion' => 'Inclusief: bouw, installatie, technisch onderhoud. Exclusief: puur architectenbureaus zonder uitvoering, bouwmarkten.',
            'market_archetype_rationale' => 'De markt speelt Maker: vakmanschap en maken als vanzelfsprekende rol.',
            'differentiation' => [
                ['name' => 'Proces vs vak', 'pole_a' => 'Strakke projectbeheersing', 'pole_b' => 'Ambachtskwaliteit', 'rationale' => 'Onderscheid zit zelden in "kwaliteit" maar in hoe die wordt bewezen.'],
                ['name' => 'Ketenspel vs specialisme', 'pole_a' => 'Van A tot Z', 'pole_b' => 'Eén discipline diep', 'rationale' => 'Full-service versus vakniche.'],
            ],
            'evidence' => [
                ['source' => 'Categorieobservatie startset', 'claim' => 'Kwaliteit staat voorop en afspraak is afspraak zijn categorietaal.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Hygiëne: veiligheid, oplevering op tijd, garantie.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'De klant centraal differentieert niet in deze markt.'],
            ],
        ],
        'zorg_welzijn' => [
            'market_definition' => 'Begeleiden, behandelen of ondersteunen van mensen in hun gezondheid, welzijn of dagelijks functioneren.',
            'audience_scope' => 'Cliënten, naasten en verwijzers',
            'geography' => 'NL/BE',
            'language' => 'nl',
            'inclusion_exclusion' => 'Inclusief: zorgaanbieders, welzijn, begeleiding. Exclusief: farma, medische devices, zorgverzekeraars.',
            'market_archetype_rationale' => 'De categoriecode is Verzorger: aandacht, veiligheid en de mens centraal.',
            'differentiation' => [
                ['name' => 'Protocol vs nabijheid', 'pole_a' => 'Richtlijn en kwaliteitssysteem', 'pole_b' => 'Persoonlijke relatie', 'rationale' => 'Beide zijn verwacht; onderscheid zit in welke spanning het merk kiest.'],
                ['name' => 'Behandelen vs begeleiden', 'pole_a' => 'Klinisch herstel', 'pole_b' => 'Leven naast de zorg', 'rationale' => 'Verschillende beloftes onder dezelfde mens-centraal-taal.'],
            ],
            'evidence' => [
                ['source' => 'Categorieobservatie startset', 'claim' => 'De mens centraal en persoonlijke aandacht zijn hygiëne, geen unieke claim.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Samen sterker en met hart en ziel zijn categorietaal.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Minimum: deskundigheid, veiligheid, betrouwbaarheid.'],
            ],
        ],
        'tech_saas' => [
            'market_definition' => 'Digitale producten die een werkproces automatiseren of inzichtelijk maken, meestal als abonnement.',
            'audience_scope' => 'Gebruikers en buyers van software',
            'geography' => 'NL/internationaal',
            'language' => 'nl',
            'inclusion_exclusion' => 'Inclusief: SaaS, platforms, toolvendors. Exclusief: maatwerk-IT zonder product, hardware, telecominfrastructuur.',
            'market_archetype_rationale' => 'De markt speelt Magier: transformatie en "we maken het makkelijk" als gedeelde code.',
            'differentiation' => [
                ['name' => 'Eenvoud vs kracht', 'pole_a' => 'Onboarding in minuten', 'pole_b' => 'Diepe configuratie', 'rationale' => 'Gebruiksgemak versus enterprise-diepte.'],
                ['name' => 'Horizontaal vs verticaal', 'pole_a' => 'Algemene workflow', 'pole_b' => 'Branche-specifiek', 'rationale' => 'Waar het product zijn taal vandaan haalt.'],
            ],
            'evidence' => [
                ['source' => 'Categorieobservatie startset', 'claim' => 'Innovatief, seamless, next-gen en game-changer zijn categorietaal.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Hygiëne: veiligheid, schaalbaarheid, support, gebruiksgemak.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'De toekomst van… is een cliché, geen as.'],
            ],
        ],
        'retail' => [
            'market_definition' => 'Aanbieden van fysieke of digitale producten aan consumenten, met keuze, prijs en beschikbaarheid als basis.',
            'audience_scope' => 'Consumenten en shoppers',
            'geography' => 'NL/BE',
            'language' => 'nl',
            'inclusion_exclusion' => 'Inclusief: winkels, webshops, consumentenmerken. Exclusief: groothandel B2B-only, marktplaatsen zonder eigen merk.',
            'market_archetype_rationale' => 'De categorie speelt Buur: toegankelijk, voor jou, zonder afstand.',
            'differentiation' => [
                ['name' => 'Prijs vs selectie', 'pole_a' => 'Laagste drempel', 'pole_b' => 'Curatie en smaak', 'rationale' => 'Niet "kwaliteit voor iedereen" maar welke schaarste je creëert.'],
                ['name' => 'Gemak vs beleving', 'pole_a' => 'Snel en frictieloos', 'pole_b' => 'Merkwereld', 'rationale' => 'Convenience versus identiteit.'],
            ],
            'evidence' => [
                ['source' => 'Categorieobservatie startset', 'claim' => 'Beste prijs en voor ieder wat wils zijn categorietaal.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Hygiëne: beschikbaarheid, service, leverbetrouwbaarheid.'],
                ['source' => 'Categorieobservatie startset', 'claim' => 'Wij begrijpen jou differentieert niet zonder specifieke wereld.'],
            ],
        ],
    ];
}

function ce_hba_migrate_seed_branches_to_records(PDO $pdo): void
{
    ce_ensure_hba_contentflow_schema($pdo);
    if (!ce_table_exists($pdo, 'branche_library')) {
        return;
    }

    $enrichment = ce_hba_branch_record_seed_enrichment();
    foreach ($enrichment as $code => $data) {
        $row = ce_hba_fetch_branche_by_code($pdo, $code);
        if ($row === null) {
            continue;
        }
        if (trim((string) ($row['market_definition'] ?? '')) !== '') {
            continue;
        }

        $axes = [];
        foreach ($data['differentiation'] as $axis) {
            $axes[] = [
                'name' => $axis['name'],
                'pole_a' => $axis['pole_a'],
                'pole_b' => $axis['pole_b'],
                'rationale' => $axis['rationale'],
                'evidence_ids' => [],
            ];
        }

        $dims = [
            'marktframe' => 9,
            'patroonherhaling' => 8,
            'bronkwaliteit' => 8,
            'eenduidigheid' => 9,
        ];

        $sets = [
            'market_definition = :market_definition',
            'audience_scope = :audience_scope',
            'geography = :geography',
            'language = :language',
            'inclusion_exclusion = :inclusion_exclusion',
            'market_archetype_rationale = :market_archetype_rationale',
            'confidence_total = :confidence_total',
            'confidence_dimensions = :confidence_dimensions',
            'status = :status',
            'last_validated_at = NOW()',
        ];
        $params = [
            'code' => $code,
            'market_definition' => $data['market_definition'],
            'audience_scope' => $data['audience_scope'],
            'geography' => $data['geography'],
            'language' => $data['language'],
            'inclusion_exclusion' => $data['inclusion_exclusion'],
            'market_archetype_rationale' => $data['market_archetype_rationale'],
            'confidence_total' => 9,
            'confidence_dimensions' => json_encode($dims, JSON_UNESCAPED_UNICODE),
            'status' => 'active',
        ];
        if (ce_table_has_column($pdo, 'branche_library', 'branche_definitie') && trim((string) ($row['branche_definitie'] ?? '')) === '') {
            $sets[] = 'branche_definitie = :branche_definitie';
            $params['branche_definitie'] = $data['market_definition'];
        }
        if (ce_table_has_column($pdo, 'branche_library', 'differentiatie_assen')) {
            $sets[] = 'differentiatie_assen = :differentiatie_assen';
            $params['differentiatie_assen'] = json_encode($axes, JSON_UNESCAPED_UNICODE);
        }
        if (ce_table_has_column($pdo, 'branche_library', 'bronnen_kwaliteit') && trim((string) ($row['bronnen_kwaliteit'] ?? '')) === '') {
            $sets[] = 'bronnen_kwaliteit = :bronnen_kwaliteit';
            $params['bronnen_kwaliteit'] = 'rijk';
        }

        $sql = 'UPDATE branche_library SET ' . implode(', ', $sets) . ' WHERE branche_code = :code LIMIT 1';
        $pdo->prepare($sql)->execute($params);

        $branchId = (string) ($row['id'] ?? '');
        if ($branchId !== '' && ce_table_exists($pdo, 'hba_branch_evidence')) {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM hba_branch_evidence WHERE branch_id = :id');
            $countStmt->execute(['id' => $branchId]);
            if ((int) $countStmt->fetchColumn() === 0) {
                foreach ($data['evidence'] as $item) {
                    ce_hba_store_branch_evidence($pdo, $branchId, $item['source'], null, $item['claim'], 3);
                }
            }
        }
    }
}

function ce_hba_store_branch_evidence(
    PDO $pdo,
    string $branchId,
    string $source,
    ?string $url,
    string $claim,
    int $phase,
): string {
    $id = ce_uuid();
    $stmt = $pdo->prepare(
        'INSERT INTO hba_branch_evidence (id, branch_id, source, url_or_internal_ref, observed_claim, observed_at, phase)
         VALUES (:id, :branch_id, :source, :url, :claim, CURDATE(), :phase)'
    );
    $stmt->execute([
        'id' => $id,
        'branch_id' => $branchId,
        'source' => $source,
        'url' => $url,
        'claim' => $claim,
        'phase' => $phase,
    ]);

    return $id;
}

/** @return list<array<string, mixed>> */
function ce_hba_fetch_branch_evidence(PDO $pdo, string $branchId): array
{
    if (!ce_table_exists($pdo, 'hba_branch_evidence')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, source, url_or_internal_ref, observed_claim, observed_at, phase
         FROM hba_branch_evidence WHERE branch_id = :id ORDER BY created_at ASC'
    );
    $stmt->execute(['id' => $branchId]);

    return $stmt->fetchAll() ?: [];
}

/** @return array<string, mixed>|null */
function ce_hba_match_branch_record(PDO $pdo, string $label, array $frame = []): ?array
{
    $byLabel = ce_hba_fetch_branche_by_label($pdo, $label);
    if ($byLabel === null) {
        return null;
    }

    $wantedGeo = strtolower(trim((string) ($frame['geography'] ?? '')));
    $wantedAudience = strtolower(trim((string) ($frame['audience'] ?? $frame['audience_scope'] ?? '')));
    $rowGeo = strtolower(trim((string) ($byLabel['geography'] ?? '')));
    $rowAudience = strtolower(trim((string) ($byLabel['audience_scope'] ?? '')));

    if ($wantedGeo !== '' && $rowGeo !== '' && !str_contains($rowGeo, $wantedGeo) && !str_contains($wantedGeo, $rowGeo)) {
        return null;
    }
    if ($wantedAudience !== '' && $rowAudience !== '' && !str_contains($rowAudience, $wantedAudience) && !str_contains($wantedAudience, $rowAudience)) {
        return null;
    }

    return $byLabel;
}

function ce_hba_v2_call_json_with_web_search(string $systemPrompt, string $userPrompt, int $maxTokens = 8192): array
{
    $keys = ce_llm_visibility_api_keys();
    $key = trim((string) ($keys['anthropic'] ?? ''));
    if ($key === '') {
        return ['ok' => false, 'error' => 'ANTHROPIC_API_KEY ontbreekt'];
    }

    $model = ce_hba_model_for(CE_HBA_FN_BRANCHE_AFLEIDER);

    $payload = json_encode([
        'model' => $model,
        'max_tokens' => max(1024, min(8192, $maxTokens)),
        'temperature' => 0.2,
        'system' => $systemPrompt,
        'tools' => [
            [
                'type' => 'web_search_20250305',
                'name' => 'web_search',
                'max_uses' => 8,
            ],
        ],
        'messages' => [
            [
                'role' => 'user',
                'content' => $userPrompt . "\n\nAntwoord uitsluitend met geldige JSON, zonder markdown fences.",
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $res = ce_llm_visibility_http_post(
        'https://api.anthropic.com/v1/messages',
        [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        $payload,
        180,
    );

    if (!$res['ok']) {
        return ce_hba_v2_call_json_for(CE_HBA_FN_BRANCHE_AFLEIDER, $systemPrompt, $userPrompt, 0.2, $maxTokens);
    }

    $raw = json_decode($res['body'], true);
    if (!is_array($raw)) {
        return ce_hba_v2_call_json_for(CE_HBA_FN_BRANCHE_AFLEIDER, $systemPrompt, $userPrompt, 0.2, $maxTokens);
    }

    $text = '';
    foreach (($raw['content'] ?? []) as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['type'] ?? '') === 'text') {
            $text .= (string) ($block['text'] ?? '');
        }
    }

    $text = trim($text);
    if (str_starts_with($text, '```')) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
    }

    $data = json_decode($text, true);
    if (!is_array($data)) {
        return ce_hba_v2_call_json_for(CE_HBA_FN_BRANCHE_AFLEIDER, $systemPrompt, $userPrompt, 0.2, $maxTokens);
    }

    return ['ok' => true, 'data' => $data, 'model' => $model];
}

function ce_hba_branch_derive_system_prompt(): string
{
    return <<<'PROMPT'
Je leidt een branchrecord af voor Brandflocks. Werk in ZEVEN FASES. Sla geen fase over.
Evidence eerst, dan pas clusteren. Nooit afleiden uit één bedrijf of één bron.
Hygiënefactor en differentiatie-as nooit samenvoegen. Markt-archetype is een observatie
van de categorie, NOOIT het merkarchetype van de klant.
Twee duidelijke submarkten → vernauw of splits het marktframe, middelen is verboden.

FASE 0 — MARKTFRAME
Input: merknaam, aanbod, klant/prospect, geografie, taal, koopsituatie, eventuele concurrenten.
Output: werkdefinitie + expliciete inclusie/exclusiegrenzen.

FASE 1 — MARKTSET
Stel een representatieve set aanbieders/bronnen samen, niet alleen de bekendste spelers.
Gebruik web search. Minimaal 4 onafhankelijke bronnen.

FASE 2 — CATEGORIECOMMUNICATIE
Extraheer per speler: claims, proposities, waarden, bewijsvoering, terugkerende formuleringen.
Houd ruwe observatie gescheiden van interpretatie. Elke observatie krijgt source + url.

FASE 3 — GENERIEKE PATRONEN
Cluster wat aantoonbaar terugkomt tot vier velden:
- hygiene_factors
- category_language
- market_archetype { label, rationale, evidence_ids }
- differentiation_axes[] { name, pole_a, pole_b, rationale, evidence_ids }
Een patroon is pas categoriegeneriek bij herhaling over meerdere onafhankelijke bronnen.

FASE 4 — CONTRAST EN AMBIGUÏTEIT
Onderscheid: veel voorkomend / noodzakelijk verwacht / werkelijk onderscheidend.
Signaleer te brede marktdefinities.

FASE 5 — CONFIDENCE (0-10 per dimensie)
dimensies: marktframe, patroonherhaling, bronkwaliteit, eenduidigheid.
confidence_total = afgerond gemiddelde. Score 0 op marktframe maakt het record onbruikbaar.

FASE 6 — OPSLAAN
Geef een versieerbaar record terug. status = active tenzij confidence_total <= 5 of marktframe = 0
(dan status = insufficient).

OUTPUT (JSON):
{
  "branch_name": "",
  "market_definition": "",
  "audience_scope": "",
  "geography": "",
  "language": "",
  "inclusion_exclusion": "",
  "hygiene_factors": ["..."],
  "category_language": ["..."],
  "market_archetype": { "label": "", "rationale": "", "evidence_ids": ["e1"] },
  "differentiation_axes": [
    { "name": "", "pole_a": "", "pole_b": "", "rationale": "", "evidence_ids": ["e1"] }
  ],
  "evidence": [
    { "id": "e1", "source": "", "url": "", "observed_claim": "", "phase": 2 }
  ],
  "confidence_total": 0,
  "confidence_dimensions": {
    "marktframe": 0,
    "patroonherhaling": 0,
    "bronkwaliteit": 0,
    "eenduidigheid": 0
  },
  "status": "active|insufficient",
  "waar_onderscheid_zit": "",
  "vakcontext": {
    "denkers_of_frameworks": [],
    "klassieke_debatten": [],
    "gevestigde_wijsheden": []
  },
  "bronnen_kwaliteit": "mager|voldoende|rijk"
}
PROMPT;
}

/** @param array<string, mixed> $body */
function ce_hba_resolve_or_derive_branch(PDO $pdo, string $clientId, array $body): array
{
    ce_ensure_hba_contentflow_schema($pdo);
    ce_hba_migrate_seed_branches_to_records($pdo);

    $label = trim((string) ($body['brancheNaam'] ?? $body['brancheLabel'] ?? $body['branche_label'] ?? $body['branch_name'] ?? ''));
    if ($label === '') {
        return ['ok' => false, 'error' => 'Vul een branche in'];
    }

    $frame = [
        'brand' => trim((string) ($body['merknaam'] ?? $body['brand'] ?? '')),
        'offer' => trim((string) ($body['aanbod'] ?? $body['offer'] ?? '')),
        'audience' => trim((string) ($body['doelgroep'] ?? $body['audience'] ?? '')),
        'geography' => trim((string) ($body['geografie'] ?? $body['geography'] ?? 'NL')),
        'language' => trim((string) ($body['taal'] ?? $body['language'] ?? 'nl')),
        'buying' => trim((string) ($body['koopsituatie'] ?? $body['buying'] ?? '')),
        'competitors' => trim((string) ($body['concurrenten'] ?? $body['competitors'] ?? '')),
    ];

    $codeHint = trim((string) ($body['brancheCode'] ?? $body['branche_code'] ?? ''));
    $existing = $codeHint !== '' ? ce_hba_fetch_branche_by_code($pdo, $codeHint) : null;
    $existing ??= ce_hba_match_branch_record($pdo, $label, $frame);

    if ($existing !== null) {
        $use = ce_hba_branch_confidence_use($existing);
        return [
            'ok' => true,
            'branch' => ce_hba_branch_record_dto($pdo, $existing),
            'derived' => false,
            'confidence' => $use,
        ];
    }

    $rateLimit = ce_hba_wizard_rate_limit_check($clientId, 'wizard_check');
    if (!$rateLimit['ok']) {
        return $rateLimit;
    }

    $user = "Branche-invoer: {$label}\n"
        . "Merknaam: " . ($frame['brand'] !== '' ? $frame['brand'] : '(onbekend)') . "\n"
        . "Aanbod: " . ($frame['offer'] !== '' ? $frame['offer'] : '(onbekend)') . "\n"
        . "Klant/prospect: " . ($frame['audience'] !== '' ? $frame['audience'] : '(onbekend)') . "\n"
        . "Geografie: {$frame['geography']}\n"
        . "Taal: {$frame['language']}\n"
        . "Koopsituatie: " . ($frame['buying'] !== '' ? $frame['buying'] : '(onbekend)') . "\n"
        . "Genoemde concurrenten: " . ($frame['competitors'] !== '' ? $frame['competitors'] : '(geen)');

    $llm = ce_hba_v2_call_json_with_web_search(ce_hba_branch_derive_system_prompt(), $user);
    if (!$llm['ok']) {
        return ['ok' => false, 'error' => $llm['error'] ?? 'Branche afleiden mislukt'];
    }
    ce_hba_wizard_rate_limit_increment($clientId, 'wizard_check');

    $data = $llm['data'];
    $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
    if (count($evidence) < 2) {
        $data['confidence_total'] = min((int) ($data['confidence_total'] ?? 0), 4);
        $data['status'] = 'insufficient';
        $dims = is_array($data['confidence_dimensions'] ?? null) ? $data['confidence_dimensions'] : [];
        $dims['bronkwaliteit'] = min((int) ($dims['bronkwaliteit'] ?? 0), 3);
        $data['confidence_dimensions'] = $dims;
    }

    $stored = ce_hba_store_branch_record($pdo, $label, $data, $frame);

    return [
        'ok' => true,
        'branch' => $stored,
        'derived' => true,
        'confidence' => ce_hba_branch_confidence_use([
            'confidence_total' => $stored['confidenceTotal'] ?? 0,
            'confidence_dimensions' => $stored['confidenceDimensions'] ?? [],
        ]),
    ];
}

/** @param array<string, mixed> $data @param array<string, string> $frame */
function ce_hba_store_branch_record(PDO $pdo, string $label, array $data, array $frame): array
{
    ce_ensure_hba_contentflow_schema($pdo);
    $code = ce_hba_branche_unique_code($pdo, (string) ($data['branch_name'] ?? $label));
    $id = ce_uuid();

    $hygiene = $data['hygiene_factors'] ?? $data['hygienefactoren'] ?? [];
    $category = $data['category_language'] ?? $data['categorietaal'] ?? [];
    $axes = $data['differentiation_axes'] ?? $data['differentiatie_assen'] ?? [];
    $vakcontext = $data['vakcontext'] ?? [];
    $archetype = $data['market_archetype'] ?? [];
    if (!is_array($archetype)) {
        $archetype = ['label' => (string) $archetype, 'rationale' => '', 'evidence_ids' => []];
    }

    $confidenceTotal = (int) ($data['confidence_total'] ?? 0);
    $dims = is_array($data['confidence_dimensions'] ?? null) ? $data['confidence_dimensions'] : [];
    $marktframe = (int) ($dims['marktframe'] ?? 0);
    $status = (string) ($data['status'] ?? 'active');
    if ($confidenceTotal <= 5 || $marktframe === 0) {
        $status = 'insufficient';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO branche_library (
            id, branche_code, branche_label, markt_archetype, hygienefactoren, categorietaal, bron,
            branche_definitie, bronnen_kwaliteit, vakcontext, differentiatie_assen, waar_onderscheid_zit,
            market_definition, audience_scope, geography, language, inclusion_exclusion,
            market_archetype_rationale, confidence_total, confidence_dimensions, version, last_validated_at, status
        ) VALUES (
            :id, :code, :label, :markt_archetype, :hygienefactoren, :categorietaal, :bron,
            :branche_definitie, :bronnen_kwaliteit, :vakcontext, :differentiatie_assen, :waar_onderscheid_zit,
            :market_definition, :audience_scope, :geography, :language, :inclusion_exclusion,
            :market_archetype_rationale, :confidence_total, :confidence_dimensions, 1, NOW(), :status
        )'
    );
    $stmt->execute([
        'id' => $id,
        'code' => $code,
        'label' => trim((string) ($data['branch_name'] ?? $label)),
        'markt_archetype' => trim((string) ($archetype['label'] ?? '')),
        'hygienefactoren' => json_encode(is_array($hygiene) ? $hygiene : [], JSON_UNESCAPED_UNICODE),
        'categorietaal' => json_encode(is_array($category) ? $category : [], JSON_UNESCAPED_UNICODE),
        'bron' => 'afgeleid',
        'branche_definitie' => trim((string) ($data['market_definition'] ?? '')),
        'bronnen_kwaliteit' => trim((string) ($data['bronnen_kwaliteit'] ?? 'mager')),
        'vakcontext' => json_encode(is_array($vakcontext) ? $vakcontext : [], JSON_UNESCAPED_UNICODE),
        'differentiatie_assen' => json_encode(is_array($axes) ? $axes : [], JSON_UNESCAPED_UNICODE),
        'waar_onderscheid_zit' => trim((string) ($data['waar_onderscheid_zit'] ?? '')),
        'market_definition' => trim((string) ($data['market_definition'] ?? '')),
        'audience_scope' => trim((string) ($data['audience_scope'] ?? $frame['audience'] ?? '')),
        'geography' => trim((string) ($data['geography'] ?? $frame['geography'] ?? 'NL')),
        'language' => trim((string) ($data['language'] ?? $frame['language'] ?? 'nl')),
        'inclusion_exclusion' => trim((string) ($data['inclusion_exclusion'] ?? '')),
        'market_archetype_rationale' => trim((string) ($archetype['rationale'] ?? '')),
        'confidence_total' => $confidenceTotal,
        'confidence_dimensions' => json_encode($dims, JSON_UNESCAPED_UNICODE),
        'status' => $status,
    ]);

    $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
    foreach ($evidence as $item) {
        if (!is_array($item)) {
            continue;
        }
        $claim = trim((string) ($item['observed_claim'] ?? $item['claim'] ?? ''));
        if ($claim === '') {
            continue;
        }
        ce_hba_store_branch_evidence(
            $pdo,
            $id,
            trim((string) ($item['source'] ?? 'web')),
            trim((string) ($item['url'] ?? '')) ?: null,
            $claim,
            (int) ($item['phase'] ?? 2),
        );
    }

    $row = ce_hba_fetch_branche_by_code($pdo, $code);

    return ce_hba_branch_record_dto($pdo, $row ?? ['id' => $id, 'branche_code' => $code, 'branche_label' => $label]);
}

/** @param array<string, mixed> $row */
function ce_hba_branch_record_dto(PDO $pdo, array $row): array
{
    $id = (string) ($row['id'] ?? '');
    $dims = $row['confidence_dimensions'] ?? [];
    if (is_string($dims)) {
        $decoded = json_decode($dims, true);
        $dims = is_array($decoded) ? $decoded : [];
    }
    $axes = $row['differentiatie_assen'] ?? [];
    if (is_string($axes)) {
        $decoded = json_decode($axes, true);
        $axes = is_array($decoded) ? $decoded : [];
    }
    $use = ce_hba_branch_confidence_use($row);

    return [
        'id' => $id,
        'code' => (string) ($row['branche_code'] ?? ''),
        'label' => (string) ($row['branche_label'] ?? ''),
        'marktArchetype' => $row['markt_archetype'] ?? null,
        'bron' => (string) ($row['bron'] ?? 'gecureerd'),
        'brancheDefinitie' => (string) ($row['branche_definitie'] ?? $row['market_definition'] ?? ''),
        'bronnenKwaliteit' => (string) ($row['bronnen_kwaliteit'] ?? ''),
        'marketDefinition' => (string) ($row['market_definition'] ?? $row['branche_definitie'] ?? ''),
        'audienceScope' => (string) ($row['audience_scope'] ?? ''),
        'geography' => (string) ($row['geography'] ?? ''),
        'language' => (string) ($row['language'] ?? 'nl'),
        'inclusionExclusion' => (string) ($row['inclusion_exclusion'] ?? ''),
        'marketArchetypeRationale' => (string) ($row['market_archetype_rationale'] ?? ''),
        'hygieneFactors' => ce_hba_decode_json_list($row['hygienefactoren'] ?? []),
        'categoryLanguage' => ce_hba_decode_json_list($row['categorietaal'] ?? []),
        'differentiationAxes' => is_array($axes) ? $axes : [],
        'confidenceTotal' => isset($row['confidence_total']) ? (int) $row['confidence_total'] : null,
        'confidenceDimensions' => is_array($dims) ? $dims : [],
        'version' => (int) ($row['version'] ?? 1),
        'status' => (string) ($row['status'] ?? 'active'),
        'usable' => $use['usable'],
        'indicative' => $use['indicative'],
        'blocked' => $use['blocked'],
        'confidenceReason' => $use['reason'],
        'evidence' => $id !== '' ? array_map(static function (array $item): array {
            return [
                'id' => (string) ($item['id'] ?? ''),
                'source' => (string) ($item['source'] ?? ''),
                'url' => (string) ($item['url_or_internal_ref'] ?? ''),
                'observedClaim' => (string) ($item['observed_claim'] ?? ''),
                'observedAt' => (string) ($item['observed_at'] ?? ''),
                'phase' => (int) ($item['phase'] ?? 2),
            ];
        }, ce_hba_fetch_branch_evidence($pdo, $id)) : [],
    ];
}
