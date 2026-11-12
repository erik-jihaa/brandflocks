<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

const CE_SUPPORT_CHAT_MESSAGE_RETENTION_MONTHS = 12;

function ce_ensure_support_service_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!ce_table_exists($pdo, 'support_chat_messages')) {
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS support_chat_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id CHAR(36) NOT NULL,
                question TEXT NOT NULL,
                redacted TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_support_chat_client_created (client_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );
        unset($GLOBALS['ce_table_columns']['support_chat_messages']);
    }

    if (!ce_table_exists($pdo, 'service_tickets')) {
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS service_tickets (
                id CHAR(36) NOT NULL,
                client_id CHAR(36) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status ENUM('open', 'in_progress', 'closed') NOT NULL DEFAULT 'open',
                note TEXT NULL,
                created_by_platform_user_id CHAR(36) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_service_tickets_client (client_id, status, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );
        unset($GLOBALS['ce_table_columns']['service_tickets']);
    }

    ce_support_ensure_ticket_import_columns($pdo);
}

function ce_support_ensure_ticket_import_columns(PDO $pdo): void
{
    static $columnsDone = false;
    if ($columnsDone) {
        return;
    }
    $columnsDone = true;

    if (!ce_table_exists($pdo, 'service_tickets')) {
        return;
    }

    if (!ce_table_has_column($pdo, 'service_tickets', 'sender_email')) {
        $pdo->exec('ALTER TABLE service_tickets ADD COLUMN sender_email VARCHAR(255) NULL AFTER note');
    }
    if (!ce_table_has_column($pdo, 'service_tickets', 'external_message_id')) {
        $pdo->exec('ALTER TABLE service_tickets ADD COLUMN external_message_id VARCHAR(512) NULL AFTER sender_email');
    }
    if (!ce_table_has_column($pdo, 'service_tickets', 'source')) {
        $pdo->exec("ALTER TABLE service_tickets ADD COLUMN source VARCHAR(32) NOT NULL DEFAULT 'manual' AFTER external_message_id");
    }

    try {
        $pdo->exec('CREATE UNIQUE INDEX idx_service_tickets_external_msg ON service_tickets (external_message_id(191))');
    } catch (Throwable) {
        // index bestaat al
    }
}

function ce_support_chat_log_question(PDO $pdo, string $clientId, string $question): void
{
    ce_ensure_support_service_schema($pdo);
    $question = trim($question);
    if ($question === '') {
        return;
    }
    if (mb_strlen($question) > 1500) {
        $question = mb_substr($question, 0, 1500);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO support_chat_messages (client_id, question) VALUES (:client_id, :question)'
    );
    $stmt->execute(['client_id' => $clientId, 'question' => $question]);
}

/** @return array{deleted: int} */
function ce_support_service_prune_old_messages(PDO $pdo): array
{
    ce_ensure_support_service_schema($pdo);
    $months = CE_SUPPORT_CHAT_MESSAGE_RETENTION_MONTHS;
    $stmt = $pdo->prepare(
        "DELETE FROM support_chat_messages
         WHERE created_at < DATE_SUB(NOW(), INTERVAL {$months} MONTH)"
    );
    $stmt->execute();

    return ['deleted' => $stmt->rowCount()];
}

/** @return list<array<string, mixed>> */
function ce_support_fetch_chat_messages(PDO $pdo, string $clientId, int $limit = 50): array
{
    ce_ensure_support_service_schema($pdo);
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, client_id, question, redacted, created_at
         FROM support_chat_messages
         WHERE client_id = :client_id AND redacted = 0
         ORDER BY created_at DESC
         LIMIT {$limit}"
    );
    $stmt->execute(['client_id' => $clientId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'clientId' => (string) ($row['client_id'] ?? ''),
            'question' => (string) ($row['question'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }

    return $rows;
}

/** @return array{open: int, supportEmail: int} */
function ce_support_open_ticket_counts(PDO $pdo, string $clientId): array
{
    ce_ensure_support_service_schema($pdo);

    if (!ce_table_exists($pdo, 'service_tickets')) {
        return ['open' => 0, 'supportEmail' => 0];
    }

    $openStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM service_tickets
         WHERE client_id = :client_id AND status IN ('open', 'in_progress')"
    );
    $openStmt->execute(['client_id' => $clientId]);
    $open = (int) $openStmt->fetchColumn();

    $emailStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM service_tickets
         WHERE client_id = :client_id
           AND status IN ('open', 'in_progress')
           AND source IN ('email_import', 'email_import_escalation')"
    );
    $emailStmt->execute(['client_id' => $clientId]);
    $supportEmail = (int) $emailStmt->fetchColumn();

    return ['open' => $open, 'supportEmail' => $supportEmail];
}

