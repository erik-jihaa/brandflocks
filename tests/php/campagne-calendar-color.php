#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Kalenderkleur: backfill volgt de oude colorIndex (eerste keer in agenda-volgorde, % 3).
 * Nieuwe keuze mag 0–3; leeg is geen kleur.
 */
require_once dirname(__DIR__, 2) . '/web/api/campagne-calendar-color.php';

$fail = static function (string $msg): void {
    fwrite(STDERR, "campagne-calendar-color FAIL: {$msg}\n");
    exit(1);
};

$colors = ce_campagne_calendar_colors_for_row_order(['a', 'a', '', 'b', 'c', 'd']);
if (($colors['a'] ?? null) !== 0 || ($colors['b'] ?? null) !== 1 || ($colors['c'] ?? null) !== 2 || ($colors['d'] ?? null) !== 0) {
    $fail('eerste keer in volgorde moet 0, 1, 2, 0 zijn (herhaling slaat over, vierde wikkelt % 3)');
}
if (count($colors) !== 4) {
    $fail('lege id telt niet mee');
}

if (ce_campagne_normalize_calendar_color('3') !== 3 || ce_campagne_normalize_calendar_color(0) !== 0) {
    $fail('0 en 3 zijn geldige kleuren');
}

foreach ([null, '', 4, -1] as $raw) {
    try {
        ce_campagne_normalize_calendar_color($raw);
        $fail('ongeldige kleur moet weigeren: ' . var_export($raw, true));
    } catch (InvalidArgumentException) {
    }
}

echo "campagne-calendar-color OK\n";
