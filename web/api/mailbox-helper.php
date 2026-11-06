<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/credential-crypto.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/event-log-repository.php';

function ce_ensure_newsletter_items_schema(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'newsletter_items')) {
        $pdo->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS newsletter_items (
  id CHAR(36) NOT NULL PRIMARY KEY,
  source_id CHAR(36) NOT NULL,
  client_id CHAR(36) NOT NULL,
  subject VARCHAR(500) NOT NULL,
  body_text MEDIUMTEXT NULL,
  received_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_source (source_id),
  KEY idx_client (client_id)
)
SQL
        );
    }

    $columns = [
        'source_id' => 'CHAR(36) NOT NULL',
        'client_id' => 'CHAR(36) NOT NULL',
        'subject' => 'VARCHAR(500) NOT NULL',
        'body_text' => 'MEDIUMTEXT NULL',
        'received_at' => 'DATETIME NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($columns as $column => $definition) {
        if (ce_table_has_column($pdo, 'newsletter_items', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE newsletter_items ADD COLUMN `$column` $definition");
            unset($GLOBALS['ce_table_columns']['newsletter_items']);
        } catch (Throwable) {
            // Kolom bestaat al of ALTER niet toegestaan.
        }
    }
}

/** @return list<string> */
function ce_mailbox_host_variants(string $host, bool $preferredOnly = false): array
{
    $host = strtolower(trim($host, '. '));
    if ($host === '') {
        return [];
    }

    if ($preferredOnly) {
        return [
            "{{$host}:993/imap/ssl}INBOX",
            "{{$host}:993/imap/ssl/novalidate-cert}INBOX",
        ];
    }

    $flags = [
        '/imap/ssl/novalidate-cert',
        '/imap/ssl',
        '/imap/tls/novalidate-cert',
        '/imap/notls',
    ];

    $mailboxes = [];
    foreach ([993, 143] as $port) {
        foreach ($flags as $flag) {
            if ($port === 993 && str_contains($flag, '/tls/')) {
                continue;
            }
            if ($port === 993 && str_contains($flag, '/notls')) {
                continue;
            }
            if ($port === 143 && str_contains($flag, '/ssl')) {
                continue;
            }
            $mailboxes[] = "{{$host}:{$port}{$flag}}INBOX";
        }
    }

    return array_values(array_unique($mailboxes));
}

function ce_mailbox_default_imap_host(string $email): string
{
    $domain = strtolower(ltrim((string) strrchr(trim($email), '@'), '@'));
    if ($domain === '') {
        return '';
    }

    return "mail.{$domain}";
}

/** @return list<string> */
function ce_mailbox_login_identities(string $email): array
{
    $email = trim($email);
    $local = strstr($email, '@', true);
    $ids = [$email];
    if (is_string($local) && $local !== '' && $local !== $email) {
        $ids[] = $local;
    }

    return array_values(array_unique($ids));
}

function ce_mailbox_configure_timeouts(): void
{
    if (!function_exists('imap_timeout')) {
        return;
    }

    imap_timeout(IMAP_OPENTIMEOUT, 15);
    imap_timeout(IMAP_READTIMEOUT, 30);
    imap_timeout(IMAP_WRITETIMEOUT, 30);
    imap_timeout(IMAP_CLOSETIMEOUT, 10);
}

function ce_mailbox_looks_like_gmail(string $email, ?string $imapHost = null): bool
{
    $domain = strtolower(ltrim((string) strrchr(trim($email), '@'), '@'));
    if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
        return true;
    }

    $host = strtolower(trim((string) $imapHost));

    return $host === 'imap.gmail.com' || str_contains($host, 'gmail.com');
}

