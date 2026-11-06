<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/calendar-oauth-helper.php';

$config = ce_calendar_oauth_config();
if ($config === null) {
    ce_json_response([
        'configured' => false,
        'note' => 'Google OAuth ontbreekt — stel GOOGLE_CLIENT_ID en GOOGLE_CLIENT_SECRET in (.env). Schakel ook de Google Calendar API in.',
    ]);
}

ce_json_response([
    'configured' => true,
    'redirectUri' => $config['redirectUri'],
]);
