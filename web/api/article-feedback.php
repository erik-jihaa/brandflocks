<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/feedback-repository.php';

$slotId = $_GET['slot_id'] ?? '';
$feedbackId = $_GET['feedback_id'] ?? '';

$feedbackIdValid = preg_match('/^[0-9a-f]{32}$/i', $feedbackId) === 1
    || preg_match('/^[0-9a-f-]{36}$/i', $feedbackId) === 1;
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId) || !$feedbackIdValid) {
    ce_json_error('Invalid id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'PATCH') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$status = (string) ($body['status'] ?? '');
if (!in_array($status, ['okay', 'deleted', 'open'], true)) {
    ce_json_error('Invalid status', 400);
}

$pdo = ce_db();
$updated = ce_update_feedback_status($pdo, $feedbackId, $status, $slotId);
if (!$updated) {
    ce_json_error('Feedback not found', 404);
}

ce_json_response($updated);
