<?php

declare(strict_types=1);

require_once __DIR__ . '/testset-scores-repository.php';

if (ce_is_production()) {
    ce_json_error('Niet beschikbaar', 404);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$stap = trim((string) ($_GET['stap'] ?? '1-nulmeting'));
if ($stap === '') {
    $stap = '1-nulmeting';
}

$pdo = ce_db();

if ($method === 'GET') {
    ce_json_response([
        'ok' => true,
        'stap' => $stap,
        'scorers' => CE_TESTSET_BLIND_SCORERS,
        'entries' => ce_testset_blind_scores_list($pdo, $stap),
    ]);
}

if ($method === 'POST') {
    try {
        $body = ce_read_json_body();
        if (!isset($body['stap']) || trim((string) $body['stap']) === '') {
            $body['stap'] = $stap;
        }
        ce_json_response(ce_testset_blind_score_save($pdo, $body));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    }
}

ce_json_error('Method not allowed', 405);