/** @return list<string> */
function ce_mailbox_imap_candidates(string $email, ?string $imapHost = null): array
{
    $explicit = trim((string) $imapHost);
    if ($explicit !== '') {
        return ce_mailbox_host_variants($explicit, true);
    }

    $domain = strtolower(ltrim((string) strrchr(trim($email), '@'), '@'));
    $known = [
        'gmail.com' => ['imap.gmail.com'],
        'googlemail.com' => ['imap.gmail.com'],
        'outlook.com' => ['outlook.office365.com'],
        'hotmail.com' => ['outlook.office365.com'],
        'live.com' => ['outlook.office365.com'],
        'live.nl' => ['outlook.office365.com'],
        'office365.com' => ['outlook.office365.com'],
        'icloud.com' => ['imap.mail.me.com'],
        'me.com' => ['imap.mail.me.com'],
        'mac.com' => ['imap.mail.me.com'],
        'ziggo.nl' => ['imap.ziggo.nl'],
        'kpnmail.nl' => ['imap.kpnmail.nl'],
        'xs4all.nl' => ['imap.xs4all.nl'],
    ];

    $hosts = [];
    if ($domain !== '') {
        $hosts[] = "mail.{$domain}";
        $hosts[] = "imap.{$domain}";
    }

    if (isset($known[$domain])) {
        $hosts = array_merge($hosts, $known[$domain]);
    }

    if ($domain !== '') {
        $mxRecords = @dns_get_record($domain, DNS_MX);
        if (is_array($mxRecords)) {
            usort($mxRecords, static fn (array $a, array $b): int => ($a['pri'] ?? 999) <=> ($b['pri'] ?? 999));
            foreach ($mxRecords as $mx) {
                $target = strtolower(rtrim((string) ($mx['target'] ?? ''), '.'));
                if ($target !== '') {
                    $hosts[] = $target;
                }
            }
        }

        $hosts[] = $domain;
    }

    $hosts = array_values(array_unique(array_filter($hosts)));

    $candidates = [];
    foreach ($hosts as $host) {
        $candidates = array_merge($candidates, ce_mailbox_host_variants($host));
    }

    return array_values(array_unique($candidates));
}

/**
 * @param-out string $error
 * @param-out string $loginUsed
 * @return resource|false
 */
function ce_mailbox_open(
    string $email,
    string $password,
    ?string &$error = null,
    ?string &$mailboxUsed = null,
    ?string $imapHost = null,
    ?string &$loginUsed = null,
) {
    $email = trim($email);
    $password = trim($password);
    if ($email === '' || $password === '') {
        $error = 'E-mailadres en wachtwoord zijn verplicht';
        return false;
    }

    if (!function_exists('imap_open')) {
        $error = 'PHP IMAP-extensie ontbreekt op de server — vraag hosting om php-imap in te schakelen';
        return false;
    }

    ce_mailbox_configure_timeouts();

    $lastError = '';
    foreach (ce_mailbox_imap_candidates($email, $imapHost) as $mailbox) {
        foreach (ce_mailbox_login_identities($email) as $login) {
            imap_errors();
            imap_alerts();
            $connection = @imap_open($mailbox, $login, $password, OP_READONLY, 1, [
                'DISABLE_AUTHENTICATOR' => 'GSSAPI',
            ]);
            if ($connection !== false) {
                $mailboxUsed = $mailbox;
                $loginUsed = $login;
                return $connection;
            }
            $errors = imap_errors() ?: [];
            $lastError = $errors !== [] ? (string) end($errors) : 'IMAP login mislukt';
        }
    }

    $error = $lastError !== '' ? $lastError : 'Geen IMAP-verbinding mogelijk voor dit adres';
    return false;
}

function ce_mailbox_probe(string $email, string $password, ?string &$error = null, ?string $imapHost = null): bool
{
    $connection = ce_mailbox_open($email, $password, $error, $mailboxUsed, $imapHost);
    if ($connection === false) {
        return false;
    }

    imap_close($connection);

    return true;
}

/** @return array<string, mixed> */
function ce_mailbox_diagnose(string $email, string $password, ?string $imapHost = null): array
{
    $mailboxUsed = '';
    $loginUsed = '';
    $error = '';
    $connection = ce_mailbox_open($email, $password, $error, $mailboxUsed, $imapHost, $loginUsed);
    if ($connection === false) {
        return [
            'ok' => false,
            'error' => $error,
            'imapHost' => $imapHost ?: ce_mailbox_default_imap_host($email),
        ];
    }

    $folders = [];
    if (ce_mailbox_looks_like_gmail($email, $imapHost)) {
        $folders[] = [
            'folder' => 'INBOX',
            'messages' => imap_num_msg($connection),
        ];
    } else {
        foreach (ce_mailbox_sync_folders($connection, $mailboxUsed, $email, $imapHost) as $folder) {
            if (!@imap_reopen($connection, $folder, OP_READONLY)) {
                continue;
            }
            $label = function_exists('imap_utf7_decode') ? (imap_utf7_decode($folder) ?: $folder) : $folder;
            $folders[] = [
                'folder' => $label,
                'messages' => imap_num_msg($connection),
            ];
        }
    }

    imap_close($connection);

    return [
        'ok' => true,
        'mailboxUsed' => $mailboxUsed,
        'loginUsed' => $loginUsed,
        'imapHost' => $imapHost ?: ce_mailbox_default_imap_host($email),
        'folders' => $folders,
        'totalMessages' => array_sum(array_map(static fn (array $row): int => (int) ($row['messages'] ?? 0), $folders)),
    ];
}

