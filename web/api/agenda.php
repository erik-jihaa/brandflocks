<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/tone-styles.php';
require_once __DIR__ . '/content-rhythm-repository.php';
require_once __DIR__ . '/publications-repository.php';
require_once __DIR__ . '/agenda-portfolio-signals-repository.php';
require_once __DIR__ . '/testset-repository.php';
require_once __DIR__ . '/campagne-preview-templates.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
ce_ensure_agenda_scheduling_schema($pdo);
ce_ensure_article_editor_columns($pdo);
ce_ensure_themes_schema($pdo);
ce_ensure_slot_pipeline_statuses($pdo);
ce_fix_pipeline_slot_statuses($pdo);
ce_ensure_headline_human_column($pdo);

$feedbackJoin = '';
$feedbackSelect = '0 AS open_feedback_count';
if (ce_table_exists($pdo, 'article_feedback')) {
    $feedbackJoin = <<<'SQL'
LEFT JOIN (
    SELECT slot_id, COUNT(*) AS open_feedback_count
    FROM article_feedback
    WHERE status = 'open'
    GROUP BY slot_id
) fb ON fb.slot_id = s.id
SQL;
    $feedbackSelect = 'COALESCE(fb.open_feedback_count, 0) AS open_feedback_count';
}

$articleJoin = ce_sql_latest_article_join('s');
$themeJoin = '';
$themeSelect = 'NULL AS theme_cycle_week, NULL AS theme_editor_suggested';
if (ce_table_exists($pdo, 'themes') && ce_table_has_column($pdo, 'content_slots', 'theme_id')) {
    $themeJoin = 'LEFT JOIN themes t ON t.id = s.theme_id';
    $themeEditorSelect = ce_table_has_column($pdo, 'themes', 'editor_suggested')
        ? 't.editor_suggested AS theme_editor_suggested'
        : 'NULL AS theme_editor_suggested';
    $themeSelect = "t.cycle_week AS theme_cycle_week, {$themeEditorSelect}";
}

$originSelect = ce_table_has_column($pdo, 'content_slots', 'origin')
    ? 's.origin AS slot_origin'
    : "NULL AS slot_origin";

$rhythmSelect = ce_table_has_column($pdo, 'content_slots', 'rhythm_role')
    ? 's.rhythm_role AS slot_rhythm_role'
    : 'NULL AS slot_rhythm_role';
$publishDateSelect = ce_table_has_column($pdo, 'content_slots', 'publish_date')
    ? 's.publish_date AS slot_publish_date'
    : 'NULL AS slot_publish_date';
$publishTimeSelect = ce_table_has_column($pdo, 'content_slots', 'publish_time')
    ? 's.publish_time AS slot_publish_time'
    : 'NULL AS slot_publish_time';
$campaignIdSelect = ce_table_has_column($pdo, 'content_slots', 'campaign_id')
    ? 's.campaign_id AS slot_campaign_id'
    : 'NULL AS slot_campaign_id';
$campaignPlatformSelect = ce_table_has_column($pdo, 'content_slots', 'campaign_platform')
    ? 's.campaign_platform AS slot_campaign_platform'
    : 'NULL AS slot_campaign_platform';
$campaignFaseSelect = ce_table_has_column($pdo, 'content_slots', 'campaign_fase')
    ? 's.campaign_fase AS slot_campaign_fase'
    : 'NULL AS slot_campaign_fase';
$campaignOpdrachtSelect = ce_table_has_column($pdo, 'content_slots', 'campaign_opdracht')
    ? 's.campaign_opdracht AS slot_campaign_opdracht'
    : 'NULL AS slot_campaign_opdracht';
$campaignTemplateSelect = ce_table_exists($pdo, 'campaigns') && ce_table_has_column($pdo, 'campaigns', 'template_key')
    ? 'camp.template_key AS campaign_template_key'
    : 'NULL AS campaign_template_key';
$campaignJoin = '';
$campaignTitleSelect = 'NULL AS campaign_title';
if (ce_table_exists($pdo, 'campaigns') && ce_table_has_column($pdo, 'content_slots', 'campaign_id')) {
    $campaignJoin = 'LEFT JOIN campaigns camp ON camp.id = s.campaign_id';
    $campaignTitleSelect = 'camp.title AS campaign_title';
}
$articlePlatformSelect = ce_table_has_column($pdo, 'articles', 'platform_final')
    ? 'a.platform_final AS article_platform'
    : 'NULL AS article_platform';
$articleHumanSelect = ce_table_has_column($pdo, 'articles', 'content_human')
    ? 'a.content_human AS article_content_human'
    : 'NULL AS article_content_human';
$articleAiSelect = ce_table_has_column($pdo, 'articles', 'content_ai')
    ? 'a.content_ai AS article_content_ai'
    : 'NULL AS article_content_ai';

