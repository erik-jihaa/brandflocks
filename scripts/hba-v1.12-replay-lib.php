<?php
declare(strict_types=1);

/** v1.12 met oude trede-2: foe op kern alleen als !kern_categorietaal (parse_kern). */
function hba_diagnose_resolve_legacy_trede2(array $tool, array $context = []): string
{
    $flags = ce_hba_flags_from_tool($tool);
    $foeOnKern = !empty($context['foe_on_kern']);
    $merkBew = ce_hba_parse_merkbewering($tool);

    if (in_array('constraint_breach', $flags, true)) {
        return 'regelbreuk';
    }
    if (in_array('foe_vervaging', $flags, true) && $foeOnKern && !ce_hba_parse_kern_categorietaal($tool)) {
        return 'tegenkracht';
    }
    if (!$merkBew) {
        return ce_hba_op_merk_of_neutraal($tool);
    }
    if (ce_hba_parse_kern_categorietaal($tool)) {
        return 'categorietaal';
    }

    return ce_hba_op_merk_of_neutraal($tool);
}

/** @return 'geen_foe'|'kop'|'openingszin'|'verderop' */
function hba_diagnose_foe_zone(string $foeCitaat, string $postText): string
{
    $foeCitaat = trim($foeCitaat);
    if ($foeCitaat === '') {
        return 'geen_foe';
    }
    $zones = ce_hba_resolve_kop_en_openingszin($postText, null);
    if (ce_hba_flag_citation_on_kernbewering($foeCitaat, $zones['kop'], '')) {
        return 'kop';
    }
    if (ce_hba_flag_citation_on_kernbewering($foeCitaat, '', $zones['opening'])) {
        return 'openingszin';
    }
    if (ce_hba_flag_citation_on_kernbewering($foeCitaat, $zones['kop'], $zones['opening'])) {
        return 'kop';
    }

    return 'verderop';
}

/** @return array<string, array<int, array{post_tekst: string, label: string}>> */
function hba_diagnose_load_testposts(string $path): array
{
    $data = json_decode((string) file_get_contents($path), true);
    $out = [];
    foreach ($data['merken'] ?? [] as $block) {
        $merk = (string) ($block['testmerk']['merknaam'] ?? '');
        if ($merk === '') {
            continue;
        }
        $slots = [];
        $i = 0;
        foreach ($block['testposts'] ?? [] as $tp) {
            $i++;
            $label = (string) ($tp['label'] ?? '');
            if ($label === '') {
                $defaults = [
                    1 => 'Op merk',
                    2 => 'Categorietaal',
                    3 => 'Breekt de regel',
                    4 => 'Neutraal / grensgeval',
                    5 => 'Verdedigt de regel',
                    6 => 'Mechanisme dat markthygiëne is',
                ];
                $label = $defaults[$i] ?? ('Post ' . $i);
            }
            if ($merk === 'Duinroos Thuiszorg' && $i === 7) {
                $label = 'Kernbewering zonder bewijs (V0 v1.9)';
            }
            if ($merk === 'Stelwerk' && $i === 5) {
                $label = 'Samenwerking/ervaring zonder bewijs (V0 v1.9)';
            }
            if ($merk === 'Stelwerk' && $i === 6) {
                $label = 'Branchetaal uit de verboden logica';
            }
            if ($merk === 'Duinroos Thuiszorg' && $i === 5) {
                $label = 'Archetype zonder bewijs';
            }
            $slots[$i] = [
                'post_tekst' => (string) ($tp['post_tekst'] ?? ''),
                'label' => $label,
            ];
        }
        $out[$merk] = $slots;
    }

    return $out;
}

/** @param array<string, mixed> $run */
function hba_diagnose_build_tool(array $run, string $merk, string $label): array
{
    $flags = is_array($run['flags'] ?? null) ? $run['flags'] : [];
    $removed = is_array($run['flags_verwijderd'] ?? null) ? $run['flags_verwijderd'] : [];
    foreach ($removed as $item) {
        $f = (string) ($item['flag'] ?? '');
        if ($f !== '') {
            $flags = array_values(array_filter($flags, static fn (string $x): bool => $x !== $f));
        }
    }
    $bc = isset($run['brandContribution']) ? (float) $run['brandContribution'] : 0.0;
    $merkBew = stripos($label, 'Neutraal') !== false ? 'nee' : 'ja';
    $kernModel = null;
    if ($merkBew === 'ja' && in_array('category_fallback', $flags, true)) {
        $kernModel = 'ja';
    }

    return [
        'merkbewering' => $merkBew,
        'kern_categorietaal' => $kernModel,
        'contentrol' => stripos($label, 'Neutraal') !== false ? 'educatie' : 'product',
        'flag_citaten' => is_array($run['flag_citaten'] ?? null) ? $run['flag_citaten'] : [],
        'scores' => [
            'brand_contribution' => ['score' => $bc],
            'architecture_risk' => ['flags' => $flags],
        ],
    ];
}

function hba_diagnose_judge(string $resolved, ?string $verwacht): string
{
    if ($verwacht === null || $verwacht === '') {
        return 'niet_beoordeeld';
    }

    return $resolved === $verwacht ? 'goed' : 'fout';
}
