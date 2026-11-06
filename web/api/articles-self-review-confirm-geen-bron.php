<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/self-review-confirm-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$citaat = trim((string) ($body['citaat'] ?? ''));
$bronType = trim((string) ($body['bronType'] ?? $body['bron_type'] ?? ''));
$bronWaarde = trim((string) ($body['bronWaarde'] ?? $body['bron_waarde'] ?? ''));

if ($citaat === '') {
    ce_json_error('citaat is verplicht', 400);
}

$result = ce_self_review_confirm_geen_bron(ce_db(), $slotId, $citaat, $bronType, $bronWaarde);
if (($result['ok'] ?? false) !== true) {
    ce_json_error((string) ($result['error'] ?? 'Bevestigen mislukt'), 400);
}

ce_json_response([
    'ok' => true,
    'selfReview' => $result['selfReview'] ?? null,
]);
