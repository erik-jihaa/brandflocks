<?php

declare(strict_types=1);

require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/mailbox-helper.php';
require_once __DIR__ . '/scrape-repository.php';
require_once __DIR__ . '/wp-diagnose-repository.php';

function ce_platform_assert_tenant_exists(PDO $pdo, string $clientId): void
{
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Tenant niet gevonden');
    }
}

/** @return list<int> */
function ce_platform_default_refill_weeks(PDO $pdo, string $clientId): array
{
    $stmt = $pdo->prepare('SELECT planning_horizon_weeks FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $horizon = max(1, (int) ($stmt->fetchColumn() ?: 4));
    $weeks = [];
    $date = new DateTimeImmutable('now');
    for ($i = 0; $i < $horizon; $i++) {
        $weeks[] = (int) $date->format('W');
        $date = $date->modify('+1 week');
    }

    return array_values(array_unique($weeks));
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_platform_theme_refill(PDO $pdo, string $clientId, array $body): array
{
    ce_platform_assert_tenant_exists($pdo, $clientId);

    $weeksRaw = $body['weeks'] ?? $body['isoWeeks'] ?? [];
    if (!is_array($weeksRaw) || $weeksRaw === []) {
        $weeksRaw = ce_platform_default_refill_weeks($pdo, $clientId);
    }

    $force = (bool) ($body['force'] ?? false);
    $isoYear = isset($body['isoYear']) ? (int) $body['isoYear'] : null;

    $cycleWeeks = [];
    foreach ($weeksRaw as $weekNum) {
        $weekNum = (int) $weekNum;
        if ($weekNum < 1 || $weekNum > 53) {
            continue;
        }
        $cycleWeeks[] = ce_cycle_monday_for_iso_week($weekNum, $isoYear);
    }

    if ($cycleWeeks === []) {
        throw new InvalidArgumentException('Geen geldige weeknummers');
    }

    $deleted = [];
    $skipped = [];

    foreach ($cycleWeeks as $cycleWeek) {
        $stmt = $pdo->prepare(
            "SELECT id, title, motivation, rationale, status
             FROM themes
             WHERE client_id = :client_id
               AND DATE(cycle_week) = DATE(:cycle_week)
               AND COALESCE(status, 'proposed') NOT IN ('deleted', 'converted', 'archived')"
        );
        $stmt->execute(['client_id' => $clientId, 'cycle_week' => $cycleWeek]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? 'proposed')));
            if ($status !== 'proposed' && $status !== '') {
                $skipped[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'cycleWeek' => $cycleWeek,
                    'reason' => 'status_' . $status,
                ];
                continue;
            }

            if (!$force && !ce_theme_is_planning_placeholder($row)) {
                $skipped[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'cycleWeek' => $cycleWeek,
                    'reason' => 'not_placeholder',
                ];
                continue;
            }

            $pdo->prepare('DELETE FROM themes WHERE id = :id AND client_id = :client_id')
                ->execute(['id' => (string) ($row['id'] ?? ''), 'client_id' => $clientId]);
            $deleted[] = [
                'id' => (string) ($row['id'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'cycleWeek' => $cycleWeek,
            ];
        }
    }

    $ce01 = ce_trigger_ce01_theme_director($clientId, $cycleWeeks);

    return [
        'ok' => ($ce01['ok'] ?? false) === true,
        'clientId' => $clientId,
        'weeks' => array_map('intval', $weeksRaw),
        'cycleWeeks' => $cycleWeeks,
        'deleted' => $deleted,
        'skipped' => $skipped,
        'ce01' => $ce01,
    ];
}

/** @return array<string, mixed> */
function ce_platform_mailbox_sync(PDO $pdo, string $clientId): array
{
    ce_platform_assert_tenant_exists($pdo, $clientId);
    ce_ensure_sources_schema($pdo);

    $stmt = $pdo->prepare(
        "SELECT id, title, mailbox_email, mailbox_password_ref, mailbox_imap_host
         FROM sources
         WHERE category = 'mailbox' AND client_id = :client_id
         ORDER BY title ASC"
    );
    $stmt->execute(['client_id' => $clientId]);

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = (string) ($row['id'] ?? '');
        $email = trim((string) ($row['mailbox_email'] ?? ''));
        $imapHost = ce_mailbox_resolve_imap_host($email, (string) ($row['mailbox_imap_host'] ?? ''));

        $entry = [
            'sourceId' => $id,
            'title' => $row['title'] ?? '',
            'mailboxEmail' => $email,
            'ok' => true,
        ];

        try {
            $password = ce_credential_resolve_plaintext(null, (string) ($row['mailbox_password_ref'] ?? ''));
        } catch (Throwable $e) {
            $entry['ok'] = false;
            $entry['error'] = 'Wachtwoord decrypt mislukt';
            $results[] = $entry;
            continue;
        }

        if ($email === '' || $password === '') {
            $entry['ok'] = false;
            $entry['error'] = 'E-mail of wachtwoord ontbreekt';
            $results[] = $entry;
            continue;
        }

        $syncDebug = [];
        $synced = ce_mailbox_sync_newsletters($pdo, $id, $email, $password, 15, $imapHost, $syncDebug);
        $entry['synced'] = $synced;
        $entry['syncDebug'] = $syncDebug;
        $collected = (int) ($syncDebug['collected'] ?? 0);
        $syncError = trim((string) ($syncDebug['error'] ?? ''));
        if ($syncError !== '') {
            $pdo->prepare('UPDATE sources SET url_status = :status, last_checked_at = NOW() WHERE id = :id')
                ->execute(['status' => 'unreachable', 'id' => $id]);
            $entry['ok'] = false;
            $entry['error'] = $syncError;
        } elseif ($collected > 0) {
            $pdo->prepare('UPDATE sources SET url_status = :status, last_checked_at = NOW() WHERE id = :id')
                ->execute(['status' => 'reachable', 'id' => $id]);
        } else {
            $pdo->prepare('UPDATE sources SET url_status = :status, last_checked_at = NOW() WHERE id = :id')
                ->execute(['status' => 'unreachable', 'id' => $id]);
            $entry['ok'] = false;
            $entry['error'] = 'Verbinding OK, maar 0 berichten in INBOX.';
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM newsletter_items WHERE source_id = :id');
        $countStmt->execute(['id' => $id]);
        $entry['newsletterItems'] = (int) $countStmt->fetchColumn();
        $results[] = $entry;
    }

    $ok = $results === [] || array_reduce(
        $results,
        static fn (bool $carry, array $item): bool => $carry && ($item['ok'] ?? false) === true,
        true,
    );

    return [
        'ok' => $ok,
        'imapExtension' => function_exists('imap_open'),
        'results' => $results,
    ];
}

/** @return array<string, mixed> */
function ce_platform_scraper_sync(PDO $pdo, string $clientId): array
{
    ce_platform_assert_tenant_exists($pdo, $clientId);
    ce_ensure_sources_schema($pdo);
    ce_ensure_research_items_schema($pdo);

    $stmt = $pdo->prepare(
        "SELECT id, title, category
         FROM sources
         WHERE category IN ('scraper', 'linkedin') AND client_id = :client_id
         ORDER BY category ASC, title ASC"
    );
    $stmt->execute(['client_id' => $clientId]);

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = (string) ($row['id'] ?? '');
        $entry = [
            'sourceId' => $id,
            'title' => $row['title'] ?? '',
            'category' => $row['category'] ?? '',
            'ok' => true,
        ];

        $entry['probe'] = ce_probe_scraper_source($pdo, $id);
        $entry['scraperRun'] = ce_run_scraper_source($pdo, $id, null, null, null);
        if (($entry['scraperRun']['ok'] ?? false) !== true) {
            $entry['ok'] = false;
        }
        $urlStatus = ($entry['probe']['reachable'] ?? false) ? 'reachable' : 'unreachable';
        $pdo->prepare('UPDATE sources SET url_status = :status, last_checked_at = NOW() WHERE id = :id')
            ->execute(['status' => $urlStatus, 'id' => $id]);
        $results[] = $entry;
    }

    $ok = $results === [] || array_reduce(
        $results,
        static fn (bool $carry, array $item): bool => $carry && ($item['ok'] ?? false) === true,
        true,
    );

    return [
        'ok' => $ok,
        'results' => $results,
    ];
}
