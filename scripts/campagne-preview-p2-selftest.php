#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/web/api/campagne-preview-templates.php';

$lancering = ce_campagne_preview_resolve_phases('lancering', null);
$seq20 = ce_campagne_preview_allocate_phases($lancering, 20);
$counts = [];
foreach ($seq20 as $phase) {
    $k = (string) $phase['key'];
    $counts[$k] = ($counts[$k] ?? 0) + 1;
}
$expected = [
    'aankondiging' => 3,
    'waarom' => 4,
    'hoe_werkt' => 4,
    'bewust_niet' => 2,
    'bewijs' => 4,
    'actie' => 3,
];
$ok = $counts === $expected;

$serie = ce_campagne_preview_resolve_phases('serie', null);
$seq6 = ce_campagne_preview_allocate_phases($serie, 6);
$serieCounts = [];
foreach ($seq6 as $phase) {
    $k = (string) $phase['key'];
    $serieCounts[$k] = ($serieCounts[$k] ?? 0) + 1;
}
$ok = $ok && count($seq6) === 6 && array_sum($serieCounts) === 6;

$tuesdays = ce_campagne_preview_weekly_dates('2026-10-27', 2, 6, '2026-12-31');
$ok = $ok && count($tuesdays) === 6;
foreach ($tuesdays as $d) {
    $ok = $ok && (int) (new DateTimeImmutable($d))->format('N') === 2;
}

$disabled = ce_campagne_preview_resolve_phases('lancering', ['disabledKeys' => ['actie']]);
$seqNoActie = ce_campagne_preview_allocate_phases($disabled, 10);
$hasActie = false;
foreach ($seqNoActie as $phase) {
    if (($phase['key'] ?? '') === 'actie') {
        $hasActie = true;
    }
}
$ok = $ok && !$hasActie && count($seqNoActie) === 10;

echo $ok ? "P2 selftest OK\n" : "P2 selftest FAIL\n";
echo 'lancering 20: ' . json_encode($counts) . "\n";
echo 'serie dinsdagen: ' . implode(', ', $tuesdays) . "\n";

exit($ok ? 0 : 1);
