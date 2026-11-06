<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/publications-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PATCH' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$slotId = trim((string) ($_GET['slot_id'] ?? ''));
if ($slotId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('slot_id ontbreekt of is ongeldig', 400);
}

$body = ce_read_json_body();
$publishDate = trim((string) ($body['publishDate'] ?? $body['publish_date'] ?? ''));
$publishTime = trim((string) ($body['publishTime'] ?? $body['publish_time'] ?? ''));
$platform = trim((string) ($body['platform'] ?? $body['platformKey'] ?? ''));

try {
    ce_json_response(ce_schedule_slot(
        ce_db(),
        $slotId,
        $publishDate,
        $publishTime !== '' ? $publishTime : null,
        $platform !== '' ? $platform : null,
    ));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}