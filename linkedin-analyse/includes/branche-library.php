<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

/**
 * Statische kopie van CE branche_library — bron: web/api/hba-branche-seed.php
 * Bij wijzigingen daar dit array-blok handmatig overnemen.
 *
 * @return list<array{
 *   code: string,
 *   label: string,
 *   markt_archetype: string,
 *   hygienefactoren: list<string>,
 *   categorietaal: list<string>,
 * }>
 */
function li_branche_library_rows(): array
{
    return [
        [
            'code' => 'zakelijke_dienstverlening',
            'label' => 'Zakelijke dienstverlening / consultancy',
            'markt_archetype' => 'Wijze',
            'hygienefactoren' => ['Deskundigheid', 'betrouwbaarheid', 'discretie', 'aantoonbare resultaten', 'senioriteit van het team'],
            'categorietaal' => ['Wij ontzorgen', 'strategische partner', 'op maat', 'thought leadership', 'wij denken met u mee', 'bewezen aanpak', 'resultaatgericht'],
        ],
        [
            'code' => 'supply_chain_logistiek',
            'label' => 'Supply chain / logistiek',
            'markt_archetype' => 'Verzorger',
            'hygienefactoren' => ['Betrouwbaarheid', 'tracking', 'veiligheid', 'leverbetrouwbaarheid', 'schaalbaarheid'],
            'categorietaal' => ['End-to-end', 'seamless', 'realtime inzicht', 'wij ontzorgen uw keten', 'innovatieve oplossingen', 'uw betrouwbare partner'],
        ],
        [
            'code' => 'bouw_techniek',
            'label' => 'Bouw / techniek / installatie',
            'markt_archetype' => 'Maker',
            'hygienefactoren' => ['Vakmanschap', 'veiligheid', 'betrouwbaarheid', 'oplevering op tijd', 'garantie'],
            'categorietaal' => ['Kwaliteit staat voorop', 'vakmanschap', 'van A tot Z', 'de klant centraal', 'no-nonsense', 'afspraak is afspraak'],
        ],
        [
            'code' => 'zorg_welzijn',
            'label' => 'Zorg / welzijn',
            'markt_archetype' => 'Verzorger',
            'hygienefactoren' => ['Deskundigheid', 'veiligheid', 'empathie', 'betrouwbaarheid', 'aandacht voor de cliënt'],
            'categorietaal' => ['De mens centraal', 'persoonlijke aandacht', 'samen sterker', 'kwaliteit van leven', 'met hart en ziel'],
        ],
        [
            'code' => 'tech_saas',
            'label' => 'Technologie / software / SaaS',
            'markt_archetype' => 'Magier',
            'hygienefactoren' => ['Betrouwbaarheid', 'veiligheid', 'schaalbaarheid', 'gebruiksgemak', 'support'],
            'categorietaal' => ['Innovatief', 'next-gen', 'empowering', 'seamless', 'wij maken het makkelijk', 'de toekomst van...', 'game-changer'],
        ],
        [
            'code' => 'retail',
            'label' => 'Retail / consumentenmerken',
            'markt_archetype' => 'Buur',
            'hygienefactoren' => ['Prijs-kwaliteitverhouding', 'beschikbaarheid', 'service', 'betrouwbaarheid van levering'],
            'categorietaal' => ['Beste prijs', 'voor ieder wat wils', 'kwaliteit voor iedereen', 'wij begrijpen jou', 'jouw partner in...'],
        ],
    ];
}

/** @return array<string, array{code: string, label: string, markt_archetype: string, hygienefactoren: list<string>, categorietaal: list<string>}> */
function li_branche_library_index(): array
{
    static $index = null;
    if (is_array($index)) {
        return $index;
    }

    $index = [];
    foreach (li_branche_library_rows() as $row) {
        $index[$row['code']] = $row;
    }

    return $index;
}

/** @return list<array{value: string, label: string}> */
function li_branche_form_options(): array
{
    $options = [];
    foreach (li_branche_library_rows() as $row) {
        $options[] = [
            'value' => $row['code'],
            'label' => $row['label'],
        ];
    }

    return $options;
}

function li_branche_is_valid(string $code): bool
{
    return isset(li_branche_library_index()[$code]);
}

/** Leeg of "overig" → geen distinctiveness-check. */
function li_branche_code_for_scan(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '' || $raw === 'overig') {
        return null;
    }

    return li_branche_is_valid($raw) ? $raw : null;
}

function li_branche_label(?string $code): ?string
{
    $code = li_branche_code_for_scan($code);
    if ($code === null) {
        return null;
    }

    return li_branche_library_index()[$code]['label'] ?? null;
}

/** @return array{code: string, label: string, markt_archetype: string, hygienefactoren: list<string>, categorietaal: list<string>}|null */
function li_branche_row(?string $code): ?array
{
    $code = li_branche_code_for_scan($code);
    if ($code === null) {
        return null;
    }

    return li_branche_library_index()[$code] ?? null;
}
