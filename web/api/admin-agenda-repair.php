<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/fastlane-repository.php';
require_once __DIR__ . '/review-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$body = ce_read_json_body();
$clientId = ce_resolve_client_id($pdo, trim((string) ($body['clientId'] ?? '')));
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 404);
}

/** @var list<string> */
$resubmitThemeIds = $body['resubmitThemeIds'] ?? ['bceab6c8-1a26-42aa-b226-2cefc935ed1d'];
if (!is_array($resubmitThemeIds)) {
    $resubmitThemeIds = [];
}

/** @var list<array{themeId?: string, slotId?: string, cycleWeek?: string}> */
$fixWeekAssignments = $body['fixWeekAssignments'] ?? [];
if (is_array($fixWeekAssignments)) {
    foreach ($fixWeekAssignments as $fix) {
        if (!is_array($fix)) {
            continue;
        }
        $cycleWeek = trim((string) ($fix['cycleWeek'] ?? ''));
        if ($cycleWeek === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cycleWeek)) {
            continue;
        }
        $phaseKey = ce_phase_key_from_date($cycleWeek) ?? '';
        if ($phaseKey === '') {
            continue;
        }

        $themeId = trim((string) ($fix['themeId'] ?? ''));
        if ($themeId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $themeId)) {
            $pdo->prepare('UPDATE themes SET cycle_week = :cycle_week WHERE id = :id AND client_id = :client_id')
                ->execute(['cycle_week' => $cycleWeek, 'id' => $themeId, 'client_id' => $clientId]);
        }

        $slotId = trim((string) ($fix['slotId'] ?? ''));
        if ($slotId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
            $pdo->prepare('UPDATE content_slots SET phase_key = :phase_key WHERE id = :id AND client_id = :client_id')
                ->execute(['phase_key' => $phaseKey, 'id' => $slotId, 'client_id' => $clientId]);
        }
    }
}

$phaseKeysUpdated = 0;
if (ce_table_exists($pdo, 'content_slots') && ce_table_exists($pdo, 'themes')) {
    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT s.id AS slot_id, s.phase_key, t.cycle_week
FROM content_slots s
INNER JOIN themes t ON t.id = s.theme_id
WHERE s.client_id = :client_id
  AND t.cycle_week IS NOT NULL
  AND TRIM(t.cycle_week) <> ''
SQL
    );
    $stmt->execute(['client_id' => $clientId]);
    $update = $pdo->prepare('UPDATE content_slots SET phase_key = :phase_key WHERE id = :id');
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $phaseKey = ce_phase_key_from_date((string) ($row['cycle_week'] ?? ''));
        if ($phaseKey === null || $phaseKey === '') {
            continue;
        }
        $current = trim((string) ($row['phase_key'] ?? ''));
        if ($current === $phaseKey) {
            continue;
        }
        $update->execute(['phase_key' => $phaseKey, 'id' => $row['slot_id']]);
        $phaseKeysUpdated++;
    }
}

$resubmitted = [];
foreach ($resubmitThemeIds as $themeId) {
    $themeId = trim((string) $themeId);
    if ($themeId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $themeId)) {
        continue;
    }

    $themeStmt = $pdo->prepare('SELECT id, title, client_id, cycle_week FROM themes WHERE id = :id LIMIT 1');
    $themeStmt->execute(['id' => $themeId]);
    $themeRow = $themeStmt->fetch() ?: null;

    $slotId = ce_create_slot_from_theme($pdo, $themeId, $clientId) ?? '';
    if ($slotId === '' && $themeRow) {
        $pdo->prepare(
            "UPDATE themes SET status = 'proposed', feedback = 'none' WHERE id = :id"
        )->execute(['id' => $themeId]);
        $result = ce_submit_theme($pdo, $themeId);
        $slotId = ce_create_slot_from_theme($pdo, $themeId, $clientId) ?? '';
    } else {
        $result = ['webhookTriggered' => false, 'webhookResult' => null];
    }

    $resubmitted[] = [
        'themeId' => $themeId,
        'themeFound' => $themeRow !== null,
        'themeTitle' => $themeRow ? (string) ($themeRow['title'] ?? '') : null,
        'slotId' => $slotId !== '' ? $slotId : null,
        'webhookTriggered' => (bool) ($result['webhookTriggered'] ?? false),
        'webhookOk' => (bool) (($result['webhookResult']['ok'] ?? false)),
    ];
}

ce_json_response([
    'ok' => true,
    'phaseKeysUpdated' => $phaseKeysUpdated,
    'resubmitted' => $resubmitted,
]);
