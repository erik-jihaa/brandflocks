<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/mail-helper.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$body = ce_read_json_body();
$to = strtolower(trim((string) ($body['to'] ?? '')));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    ce_json_error('to: geldig e-mailadres vereist', 400);
}

$product = ce_product_name();
$from = ce_mail_from_address();
$envelope = ce_mail_envelope_address($from);
$result = ce_send_mail(
    $to,
    $product . ' — mailtest',
    "Dit is een testmail van {$product}.\n\nFrom-header: {$from}\nEnvelope (-f): {$envelope}\n",
);

ce_json_response([
    'ok' => $result['ok'],
    'to' => $to,
    'from' => $result['from'] ?? ce_mail_from_address(),
    'envelope' => $result['envelope'] ?? ce_mail_envelope_address(),
    'error' => $result['error'] ?? null,
]);
