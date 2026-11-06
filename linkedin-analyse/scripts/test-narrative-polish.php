#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Snelle unit-check voor li_narrative_polish_dutch() — geen API-call.
 * Gebruik: php scripts/test-narrative-polish.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

/** @return list<array{in: string, want: string}> */
function li_narrative_polish_fixtures(): array
{
    return [
        [
            'in' => "Merkidentiteit (implicit in 'samen vieren', 'samenwerking')",
            'want' => 'Merkidentiteit',
        ],
        [
            'in' => "Merkidentiteit (impliciet in 'samen vieren', 'samenwerking')",
            'want' => 'Merkidentiteit',
        ],
        [
            'in' => "Positionering (explicit in 'kwaliteit')",
            'want' => 'Positionering',
        ],
        [
            'in' => "Positionering (implicit/explicit in 'kwaliteit')",
            'want' => 'Positionering',
        ],
        [
            'in' => 'De boodschap voelt genuiner dan gemiddeld',
            'want' => 'De boodschap voelt echter dan gemiddeld',
        ],
        [
            'in' => 'Lage engagement op de posts',
            'want' => 'Lage reacties op de posts',
        ],
    ];
}

$failed = 0;
foreach (li_narrative_polish_fixtures() as $i => $case) {
    $got = li_narrative_polish_dutch($case['in']);
    if ($got !== $case['want']) {
        $failed++;
        fwrite(STDERR, "FAIL #{$i}\n  in:   {$case['in']}\n  want: {$case['want']}\n  got:  {$got}\n");
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failure(s).\n");
    exit(1);
}

echo 'PASS — ' . count(li_narrative_polish_fixtures()) . " polish cases\n";
exit(0);
