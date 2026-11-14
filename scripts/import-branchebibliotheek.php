#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Importeer branchelijst.csv (102) + 20 gecureerde JSON-bestanden naar branche_catalog / branche_library.
 *
 *   php scripts/import-branchebibliotheek.php
 *   php scripts/import-branchebibliotheek.php --dry-run
 *   php scripts/import-branchebibliotheek.php --dir=/pad/naar/Branchebibliotheek-voor-Erik-2nov
 */

require_once __DIR__ . '/../web/api/bootstrap.php';
require_once __DIR__ . '/../web/api/branche-bibliotheek-repository.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$dirOverride = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--dir=')) {
        $dirOverride = substr($arg, 6);
    }
}

try {
    $dir = ce_branche_bibliotheek_resolve_dir($dirOverride);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo 'Branchebibliotheek: ' . $dir . "\n";
if ($dryRun) {
    echo "(dry-run — geen database-wijzigingen)\n";
}

$pdo = null;
if (!$dryRun) {
    $pdo = ce_db();
    if (!($pdo instanceof PDO)) {
        fwrite(STDERR, "Geen databaseverbinding; controleer .env\n");
        exit(1);
    }
}

$stats = ce_branche_bibliotheek_import($pdo, $dir, $dryRun);

echo "Catalogusregels: {$stats['catalog']}\n";
echo "Gecureerd (JSON): {$stats['curated']}\n";
if ($stats['errors'] !== []) {
    echo "Fouten:\n";
    foreach ($stats['errors'] as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

echo "Import voltooid.\n";