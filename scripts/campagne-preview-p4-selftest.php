#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/web/api/campagne-preview-plan-check.php';

$lanceringSlots = [];
foreach (['aankondiging', 'aankondiging', 'aankondiging', 'waarom', 'waarom', 'waarom', 'waarom', 'hoe_werkt', 'hoe_werkt', 'hoe_werkt', 'hoe_werkt', 'bewust_niet', 'bewust_niet', 'bewijs', 'bewijs', 'bewijs', 'bewijs', 'actie', 'actie', 'actie'] as $fase) {
    $rol = in_array($fase, ['aankondiging', 'actie'], true) ? 'activatie' : 'merk';
    $lanceringSlots[] = ['rol' => $rol, 'faseKey' => $fase, 'opdracht' => 'Blend lancering'];
}

$balansOk = ce_campagne_plan_check_php_balans($lanceringSlots, 'lancering');
$ok = is_array($balansOk) && ($balansOk['signaal'] ?? true) === false;

$serieActivatie = array_fill(0, 6, ['rol' => 'activatie']);
$balansSerie = ce_campagne_plan_check_php_balans($serieActivatie, 'serie');
$ok = $ok && is_array($balansSerie) && ($balansSerie['signaal'] ?? false) === true;

$arch = [
    'regels' => 'Wij verkopen geen koffie die langer dan zes weken geleden is gebrand, ook niet met korting.',
    'wat_bewust_niet' => 'aanbiedingen en stapelkorting op oudere voorraad',
];
$regel = ce_campagne_plan_check_php_regelbreuk(
    $arch,
    ['wat' => 'Oude blend met 20% korting op koffie die al weken ligt.'],
    [['opdracht' => 'Actie: 20% korting op restpartij']],
    ['title' => 'Korting op oude voorraad'],
);
$ok = $ok && is_array($regel) && ($regel['signaal'] ?? false) === true;

$geenRegel = ce_campagne_plan_check_php_regelbreuk(
    $arch,
    ['wat' => 'Nieuwe blend, vers gebrand.'],
    [['opdracht' => 'Aankondiging van de nieuwe blend']],
    ['title' => 'Lancering blend'],
);
$ok = $ok && $geenRegel === null;

$ontbreekt = ce_campagne_plan_check_php_ontbreekt(['ontbreekt' => ['Waarom deze blend bestaat']]);
$ok = $ok && is_array($ontbreekt) && ($ontbreekt['soort'] ?? '') === 'doelpagina';

$catalog = ce_campagne_plan_check_php_signalen(
    $arch,
    ['wat' => 'Nieuwe blend'],
    $lanceringSlots,
    ['title' => 'Lancering blend'],
    'lancering',
    ['wat_je_doet', 'waarom'],
);
$soorten = array_map(static fn (array $s): string => (string) ($s['soort'] ?? ''), $catalog);
$ok = $ok && $soorten === CE_CAMPAGNE_PLAN_CHECK_SOORTEN;

echo $ok ? "P4 selftest OK\n" : "P4 selftest FAIL\n";
exit($ok ? 0 : 1);
