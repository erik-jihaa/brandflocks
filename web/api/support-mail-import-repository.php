<?php

declare(strict_types=1);

require_once __DIR__ . '/mailbox-helper.php';
require_once __DIR__ . '/support-service-repository.php';
require_once __DIR__ . '/event-log-repository.php';

function ce_support_mail_import_config(): array
{
    $email = ce_config_value('CE_SUPPORT_IMAP_EMAIL', 'support_imap_email', '');
    if ($email === '') {
        $email = ce_support_email();
    }

    $password = ce_config_value('CE_SUPPORT_IMAP_PASSWORD', 'support_imap_password', '');
    $hostRaw = ce_config_value('CE_SUPPORT_IMAP_HOST', 'support_imap_host', '');
    $host = trim($hostRaw) !== '' ? trim($hostRaw) : null;

    return [
        'email' => $email,
        'password' => $password,
        'host' => $host,
    ];
}

function ce_support_header_address(mixed $addr): string
{
    if (!is_object($addr)) {
        return '';
    }
    $mb = (string) ($addr->mailbox ?? '');
    $hostPart = (string) ($addr->host ?? '');
    if ($mb === '' || $hostPart === '') {
        return '';
    }

    return ce_support_extract_email_address($mb . '@' . $hostPart);
}

/** @return list<string> Unieke afzenderkandidaten (from → reply-to → sender). */
function ce_support_sender_candidates(object $header): array
{
    $seen = [];
    $candidates = [];

    $push = static function (string $email) use (&$seen, &$candidates): void {
        if ($email === '' || isset($seen[$email])) {
            return;
        }
        $seen[$email] = true;
        $candidates[] = $email;
    };

    if (isset($header->from[0])) {
        $push(ce_support_header_address($header->from[0]));
    }
    if (isset($header->reply_to) && is_array($header->reply_to)) {
        foreach ($header->reply_to as $addr) {
            $push(ce_support_header_address($addr));
        }
    }
    if (isset($header->sender[0])) {
        $push(ce_support_header_address($header->sender[0]));
    }

    return $candidates;
}

/** @return array{email: string, reason: string}|null */
function ce_support_resolve_import_sender(object $header, string $supportInbox): ?array
{
    $candidates = ce_support_sender_candidates($header);
    if ($candidates === []) {
        return null;
    }

    $supportNorm = strtolower(trim($supportInbox));
    $external = array_values(array_filter(
        $candidates,
        static fn (string $email): bool => strtolower($email) !== $supportNorm,
    ));

    if ($external === []) {
        return ['email' => $candidates[0], 'reason' => 'self'];
    }

    return ['email' => $external[0], 'reason' => 'ok'];
}

function ce_support_extract_email_address(string $fromHeader): string
{
    $fromHeader = trim($fromHeader);
    if ($fromHeader === '') {
        return '';
    }
    if (preg_match('/<([^>]+)>/', $fromHeader, $m)) {
        return strtolower(trim($m[1]));
    }
    if (filter_var($fromHeader, FILTER_VALIDATE_EMAIL)) {
        return strtolower($fromHeader);
    }

    return '';
}

function ce_support_match_client_by_email(PDO $pdo, string $email): ?string
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM clients
         WHERE LOWER(TRIM(contact_email)) = :email
            OR LOWER(TRIM(reviewer_email)) = :email
         ORDER BY active DESC, name ASC
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);

    $id = $stmt->fetchColumn();

    return is_string($id) && $id !== '' ? $id : null;
}

/** @return array{clientId: string|null, contactEmail: string}|null */
function ce_support_parse_escalation_body(string $body): ?array
{
    if (!preg_match('/Support-escalatie vanuit Content Engine/i', $body)) {
        return null;
    }

    $clientId = null;
    if (preg_match(
        '/Client ID:\s*([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i',
        $body,
        $match,
    )) {
        $clientId = strtolower($match[1]);
    }

    $contactEmail = '';
    if (preg_match('/Contact:.*?<([^>\s]+@[^>\s]+)>/i', $body, $match)) {
        $contactEmail = ce_support_extract_email_address($match[1]);
    }

    if ($clientId === null && $contactEmail === '') {
        return null;
    }

    return [
        'clientId' => $clientId,
        'contactEmail' => $contactEmail,
    ];
}

function ce_support_client_exists(PDO $pdo, string $clientId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);

    return (bool) $stmt->fetchColumn();
}

function ce_support_ticket_exists_for_message(PDO $pdo, string $messageId): bool
{
    if ($messageId === '') {
        return false;
    }
    ce_support_ensure_ticket_import_columns($pdo);
    $stmt = $pdo->prepare('SELECT id FROM service_tickets WHERE external_message_id = :mid LIMIT 1');
    $stmt->execute(['mid' => $messageId]);

    return (bool) $stmt->fetchColumn();
}

