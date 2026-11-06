<?php

declare(strict_types=1);

function ce_mail_from_address(): string
{
    ce_load_env();
    $from = trim((string) (getenv('CE_MAIL_FROM') ?: ''));

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($from === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $from = trim((string) ($config['mail_from'] ?? ''));
        }
    }

    if ($from !== '') {
        return $from;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'ce.igisnext.com';

    return "Content Engine <noreply@{$host}>";
}

/** @return array{ok: bool, error?: string} */
function ce_send_mail(string $to, string $subject, string $textBody, ?string $htmlBody = null, ?string $replyTo = null): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Ongeldig e-mailadres'];
    }

    $from = ce_mail_from_address();
    $reply = trim((string) ($replyTo ?? ''));
    if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) {
        $reply = '';
    }
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $from,
        'Reply-To: ' . ($reply !== '' ? $reply : $from),
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    if ($htmlBody !== null && $htmlBody !== '') {
        $boundary = 'ce_' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $textBody . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . "--{$boundary}--";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $body = $textBody;
    }

    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $subjectEncoded, $body, implode("\r\n", $headers));

    if (!$ok) {
        return ['ok' => false, 'error' => 'mail() mislukt op de server'];
    }

    return ['ok' => true];
}