/** @return list<array<string, mixed>> */
function ce_support_fetch_tickets(PDO $pdo, string $clientId, int $limit = 100): array
{
    ce_ensure_support_service_schema($pdo);
    ce_support_ensure_ticket_import_columns($pdo);
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, client_id, subject, status, note, sender_email, source, created_by_platform_user_id, created_at, updated_at
         FROM service_tickets
         WHERE client_id = :client_id
         ORDER BY updated_at DESC
         LIMIT {$limit}"
    );
    $stmt->execute(['client_id' => $clientId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $rows[] = ce_support_ticket_dto($row);
    }

    return $rows;
}

/** @param array<string, mixed> $row @return array<string, mixed> */
function ce_support_ticket_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'clientId' => (string) ($row['client_id'] ?? ''),
        'subject' => (string) ($row['subject'] ?? ''),
        'status' => (string) ($row['status'] ?? 'open'),
        'note' => (string) ($row['note'] ?? ''),
        'senderEmail' => (string) ($row['sender_email'] ?? ''),
        'source' => (string) ($row['source'] ?? 'manual'),
        'createdByPlatformUserId' => (string) ($row['created_by_platform_user_id'] ?? ''),
        'createdAt' => (string) ($row['created_at'] ?? ''),
        'updatedAt' => (string) ($row['updated_at'] ?? ''),
    ];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_support_create_ticket(PDO $pdo, string $clientId, array $body, ?string $platformUserId): array
{
    ce_ensure_support_service_schema($pdo);

    $subject = trim((string) ($body['subject'] ?? ''));
    if ($subject === '') {
        throw new InvalidArgumentException('subject is verplicht');
    }
    if (mb_strlen($subject) > 255) {
        $subject = mb_substr($subject, 0, 255);
    }

    $note = trim((string) ($body['note'] ?? ''));
    $status = strtolower(trim((string) ($body['status'] ?? 'open')));
    if (!in_array($status, ['open', 'in_progress', 'closed'], true)) {
        $status = 'open';
    }

    $exists = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
    $exists->execute(['id' => $clientId]);
    if (!$exists->fetch()) {
        throw new RuntimeException('Tenant niet gevonden');
    }

    $id = sprintf(
        '%s-%s-%s-%s-%s',
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(6)),
    );

    $stmt = $pdo->prepare(
        'INSERT INTO service_tickets (id, client_id, subject, status, note, created_by_platform_user_id)
         VALUES (:id, :client_id, :subject, :status, :note, :created_by)'
    );
    $stmt->execute([
        'id' => $id,
        'client_id' => $clientId,
        'subject' => $subject,
        'status' => $status,
        'note' => $note !== '' ? $note : null,
        'created_by' => $platformUserId !== null && $platformUserId !== '' ? $platformUserId : null,
    ]);

    $fetch = $pdo->prepare('SELECT * FROM service_tickets WHERE id = :id LIMIT 1');
    $fetch->execute(['id' => $id]);
    $row = $fetch->fetch();
    if (!$row) {
        throw new RuntimeException('Ticket aanmaken mislukt');
    }

    return ce_support_ticket_dto($row);
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_support_update_ticket(PDO $pdo, string $clientId, string $ticketId, array $body): array
{
    ce_ensure_support_service_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM service_tickets WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $ticketId, 'client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Ticket niet gevonden');
    }

    $sets = [];
    $params = ['id' => $ticketId, 'client_id' => $clientId];

    if (array_key_exists('subject', $body)) {
        $subject = trim((string) $body['subject']);
        if ($subject === '') {
            throw new InvalidArgumentException('subject mag niet leeg zijn');
        }
        $sets[] = 'subject = :subject';
        $params['subject'] = mb_strlen($subject) > 255 ? mb_substr($subject, 0, 255) : $subject;
    }

    if (array_key_exists('note', $body)) {
        $note = trim((string) $body['note']);
        $sets[] = 'note = :note';
        $params['note'] = $note !== '' ? $note : null;
    }

    if (array_key_exists('status', $body)) {
        $status = strtolower(trim((string) $body['status']));
        if (!in_array($status, ['open', 'in_progress', 'closed'], true)) {
            throw new InvalidArgumentException('status is ongeldig');
        }
        $sets[] = 'status = :status';
        $params['status'] = $status;
    }

    if ($sets === []) {
        return ce_support_ticket_dto($row);
    }

    $pdo->prepare('UPDATE service_tickets SET ' . implode(', ', $sets) . ' WHERE id = :id AND client_id = :client_id')
        ->execute($params);

    $fetch = $pdo->prepare('SELECT * FROM service_tickets WHERE id = :id LIMIT 1');
    $fetch->execute(['id' => $ticketId]);
    $updated = $fetch->fetch();

    return ce_support_ticket_dto($updated ?: $row);
}

