<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

/** @return list<string> */
function ce_retroactive_migration_filenames(): array
{
    return [
        'add_article_feedback_table.sql',
        'add_article_self_review.sql',
        'add_campaign_end_date.sql',
        'add_campaign_start_date.sql',
        'add_campaigns_table.sql',
        'add_campagne_preview_p1.sql',
        'add_campagne_preview_p2.sql',
        'add_campagne_preview_p3.sql',
        'add_campagne_preview_p4.sql',
        'add_campagne_preview_p5.sql',
        'add_client_images_table.sql',
        'add_linkedin_posts_table.sql',
        'add_objectives_table.sql',
        'add_publish_mode.sql',
        'add_report_article_counts.sql',
        'add_scrape_runs_table.sql',
        'add_source_checks_per_week.sql',
    ];
}

$pdo = ce_db();

$pdo->exec(
    <<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
);

$insert = $pdo->prepare(
    'INSERT IGNORE INTO schema_migrations (filename) VALUES (:filename)'
);

$inserted = [];
foreach (ce_retroactive_migration_filenames() as $filename) {
    $insert->execute(['filename' => $filename]);
    if ($insert->rowCount() > 0) {
        $inserted[] = $filename;
    }
}

$applied = $pdo->query('SELECT filename, applied_at FROM schema_migrations ORDER BY applied_at ASC')
    ->fetchAll(PDO::FETCH_ASSOC) ?: [];

ce_json_response([
    'ok' => true,
    'inserted' => $inserted,
    'applied' => $applied,
]);