function ce_mailbox_decode_header(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '';
    }

    if (!function_exists('imap_mime_header_decode')) {
        return trim(imap_utf8($raw));
    }

    $parts = imap_mime_header_decode($raw);
    if (!is_array($parts)) {
        return trim(imap_utf8($raw));
    }

    $out = '';
    foreach ($parts as $part) {
        $out .= (string) ($part->text ?? '');
    }

    return trim($out);
}

/** @param object $header */
function ce_mailbox_subject_from_header(object $header, int $msgNo): string
{
    $subject = ce_mailbox_decode_header(isset($header->subject) ? (string) $header->subject : '');
    if ($subject !== '') {
        return $subject;
    }

    $from = '';
    if (isset($header->from[0])) {
        $mailbox = (string) ($header->from[0]->mailbox ?? '');
        $host = (string) ($header->from[0]->host ?? '');
        if ($mailbox !== '' && $host !== '') {
            $from = $mailbox . '@' . $host;
        }
    }

    return $from !== '' ? "Mail van {$from}" : "Nieuwsbrief #{$msgNo}";
}

function ce_mailbox_decode_part_body(string $body, int $encoding): string
{
    return match ($encoding) {
        ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
        ENCBASE64 => (string) base64_decode($body, true),
        default => $body,
    };
}

/** @param object|null $structure */
function ce_mailbox_fetch_part_text($connection, int $msgNo, ?object $structure, string $partNo = ''): string
{
    if ($structure === null) {
        $body = @imap_body($connection, $msgNo);
        return is_string($body) ? trim(imap_utf8($body)) : '';
    }

    $type = (int) ($structure->type ?? TYPETEXT);
    $subtype = strtoupper((string) ($structure->subtype ?? ''));

    if ($type === TYPETEXT) {
        $body = $partNo === '' ? @imap_body($connection, $msgNo) : @imap_fetchbody($connection, $msgNo, $partNo);
        if (!is_string($body) || trim($body) === '') {
            return '';
        }
        $decoded = ce_mailbox_decode_part_body($body, (int) ($structure->encoding ?? ENC7BIT));
        $text = trim(imap_utf8($decoded));
        if ($subtype === 'HTML') {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        }
        return $text;
    }

    if ($type === TYPEMULTIPART && isset($structure->parts) && is_array($structure->parts)) {
        $plain = '';
        $html = '';
        foreach ($structure->parts as $index => $part) {
            $nextPart = $partNo === '' ? (string) ($index + 1) : $partNo . '.' . ($index + 1);
            $chunk = ce_mailbox_fetch_part_text($connection, $msgNo, $part, $nextPart);
            if ($chunk === '') {
                continue;
            }
            $partSubtype = strtoupper((string) ($part->subtype ?? ''));
            if ($partSubtype === 'PLAIN' && $plain === '') {
                $plain = $chunk;
            } elseif ($partSubtype === 'HTML' && $html === '') {
                $html = $chunk;
            }
        }
        return $plain !== '' ? $plain : $html;
    }

    return '';
}

function ce_mailbox_fetch_body_text($connection, int $msgNo): string
{
    $structure = @imap_fetchstructure($connection, $msgNo);
    if ($structure !== false) {
        $fromStructure = ce_mailbox_fetch_part_text($connection, $msgNo, $structure);
        if ($fromStructure !== '') {
            return $fromStructure;
        }
    }

    $parts = ['1', '1.1', '1.2', '2'];
    foreach ($parts as $part) {
        $body = @imap_fetchbody($connection, $msgNo, $part);
        if (is_string($body) && trim($body) !== '') {
            return trim(imap_utf8($body));
        }
    }

    $body = @imap_body($connection, $msgNo);
    if (is_string($body) && trim($body) !== '') {
        $text = trim(imap_utf8($body));
        if (str_contains($text, '<html') || str_contains($text, '<body')) {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        }
        return $text;
    }

    return '';
}

