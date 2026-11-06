<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/review-repository.php';
require_once __DIR__ . '/mail-helper.php';

function ce_ensure_password_reset_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (ce_table_exists($pdo, 'password_reset_tokens')) {
        return;
    }

    try {
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
              id CHAR(36) NOT NULL PRIMARY KEY,
              client_id CHAR(36) NOT NULL,
              token_hash CHAR(64) NOT NULL,
              email VARCHAR(255) NOT NULL,
              expires_at DATETIME NOT NULL,
              used_at DATETIME NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              KEY idx_token_hash (token_hash),
              KEY idx_client (client_id),
              KEY idx_email (email)
            )
            SQL
        );
    } catch (Throwable) {
        // Tabel bestaat al of geen CREATE-rechten.
    }
}

/** @return list<array<string, mixed>> */
function ce_find_clients_by_login_email(PDO $pdo, string $email): array
{
    $rows = ce_clients_matching_login_email($pdo, $email);
    $clients = [];
    foreach ($rows as $row) {
        $clients[] = [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'contact_email' => (string) ($row['contact_email'] ?? ''),
        ];
    }

    return $clients;
}

function ce_password_reset_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function ce_password_reset_page_url(string $token): string
{
    return ce_public_base_url() . '/reset-password/' . rawurlencode($token);
}

function ce_create_password_reset_token(PDO $pdo, string $clientId, string $email): string
{
    ce_ensure_password_reset_schema($pdo);

    $token = bin2hex(random_bytes(32));
    $hash = ce_password_reset_token_hash($token);
    $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

    $pdo->prepare(
        'INSERT INTO password_reset_tokens (id, client_id, token_hash, email, expires_at)
         VALUES (:id, :client_id, :token_hash, :email, :expires_at)'
    )->execute([
        'id' => ce_uuid(),
        'client_id' => $clientId,
        'token_hash' => $hash,
        'email' => strtolower(trim($email)),
        'expires_at' => $expiresAt,
    ]);

    return $token;
}

/** @return array{ok: true, message: string} */
function ce_request_password_reset(PDO $pdo, string $email): array
{
    $message = 'Als dit e-mailadres bij een klant hoort, ontvang je binnen enkele minuten een mail met een link om een nieuw wachtwoord in te stellen.';

    $clients = ce_find_clients_by_login_email($pdo, $email);
    if ($clients === []) {
        return ['ok' => true, 'message' => $message];
    }

    $links = [];
    foreach ($clients as $client) {
        $clientId = (string) ($client['id'] ?? '');
        $company = trim((string) ($client['name'] ?? 'Account'));
        if ($clientId === '') {
            continue;
        }

        $token = ce_create_password_reset_token($pdo, $clientId, $email);
        $links[] = [
            'company' => $company,
            'url' => ce_password_reset_page_url($token),
        ];
    }

    if ($links === []) {
        return ['ok' => true, 'message' => $message];
    }

    $base = ce_public_base_url();
    $lines = [
        'Hallo,',
        '',
        'Je hebt een nieuw wachtwoord aangevraagd voor de Content Engine.',
        '',
    ];

    if (count($links) === 1) {
        $lines[] = 'Open deze link om een nieuw wachtwoord in te stellen (geldig 1 uur):';
        $lines[] = $links[0]['url'];
    } else {
        $lines[] = 'Je e-mailadres hoort bij meerdere accounts. Kies het juiste account:';
        $lines[] = '';
        foreach ($links as $link) {
            $lines[] = $link['company'] . ':';
            $lines[] = $link['url'];
            $lines[] = '';
        }
    }

    $lines[] = 'Als je dit niet hebt aangevraagd, kun je deze mail negeren.';
    $lines[] = '';
    $lines[] = '— Content Engine';
    $textBody = implode("\n", $lines);

    $htmlParts = ['<p>Hallo,</p><p>Je hebt een nieuw wachtwoord aangevraagd voor de Content Engine.</p>'];
    if (count($links) === 1) {
        $url = htmlspecialchars($links[0]['url'], ENT_QUOTES, 'UTF-8');
        $htmlParts[] = '<p><a href="' . $url . '">Nieuw wachtwoord instellen</a> (geldig 1 uur)</p>';
    } else {
        $htmlParts[] = '<p>Je e-mailadres hoort bij meerdere accounts:</p><ul>';
        foreach ($links as $link) {
            $url = htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars($link['company'], ENT_QUOTES, 'UTF-8');
            $htmlParts[] = '<li><strong>' . $name . '</strong> — <a href="' . $url . '">wachtwoord resetten</a></li>';
        }
        $htmlParts[] = '</ul>';
    }
    $htmlParts[] = '<p>Als je dit niet hebt aangevraagd, kun je deze mail negeren.</p>';
    $htmlBody = implode("\n", $htmlParts);

    $mail = ce_send_mail($email, 'Content Engine — wachtwoord resetten', $textBody, $htmlBody);
    if (!$mail['ok']) {
        error_log('CE password reset mail failed: ' . ($mail['error'] ?? 'unknown'));
        require_once __DIR__ . '/event-log-repository.php';
        ce_log_event($pdo, 'error', 'auth', 'password_reset_mail_failed', (string) ($mail['error'] ?? 'mail() mislukt'), [
            'email' => $email,
        ]);
    }

    return ['ok' => true, 'message' => $message];
}