function ce_support_redact_chat_message(PDO $pdo, string $clientId, int $messageId): void
{
    ce_ensure_support_service_schema($pdo);
    $stmt = $pdo->prepare(
        'UPDATE support_chat_messages
         SET redacted = 1, question = :redacted
         WHERE id = :id AND client_id = :client_id'
    );
    $stmt->execute([
        'id' => $messageId,
        'client_id' => $clientId,
        'redacted' => '[verwijderd]',
    ]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Bericht niet gevonden');
    }
}

/** @return array<string, mixed> */
function ce_support_service_summary(PDO $pdo, string $clientId): array
{
    return [
        'chatMessages' => ce_support_fetch_chat_messages($pdo, $clientId, 30),
        'tickets' => ce_support_fetch_tickets($pdo, $clientId, 50),
    ];
}

function ce_support_reply_subject(string $subject): string
{
    $subject = trim($subject);
    if ($subject === '') {
        return 'Re: Support';
    }
    if (preg_match('/^re:\s/i', $subject)) {
        return $subject;
    }

    return 'Re: ' . $subject;
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_support_reply_ticket(
    PDO $pdo,
    string $clientId,
    string $ticketId,
    array $body,
    ?string $platformUserId,
    ?array $platformUser = null,
): array {
    require_once __DIR__ . '/event-log-repository.php';
    require_once __DIR__ . '/mail-helper.php';

    ce_ensure_support_service_schema($pdo);
    ce_support_ensure_ticket_import_columns($pdo);

    $message = trim((string) ($body['message'] ?? $body['body'] ?? ''));
    if ($message === '') {
        throw new InvalidArgumentException('message is verplicht');
    }
    if (mb_strlen($message) > 8000) {
        throw new InvalidArgumentException('message is te lang (max 8000 tekens)');
    }

    $markClosed = !empty($body['markClosed']);

    $stmt = $pdo->prepare(
        'SELECT t.*, c.name AS client_name, c.contact_email
         FROM service_tickets t
         INNER JOIN clients c ON c.id = t.client_id
         WHERE t.id = :id AND t.client_id = :client_id
         LIMIT 1'
    );
    $stmt->execute(['id' => $ticketId, 'client_id' => $clientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Ticket niet gevonden');
    }

    $recipient = strtolower(trim((string) ($row['sender_email'] ?? '')));
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $recipient = strtolower(trim((string) ($row['contact_email'] ?? '')));
    }
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Geen geldig antwoord-adres — zet contact_email op de tenant of sender op het ticket');
    }

    $tenantName = trim((string) ($row['client_name'] ?? ''));
    $subject = ce_support_reply_subject((string) ($row['subject'] ?? ''));
    $supportInbox = ce_support_email();

    $mailBody = implode("\n", array_filter([
        $message,
        '',
        '---',
        'Antwoord op je vraag via ' . ce_product_name()
            . ($tenantName !== '' ? ' (' . $tenantName . ')' : '')
            . '.',
        'Nieuwe vragen? Mail ' . $supportInbox . '.',
    ]));

    $mailResult = ce_send_mail($recipient, $subject, $mailBody, null, $supportInbox);
    if (!$mailResult['ok']) {
        throw new RuntimeException((string) ($mailResult['error'] ?? 'E-mail versturen mislukt'));
    }

    $responder = trim((string) ($platformUser['name'] ?? ''));
    if ($responder === '') {
        $responder = trim((string) ($platformUser['email'] ?? 'Platform'));
    }
    $stamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d H:i');
    $logBlock = "--- Antwoord {$stamp} ({$responder}) ---\n{$message}";

    $existingNote = trim((string) ($row['note'] ?? ''));
    $newNote = $existingNote !== '' ? $existingNote . "\n\n" . $logBlock : $logBlock;

    $newStatus = $markClosed ? 'closed' : 'in_progress';
    if (($row['status'] ?? '') === 'closed' && !$markClosed) {
        $newStatus = 'in_progress';
    }

    $pdo->prepare(
        'UPDATE service_tickets SET note = :note, status = :status WHERE id = :id AND client_id = :client_id'
    )->execute([
        'note' => $newNote,
        'status' => $newStatus,
        'id' => $ticketId,
        'client_id' => $clientId,
    ]);

    ce_log_event($pdo, 'info', 'platform', 'support_ticket_reply', null, [
        'clientId' => $clientId,
        'ticketId' => $ticketId,
        'recipient' => $recipient,
        'platformUserId' => $platformUserId ?? '',
        'markClosed' => $markClosed,
    ]);

    $fetch = $pdo->prepare('SELECT * FROM service_tickets WHERE id = :id LIMIT 1');
    $fetch->execute(['id' => $ticketId]);
    $updated = $fetch->fetch(PDO::FETCH_ASSOC);

    return [
        'ok' => true,
        'ticket' => ce_support_ticket_dto($updated ?: $row),
        'sentTo' => $recipient,
    ];
}
