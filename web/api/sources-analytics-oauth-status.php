<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/analytics-oauth-helper.php';

$config = ce_analytics_oauth_config();
if ($config === null) {
    ce_json_response([
        'configured' => false,
        'note' => 'Google OAuth ontbreekt — stel GOOGLE_CLIENT_ID en GOOGLE_CLIENT_SECRET in (.env).',
    ]);
}

ce_json_response([
    'configured' => true,
    'redirectUri' => $config['redirectUri'],
]);
