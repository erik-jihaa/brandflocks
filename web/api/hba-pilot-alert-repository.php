<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-pilot-weekly-repository.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/event-log-repository.php';
require_once __DIR__ . '/mail-helper.php';
require_once __DIR__ . '/mappers.php';

/** @return list<string> */
function ce_hba_pilot_alert_recipients(): array
{
    $raw = ce_config_value('CE_PILOT_ALERT_EMAILS', 'pilot_alert_emails');
    if ($raw === '') {
        $fallback = ce_support_email();
        if ($fallback === '') {
            return [];
        }

        return [$fallback];
    }

    $parts = preg_split('/[\s,;]+/', $raw) ?: [];

    return array_values(array_filter(array_map(static fn (string $e): string => trim($e), $parts)));
}

/**
 * @param array{clientId: string, clientName: string, slotId: string, articleId: string, postTitel: string, hoofdoordeel: string, citaat: string, uitleg: string, hintId: string} $payload
 * @return array{ok: bool, sent?: bool, error?: string}
 */
function ce_hba_pilot_send_negeer_kritiek_mail(array $payload): array
{
    if (ce_env() === 'local') {
        return ['ok' => true, 'sent' => false];
    }

    $recipients = ce_hba_pilot_alert_recipients();
    if ($recipients === []) {
        return ['ok' => false, 'error' => 'no_recipients'];
    }

    $hoofd = $payload['hoofdoordeel'] ?? '';
    if (!in_array($hoofd, ['tegenkracht', 'regelbreuk'], true)) {
        return ['ok' => true, 'sent' => false];
    }

    $label = $hoofd === 'tegenkracht' ? 'Positiebreuk' : 'Regelbreuk';
    $env = strtoupper(ce_env());
    $subject = sprintf('[CE %s] Pilot — Negeer bij %s (%s)', $env, $label, $payload['clientName'] ?? '');

    $lines = [
        'Een pilotklant heeft een positioneringshint genegeerd (Negeer) bij een post met een kritiek hoofdoordeel.',
        '',
        'Merk:         ' . ($payload['clientName'] ?? ''),
        'Post:         ' . ($payload['postTitel'] ?? ''),
        'Hoofdoordeel: ' . $hoofd . ' (' . $label . ')',
        'Slot:         ' . ($payload['slotId'] ?? ''),
        '',
        'Citaat:',
        $payload['citaat'] !== '' ? $payload['citaat'] : '(geen citaat in flag_citaten)',
        '',
        'Uitleg:',
        $payload['uitleg'] !== '' ? $payload['uitleg'] : '(geen uitleg)',
        '',
        'Hint-id:      ' . ($payload['hintId'] ?? ''),
        'Tijd:         ' . date('Y-m-d H:i:s'),
    ];

    $to = $recipients[0];
    $extra = array_slice($recipients, 1);
    $sent = ce_send_mail($to, $subject, implode("\n", $lines), null, null);
    foreach ($extra as $cc) {
        if ($cc !== $to) {
            ce_send_mail($cc, $subject, implode("\n", $lines));
        }
    }

    return ['ok' => !empty($sent['ok']), 'sent' => !empty($sent['ok'])];
}

/**
 * @return array{hoofdoordeel: ?string, citaat: string, uitleg: string}
 */
function ce_hba_pilot_score_alert_fields(PDO $pdo, string $clientId, string $articleId): array
{
    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if ($narrative === null) {
        return ['hoofdoordeel' => null, 'citaat' => '', 'uitleg' => ''];
    }
    $scoreRow = ce_hba_fetch_score_for_article($pdo, $articleId, (string) ($narrative['id'] ?? ''));
    if ($scoreRow === null) {
        return ['hoofdoordeel' => null, 'citaat' => '', 'uitleg' => ''];
    }

    $raw = ce_hba_pilot_decode_raw($scoreRow['raw_llm_response'] ?? null);
    $meta = ce_hba_hoofdoordeel_from_raw($raw);
    $hoofd = trim((string) ($meta['hoofdoordeel'] ?? ''));
    if ($hoofd === '') {
        return ['hoofdoordeel' => null, 'citaat' => '', 'uitleg' => ''];
    }

    $u = ce_hba_pilot_score_uitleg($raw, $hoofd);

    return ['hoofdoordeel' => $hoofd, 'citaat' => $u['citaat'], 'uitleg' => $u['uitleg']];
}

/**
 * Na Negeer: event_log voor weekoverzicht; mail bij tegenkracht/regelbreuk.
 *
 * @return array{ok: bool, mail?: array<string, mixed>, error?: string}
 */
