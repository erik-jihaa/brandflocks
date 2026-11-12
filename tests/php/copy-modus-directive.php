<?php

declare(strict_types=1);

require_once __DIR__ . '/../../web/api/copy-modus.php';
require_once __DIR__ . '/../../web/api/channel-copy-constraints.php';

function fail(string $msg): void
{
    fwrite(STDERR, "copy-modus-directive FAIL: {$msg}\n");
    exit(1);
}

if (ce_copy_modus_from_template_key('lancering') !== CE_COPY_MODUS_SELL) {
    fail('lancering → sell_activatie');
}
if (ce_copy_modus_from_template_key('touch') !== CE_COPY_MODUS_MERK) {
    fail('touch → merkbouw_bewijs');
}
if (ce_copy_modus_from_template_key('serie') !== CE_COPY_MODUS_MERK) {
    fail('serie/tell → merkbouw_bewijs');
}

$sellLines = ce_copy_modus_directive_lines(CE_COPY_MODUS_SELL);
if ($sellLines === [] || !str_contains($sellLines[0], 'activatie')) {
    fail('sell directive mist activatie');
}
$merkLines = ce_copy_modus_directive_lines(CE_COPY_MODUS_MERK);
if (!str_contains($merkLines[0], 'geen verkooptaal')) {
    fail('merk directive mist verkoopverbod');
}

$li = ce_channel_copy_constraint_line('linkedin', 'post');
if ($li === '' || !str_contains($li, 'LinkedIn-post')) {
    fail('linkedin post constraint');
}
$ig = ce_channel_copy_constraint_line('instagram', null);
if ($ig === '' || !str_contains(strtolower($ig), 'instagram')) {
    fail('instagram default textvorm');
}

echo "copy-modus-directive OK\n";
