<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/client-context.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$body = ce_read_json_body();
$clientId = ce_resolve_client_id($pdo, trim((string) ($body['clientId'] ?? $body['client_id'] ?? '')));
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 404);
}

$weeksRaw = $body['weeks'] ?? $body['isoWeeks'] ?? [];
if (!is_array($weeksRaw) || $weeksRaw === []) {
    ce_json_error('weeks is verplicht (array van ISO-weeknummers)', 400);
}

$force = (bool) ($body['force'] ?? false);
$isoYear = isset($body['isoYear']) ? (int) $body['isoYear'] : null;

$cycleWeeks = [];
foreach ($weeksRaw as $weekNum) {
    $weekNum = (int) $weekNum;
    if ($weekNum < 1 || $weekNum > 53) {
        continue;
    }
    $cycleWeeks[] = ce_cycle_monday_for_iso_week($weekNum, $isoYear);
}

if ($cycleWeeks === []) {
    ce_json_error('Geen geldige weeknummers', 400);
}

$deleted = [];
$skipped = [];

foreach ($cycleWeeks as $cycleWeek) {
    $stmt = $pdo->prepare(
        "SELECT id, title, motivation, rationale, status
         FROM themes
         WHERE client_id = :client_id
           AND DATE(cycle_week) = DATE(:cycle_week)
           AND COALESCE(status, 'proposed') NOT IN ('deleted', 'converted', 'archived')"
    );
    $stmt->execute(['client_id' => $clientId, 'cycle_week' => $cycleWeek]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $status = strtolower(trim((string) ($row['status'] ?? 'proposed')));
        if ($status !== 'proposed' && $status !== '') {
            $skipped[] = [
                'id' => (string) ($row['id'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'cycleWeek' => $cycleWeek,
                'reason' => 'status_' . $status,
            ];
            continue;
        }

        if (!$force && !ce_theme_is_planning_placeholder($row)) {
            $skipped[] = [
                'id' => (string) ($row['id'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'cycleWeek' => $cycleWeek,
                'reason' => 'not_placeholder',
            ];
            continue;
        }

        $pdo->prepare(
            'UPDATE themes SET status = :status, feedback = :feedback
             WHERE id = :id AND client_id = :client_id'
        )->execute([
            'status' => ce_pick_status_value($pdo, 'themes', ['deleted', 'archived', 'proposed']),
            'feedback' => 'deleted',
            'id' => (string) ($row['id'] ?? ''),
            'client_id' => $clientId,
        ]);
        $deleted[] = [
            'id' => (string) ($row['id'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'cycleWeek' => $cycleWeek,
        ];
    }
}

$ce01 = ce_trigger_ce01_theme_director($clientId, $cycleWeeks);

ce_json_response([
    'ok' => ($ce01['ok'] ?? false) === true,
    'clientId' => $clientId,
    'weeks' => array_map('intval', $weeksRaw),
    'cycleWeeks' => $cycleWeeks,
    'deleted' => $deleted,
    'skipped' => $skipped,
    'ce01' => $ce01,
]);
