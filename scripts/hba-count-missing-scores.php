#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Tel artikelen met self_review maar zonder hba_scores op huidige brand_narrative.
 *
 *   php scripts/hba-count-missing-scores.php production
 */

$root = dirname(__DIR__);
require_once $root . '/web/api/bootstrap.php';
require_once $root . '/web/api/hba-repository.php';
require_once $root . '/web/api/client-repository.php';

$env = $argv[1] ?? 'production';
$envFile = $root . '/.env.' . $env;
if (!is_file($envFile)) {
    fwrite(STDERR, "Geen {$envFile}\n");
    exit(1);
}

$pdo = ce_db();
if (!ce_table_exists($pdo, 'articles') || !ce_table_exists($pdo, 'hba_scores')) {
    echo "Geen articles/hba_scores\n";
    exit(0);
}

$clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$total = 0;

foreach ($clients as $client) {
    $clientId = (string) ($client['id'] ?? '');
    if ($clientId === '') {
        continue;
    }
    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!is_array($narrative)) {
        continue;
    }
    $narrativeId = (string) ($narrative['id'] ?? '');
    if ($narrativeId === '') {
        continue;
    }

    $sql = <<<SQL
        SELECT a.id, a.slot_id, a.updated_at
        FROM articles a
        INNER JOIN content_slots cs ON cs.id = a.slot_id AND cs.client_id = :client_id
        WHERE a.self_review IS NOT NULL
          AND TRIM(a.self_review) <> ''
          AND TRIM(COALESCE(a.content_ai, '')) <> ''
          AND NOT EXISTS (
            SELECT 1 FROM hba_scores hs
            WHERE hs.post_id = a.id AND hs.brand_narrative_id = :narrative_id
          )
        ORDER BY a.updated_at DESC
        LIMIT 50
        SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $clientId, 'narrative_id' => $narrativeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === []) {
        continue;
    }

    echo ($client['name'] ?? $clientId) . ' — ' . count($rows) . " artikel(en) met self_review zonder score\n";
    foreach (array_slice($rows, 0, 10) as $row) {
        echo '  ' . ($row['id'] ?? '') . ' slot=' . ($row['slot_id'] ?? '') . "\n";
    }
    $total += count($rows);
}

echo "\nTotaal (max 50 per tenant): {$total}\n";
