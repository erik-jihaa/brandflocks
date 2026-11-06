<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/calendar-oauth-helper.php';

$sourceId = trim((string) ($_GET['source_id'] ?? ''));
if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    ce_json_error('Invalid source_id', 400);
}

$pdo = ce_db();
$result = ce_calendar_list_result_for_source($pdo, $sourceId);
$calendars = $result['items'];
$error = trim((string) ($result['error'] ?? ''));

if ($calendars === [] && !ce_calendar_get_refresh_token($pdo, $sourceId)) {
    ce_json_response([
        'configured' => false,
        'calendars' => [],
        'note' => 'Koppel eerst je Google-account.',
    ]);
}

ce_json_response([
    'configured' => true,
    'calendars' => array_map(
        static fn (array $row): array => [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'primary' => (bool) ($row['primary'] ?? false),
        ],
        $calendars,
    ),
    'note' => $error !== ''
        ? $error
        : ($calendars === [] ? 'Geen agenda\'s gevonden op dit Google-account.' : null),
]);
