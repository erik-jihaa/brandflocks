#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Reset pipeline content (themes, slots, articles, research) while keeping
 * clients (strategy), sources, and newsletter_items.
 *
 * Usage: php scripts/reset-content.php --confirm
 */

require_once __DIR__ . '/../web/api/bootstrap.php';
require_once __DIR__ . '/../web/api/content-reset.php';

if (!in_array('--confirm', $argv ?? [], true)) {
    fwrite(STDERR, "Dit wist themes, content_slots, articles, research_items en gerelateerde data.\n");
    fwrite(STDERR, "Strategy (clients) en sources blijven behouden.\n");
    fwrite(STDERR, "Gebruik: php scripts/reset-content.php --confirm\n");
    exit(1);
}

$counts = ce_reset_pipeline_content(ce_db());

echo "Content reset voltooid:\n";
foreach ($counts as $table => $count) {
    echo "  - {$table}: {$count} rijen verwijderd\n";
}
if ($counts === []) {
    echo "  (geen content-tabellen gevonden)\n";
}
