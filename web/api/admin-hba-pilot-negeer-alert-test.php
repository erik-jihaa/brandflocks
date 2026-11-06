<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/hba-pilot-alert-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (ce_is_production()) {
    ce_json_error('Alleen op staging/local', 403);
}

$slotId = trim((string) ($_GET['slotId'] ?? 'auto'));
$demo = isset($_GET['demo']) && (string) $_GET['demo'] !== '0';

$result = ce_hba_pilot_negeer_alert_test(ce_db(), $slotId);
if (($result['ok'] ?? false) === false && ($result['error'] ?? '') === 'test_slot_not_found' && $demo) {
    $mail = ce_hba_pilot_send_negeer_kritiek_mail([
        'clientId' => 'demo',
        'clientName' => 'Brandpunt Koffie (demo)',
        'slotId' => 'demo',
        'articleId' => 'demo',
        'postTitel' => 'HBA-test: Breekt de regel',
        'hoofdoordeel' => 'regelbreuk',
        'citaat' => 'Black Friday: alle zakken van twee maanden geleden voor de halve prijs!',
        'uitleg' => 'Demo-testmail A3 — geen echte Negeer-klik.',
        'hintId' => 'demo-' . gmdate('YmdHis'),
    ]);
    ce_json_response([
        'ok' => true,
        'demo' => true,
        'mail' => $mail,
        'recipients' => ce_hba_pilot_alert_recipients(),
    ]);
    exit;
}

ce_json_response($result);
