<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/hba-determinism-repository.php';
require_once __DIR__ . '/uuid-helper.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();

$clientId = trim((string) ($_GET['client_id'] ?? '016384af-b26e-4415-aa9c-abc46b804798'));
$runLlm = ($_GET['run'] ?? '') === '1';
$limit = max(1, min(10, (int) ($_GET['limit'] ?? CE_HBA_DETERMINISM_DEFAULT_ARTICLES)));
$runsPerArticle = max(2, min(5, (int) ($_GET['runs'] ?? CE_HBA_DETERMINISM_DEFAULT_RUNS)));
$maxSpread = (float) ($_GET['max_spread'] ?? CE_HBA_DETERMINISM_MAX_SPREAD);
$batchOffset = isset($_GET['offset']) ? (int) $_GET['offset'] : null;
$runIndex = isset($_GET['run_index']) ? (int) $_GET['run_index'] : 0;
$batchMode = ($_GET['batch'] ?? '') === '1' || $batchOffset !== null;

if (!$runLlm) {
    $articles = ce_hba_determinism_pick_articles($pdo, $clientId, $limit);
    $settings = ce_hba_determinism_client_settings($pdo, $clientId);
    ce_json_response([
        'ok' => true,
        'phase' => 3,
        'section' => 'determinism',
        'runLlm' => false,
        'allPass' => null,
        'client' => [
            'id' => $clientId,
            'scoreDisplayMode' => $settings['displayMode'],
            'scoreAveraging' => $settings['averaging'],
            'determinismStatus' => $settings['status'],
            'determinismMeasuredAt' => $settings['measuredAt'],
        ],
        'config' => [
            'articleLimit' => $limit,
            'runsPerArticle' => $runsPerArticle,
            'maxSpread' => $maxSpread,
            'temperature' => CE_HBA_CONTENT_SCORE_TEMPERATURE,
        ],
        'articlesPicked' => count($articles),
        'articleSample' => array_map(static fn (array $row): array => [
            'id' => (string) ($row['id'] ?? ''),
            'headline' => (string) ($row['headline'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
        ], array_slice($articles, 0, 3)),
        'note' => 'Gebruik run=1 voor live scoring (10 artikelen × 3 runs, force=true)',
    ]);
}

try {
    if ($batchMode) {
        $offset = $batchOffset ?? 0;
        $payload = ce_hba_determinism_run_measurement_batch(
            $pdo,
            $clientId,
            $offset,
            max(0, min($runsPerArticle - 1, $runIndex)),
            $limit,
            $runsPerArticle,
            $maxSpread,
        );
    } else {
        $payload = ce_hba_determinism_run_measurement(
            $pdo,
            $clientId,
            $limit,
            $runsPerArticle,
            $maxSpread,
            true,
        );
    }
    $payload['phase'] = 3;
    $payload['runLlm'] = true;
    ce_json_response($payload);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