/** @return list<string> */
function ce_mailbox_sync_folders($connection, string $mailboxUsed, ?string $email = null, ?string $imapHost = null): array
{
    if ($email !== null && ce_mailbox_looks_like_gmail($email, $imapHost)) {
        // Gmail: honderden labels via imap_list — alleen INBOX scannen (nieuwsbrieven + snelle check).
        return [$mailboxUsed];
    }

    $brace = strrpos($mailboxUsed, '}');
    if ($brace === false) {
        return [$mailboxUsed];
    }

    $baseRef = substr($mailboxUsed, 0, $brace + 1);
    $ordered = [];
    $seen = [];

    $add = static function (string $path) use (&$ordered, &$seen): void {
        $key = strtolower($path);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $ordered[] = $path;
    };

    $add($mailboxUsed);

    $skipPattern = '/sent|trash|deleted|spam|junk|draft|concept|prullen|verwijder|outbox|spambox/i';
    $list = @imap_list($connection, $baseRef, '*') ?: [];
    $priority = [];
    $other = [];

    foreach ($list as $path) {
        $label = function_exists('imap_utf7_decode') ? (imap_utf7_decode($path) ?: $path) : $path;
        if (preg_match($skipPattern, $label)) {
            continue;
        }
        if (preg_match('/inbox|newsletter|nieuwsbrief|all mail|alle mail|archive|archief/i', $label)) {
            $priority[] = $path;
        } else {
            $other[] = $path;
        }
    }

    foreach ([...$priority, ...array_slice($other, 0, 8)] as $path) {
        $add($path);
    }

    return $ordered;
}

/**
 * @return list<array{mailbox: string, msgNo: int, ts: int}>
 */
function ce_mailbox_collect_recent_messages($connection, string $mailboxUsed, int $limit, ?string $email = null, ?string $imapHost = null): array
{
    $collected = [];

    foreach (ce_mailbox_sync_folders($connection, $mailboxUsed, $email, $imapHost) as $folder) {
        if (!@imap_reopen($connection, $folder, OP_READONLY)) {
            continue;
        }

        $count = imap_num_msg($connection);
        if ($count <= 0) {
            continue;
        }

        $start = max(1, $count - max($limit * 3, 30) + 1);
        for ($msgNo = $count; $msgNo >= $start; $msgNo--) {
            $header = imap_headerinfo($connection, $msgNo);
            if ($header === false) {
                continue;
            }

            $ts = 0;
            if (isset($header->date)) {
                $parsed = strtotime((string) $header->date);
                if ($parsed !== false) {
                    $ts = $parsed;
                }
            }

            $collected[] = [
                'mailbox' => $folder,
                'msgNo' => $msgNo,
                'ts' => $ts,
            ];
        }
    }

    usort($collected, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);

    return array_slice($collected, 0, $limit);
}

