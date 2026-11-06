<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/testset-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$freezeLabel = trim((string) ($body['freezeLabel'] ?? '2026-10-02'));
$slotIds = $body['slotIds'] ?? CE_TESTSET_SOURCE_SLOT_IDS;
if (!is_array($slotIds)) {
    ce_json_error('slotIds moet een array zijn', 400);
}

$normalized = [];
foreach ($slotIds as $id) {
    $id = trim((string) $id);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) {
        ce_json_error('Ongeldig slot-id: ' . $id, 400);
    }
    $normalized[] = $id;
}

if ($normalized === []) {
    ce_json_error('Geen slotIds', 400);
}

try {
    ce_json_response(ce_testset_freeze_slots(ce_db(), $normalized, $freezeLabel));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 409);
}
