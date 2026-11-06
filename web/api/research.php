<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$slotId = (string) ($_GET['slot_id'] ?? '');
if ($slotId === '') {
    ce_json_error('slot_id ontbreekt', 400);
}

$pdo = ce_db();

try {
    ce_json_response(ce_fetch_slot_research($pdo, $slotId));
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
} catch (Throwable $e) {
    error_log('research.php: ' . $e->getMessage());
    ce_json_error('Research laden mislukt', 500);
}
