<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/slot-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$slotId = (string) ($_GET['slot_id'] ?? '');
if ($slotId === '') {
    ce_json_error('slot_id ontbreekt', 400);
}

$pdo = ce_db();

try {
    ce_json_response(ce_submit_slot_research($pdo, $slotId));
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
} catch (Throwable $e) {
    $message = $e->getMessage();
    if (getenv('CE_DEBUG') === '1') {
        $message .= ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
    ce_json_error($message, 500);
}
