<?php

declare(strict_types=1);

/**
 * Methodiektermen die nooit in klantzichtbare architectuur-output mogen (stap 3 / §5.3).
 * Dimensie-toelichtingen en -verbeteringen vallen hieronder: de wizard toont ze.
 *
 * @return list<string>
 */
function ce_hba_architecture_client_forbidden_terms(): array
{
    return [
        'archetype',
        'story triangle',
        'storytriangle',
        'contextprofiel',
        'held',
        'gids',
        'foe',
        'hero',
        'guide',
        'storybrand',
        'mechanisme',
        'geloofwaardigheidstest',
        'merklogica',
        'branchenorm',
        'categorietaal',
        'hoofdoordeel',
        'hba',
    ];
}

function ce_hba_architecture_forbidden_correction_suffix(): string
{
    return "\n\nCORRECTIE: je vorige JSON bevatte methodiektermen die de klant mag zien. "
        . "Herschrijf alle toelichtingen, verbeteringen en suggesties in gewone taal. "
        . "Gebruik nooit: archetype, Story Triangle, contextprofiel, held, gids, Foe, hero, guide, "
        . "StoryBrand, mechanisme, geloofwaardigheidstest, merklogica, branchenorm, "
        . "categorietaal, hoofdoordeel of HBA. "
        . "Zeg in plaats daarvan: persoonlijkheid, doel, waar jullie niet in geloven, merkregel, "
        . "onderscheid, of wat een concurrent ook zou kunnen zeggen.";
}

/**
 * @return list<string>
 */
function ce_hba_architecture_forbidden_hits_in_text(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    $lower = mb_strtolower($text, 'UTF-8');
    $hits = [];
    foreach (ce_hba_architecture_client_forbidden_terms() as $term) {
        $needle = mb_strtolower(trim($term), 'UTF-8');
        if ($needle === '') {
            continue;
        }
        if (str_contains($needle, ' ')) {
            if (str_contains($lower, $needle)) {
                $hits[] = $term;
            }
            continue;
        }
        if (preg_match('/\\b' . preg_quote($needle, '/') . '\\b/u', $lower)) {
            $hits[] = $term;
        }
    }

    return array_values(array_unique($hits));
}

/**
 * @param array<string, mixed> $hard
 * @param array<string, mixed> $soft
 * @return list<array{veld: string, hits: list<string>, fragment: string}>
 */
function ce_hba_architecture_scan_client_forbidden(array $hard, array $soft): array
{
    $findings = [];
    $dim = is_array($hard['dimensies'] ?? null) ? $hard['dimensies'] : [];
    foreach (['kracht_triangle', 'relevantie_tegenkracht', 'relevantie_rol', 'scherpte_en_uniciteit'] as $key) {
        $block = is_array($dim[$key] ?? null) ? $dim[$key] : [];
        foreach (['toelichting', 'verbetering'] as $field) {
            $fragment = (string) ($block[$field] ?? '');
            $hits = ce_hba_architecture_forbidden_hits_in_text($fragment);
            if ($hits !== []) {
                $findings[] = [
                    'veld' => 'dimensies.' . $key . '.' . $field,
                    'hits' => $hits,
                    'fragment' => mb_substr($fragment, 0, 160),
                ];
            }
        }
    }
    $regels = is_array($hard['regels_oordeel'] ?? null) ? $hard['regels_oordeel'] : [];
    foreach (['toelichting', 'verbetering'] as $field) {
        $fragment = (string) ($regels[$field] ?? '');
        $hits = ce_hba_architecture_forbidden_hits_in_text($fragment);
        if ($hits !== []) {
            $findings[] = [
                'veld' => 'regels_oordeel.' . $field,
                'hits' => $hits,
                'fragment' => mb_substr($fragment, 0, 160),
            ];
        }
    }

    foreach (is_array($soft['toon_suggesties'] ?? null) ? $soft['toon_suggesties'] : [] as $i => $item) {
        $fragment = is_string($item) ? $item : '';
        $hits = ce_hba_architecture_forbidden_hits_in_text($fragment);
        if ($hits !== []) {
            $findings[] = [
                'veld' => 'toon_suggesties[' . $i . ']',
                'hits' => $hits,
                'fragment' => mb_substr($fragment, 0, 160),
            ];
        }
    }
    foreach (is_array($soft['voorbeeld_suggesties'] ?? null) ? $soft['voorbeeld_suggesties'] : [] as $i => $item) {
        $fragment = is_array($item) ? (string) ($item['tekst'] ?? '') : (is_string($item) ? $item : '');
        $hits = ce_hba_architecture_forbidden_hits_in_text($fragment);
        if ($hits !== []) {
            $findings[] = [
                'veld' => 'voorbeeld_suggesties[' . $i . ']',
                'hits' => $hits,
                'fragment' => mb_substr($fragment, 0, 160),
            ];
        }
    }

    return $findings;
}

