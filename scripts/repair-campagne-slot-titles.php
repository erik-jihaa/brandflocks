#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Backfill campaign_id uit slot-markers en herteken n/m-titels.
 *
 *   php scripts/repair-campagne-slot-titles.php
 *   php scripts/repair-campagne-slot-titles.php <campaign-uuid>
 */
$root = dirname(__DIR__);
require_once $root . '/web/api/bootstrap.php';
require_once $root . '/web/api/campagne-preview-repository.php';

$pdo = ce_db();
$campaignArg = isset($argv[1]) ? trim((string) $argv[1]) : '';

if ($campaignArg !== '') {
    ce_campagne_repair_campaign_slots($pdo, $campaignArg);
    echo "Repaired campaign {$campaignArg}\n";
    exit(0);
}

if (!ce_table_exists($pdo, 'campaigns')) {
    fwrite(STDERR, "No campaigns table\n");
    exit(1);
}

$ids = $pdo->query('SELECT id FROM campaigns ORDER BY created_at DESC')->fetchAll(PDO::FETCH_COLUMN);
$count = 0;
foreach ($ids as $cid) {
    $cid = trim((string) $cid);
    if ($cid === '') {
        continue;
    }
    ce_campagne_repair_campaign_slots($pdo, $cid);
    ++$count;
}

echo "Repaired {$count} campaign(s)\n";
