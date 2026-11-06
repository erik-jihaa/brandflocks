#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/web/api/campagne-preview-ankers.php';

$slots = [];
for ($i = 0; $i < 20; $i++) {
    $fases = ['aankondiging', 'waarom', 'hoe_werkt', 'bewust_niet', 'bewijs', 'actie'];
    $slots[] = [
        'slotId' => 's' . $i,
        'publishDate' => sprintf('2026-11-%02d', $i + 1),
        'slotIndex' => $i + 1,
        'faseKey' => $fases[$i % count($fases)],
    ];
}
$questions = [
    ['id' => 'q1', 'question' => 'Waarom Brandpunt?'],
    ['id' => 'q2', 'question' => 'Wat is vers gebrand?'],
    ['id' => 'q3', 'question' => 'Hoe kies ik bonen?'],
];
$assigned = ce_campagne_preview_allocate_geo_ankers($slots, $questions);
$ok = count($assigned) === 7;

$with = ce_campagne_preview_opdracht_with_geo_anker('Leg uit wat het is.', 'Waarom Brandpunt?');
$ok = $ok && str_contains($with, CE_CAMPAGNE_GEO_ANKER_PREFIX);
$stripped = ce_campagne_preview_strip_geo_anker($with);
$ok = $ok && $stripped === 'Leg uit wat het is.';

$six = array_slice($slots, 0, 6);
$assigned6 = ce_campagne_preview_allocate_geo_ankers($six, $questions);
$ok = $ok && count($assigned6) === 2;

$empty = ce_campagne_preview_allocate_geo_ankers($slots, []);
$ok = $ok && $empty === [];

echo $ok ? "P5 selftest OK\n" : "P5 selftest FAIL count=" . count($assigned) . " six=" . count($assigned6) . "\n";
exit($ok ? 0 : 1);
