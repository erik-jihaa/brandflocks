<?php

declare(strict_types=1);

require_once __DIR__ . '/../../web/api/hba-derived-branche-log.php';

$failed = 0;
function fail(string $msg): void
{
    global $failed;
    $failed++;
    fwrite(STDERR, "FAIL: {$msg}\n");
}

if (ce_hba_derived_branche_normalize('  Loyaliteits  Programma  ') !== 'loyaliteits programma') {
    fail('normalize moet spaties en case normaliseren');
}

$curatedCatalog = ['is_gecureerd' => 1, 'branche_label' => 'Accountancy voor het mkb'];
$openCatalog = ['is_gecureerd' => 0, 'branche_label' => 'Administratiekantoren voor zzp en mkb'];
$derivedRow = ['bron' => 'afgeleid', 'branche_label' => 'Loyaliteit'];
$curatedRow = ['bron' => 'gecureerd', 'branche_label' => 'Accountancy voor het mkb'];

if (ce_hba_derived_branche_should_log($curatedCatalog, null)) {
    fail('gecureerde catalogusregel mag niet in het logboek');
}
if (ce_hba_derived_branche_should_log(null, $curatedRow)) {
    fail('gecureerde library-rij mag niet in het logboek');
}
if (!ce_hba_derived_branche_should_log($openCatalog, null)) {
    fail('open catalogusregel moet wel gelogd (afleider)');
}
if (!ce_hba_derived_branche_should_log(null, $derivedRow)) {
    fail('afgeleide library-rij moet wel gelogd');
}
if (!ce_hba_derived_branche_should_log(null, null)) {
    fail('onbekende naam moet gelogd');
}

$header = ce_hba_derived_branche_csv_header();
if (!str_starts_with($header, 'nr;branche;afbakening;markt;ronde1_volgorde;status;bestand;versie_instructie')) {
    fail('CSV-header moet branchelijst.csv-vorm hebben, kreeg: ' . $header);
}

$line = ce_hba_derived_branche_csv_line(1, [
    'typed_label' => 'Loyaliteitsprogramma\'s',
    'hit_count' => 3,
    'last_seen_at' => '2026-11-14 12:00:00',
], null);
$parts = explode(';', $line);
if (count($parts) < 10) {
    fail('CSV-regel te kort: ' . $line);
}
if ($parts[0] !== '1' || $parts[1] !== "Loyaliteitsprogramma's" || $parts[5] !== 'open' || $parts[8] !== '3') {
    fail('CSV-regel velden onjuist: ' . $line);
}

$csvRows = ce_branche_bibliotheek_catalog_from_csv(null, 120);
if (count($csvRows) !== 102) {
    fail('CSV-fallback moet 102 regels geven, kreeg ' . count($csvRows));
}
$koffie = null;
foreach ($csvRows as $row) {
    if (($row['branche_label'] ?? '') === 'Koffiebranderijen met eigen merk (specialty coffee)') {
        $koffie = $row;
        break;
    }
}
if ($koffie === null || empty($koffie['is_gecureerd'])) {
    fail('koffiebranderijen moet als gecureerd in de CSV-fallback staan');
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} fout(en)\n");
    exit(1);
}

echo "OK — derived-log regels + CSV-vorm + 102-fallback.\n";