/**
 * Na tweede poging: lege velden / drop suggesties i.p.v. blocking.
 *
 * @param array<string, mixed> $hard
 * @param array<string, mixed> $soft
 * @param list<array{veld: string, hits: list<string>, fragment: string}> $findings
 * @return array{hard: array<string, mixed>, soft: array<string, mixed>, stripped: list<string>}
 */
function ce_hba_architecture_strip_forbidden_client_text(array $hard, array $soft, array $findings): array
{
    $stripped = [];
    $dim = is_array($hard['dimensies'] ?? null) ? $hard['dimensies'] : [];
    $regels = is_array($hard['regels_oordeel'] ?? null) ? $hard['regels_oordeel'] : [];
    $toon = is_array($soft['toon_suggesties'] ?? null) ? $soft['toon_suggesties'] : [];
    $voorbeelden = is_array($soft['voorbeeld_suggesties'] ?? null) ? $soft['voorbeeld_suggesties'] : [];

    foreach ($findings as $finding) {
        $veld = (string) ($finding['veld'] ?? '');
        $stripped[] = $veld;
        if (preg_match('/^dimensies\\.([a-z_]+)\\.(toelichting|verbetering)$/', $veld, $m)) {
            $block = is_array($dim[$m[1]] ?? null) ? $dim[$m[1]] : [];
            $block[$m[2]] = '';
            $dim[$m[1]] = $block;
            continue;
        }
        if (preg_match('/^regels_oordeel\\.(toelichting|verbetering)$/', $veld, $m)) {
            $regels[$m[1]] = '';
            continue;
        }
        if (preg_match('/^toon_suggesties\\[(\\d+)\\]$/', $veld, $m)) {
            $idx = (int) $m[1];
            if (array_key_exists($idx, $toon)) {
                unset($toon[$idx]);
            }
            continue;
        }
        if (preg_match('/^voorbeeld_suggesties\\[(\\d+)\\]$/', $veld, $m)) {
            $idx = (int) $m[1];
            if (array_key_exists($idx, $voorbeelden)) {
                unset($voorbeelden[$idx]);
            }
        }
    }

    $hard['dimensies'] = $dim;
    $hard['regels_oordeel'] = $regels;
    $soft['toon_suggesties'] = array_values($toon);
    $soft['voorbeeld_suggesties'] = array_values($voorbeelden);

    return [
        'hard' => $hard,
        'soft' => $soft,
        'stripped' => array_values(array_unique($stripped)),
    ];
}

/**
 * @param list<array{veld: string, hits: list<string>, fragment: string}> $findings
 * @param list<string> $stripped
 */
function ce_hba_architecture_log_forbidden_hits(
    PDO $pdo,
    string $clientId,
    string $checkId,
    array $findings,
    bool $retried,
    array $stripped,
): void {
    if ($findings === [] && $stripped === []) {
        return;
    }
    require_once __DIR__ . '/event-log-repository.php';
    $allHits = [];
    foreach ($findings as $finding) {
        foreach ($finding['hits'] ?? [] as $hit) {
            $allHits[] = (string) $hit;
        }
    }
    ce_log_event($pdo, 'warning', 'hba', 'architectuur_verbodslijst', 'Methodiekterm in klantzichtbare architectuur-output', [
        'clientId' => $clientId,
        'checkId' => $checkId,
        'retried' => $retried,
        'hits' => array_values(array_unique($allHits)),
        'velden' => array_values(array_map(static fn (array $f): string => (string) ($f['veld'] ?? ''), $findings)),
        'stripped' => $stripped,
    ]);
}