/** @return array<string, mixed> */
function ce_support_import_emails(PDO $pdo, ?string $platformUserId, int $limit = 25): array
{
    ce_support_ensure_ticket_import_columns($pdo);

    $cfg = ce_support_mail_import_config();
    if ($cfg['email'] === '' || $cfg['password'] === '') {
        throw new RuntimeException(
            'Support IMAP niet geconfigureerd — zet CE_SUPPORT_IMAP_EMAIL en CE_SUPPORT_IMAP_PASSWORD in .env',
        );
    }

    $error = '';
    $connection = ce_mailbox_open($cfg['email'], $cfg['password'], $error, $mailboxUsed, $cfg['host']);
    if ($connection === false) {
        throw new RuntimeException($error !== '' ? $error : 'IMAP-verbinding mislukt');
    }

    $messages = ce_mailbox_collect_recent_messages($connection, $mailboxUsed, max(5, min(50, $limit)));
    $imported = [];
    $skipped = [];

    foreach ($messages as $message) {
        $msgNo = (int) ($message['msgNo'] ?? 0);
        if ($msgNo <= 0) {
            continue;
        }
        if (!@imap_reopen($connection, $message['mailbox'], OP_READONLY)) {
            continue;
        }

        $header = @imap_headerinfo($connection, $msgNo);
        if (!$header) {
            continue;
        }

        $subject = ce_mailbox_subject_from_header($header, $msgNo);
        if ($subject === '') {
            $subject = '(geen onderwerp)';
        }

        $messageId = trim((string) ($header->message_id ?? ''));
        if ($messageId !== '' && ce_support_ticket_exists_for_message($pdo, $messageId)) {
            $skipped[] = [
                'reason' => 'duplicate',
                'messageId' => $messageId,
                'subject' => $subject,
            ];
            continue;
        }

        $resolved = ce_support_resolve_import_sender($header, $cfg['email']);
        $bodyText = ce_mailbox_fetch_body_text($connection, $msgNo);
        $bodyPreview = preg_replace('/\s+/u', ' ', trim($bodyText)) ?? '';
        if (mb_strlen($bodyPreview) > 1200) {
            $bodyPreview = mb_substr($bodyPreview, 0, 1200) . '…';
        }

        $clientId = null;
        $senderEmail = '';
        $importSource = 'email_import';

        if ($resolved !== null && $resolved['reason'] === 'ok') {
            $senderEmail = $resolved['email'];
            $clientId = ce_support_match_client_by_email($pdo, $senderEmail);
        }

        if ($clientId === null) {
            $escalation = ce_support_parse_escalation_body($bodyText);
            if ($escalation !== null) {
                $importSource = 'email_import_escalation';
                if ($escalation['clientId'] !== null && ce_support_client_exists($pdo, $escalation['clientId'])) {
                    $clientId = $escalation['clientId'];
                }
                if ($escalation['contactEmail'] !== '') {
                    $senderEmail = $escalation['contactEmail'];
                    if ($clientId === null) {
                        $clientId = ce_support_match_client_by_email($pdo, $senderEmail);
                    }
                }
            }
        }

        if ($clientId === null) {
            if ($resolved === null) {
                $skipped[] = ['reason' => 'no_sender', 'messageId' => $messageId, 'subject' => $subject];
            } elseif ($resolved['reason'] === 'self') {
                $skipped[] = [
                    'reason' => 'self',
                    'messageId' => $messageId,
                    'subject' => $subject,
                    'detail' => 'Afzender is het support-adres (geen chat-escalatie met Client ID)',
                ];
            } else {
                $skipped[] = [
                    'reason' => 'no_tenant',
                    'sender' => $resolved['email'],
                    'messageId' => $messageId,
                    'subject' => $subject,
                    'detail' => 'Geen tenant met dit adres als contact- of reviewer-e-mail',
                ];
            }
            continue;
        }

        if ($senderEmail === '' && $resolved !== null) {
            $senderEmail = $resolved['email'];
        }

        $ticket = ce_support_create_ticket($pdo, $clientId, [
            'subject' => $subject,
            'note' => $bodyPreview !== '' ? $bodyPreview : null,
            'status' => 'open',
        ], $platformUserId);

        $pdo->prepare(
            'UPDATE service_tickets
             SET sender_email = :sender, external_message_id = :mid, source = :source
             WHERE id = :id'
        )->execute([
            'sender' => $senderEmail,
            'mid' => $messageId !== '' ? $messageId : null,
            'source' => $importSource,
            'id' => $ticket['id'],
        ]);

        $imported[] = [
            'ticketId' => $ticket['id'],
            'clientId' => $clientId,
            'subject' => $subject,
            'senderEmail' => $senderEmail,
        ];
    }

    @imap_close($connection);

    /** @var array<string, int> $skippedSummary */
    $skippedSummary = [];
    foreach ($skipped as $item) {
        $reason = (string) ($item['reason'] ?? 'unknown');
        $skippedSummary[$reason] = ($skippedSummary[$reason] ?? 0) + 1;
    }

    ce_log_event($pdo, 'info', 'platform', 'support_mail_import', null, [
        'imported' => count($imported),
        'skipped' => count($skipped),
        'platformUserId' => $platformUserId ?? '',
    ]);

    return [
        'ok' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'skippedSummary' => $skippedSummary,
        'importedCount' => count($imported),
        'skippedCount' => count($skipped),
    ];
}
