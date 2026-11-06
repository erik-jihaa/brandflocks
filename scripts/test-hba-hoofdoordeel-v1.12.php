#!/usr/bin/env php
<?php
/** Lokale check v1.12 hoofdoordeel-volgorde (geen LLM). */
declare(strict_types=1);

require_once __DIR__ . '/../web/api/hba-prompt.php';

function tool(array $over): array
{
    return array_merge([
        'merkbewering' => 'ja',
        'kern_categorietaal' => 'nee',
        'contentrol' => 'service',
        'scores' => [
            'brand_contribution' => ['score' => 2],
            'architecture_risk' => ['flags' => []],
        ],
    ], $over);
}

$cases = [
    ['neutraal tips', tool(['merkbewering' => 'nee', 'kern_categorietaal' => null, 'scores' => ['brand_contribution' => ['score' => 1], 'architecture_risk' => ['flags' => ['category_fallback']]]]), 'neutraal'],
    ['foe op kop zonder kernCat', tool(['kern_categorietaal' => 'ja', 'scores' => ['brand_contribution' => ['score' => 3], 'architecture_risk' => ['flags' => ['foe_vervaging', 'category_fallback']]]]), 'tegenkracht', ['foe_on_kern' => true]],
    ['merkbewering nee + regelbreuk', tool(['merkbewering' => 'nee', 'scores' => ['brand_contribution' => ['score' => 1], 'architecture_risk' => ['flags' => ['constraint_breach']]]]), 'regelbreuk'],
];

$fail = 0;
foreach ($cases as $i => $case) {
    $name = $case[0];
    $t = $case[1];
    $want = $case[2];
    $ctx = $case[3] ?? [];
    $got = ce_hba_resolve_hoofdoordeel($t, $ctx);
    if ($got !== $want) {
        echo "FAIL {$name}: got {$got}, want {$want}\n";
        $fail++;
    } else {
        echo "OK {$name} -> {$got}\n";
    }
}

exit($fail > 0 ? 1 : 0);
