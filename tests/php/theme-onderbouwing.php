<?php

declare(strict_types=1);

/**
 * Drempels thema-onderbouwing — tabel uit drempels-thema-onderbouwing.md
 * (0,1,2,3,4,7,8,11,12,20 fragmenten → verwacht aantal + label).
 */

require_once dirname(__DIR__, 2) . '/web/api/theme-onderbouwing.php';

$cases = [
    0 => 0,
    1 => 0,
    2 => 1,
    3 => 1,
    4 => 2,
    7 => 3,
    8 => 4,
    11 => 5,
    12 => 6,
    20 => 6,
];

$failed = 0;
foreach ($cases as $fragments => $expected) {
    $got = ce_theme_proposal_count_from_fragments($fragments);
    if ($got !== $expected) {
        fwrite(STDERR, "FAIL fragments={$fragments}: got {$got}, expected {$expected}\n");
        $failed++;
    }
}

$label0 = ce_theme_licht_onderbouwd_label(0);
$label1 = ce_theme_licht_onderbouwd_label(1);
$label2 = ce_theme_licht_onderbouwd_label(2);

if ($label0 !== 'licht onderbouwd thema') {
    fwrite(STDERR, "FAIL label0: " . var_export($label0, true) . "\n");
    $failed++;
}
if ($label1 !== 'licht onderbouwd — gebaseerd op 1 bron, nog niet elders bevestigd') {
    fwrite(STDERR, "FAIL label1: " . var_export($label1, true) . "\n");
    $failed++;
}
if ($label2 !== null) {
    fwrite(STDERR, "FAIL label2 should be null\n");
    $failed++;
}

if (!ce_theme_is_licht_onderbouwd(0) || !ce_theme_is_licht_onderbouwd(1) || ce_theme_is_licht_onderbouwd(2)) {
    fwrite(STDERR, "FAIL is_licht_onderbouwd\n");
    $failed++;
}

if ($failed > 0) {
    fwrite(STDERR, "theme-onderbouwing: {$failed} failure(s)\n");
    exit(1);
}

echo "theme-onderbouwing OK\n";