/** @return array{ok: bool, error?: string, clientId?: string, company?: string} */
function ce_validate_password_reset_token(PDO $pdo, string $token): array
{
    ce_ensure_password_reset_schema($pdo);

    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return ['ok' => false, 'error' => 'Ongeldige of verlopen link'];
    }

    $hash = ce_password_reset_token_hash($token);
    $stmt = $pdo->prepare(
        'SELECT t.client_id, t.expires_at, t.used_at, c.name AS company
         FROM password_reset_tokens t
         LEFT JOIN clients c ON c.id = t.client_id
         WHERE t.token_hash = :hash
         LIMIT 1'
    );
    $stmt->execute(['hash' => $hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Ongeldige of verlopen link'];
    }

    if (!empty($row['used_at'])) {
        return ['ok' => false, 'error' => 'Deze link is al gebruikt'];
    }

    $expires = strtotime((string) ($row['expires_at'] ?? ''));
    if ($expires === false || $expires < time()) {
        return ['ok' => false, 'error' => 'Deze link is verlopen — vraag een nieuwe aan'];
    }

    return [
        'ok' => true,
        'clientId' => (string) ($row['client_id'] ?? ''),
        'company' => (string) ($row['company'] ?? ''),
    ];
}

/** @return array{ok: bool, error?: string} */
function ce_complete_password_reset(PDO $pdo, string $token, string $password, string $passwordRetype): array
{
    if ($password === '' || $passwordRetype === '') {
        return ['ok' => false, 'error' => 'Wachtwoord is verplicht'];
    }
    if ($password !== $passwordRetype) {
        return ['ok' => false, 'error' => 'Wachtwoorden komen niet overeen'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Wachtwoord moet minimaal 8 tekens zijn'];
    }

    $valid = ce_validate_password_reset_token($pdo, $token);
    if (!$valid['ok']) {
        return ['ok' => false, 'error' => $valid['error'] ?? 'Ongeldige link'];
    }

    $clientId = (string) ($valid['clientId'] ?? '');
    if ($clientId === '') {
        return ['ok' => false, 'error' => 'Account niet gevonden'];
    }

    ce_ensure_account_columns($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'password_hash')) {
        return ['ok' => false, 'error' => 'Wachtwoord login is niet beschikbaar'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE clients SET password_hash = :hash WHERE id = :id')
        ->execute(['hash' => $hash, 'id' => $clientId]);

    $pdo->prepare(
        'UPDATE password_reset_tokens SET used_at = NOW()
         WHERE client_id = :client_id AND used_at IS NULL'
    )->execute(['client_id' => $clientId]);

    return ['ok' => true];
}