function ce_hba_pilot_after_hint_ignored(
    PDO $pdo,
    string $clientId,
    string $hintId,
    string $articleId,
    string $slotId,
    string $clientName,
    string $postTitel,
): array {
    $fields = ce_hba_pilot_score_alert_fields($pdo, $clientId, $articleId);
    $hoofd = $fields['hoofdoordeel'] ?? '';

    ce_log_event($pdo, 'info', 'article', 'hba_hint_genegeerd', 'Klant negeerde positioneringshint', [
        'hintId' => $hintId,
        'slotId' => $slotId,
        'articleId' => $articleId,
        'hoofdoordeel' => $hoofd,
        'postTitel' => mb_substr($postTitel, 0, 200),
        'kritiek' => in_array($hoofd, ['tegenkracht', 'regelbreuk'], true),
    ]);

    $mail = ce_hba_pilot_send_negeer_kritiek_mail([
        'clientId' => $clientId,
        'clientName' => $clientName,
        'slotId' => $slotId,
        'articleId' => $articleId,
        'postTitel' => $postTitel,
        'hoofdoordeel' => $hoofd ?? '',
        'citaat' => $fields['citaat'],
        'uitleg' => $fields['uitleg'],
        'hintId' => $hintId,
    ]);

    return ['ok' => true, 'mail' => $mail];
}

function ce_hba_pilot_resolve_test_slot_id(PDO $pdo, string $merk, int $index): ?string
{
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE TRIM(name) = :name LIMIT 1');
    $stmt->execute(['name' => $merk]);
    $clientId = (string) ($stmt->fetchColumn() ?: '');
    if ($clientId === '') {
        return null;
    }

    $slotStmt = $pdo->prepare(
        "SELECT s.id AS slot_id, s.input_human
         FROM content_slots s
         INNER JOIN articles a ON a.slot_id = s.id
         WHERE s.client_id = :client_id
           AND s.theme LIKE 'HBA-test:%'
         ORDER BY s.theme ASC"
    );
    $slotStmt->execute(['client_id' => $clientId]);
    $slotRows = $slotStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($slotRows as $i => $slotRow) {
        $marker = json_decode((string) ($slotRow['input_human'] ?? ''), true);
        if (!is_array($marker)) {
            $marker = [];
        }
        $zeroBased = max(0, (int) ($marker['index'] ?? ($i + 1)) - 1);
        if ($zeroBased === $index) {
            $id = trim((string) ($slotRow['slot_id'] ?? ''));

            return $id !== '' ? $id : null;
        }
    }

    return null;
}

/** Testmail voor Brandpunt "Breekt de regel" (admin). @return array<string, mixed> */
function ce_hba_pilot_negeer_alert_test(PDO $pdo, string $slotId): array
{
    if ($slotId === '' || $slotId === 'auto') {
        $resolved = ce_hba_pilot_resolve_test_slot_id($pdo, 'Brandpunt Koffie', 3);
        if ($resolved === null) {
            return ['ok' => false, 'error' => 'test_slot_not_found'];
        }
        $slotId = $resolved;
    }

    $titleCols = implode(', ', ce_hba_pilot_article_title_sql_columns($pdo));
    $stmt = $pdo->prepare(
        "SELECT a.id AS article_id, cs.id AS slot_id, cs.client_id, c.name AS client_name, {$titleCols}
         FROM articles a
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         INNER JOIN clients c ON c.id = cs.client_id
         WHERE cs.id = :slot_id
         LIMIT 1",
    );
    $stmt->execute(['slot_id' => $slotId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'slot_not_found'];
    }

    $clientId = (string) ($row['client_id'] ?? '');
    $articleId = (string) ($row['article_id'] ?? '');
    $fields = ce_hba_pilot_score_alert_fields($pdo, $clientId, $articleId);
    $title = ce_article_headline($row);

    $mail = ce_hba_pilot_send_negeer_kritiek_mail([
        'clientId' => $clientId,
        'clientName' => (string) ($row['client_name'] ?? ''),
        'slotId' => (string) ($row['slot_id'] ?? ''),
        'articleId' => $articleId,
        'postTitel' => $title !== '' ? $title : 'HBA-test: Breekt de regel',
        'hoofdoordeel' => $fields['hoofdoordeel'] ?? 'regelbreuk',
        'citaat' => $fields['citaat'],
        'uitleg' => $fields['uitleg'],
        'hintId' => 'test-' . gmdate('YmdHis'),
    ]);

    return [
        'ok' => true,
        'slotId' => $slotId,
        'hoofdoordeel' => $fields['hoofdoordeel'],
        'mail' => $mail,
        'recipients' => ce_hba_pilot_alert_recipients(),
    ];
}
