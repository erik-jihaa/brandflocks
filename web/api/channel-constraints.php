<?php

declare(strict_types=1);

require_once __DIR__ . '/channel-copy-constraints.php';

ce_json_response([
    'ok' => true,
    ...ce_channel_constraints_public_payload(),
]);