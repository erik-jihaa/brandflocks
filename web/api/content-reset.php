<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/schema-helper.php';

/** Corrigeer pipeline-slots die per ongeluk als editorial approved staan. */
function ce_fix_pipeline_slot_statuses(PDO $pdo): int
{
    if (!ce_table_exists($pdo, 'content_slots')) {
        return 0;
    }

    ce_ensure_slot_pipeline_statuses($pdo);

    $sql = <<<'SQL'
UPDATE content_slots s
SET status = 'themed'
WHERE COALESCE(s.status, '') = 'approved'
  AND NOT EXISTS (
    SELECT 1 FROM articles a
    WHERE a.slot_id = s.id
      AND COALESCE(a.status, '') NOT IN ('deleted', '')
  )
SQL;

    return $pdo->exec($sql);
}

/** @return array<string, int> */
function ce_reset_pipeline_content(PDO $pdo): array
{
    /** @var list<string> */
    $tables = [
        'article_review_edits',
        'article_feedback',
        'articles',
        'research_items',
        'content_slots',
        'themes',
        'performance_reports',
    ];

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $counts = [];
    foreach ($tables as $table) {
        if (!ce_table_exists($pdo, $table)) {
            continue;
        }
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $pdo->exec("DELETE FROM `{$table}`");
        $counts[$table] = $count;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    return $counts;
}

/** @return list<string> */
function ce_fetch_ids_for_client(PDO $pdo, string $table, string $clientId): array
{
    if (!ce_table_exists($pdo, $table) || !ce_table_has_column($pdo, $table, 'client_id')) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE client_id = :client_id");
    $stmt->execute(['client_id' => $clientId]);

    return array_values(array_filter(array_map(
        static fn ($row) => (string) ($row['id'] ?? ''),
        $stmt->fetchAll() ?: [],
    )));
}

/** @param list<string> $ids */
function ce_delete_by_ids(PDO $pdo, string $table, string $column, array $ids): int
{
    if ($ids === [] || !ce_table_exists($pdo, $table) || !ce_table_has_column($pdo, $table, $column)) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN ({$placeholders})");
    $stmt->execute($ids);

    return $stmt->rowCount();
}

/** @return array<string, int> */
function ce_reset_client_content(PDO $pdo, string $clientId): array
{
    $clientId = trim($clientId);
    if ($clientId === '') {
        throw new InvalidArgumentException('clientId is verplicht');
    }

    if (!ce_table_exists($pdo, 'clients')) {
        throw new RuntimeException('clients-tabel ontbreekt');
    }

    $check = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
    $check->execute(['id' => $clientId]);
    $client = $check->fetch();
    if (!$client) {
        throw new RuntimeException('Client niet gevonden');
    }

    $slotIds = ce_fetch_ids_for_client($pdo, 'content_slots', $clientId);
    $themeIds = ce_fetch_ids_for_client($pdo, 'themes', $clientId);
    $sourceIds = ce_fetch_ids_for_client($pdo, 'sources', $clientId);

    $articleIds = [];
    if ($slotIds !== [] && ce_table_exists($pdo, 'articles') && ce_table_has_column($pdo, 'articles', 'slot_id')) {
        $placeholders = implode(', ', array_fill(0, count($slotIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM articles WHERE slot_id IN ({$placeholders})");
        $stmt->execute($slotIds);
        $articleIds = array_values(array_filter(array_map(
            static fn ($row) => (string) ($row['id'] ?? ''),
            $stmt->fetchAll() ?: [],
        )));
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $counts = [];

    $counts['article_review_edits'] = ce_delete_by_ids($pdo, 'article_review_edits', 'article_id', $articleIds)
        + ce_delete_by_ids($pdo, 'article_review_edits', 'slot_id', $slotIds);
    $counts['article_feedback'] = ce_delete_by_ids($pdo, 'article_feedback', 'article_id', $articleIds)
        + ce_delete_by_ids($pdo, 'article_feedback', 'slot_id', $slotIds);
    $counts['articles'] = ce_delete_by_ids($pdo, 'articles', 'slot_id', $slotIds);

    $researchDeleted = 0;
    $researchDeleted += ce_delete_by_ids($pdo, 'research_items', 'slot_id', $slotIds);
    $researchDeleted += ce_delete_by_ids($pdo, 'research_items', 'theme_id', $themeIds);
    if (ce_table_exists($pdo, 'research_items') && ce_table_has_column($pdo, 'research_items', 'client_id')) {
        $stmt = $pdo->prepare('DELETE FROM research_items WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);
        $researchDeleted += $stmt->rowCount();
    }
    $counts['research_items'] = $researchDeleted;

    $counts['content_slots'] = ce_delete_by_ids($pdo, 'content_slots', 'id', $slotIds);
    $counts['themes'] = ce_delete_by_ids($pdo, 'themes', 'id', $themeIds);
    $counts['performance_reports'] = (function () use ($pdo, $clientId): int {
        if (!ce_table_exists($pdo, 'performance_reports') || !ce_table_has_column($pdo, 'performance_reports', 'client_id')) {
            return 0;
        }
        $stmt = $pdo->prepare('DELETE FROM performance_reports WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);

        return $stmt->rowCount();
    })();
    $counts['scrape_runs'] = (function () use ($pdo, $clientId): int {
        if (!ce_table_exists($pdo, 'scrape_runs') || !ce_table_has_column($pdo, 'scrape_runs', 'client_id')) {
            return 0;
        }
        $stmt = $pdo->prepare('DELETE FROM scrape_runs WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);

        return $stmt->rowCount();
    })();
    $counts['newsletter_items'] = (function () use ($pdo, $clientId, $sourceIds): int {
        if (!ce_table_exists($pdo, 'newsletter_items')) {
            return 0;
        }
        $deleted = 0;
        if (ce_table_has_column($pdo, 'newsletter_items', 'client_id')) {
            $stmt = $pdo->prepare('DELETE FROM newsletter_items WHERE client_id = :client_id');
            $stmt->execute(['client_id' => $clientId]);
            $deleted += $stmt->rowCount();
        }
        if ($sourceIds !== [] && ce_table_has_column($pdo, 'newsletter_items', 'source_id')) {
            $deleted += ce_delete_by_ids($pdo, 'newsletter_items', 'source_id', $sourceIds);
        }

        return $deleted;
    })();
    $counts['sources'] = ce_delete_by_ids($pdo, 'sources', 'id', $sourceIds);

    $counts['client_images'] = (function () use ($pdo, $clientId): int {
        if (!ce_table_exists($pdo, 'client_images') || !ce_table_has_column($pdo, 'client_images', 'client_id')) {
            return 0;
        }
        require_once __DIR__ . '/client-images-repository.php';
        ce_ensure_client_images_schema($pdo);
        $stmt = $pdo->prepare('SELECT id FROM client_images WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);
        $deleted = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (ce_delete_client_image($pdo, $clientId, (string) ($row['id'] ?? ''))) {
                $deleted++;
            }
        }

        return $deleted;
    })();

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    return [
        'clientId' => $clientId,
        'company' => (string) ($client['name'] ?? ''),
        'deleted' => array_filter($counts, static fn (int $count): bool => $count > 0),
        'totals' => $counts,
    ];
}

/**
 * Verwijder agenda-/contentpipeline zonder bronnen, strategie of merkarchitectuur.
 *
 * @return array{clientId: string, company: string, deleted: array<string, int>, totals: array<string, int>}
 */
function ce_reset_client_pipeline_content(PDO $pdo, string $clientId): array
{
    $clientId = trim($clientId);
    if ($clientId === '') {
        throw new InvalidArgumentException('clientId is verplicht');
    }

    $check = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
    $check->execute(['id' => $clientId]);
    $client = $check->fetch();
    if (!$client) {
        throw new RuntimeException('Client niet gevonden');
    }

    $slotIds = ce_fetch_ids_for_client($pdo, 'content_slots', $clientId);
    $themeIds = ce_fetch_ids_for_client($pdo, 'themes', $clientId);
    $campaignIds = ce_fetch_ids_for_client($pdo, 'campaigns', $clientId);

    $articleIds = [];
    if ($slotIds !== [] && ce_table_exists($pdo, 'articles') && ce_table_has_column($pdo, 'articles', 'slot_id')) {
        $placeholders = implode(', ', array_fill(0, count($slotIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM articles WHERE slot_id IN ({$placeholders})");
        $stmt->execute($slotIds);
        $articleIds = array_values(array_filter(array_map(
            static fn ($row) => (string) ($row['id'] ?? ''),
            $stmt->fetchAll() ?: [],
        )));
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $counts = [];
    $counts['hba_scores'] = ce_delete_by_ids($pdo, 'hba_scores', 'post_id', $articleIds);
    $counts['publications'] = ce_delete_by_ids($pdo, 'publications', 'article_id', $articleIds);
    $counts['article_review_edits'] = ce_delete_by_ids($pdo, 'article_review_edits', 'article_id', $articleIds)
        + ce_delete_by_ids($pdo, 'article_review_edits', 'slot_id', $slotIds);
    $counts['article_feedback'] = ce_delete_by_ids($pdo, 'article_feedback', 'article_id', $articleIds)
        + ce_delete_by_ids($pdo, 'article_feedback', 'slot_id', $slotIds);
    $counts['articles'] = ce_delete_by_ids($pdo, 'articles', 'slot_id', $slotIds);

    $researchDeleted = 0;
    $researchDeleted += ce_delete_by_ids($pdo, 'research_items', 'slot_id', $slotIds);
    $researchDeleted += ce_delete_by_ids($pdo, 'research_items', 'theme_id', $themeIds);
    if (ce_table_exists($pdo, 'research_items') && ce_table_has_column($pdo, 'research_items', 'client_id')) {
        $stmt = $pdo->prepare('DELETE FROM research_items WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);
        $researchDeleted += $stmt->rowCount();
    }
    $counts['research_items'] = $researchDeleted;

    $counts['content_slots'] = ce_delete_by_ids($pdo, 'content_slots', 'id', $slotIds);
    $counts['themes'] = ce_delete_by_ids($pdo, 'themes', 'id', $themeIds);
    $counts['campaigns'] = ce_delete_by_ids($pdo, 'campaigns', 'id', $campaignIds);

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    return [
        'clientId' => $clientId,
        'company' => (string) ($client['name'] ?? ''),
        'deleted' => array_filter($counts, static fn (int $count): bool => $count > 0),
        'totals' => $counts,
    ];
}

/** @return array<string, int> */
function ce_blank_client_strategy(PDO $pdo, string $clientId): array
{
    if (!ce_table_exists($pdo, 'clients')) {
        throw new RuntimeException('clients-tabel ontbreekt');
    }

    $clientId = trim($clientId);
    if ($clientId === '') {
        throw new InvalidArgumentException('clientId is verplicht');
    }

    $blankFields = [
        'archetype' => '',
        'archetype_traits' => '',
        'adversary' => '',
        'brand_goal' => '',
        'brandstory' => '',
        'tone_of_voice' => '',
        'writing_guide' => '',
        'logo_url' => '',
        'image_database_url' => '',
    ];

    $sets = [];
    $params = ['id' => $clientId];
    $cleared = [];

    foreach ($blankFields as $column => $value) {
        if (!ce_table_has_column($pdo, 'clients', $column)) {
            continue;
        }
        $sets[] = "$column = :$column";
        $params[$column] = $value;
        $cleared[$column] = 1;
    }

    if ($sets === []) {
        return $cleared;
    }

    $pdo->prepare('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

    if (ce_table_has_column($pdo, 'clients', 'image_database_password')) {
        $pdo->prepare('UPDATE clients SET image_database_password = NULL WHERE id = :id')->execute(['id' => $clientId]);
        $cleared['image_database_password'] = 1;
    }

    return $cleared;
}

/** @return array<string, mixed> */
function ce_prepare_blank_tenant(PDO $pdo, string $clientId, bool $blankStrategy = true): array
{
    $content = ce_reset_client_content($pdo, $clientId);
    $strategy = $blankStrategy ? ce_blank_client_strategy($pdo, $clientId) : [];

    return [
        'ok' => true,
        'clientId' => $content['clientId'],
        'company' => $content['company'],
        'deleted' => $content['deleted'],
        'totals' => $content['totals'],
        'strategyCleared' => array_keys($strategy),
    ];
}

/** @return array<string, mixed> */
function ce_delete_client(PDO $pdo, string $clientId, string $confirmCompanyName): array
{
    $clientId = trim($clientId);
    $confirmCompanyName = trim($confirmCompanyName);
    if ($clientId === '') {
        throw new InvalidArgumentException('clientId is verplicht');
    }
    if ($confirmCompanyName === '') {
        throw new InvalidArgumentException('confirmCompanyName is verplicht');
    }

    $check = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
    $check->execute(['id' => $clientId]);
    $client = $check->fetch();
    if (!$client) {
        throw new RuntimeException('Client niet gevonden');
    }

    $company = trim((string) ($client['name'] ?? ''));
    if (!hash_equals($company, $confirmCompanyName)) {
        throw new InvalidArgumentException('confirmCompanyName komt niet overeen met bedrijfsnaam');
    }

    $content = ce_reset_client_content($pdo, $clientId);

    if (ce_table_exists($pdo, 'password_reset_tokens') && ce_table_has_column($pdo, 'password_reset_tokens', 'client_id')) {
        $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);
        $content['totals']['password_reset_tokens'] = $stmt->rowCount();
    }

    $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Client verwijderen mislukt');
    }

    return [
        'ok' => true,
        'clientId' => $clientId,
        'company' => $company,
        'deleted' => $content['deleted'],
        'totals' => $content['totals'],
        'clientRemoved' => true,
    ];
}

/**
 * Destructieve endpoints in productie: eis een expliciete extra bevestiging.
 * Adresseert het mass-delete-incident — een reset-token alleen is te makkelijk.
 */
function ce_require_destructive_confirmation(string $action): void
{
    if (!ce_is_production()) {
        return;
    }

    $expected = 'production';
    $body = [];
    $raw = ce_raw_request_body();
    if (trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }

    $provided = trim((string) ($body['confirmEnvironment'] ?? $_GET['confirm_environment'] ?? ''));
    if (!hash_equals($expected, $provided)) {
        require_once __DIR__ . '/event-log-repository.php';
        ce_log_event(null, 'warn', 'auth', 'destructive_blocked', 'Destructieve actie geweigerd zonder bevestiging', [
            'action' => $action,
        ]);

        ce_json_error(
            'Deze actie is destructief en draait op productie. Stuur "confirmEnvironment": "production" mee om te bevestigen.',
            409,
        );
    }
}

function ce_reset_token_configured(): ?string
{
    ce_load_env();
    $token = trim((string) (getenv('CE_RESET_TOKEN') ?: ''));
    if ($token === '') {
        $local = dirname(__DIR__) . '/includes/ce.local.php';
        if (is_readable($local)) {
            $config = require $local;
            if (is_array($config)) {
                $token = trim((string) ($config['reset_token'] ?? ''));
            }
        }
    }

    return $token !== '' ? $token : null;
}

function ce_reset_token_valid(): bool
{
    $expected = ce_reset_token_configured();
    if ($expected === null) {
        return false;
    }

    $provided = trim((string) ($_SERVER['HTTP_X_CE_RESET_TOKEN'] ?? ''));
    if ($provided === '') {
        return false;
    }

    return hash_equals($expected, $provided);
}
