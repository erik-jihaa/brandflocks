<?php

declare(strict_types=1);

require_once __DIR__ . '/../web/api/mappers.php';

$cases = [
    [
        'Ritmrol 2 = inzicht. Trending signaal: De markt verschuift.',
        'De markt verschuift.',
    ],
    [
        'Ritmrol: Inzicht. Trending signaal: Kort verhaal.',
        'Kort verhaal.',
    ],
    [
        'Ritmrol: Autoriteit. Dit blijft staan.',
        'Dit blijft staan.',
    ],
    [
        'Geen prefix hier.',
        'Geen prefix hier.',
    ],
];

$failed = 0;
foreach ($cases as [$input, $expected]) {
    $got = ce_strip_theme_story_leadin($input);
    if ($got !== $expected) {
        echo "FAIL\n  in:  {$input}\n  exp: {$expected}\n  got: {$got}\n";
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} case(s) failed\n");
    exit(1);
}

echo "OK " . count($cases) . " cases\n";
