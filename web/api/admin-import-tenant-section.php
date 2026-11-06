<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/tenant-bundle-import.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$bundle = is_array($body['bundle'] ?? null) ? $body['bundle'] : null;
$clientId = trim((string) ($body['clientId'] ?? $body['client_id'] ?? ($bundle['clientId'] ?? $bundle['client']['id'] ?? '')));
$sectionsRaw = $body['sections'] ?? ['agenda', 'findability'];
$sections = is_array($sectionsRaw) ? $sectionsRaw : array_map('trim', explode(',', (string) $sectionsRaw));

if ($clientId === '' || !is_array($bundle)) {
    ce_json_error('clientId en bundle zijn verplicht', 400);
}

try {
    $applied = ce_import_tenant_bundle_sections(ce_db(), $clientId, $bundle, $sections);
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
} catch (Throwable $e) {
    ce_json_error('Import mislukt: ' . $e->getMessage(), 500);
}

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'sections' => $sections,
    'applied' => $applied,
]);
