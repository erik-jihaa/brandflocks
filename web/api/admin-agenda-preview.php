<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/theme-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$clientId = trim((string) ($_GET['client_id'] ?? ''));
if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('Invalid client_id', 400);
}

ce_ensure_themes_schema($pdo);

$themeWeeks = ce_fetch_theme_weeks($pdo, $clientId);
$horizon = ce_client_planning_horizon_weeks($pdo, $clientId);
$horizonMondays = ce_upcoming_cycle_weeks($horizon);
$currentWeek = ce_week_start();

// Minimal slot-week numbers (same query shape as agenda.php)
$stmt = $pdo->prepare(
    "SELECT s.phase_key, t.cycle_week AS theme_cycle_week
     FROM content_slots s
     LEFT JOIN themes t ON t.id = s.theme_id
     LEFT JOIN (
         SELECT a1.slot_id, a1.status AS article_status
         FROM articles a1
         INNER JOIN (
             SELECT slot_id, MAX(id) AS max_id FROM articles GROUP BY slot_id
         ) latest ON latest.max_id = a1.id
     ) a ON a.slot_id = s.id
     WHERE s.client_id = :client_id
       AND COALESCE(s.status, '') NOT IN ('archived', 'archive')
       AND COALESCE(a.article_status, '') NOT IN ('archived', 'archive')
     ORDER BY s.phase_key ASC"
);
$stmt->execute(['client_id' => $clientId]);
$rows = $stmt->fetchAll() ?: [];
$phaseWeekMap = ce_build_phase_week_map($rows);
$slotWeekNumbers = [];
foreach ($rows as $row) {
    $cycleWeek = trim((string) ($row['theme_cycle_week'] ?? ''));
    if ($cycleWeek !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cycleWeek)) {
        $parsedWeek = ce_phase_key_from_date($cycleWeek);
        $weekNumber = $parsedWeek !== null && (int) $parsedWeek > 0 ? (int) $parsedWeek : null;
    } else {
        $weekNumber = $phaseWeekMap[trim((string) ($row['phase_key'] ?? ''))] ?? null;
    }
    if ($weekNumber !== null && (int) $weekNumber > 0) {
        $slotWeekNumbers[(int) $weekNumber] = true;
    }
}

$merged = ce_merge_agenda_theme_horizon_weeks([], $themeWeeks, $currentWeek, $horizonMondays);
$mergedLabels = array_values(array_map(
    static fn (array $group): string => (string) ($group['week'] ?? ''),
    $merged,
));

// Full agenda simulation (slot groups + theme merge)
$groups = [];
foreach ($rows as $row) {
    $cycleWeek = trim((string) ($row['theme_cycle_week'] ?? ''));
    $phaseKey = $row['phase_key'] ?? null;
    if ($cycleWeek !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cycleWeek)) {
        $parsedWeek = ce_phase_key_from_date($cycleWeek);
        $weekNumber = $parsedWeek !== null && (int) $parsedWeek > 0 ? (int) $parsedWeek : null;
        $week = $weekNumber !== null ? ('Week ' . $weekNumber) : ce_week_label($phaseKey, null);
    } else {
        $weekNumber = $phaseWeekMap[trim((string) ($phaseKey ?? ''))] ?? null;
        $week = ce_week_label($phaseKey, $weekNumber);
    }
    if (!isset($groups[$week])) {
        $groups[$week] = [
            'week' => $week,
            'weekNumber' => $weekNumber,
        ];
    }
}
$fullGroups = ce_merge_agenda_theme_horizon_weeks($groups, $themeWeeks, $currentWeek, $horizonMondays);
foreach ($fullGroups as &$previewGroup) {
    $monday = trim((string) ($previewGroup['cycleWeek'] ?? ''));
    if ($monday === '' && isset($previewGroup['weekNumber']) && (int) $previewGroup['weekNumber'] > 0) {
        $range = ce_iso_week_range((int) $previewGroup['weekNumber']);
        $monday = $range['start']->format('Y-m-d');
        $previewGroup['cycleWeek'] = $monday;
    }
}
unset($previewGroup);
$fullLabels = array_values(array_map(
    static fn (array $group): string => (string) ($group['week'] ?? ''),
    $fullGroups,
));

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'currentWeek' => $currentWeek,
    'planningHorizonWeeks' => $horizon,
    'horizonMondays' => $horizonMondays,
    'themeWeeks' => $themeWeeks,
    'slotWeekNumbers' => array_keys($slotWeekNumbers),
    'mergedThemeOnlyWeeks' => $mergedLabels,
    'fullAgendaWeeks' => $fullLabels,
    'stillRequired' => array_combine(
        $horizonMondays,
        array_map(
            static fn (string $monday): int => ce_themes_still_required_for_week($pdo, $clientId, $monday),
            $horizonMondays,
        ),
    ),
]);
