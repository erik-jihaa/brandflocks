<?php

declare(strict_types=1);

/**
 * Cron: raw_data na raw_data_retention_days; metrics-fragmenten + rapportlink na 180 dagen.
 * Voorbeeld: 0 3 * * * php /path/to/linkedin-analyse/cleanup.php
 *
 * Alleen CLI — geen web-aanroep.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';

try {
    $pdo = li_pdo();
    $days = (int) li_config('raw_data_retention_days', 90);
    li_scan_run_scheduled_maintenance($pdo, $days);
    $fragmentDays = (int) li_config('metrics_fragment_retention_days', LI_SCAN_METRICS_FRAGMENT_RETENTION_DAYS);
    echo "Onderhoud uitgevoerd: ruwe posts > {$days} dagen; fragmenten/rapportlink > {$fragmentDays} dagen; login_attempts > 30 dagen.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Cleanup failed: ' . $e->getMessage() . "\n");
    exit(1);
}