$sql = <<<SQL
SELECT
    s.id AS slot_id,
    s.phase_key,
    s.theme,
    s.status AS slot_status,
    {$originSelect},
    {$rhythmSelect},
    {$publishDateSelect},
    {$publishTimeSelect},
    {$campaignIdSelect},
    {$campaignPlatformSelect},
    {$campaignFaseSelect},
    {$campaignOpdrachtSelect},
    {$campaignTemplateSelect},
    {$campaignTitleSelect},
    a.id AS article_id,
    a.headline,
    a.headline_human,
    a.status AS article_status,
    a.editor_suggested,
    a.editor_final,
    {$articlePlatformSelect},
    {$articleHumanSelect},
    {$articleAiSelect},
    {$themeSelect},
    $feedbackSelect
FROM content_slots s
{$articleJoin}
{$campaignJoin}
{$themeJoin}
$feedbackJoin
WHERE COALESCE(s.status, '') NOT IN ('archived', 'archive')
  AND COALESCE(a.status, '') NOT IN ('archived', 'archive')
  AND COALESCE(s.origin, '') NOT IN ('baseline', 'hba_testpost')
SQL;

if (ce_table_exists($pdo, 'themes') && ce_table_has_column($pdo, 'content_slots', 'theme_id')) {
    $sql .= ce_sql_exclude_testset_freeze_slots('s', 't');
} elseif (ce_table_has_column($pdo, 'content_slots', 'input_human')) {
    $sql .= ce_sql_exclude_testset_freeze_slots('s', null);
}

$clientId = ce_default_client_id($pdo);
if ($clientId !== null) {
    ce_content_rhythm_assign_roles_for_client($pdo, $clientId);
}
$scope = ce_client_scope_sql($pdo, 'content_slots', 's', $clientId);
$sql .= $scope['sql'] . "\nORDER BY s.phase_key ASC, s.theme ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($scope['params']);
$rows = $stmt->fetchAll();
$phaseWeekMap = ce_build_phase_week_map($rows);
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
            'weekRange' => $weekNumber !== null ? ce_week_range_label((int) $weekNumber) : null,
            'cycleWeek' => $cycleWeek !== '' ? $cycleWeek : null,
            'rows' => [],
        ];
    }

    $theme = trim((string) ($row['theme'] ?? ''));
    $campaignId = trim((string) ($row['slot_campaign_id'] ?? ''));
    if ($campaignId !== '') {
        require_once __DIR__ . '/campagne-preview-repository.php';
        $resolved = ce_campagne_resolve_slot_title($pdo, [
            'id' => (string) ($row['slot_id'] ?? ''),
            'theme' => $theme,
            'campaign_id' => $campaignId,
            'campaign_platform' => trim((string) ($row['slot_campaign_platform'] ?? '')),
            'campaign_fase' => trim((string) ($row['slot_campaign_fase'] ?? '')),
            'publish_date' => trim((string) ($row['slot_publish_date'] ?? '')),
        ]);
        if ($theme !== '') {
            $title = $theme;
        } elseif ($resolved !== '') {
            $title = $resolved;
        } else {
            $title = ce_article_headline($row) ?: 'Untitled';
        }
    } else {
        $title = ce_article_headline($row) ?: ($theme !== '' ? $theme : 'Untitled');
    }
    $openFeedbackCount = (int) ($row['open_feedback_count'] ?? 0);
    $articleStatus = ce_status_to_ui($row['article_status'] ?? null);
    $slotStatus = ce_status_to_ui($row['slot_status'] ?? null);
    $uiStatus = ce_resolve_editorial_status(
        $row['article_status'] ?? null,
        $row['slot_status'] ?? null,
        $openFeedbackCount,
    );
    $phase = ce_resolve_agenda_phase(
        $row['slot_status'] ?? null,
        $row['article_id'] ?? null,
        isset($row['slot_origin']) ? (string) $row['slot_origin'] : null,
    );
    $editorName = trim((string) ($row['editor_final'] ?? ''));
    if ($editorName === '') {
        $editorName = trim((string) ($row['editor_suggested'] ?? ''));
    }
    if ($editorName === '') {
        $editorName = trim((string) ($row['theme_editor_suggested'] ?? ''));
    }

    $rhythmRole = trim((string) ($row['slot_rhythm_role'] ?? ''));
    $groups[$week]['rows'][] = [
        'slotId' => $row['slot_id'],
        'articleId' => $row['article_id'],
        'title' => $title,
        'articleStatus' => $articleStatus,
        'slotStatus' => $slotStatus,
        'status' => $uiStatus,
        'phaseLabel' => ce_agenda_phase_label($phase, ce_editor_first_name($editorName)),
        'phase' => $phase,
        'editor' => $editorName !== '' ? $editorName : null,
        'editorFirstName' => ce_editor_first_name($editorName) ?: null,
        'slotOrigin' => !empty($row['slot_origin']) ? (string) $row['slot_origin'] : null,
        'fullDeleteOnRemove' => strtolower(trim((string) ($row['slot_origin'] ?? ''))) === 'fastlane',
        'rhythmRole' => $rhythmRole !== '' ? $rhythmRole : null,
        'rhythmRoleLabel' => $rhythmRole !== '' ? ce_content_rhythm_role_label($rhythmRole) : null,
        'publishDate' => trim((string) ($row['slot_publish_date'] ?? '')) ?: null,
        'publishTime' => ce_agenda_format_publish_time($row['slot_publish_time'] ?? null),
        'campaignId' => trim((string) ($row['slot_campaign_id'] ?? '')) ?: null,
        'campaignTitle' => trim((string) ($row['campaign_title'] ?? '')) ?: null,
        'platform' => ce_agenda_row_platform_label($row),
        'campaignFaseKey' => trim((string) ($row['slot_campaign_fase'] ?? '')) ?: null,
        'campaignFaseLabel' => ce_campagne_preview_fase_label(
            trim((string) ($row['campaign_template_key'] ?? '')),
            trim((string) ($row['slot_campaign_fase'] ?? '')),
        ),
        'campaignOpdracht' => trim((string) ($row['slot_campaign_opdracht'] ?? '')) ?: null,
        'articleWritten' => trim((string) ($row['article_content_human'] ?? '')) !== ''
            || trim((string) ($row['article_content_ai'] ?? '')) !== '',
    ];
}

