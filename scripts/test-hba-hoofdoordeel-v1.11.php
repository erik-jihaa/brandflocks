<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/api/hba-prompt.php';

$failed = 0;

function hba_assert(string $label, bool $ok): void
{
    global $failed;
    if ($ok) {
        fwrite(STDOUT, "OK  $label\n");
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL $label\n");
}

function tool(array $flags, array $extra = []): array
{
    return array_merge([
        'contentrol' => 'positionering',
        'kern_categorietaal' => 'nee',
        'flag_citaten' => [],
        'scores' => [
            'brand_contribution' => ['score' => 8],
            'architecture_risk' => ['flags' => $flags],
        ],
    ], $extra);
}

// 1. regelbreuk wint
hba_assert(
    'regelbreuk uit constraint_breach',
    ce_hba_resolve_hoofdoordeel(tool(['constraint_breach', 'foe_vervaging'])) === 'regelbreuk'
);

// 2. tegenkracht: foe op kop, kern niet categorietaal
hba_assert(
    'tegenkracht bij foe_on_kern',
    ce_hba_resolve_hoofdoordeel(tool(['foe_vervaging']), ['foe_on_kern' => true]) === 'tegenkracht'
);

// 3. categorietaal wint ook bij foe in bijzin
hba_assert(
    'categorietaal bij kern_categorietaal=ja + foe bijzin',
    ce_hba_resolve_hoofdoordeel(
        tool(['foe_vervaging', 'category_fallback'], ['kern_categorietaal' => 'ja']),
        ['foe_on_kern' => false]
    ) === 'categorietaal'
);

// 4. foe op kop maar kern is categorietaal → categorietaal (Stelwerk 5)
hba_assert(
    'Stelwerk 5: kern categorietaal wint van foe op kop',
    ce_hba_resolve_hoofdoordeel(
        tool(['foe_vervaging', 'category_fallback'], ['kern_categorietaal' => 'ja']),
        ['foe_on_kern' => true]
    ) === 'categorietaal'
);

// 5. model-hoofdoordeel wordt genegeerd
hba_assert(
    'model-hoofdoordeel genegeerd',
    ce_hba_resolve_hoofdoordeel(
        tool(['foe_vervaging'], ['hoofdoordeel' => 'op_merk']),
        ['foe_on_kern' => true]
    ) === 'tegenkracht'
);

// 6. op_merk vs neutraal
hba_assert(
    'op_merk bij positionering',
    ce_hba_resolve_hoofdoordeel(tool([])) === 'op_merk'
);
hba_assert(
    'neutraal bij service + BC 4',
    ce_hba_resolve_hoofdoordeel(tool([], [
        'contentrol' => 'service',
        'scores' => [
            'brand_contribution' => ['score' => 4],
            'architecture_risk' => ['flags' => []],
        ],
    ])) === 'neutraal'
);
hba_assert(
    'op_merk bij service + BC 7',
    ce_hba_resolve_hoofdoordeel(tool([], [
        'contentrol' => 'service',
        'scores' => [
            'brand_contribution' => ['score' => 7],
            'architecture_risk' => ['flags' => []],
        ],
    ])) === 'op_merk'
);

// 7. foe drop als tekst zich afzet
$drop = ce_hba_drop_foe_vervaging_tegen_tegenkracht(tool(
    ['foe_vervaging'],
    ['flag_citaten' => ['foe_vervaging' => 'Geen Black Friday, maar de regel van zes weken.']]
));
hba_assert('foe_vervaging weg bij afzetten', !in_array('foe_vervaging', ce_hba_flags_from_tool($drop['tool']), true));

$keep = ce_hba_drop_foe_vervaging_tegen_tegenkracht(tool(
    ['foe_vervaging'],
    ['flag_citaten' => ['foe_vervaging' => 'Onze wachtlijst is in één maand opgelost.']]
));
hba_assert('foe_vervaging blijft bij resultaatvieren', in_array('foe_vervaging', ce_hba_flags_from_tool($keep['tool']), true));

// 8. 100%-check
$applied = ce_hba_apply_hoofdoordeel_filter(tool(['foe_vervaging']), ['foe_on_kern' => true]);
hba_assert('volgt_uit_flags na apply', $applied['hoofdoordeel_volgt_uit_flags'] === true);
hba_assert(
    'recompute gelijk',
    ce_hba_hoofdoordeel_volgt_uit_flags($applied['tool'], ['foe_on_kern' => true])
);

// 9. promptversie + schema
hba_assert('prompt v1.11', CE_HBA_PROMPT_VERSION === 'v1.11');
$schema = ce_hba_score_tool_schema();
$props = $schema['input_schema']['properties'];
hba_assert('geen hoofdoordeel in schema', !isset($props['hoofdoordeel']));
hba_assert('wel kern_categorietaal in schema', isset($props['kern_categorietaal']));

if ($failed > 0) {
    fwrite(STDERR, "\n$failed test(s) failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAlle checks groen.\n");
exit(0);