function ce_mailbox_sync_newsletters(
    PDO $pdo,
    string $sourceId,
    string $email,
    string $password,
    int $limit = 15,
    ?string $imapHost = null,
    ?array &$debug = null,
): int {
    ce_ensure_newsletter_items_schema($pdo);

    if (!function_exists('imap_open')) {
        if ($debug !== null) {
            $debug['error'] = 'PHP IMAP-extensie ontbreekt';
        }
        return 0;
    }

    $stmt = $pdo->prepare('SELECT client_id FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $clientId = (string) ($stmt->fetchColumn() ?: '');
    if ($clientId === '') {
        if ($debug !== null) {
            $debug['error'] = 'Geen client_id voor bron';
        }
        return 0;
    }

    $error = '';
    $mailboxUsed = '';
    $connection = ce_mailbox_open($email, $password, $error, $mailboxUsed, $imapHost);
    if ($connection === false) {
        if ($debug !== null) {
            $debug['error'] = $error !== '' ? $error : 'IMAP open mislukt';
        }
        ce_log_event($pdo, 'error', 'mailbox', 'mailbox_sync_failed', $error !== '' ? $error : 'IMAP open mislukt', [
            'clientId' => $clientId,
            'sourceId' => $sourceId,
        ]);

        return 0;
    }

    $messages = ce_mailbox_collect_recent_messages($connection, $mailboxUsed, $limit, $email, $imapHost);
    if ($debug !== null) {
        $debug['collected'] = count($messages);
        $debug['mailboxUsed'] = $mailboxUsed;
        $debug['imapHost'] = $imapHost ?: ce_mailbox_default_imap_host($email);
    }
    if ($messages === []) {
        imap_close($connection);
        return 0;
    }

    $rows = [];
    foreach ($messages as $message) {
        if (!@imap_reopen($connection, $message['mailbox'], OP_READONLY)) {
            continue;
        }

        $msgNo = (int) $message['msgNo'];
        $header = imap_headerinfo($connection, $msgNo);
        if ($header === false) {
            continue;
        }

        $subject = ce_mailbox_subject_from_header($header, $msgNo);
        $bodyText = ce_mailbox_fetch_body_text($connection, $msgNo);
        $bodyText = preg_replace('/\s+/u', ' ', $bodyText) ?? '';
        $bodyText = mb_substr($bodyText, 0, 4000);

        $receivedAt = null;
        if (($message['ts'] ?? 0) > 0) {
            $receivedAt = date('Y-m-d H:i:s', (int) $message['ts']);
        }

        $rows[] = [
            'subject' => mb_substr($subject, 0, 500),
            'body_text' => $bodyText !== '' ? $bodyText : null,
            'received_at' => $receivedAt,
        ];
    }

    imap_close($connection);

    if ($debug !== null) {
        $debug['parsed'] = count($rows);
    }

    if ($rows === []) {
        return 0;
    }

    $pdo->prepare('DELETE FROM newsletter_items WHERE source_id = :source_id')->execute(['source_id' => $sourceId]);

    $inserted = 0;
    $lastInsertError = '';
    $insert = $pdo->prepare(
        'INSERT INTO newsletter_items (id, source_id, client_id, subject, body_text, received_at)
         VALUES (:id, :source_id, :client_id, :subject, :body_text, :received_at)'
    );

    foreach ($rows as $row) {
        try {
            $insert->execute([
                'id' => ce_mailbox_uuid(),
                'source_id' => $sourceId,
                'client_id' => $clientId,
                'subject' => $row['subject'],
                'body_text' => $row['body_text'],
                'received_at' => $row['received_at'],
            ]);
            $inserted++;
        } catch (Throwable $e) {
            $lastInsertError = $e->getMessage();
            continue;
        }
    }

    if ($debug !== null && $lastInsertError !== '') {
        $debug['lastInsertError'] = $lastInsertError;
    }

    return $inserted;
}

/** Sync all active mailbox sources for a client (used before CE-02). */
function ce_mailbox_sync_client_sources(PDO $pdo, ?string $clientId = null): int
{
    require_once __DIR__ . '/client-context.php';
    ce_ensure_newsletter_items_schema($pdo);

    $clientId = ce_resolve_client_id($pdo, $clientId);
    if ($clientId === null || !ce_table_exists($pdo, 'sources')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT id, mailbox_email, mailbox_password_ref, mailbox_imap_host
         FROM sources
         WHERE client_id = :client_id
           AND category = 'mailbox'
           AND COALESCE(active, 1) = 1
           AND mailbox_email IS NOT NULL
           AND TRIM(mailbox_email) <> ''"
    );
    $stmt->execute(['client_id' => $clientId]);

    $total = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $sourceId = (string) ($row['id'] ?? '');
        $email = trim((string) ($row['mailbox_email'] ?? ''));
        if ($sourceId === '' || $email === '') {
            continue;
        }

        try {
            $password = ce_credential_resolve_plaintext(null, (string) ($row['mailbox_password_ref'] ?? ''));
        } catch (Throwable) {
            continue;
        }
        if ($password === '') {
            continue;
        }

        $total += ce_mailbox_sync_newsletters(
            $pdo,
            $sourceId,
            $email,
            $password,
            15,
            ce_mailbox_resolve_imap_host($email, (string) ($row['mailbox_imap_host'] ?? '')),
        );
    }

    return $total;
}

function ce_mailbox_resolve_imap_host(string $email, ?string $configuredHost = null): ?string
{
    $configuredHost = trim((string) $configuredHost);
    if (strcasecmp($configuredHost, 'map.gmail.com') === 0) {
        $configuredHost = 'imap.gmail.com';
    }
    if ($configuredHost !== '') {
        return $configuredHost;
    }

    $default = ce_mailbox_default_imap_host($email);

    return $default !== '' ? $default : null;
}