/** @param array<string, mixed> $row */
function ce_agenda_row_platform_label(array $row): ?string
{
    $fromSlot = trim((string) ($row['slot_campaign_platform'] ?? ''));
    if ($fromSlot !== '') {
        return ce_platform_to_ui($fromSlot);
    }
    $fromArticle = trim((string) ($row['article_platform'] ?? ''));

    return $fromArticle !== '' ? ce_platform_to_ui($fromArticle) : null;
}

$currentWeek = ce_week_start();
$horizonMondays = ce_upcoming_cycle_weeks(ce_client_planning_horizon_weeks($pdo, $clientId));
$groups = ce_merge_agenda_theme_horizon_weeks(
    $groups,
    ce_fetch_theme_weeks($pdo, $clientId),
    $currentWeek,
    $horizonMondays,
);

$postsPerWeek = $clientId !== null ? ce_client_posts_per_week($pdo, $clientId) : 2;

foreach ($groups as &$group) {
    $monday = trim((string) ($group['cycleWeek'] ?? ''));
    if ($monday === '' && isset($group['weekNumber']) && (int) $group['weekNumber'] > 0) {
        $range = ce_iso_week_range((int) $group['weekNumber']);
        $monday = $range['start']->format('Y-m-d');
        $group['cycleWeek'] = $monday;
    }
    $index = 0;
    foreach ($group['rows'] as &$row) {
        $publishDate = trim((string) ($row['publishDate'] ?? ''));
        if ($publishDate === '' && $monday !== '') {
            $publishDate = (string) ce_agenda_default_publish_date($monday, $index, $postsPerWeek);
        }
        $row['publishDate'] = $publishDate !== '' ? $publishDate : null;
        $index++;
    }
    unset($row);
}
unset($group);

foreach ($groups as &$group) {
    foreach ($group['rows'] as &$row) {
        $articleId = (string) ($row['articleId'] ?? '');
        $publications = $articleId !== '' ? ce_fetch_publications_for_article($pdo, $articleId) : [];
        $pubStatus = ce_publication_calendar_status($publications);
        $row['publications'] = $publications;
        $row['publicationStatus'] = $pubStatus !== '' ? $pubStatus : null;
        $row['calendarPhase'] = ce_agenda_calendar_phase(
            (string) ($row['phase'] ?? ''),
            (string) ($row['status'] ?? ''),
            $pubStatus,
        );
        $campaignId = trim((string) ($row['campaignId'] ?? ''));
        $written = ($row['articleWritten'] ?? false) === true;
        if ($campaignId !== '' && !$written && !in_array((string) $row['calendarPhase'], ['published', 'failed', 'publish'], true)) {
            $row['calendarPhase'] = 'theme';
            $row['phase'] = 'theme';
            $row['phaseLabel'] = 'Thema';
        }
    }
    unset($row);
}
unset($group);

ce_json_response([
    'todayLabel' => ce_today_agenda_label(),
    'currentWeek' => $currentWeek,
    'postsPerWeek' => $postsPerWeek,
    'planningHorizonWeeks' => ce_client_planning_horizon_weeks($pdo, $clientId),
    'horizonMondays' => $horizonMondays,
    'portfolioSignals' => ce_agenda_portfolio_signals($pdo, $clientId),
    'groups' => array_values($groups),
]);
