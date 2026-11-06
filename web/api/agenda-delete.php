<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/client-context.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['DELETE', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo);
if ($clientId === null || trim($clientId) === '') {
    ce_json_error('Login vereist — log opnieuw in en kies je account', 401);
}

if (!ce_slot_belongs_to_client($pdo, $slotId, $clientId)) {
    ce_json_error('Slot niet gevonden', 404);
}

try {
    ce_json_response(ce_delete_agenda_slot($pdo, $slotId));
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