function ce_mailbox_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/** @return array<string, mixed>|null */
function ce_mailbox_source_for_client(PDO $pdo, string $sourceId, ?string $clientId = null): ?array
{
    require_once __DIR__ . '/client-context.php';
    $clientId = ce_resolve_client_id($pdo, $clientId);
    if ($clientId === null || !ce_table_exists($pdo, 'sources')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, category FROM sources WHERE id = :id AND client_id = :client_id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId, 'client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row || (string) ($row['category'] ?? '') !== 'mailbox') {
        return null;
    }

    return $row;
}

/** @return list<array<string, mixed>> */
function ce_fetch_newsletter_items_for_source(PDO $pdo, string $sourceId, ?string $clientId = null): array
{
    if (ce_mailbox_source_for_client($pdo, $sourceId, $clientId) === null) {
        return [];
    }

    ce_ensure_newsletter_items_schema($pdo);
    if (!ce_table_exists($pdo, 'newsletter_items')) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, subject, body_text, received_at
         FROM newsletter_items
         WHERE source_id = :source_id
         ORDER BY received_at DESC, created_at DESC'
    );
    $stmt->execute(['source_id' => $sourceId]);
    $items = [];
    while ($row = $stmt->fetch()) {
        $items[] = [
            'id' => (string) $row['id'],
            'subject' => (string) ($row['subject'] ?? ''),
            'bodyText' => $row['body_text'] !== null ? (string) $row['body_text'] : null,
            'receivedAt' => $row['received_at'] !== null ? (string) $row['received_at'] : null,
        ];
    }

    return $items;
}

/** @param array<string, mixed> $body */
function ce_update_newsletter_item(
    PDO $pdo,
    string $sourceId,
    string $itemId,
    array $body,
    ?string $clientId = null,
): ?array {
    if (ce_mailbox_source_for_client($pdo, $sourceId, $clientId) === null) {
        return null;
    }

    ce_ensure_newsletter_items_schema($pdo);
    $fields = [];
    $params = ['id' => $itemId, 'source_id' => $sourceId];

    if (array_key_exists('subject', $body)) {
        $subject = trim((string) $body['subject']);
        if ($subject === '') {
            throw new InvalidArgumentException('Onderwerp is verplicht.');
        }
        $fields[] = 'subject = :subject';
        $params['subject'] = mb_substr($subject, 0, 500);
    }

    if (array_key_exists('bodyText', $body)) {
        $bodyText = trim((string) $body['bodyText']);
        $fields[] = 'body_text = :body_text';
        $params['body_text'] = $bodyText !== '' ? mb_substr($bodyText, 0, 4000) : null;
    }

    if ($fields === []) {
        return ce_fetch_newsletter_item_row($pdo, $sourceId, $itemId);
    }

    $sql = 'UPDATE newsletter_items SET ' . implode(', ', $fields)
        . ' WHERE id = :id AND source_id = :source_id';
    $pdo->prepare($sql)->execute($params);

    return ce_fetch_newsletter_item_row($pdo, $sourceId, $itemId);
}

/** @return array<string, mixed>|null */
function ce_fetch_newsletter_item_row(PDO $pdo, string $sourceId, string $itemId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, subject, body_text, received_at
         FROM newsletter_items
         WHERE id = :id AND source_id = :source_id
         LIMIT 1'
    );
    $stmt->execute(['id' => $itemId, 'source_id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'id' => (string) $row['id'],
        'subject' => (string) ($row['subject'] ?? ''),
        'bodyText' => $row['body_text'] !== null ? (string) $row['body_text'] : null,
        'receivedAt' => $row['received_at'] !== null ? (string) $row['received_at'] : null,
    ];
}

function ce_delete_newsletter_item(PDO $pdo, string $sourceId, string $itemId, ?string $clientId = null): bool
{
    if (ce_mailbox_source_for_client($pdo, $sourceId, $clientId) === null) {
        return false;
    }

    ce_ensure_newsletter_items_schema($pdo);
    $stmt = $pdo->prepare('DELETE FROM newsletter_items WHERE id = :id AND source_id = :source_id');
    $stmt->execute(['id' => $itemId, 'source_id' => $sourceId]);

    return $stmt->rowCount() > 0;
}
