#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/web/api/campagne-preview-repository.php';

$fail = static function (string $msg): void {
    fwrite(STDERR, "campagne-slot-titles FAIL: {$msg}\n");
    exit(1);
};

$marker = json_encode([
    'campagnePreview' => true,
    'campaignId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
    'platform' => 'linkedin',
    'slotIndex' => 2,
    'totalForPlatform' => 6,
    'faseKey' => 'aankondiging',
], JSON_THROW_ON_ERROR);

$row = [
    'id' => '11111111-2222-3333-4444-555555555555',
    'theme' => 'Oud — LinkedIn 1/6',
    'input_human' => $marker,
];

$parsed = ce_campagne_marker_from_slot_row($row);
if ($parsed === null || ($parsed['campaignId'] ?? '') !== 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee') {
    $fail('marker parse');
}

$enriched = ce_campagne_enrich_slot_row($row);
if (trim((string) ($enriched['campaign_id'] ?? '')) !== 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee') {
    $fail('enrich campaign_id');
}
if (strtolower(trim((string) ($enriched['campaign_platform'] ?? ''))) !== 'linkedin') {
    $fail('enrich platform');
}

echo "campagne-slot-titles OK\n";
