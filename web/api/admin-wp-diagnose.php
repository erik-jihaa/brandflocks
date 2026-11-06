<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/wp-diagnose-repository.php';
require_once __DIR__ . '/client-context.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$clientId = trim((string) ($_GET['client_id'] ?? ''));
$company = trim((string) ($_GET['company'] ?? ''));

if ($clientId === '' && $company !== '') {
    $stmt = $pdo->prepare(
        'SELECT id FROM clients WHERE name LIKE :q OR contact_email LIKE :q ORDER BY name ASC LIMIT 1'
    );
    $stmt->execute(['q' => '%' . $company . '%']);
    $clientId = trim((string) ($stmt->fetchColumn() ?: ''));
}

if ($clientId === '') {
    $clientId = ce_resolve_client_id($pdo) ?? '';
}

try {
    ce_json_response(ce_wp_diagnose_for_client($pdo, $clientId));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
