<?php

declare(strict_types=1);

/**
 * Vernieuwt wizard-document-suggesties (inclusief kernformuleringen) uit opgeslagen documenttekst.
 * GET ?client_id=...&force=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-wizard-document-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$clientId = trim((string) ($_GET['client_id'] ?? $_GET['clientId'] ?? ''));
if ($clientId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('client_id ontbreekt of is ongeldig', 400);
}

$force = filter_var($_GET['force'] ?? '0', FILTER_VALIDATE_BOOLEAN);

$result = ce_hba_wizard_document_refresh_suggestions($clientId, $force);
ce_json_response($result);
