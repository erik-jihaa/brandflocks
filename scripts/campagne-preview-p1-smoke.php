#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P1 smoke: campagne preview → N slots (default Brandpunt 8 LI + 12 IG = 20).
 *
 * Usage: php scripts/campagne-preview-p1-smoke.php [--client-id=UUID]
 */

$root = dirname(__DIR__);
require_once $root . '/web/api/bootstrap.php';
require_once $root . '/web/api/campagne-preview-repository.php';

$clientId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--client-id=')) {
        $clientId = substr($arg, strlen('--client-id='));
    }
}

$pdo = ce_db();
if ($clientId === null) {
    $clientId = ce_default_client_id($pdo);
}

if ($clientId === null) {
    fwrite(STDERR, "Geen client_id\n");
    exit(1);
}

$start = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$end = (new DateTimeImmutable($start))->modify('+27 days')->format('Y-m-d');

$result = ce_campagne_preview_create($pdo, [
    'clientId' => $clientId,
    'title' => 'P1 smoke — lancering blend',
    'startDate' => $start,
    'endDate' => $end,
    'channels' => ['linkedin' => 8, 'instagram' => 12],
    'goal' => 'beide',
    'templateKey' => 'lancering',
]);

$count = count($result['slots'] ?? []);
$expected = 20;
echo "campaignId: {$result['campaignId']}\n";
echo "slots: {$count} (expected {$expected})\n";

exit($count === $expected ? 0 : 2);
