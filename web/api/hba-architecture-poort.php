<?php

declare(strict_types=1);

/** Waarde voor LLM-prompts — geen invulveld in de wizard. */
function ce_hba_architecture_prompt_field(string $value, string $label): string
{
    $value = trim($value);

    return $value !== '' ? $value : '(niet ingevuld — ' . $label . ')';
}

/**
 * @param array<string, mixed> $input normalized architecture input
 * @return array{
 *   tegenkrachtOntbreekt: bool,
 *   regelOntbreekt: bool,
 *   advies: array{tegenkrachtOntbreekt: string, regelOntbreekt: string}
 * }
 */
function ce_hba_architecture_veld_signalen(array $input): array
{
    $tegenkrachtOntbreekt = trim((string) ($input['foe'] ?? '')) === '';
    $regelOntbreekt = trim((string) ($input['regels'] ?? '')) === '';

    return [
        'tegenkrachtOntbreekt' => $tegenkrachtOntbreekt,
        'regelOntbreekt' => $regelOntbreekt,
        'advies' => [
            'tegenkrachtOntbreekt' => 'Vul in waar jullie merk níet in gelooft: een overtuiging of aanname uit jullie branche waar jullie bewust tegenin gaan — geen concurrent of wet.',
            'regelOntbreekt' => 'Formuleer minstens één regel waar jullie nee tegen zeggen, ook als een klant erom vraagt — bijvoorbeeld een type opdracht, korting of snelle shortcut die jullie bewust niet doen.',
        ],
    ];
}

/** @param array<string, mixed> $input */
function ce_hba_architecture_velden_compleet(array $input): bool
{
    foreach (['archetype', 'doel', 'foe', 'distinctiviteit', 'regels'] as $key) {
        if (trim((string) ($input[$key] ?? '')) === '') {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, mixed> $block
 * @return array<string, mixed>
 */
function ce_hba_architecture_force_aanscherpen(array $block, string $toelichting, string $verbetering): array
{
    return [
        'oordeel' => 'aanscherpen',
        'toelichting' => $toelichting !== '' ? $toelichting : (string) ($block['toelichting'] ?? ''),
        'verbetering' => $verbetering !== '' ? $verbetering : (string) ($block['verbetering'] ?? ''),
        'strawman_gedetecteerd' => (bool) ($block['strawman_gedetecteerd'] ?? false),
        'zit_in_hygienefactoren' => (bool) ($block['zit_in_hygienefactoren'] ?? false),
    ];
}

/**
 * Past veldbevindingen en identity-gate op dimensies toe; herberekent totaaloordeel.
 *
 * @param array<string, mixed> $hard LLM hard JSON
 * @param array<string, mixed> $input
 * @param array{goal: array{level: string, feedback: string}, foe: array{level: string, feedback: string}, pass: bool} $identityGate
 * @return array<string, mixed>
 */
function ce_hba_architecture_apply_poort(array $hard, array $input, array $identityGate): array
{
    $veld = ce_hba_architecture_veld_signalen($input);
    $dim = is_array($hard['dimensies'] ?? null) ? $hard['dimensies'] : [];
    $regels = is_array($hard['regels_oordeel'] ?? null) ? $hard['regels_oordeel'] : [];

    if ($veld['tegenkrachtOntbreekt']) {
        $advies = $veld['advies']['tegenkrachtOntbreekt'];
        $dim['relevantie_tegenkracht'] = ce_hba_architecture_force_aanscherpen(
            is_array($dim['relevantie_tegenkracht'] ?? null) ? $dim['relevantie_tegenkracht'] : [],
            'Je hebt nog geen tegenkracht ingevuld.',
            $advies,
        );
        $dim['kracht_triangle'] = ce_hba_architecture_force_aanscherpen(
            is_array($dim['kracht_triangle'] ?? null) ? $dim['kracht_triangle'] : [],
            'Zonder tegenkracht is de samenhang tussen persoonlijkheid, doel en keuze niet te toetsen.',
            $advies,
        );
    }

    if ($veld['regelOntbreekt']) {
        $advies = $veld['advies']['regelOntbreekt'];
        $regels = ce_hba_architecture_force_aanscherpen(
            $regels,
            'Je hebt nog geen regel ingevuld.',
            $advies,
        );
    }

    if ($identityGate['goal']['level'] === 'wrong_level') {
        $dim['kracht_triangle'] = ce_hba_architecture_force_aanscherpen(
            is_array($dim['kracht_triangle'] ?? null) ? $dim['kracht_triangle'] : [],
            $identityGate['goal']['feedback'],
            'Formuleer het doel als wie jullie worden als dat bereikt is — geen omzet-, lead- of marktaandeeldoel.',
        );
    }
    if ($identityGate['foe']['level'] === 'wrong_level') {
        $dim['relevantie_tegenkracht'] = ce_hba_architecture_force_aanscherpen(
            is_array($dim['relevantie_tegenkracht'] ?? null) ? $dim['relevantie_tegenkracht'] : [],
            $identityGate['foe']['feedback'],
            'Beschrijf een overtuiging of aanname uit jullie vak, geen concurrent of toezichthouder.',
        );
    }

    $hard['dimensies'] = $dim;
    $hard['regels_oordeel'] = $regels;
    $hard['totaaloordeel'] = ce_hba_architecture_recompute_totaaloordeel($dim, $regels);
    $hard['premium_laag_aangeraden'] = !empty($hard['premium_laag_aangeraden'])
        || (string) ($hard['totaaloordeel'] ?? '') === 'meetlat_te_zwak'
        || (string) (is_array($dim['scherpte_en_uniciteit'] ?? null) ? ($dim['scherpte_en_uniciteit']['oordeel'] ?? '') : '') === 'aanscherpen';

    $hard['signalen'] = [
        'tegenkrachtOntbreekt' => $veld['tegenkrachtOntbreekt'],
        'regelOntbreekt' => $veld['regelOntbreekt'],
        'identityDoelWrongLevel' => $identityGate['goal']['level'] === 'wrong_level',
        'identityTegenkrachtWrongLevel' => $identityGate['foe']['level'] === 'wrong_level',
    ];
    $hard['signaalAdvies'] = $veld['advies'];

    return $hard;
}

/**
 * @param array<string, mixed> $dim
 * @param array<string, mixed> $regels
 */
function ce_hba_architecture_recompute_totaaloordeel(array $dim, array $regels): string
{
    $aanscherpen = 0;
    foreach (['kracht_triangle', 'relevantie_tegenkracht', 'relevantie_rol', 'scherpte_en_uniciteit'] as $key) {
        $block = is_array($dim[$key] ?? null) ? $dim[$key] : [];
        if ((string) ($block['oordeel'] ?? '') === 'aanscherpen') {
            $aanscherpen++;
        }
    }
    if ((string) ($regels['oordeel'] ?? '') === 'aanscherpen') {
        $aanscherpen++;
    }
    if ($aanscherpen >= 2) {
        return 'meetlat_te_zwak';
    }
    if ($aanscherpen === 1) {
        return 'meetlat_bruikbaar_met_waarschuwing';
    }

    return 'meetlat_klaar';
}

/**
 * @param array<string, mixed> $input
 * @param array<string, mixed> $hard na apply_poort
 */
function ce_hba_architecture_meetlat_status(array $input, array $hard): string
{
    if (!ce_hba_architecture_velden_compleet($input)) {
        return 'geblokkeerd';
    }
    if ((string) ($hard['totaaloordeel'] ?? '') !== 'meetlat_klaar') {
        return 'geblokkeerd';
    }

    return 'actief';
}
