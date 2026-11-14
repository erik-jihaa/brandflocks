<?php

declare(strict_types=1);

require_once __DIR__ . '/../../web/api/branche-bibliotheek-repository.php';

$dir = ce_branche_bibliotheek_resolve_dir();
$rows = ce_branche_bibliotheek_parse_csv($dir . '/branchelijst.csv');

if (count($rows) !== 102) {
    fwrite(STDERR, 'Verwacht 102 catalogusregels, kreeg ' . count($rows) . "\n");
    exit(1);
}

$gecureerd = 0;
foreach ($rows as $row) {
    if ($row['is_gecureerd']) {
        $gecureerd++;
        $jsonPath = $dir . '/Branches/' . $row['json_filename'];
        if (!is_readable($jsonPath)) {
            fwrite(STDERR, 'Ontbrekend: ' . $row['json_filename'] . "\n");
            exit(1);
        }
        $doc = json_decode((string) file_get_contents($jsonPath), true);
        if (!is_array($doc)) {
            fwrite(STDERR, 'Ongeldig JSON: ' . $row['json_filename'] . "\n");
            exit(1);
        }
        $payload = ce_branche_bibliotheek_library_payload_from_json($doc, $row);
        if ($payload['branche_code'] === '' || $payload['branche_label'] === '') {
            fwrite(STDERR, 'Lege code/label: ' . $row['json_filename'] . "\n");
            exit(1);
        }
    }
}

if ($gecureerd !== 20) {
    fwrite(STDERR, 'Verwacht 20 gecureerde rijen, kreeg ' . $gecureerd . "\n");
    exit(1);
}

echo "OK — 102 catalogusregels, {$gecureerd} gecureerde JSON-bestanden.\n";