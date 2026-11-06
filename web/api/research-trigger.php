<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$slotId = (string) ($_GET['slot_id'] ?? '');
if ($slotId === '') {
    ce_json_error('slot_id ontbreekt', 400);
}

$pdo = ce_db();

try {
    require_once __DIR__ . '/slot-repository.php';
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'slot', $slotId);

    if (!ce_slot_exists($pdo, $slotId)) {
        throw new RuntimeException('Slot niet gevonden');
    }

    $themeId = '';
    $stmt = $pdo->prepare('SELECT theme_id FROM content_slots WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $slotId]);
    $themeId = trim((string) ($stmt->fetchColumn() ?: ''));

    // Fire-and-forget: CE-02 duurt vaak >60s; synchroon wachten veroorzaakt proxy-timeout
    // (browser: NetworkError). De UI pollt daarna op nieuwe research-items.
    $result = ce_trigger_ce02_research($themeId !== '' ? $themeId : null, $slotId, true);
    if (($result['ok'] ?? false) !== true) {
        $message = is_string($result['error'] ?? null)
            ? $result['error']
            : 'CE-02 webhook mislukt';
        ce_json_error($message, 502);
    }

    ce_json_response(array_merge($result, ce_fetch_slot_research($pdo, $slotId)));
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
} catch (Throwable $e) {
    error_log('research-trigger.php: ' . $e->getMessage());
    ce_json_error('Research starten mislukt', 500);
}
