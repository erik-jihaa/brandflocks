<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/api/hba-prompt.php';
require_once dirname(__DIR__) . '/web/api/ce03-self-review-repository.php';

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

$def = ce_hba_merkbewering_definitie();
hba_assert('merkbewering definitie niet leeg', $def !== '');
hba_assert('definitie noemt steunt_op/context', str_contains($def, 'steunt_op') || str_contains($def, 'voortbouwt'));

$fixed = ce_ce03_stap3a_brandpunt_text_fix(
    'Dan zetten wij een vers gezette kop voor je neer en leg je uit wat je proeft.'
);
hba_assert('stap3a tekstfix', str_contains($fixed, 'leggen wij uit'));

$norm = ce_ce03_normalize_merk_beweringen([
    ['citaat' => 'Proefochtend.', 'bron' => 'bewijsveld', 'steunt_op' => 'Elke eerste donderdag.'],
]);
hba_assert('normalize steunt_op', ($norm[0]['steunt_op'] ?? '') !== '');

if ($failed > 0) {
    fwrite(STDERR, "{$failed} test(s) gefaald\n");
    exit(1);
}

fwrite(STDOUT, "Alle tests OK\n");
