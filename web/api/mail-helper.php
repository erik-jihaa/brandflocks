<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ce_mail_extract_address(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/<([^>]+)>/', $value, $matches)) {
        $value = trim($matches[1]);
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    return $value;
}

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

    $defaults = ce_install_contact_defaults();
    if (ce_is_brandflocks_install()) {
        return ce_product_name() . ' <' . $defaults['review'] . '>';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return ce_product_name() . " <noreply@{$host}>";
}

/** SMTP envelope (-f) — op shared hosting vaak verplicht voor geldig afzenderdomein. */
function ce_mail_envelope_address(?string $fromHeader = null): string
{
    ce_load_env();
    $explicit = trim((string) (getenv('CE_MAIL_ENVELOPE') ?: ''));
    if ($explicit === '') {
        $local = dirname(__DIR__) . '/includes/ce.local.php';
        if (is_readable($local)) {
            $config = require $local;
            if (is_array($config)) {
                $explicit = trim((string) ($config['mail_envelope'] ?? ''));
            }
        }
    }

    $parsed = ce_mail_extract_address($explicit);
    if ($parsed !== '') {
        return $parsed;
    }

    $fromHeader ??= ce_mail_from_address();
    $parsed = ce_mail_extract_address($fromHeader);
    if ($parsed !== '') {
        return $parsed;
    }

    if (ce_is_brandflocks_install()) {
        return ce_install_contact_defaults()['review'];
    }

    $support = ce_mail_extract_address(ce_install_contact_defaults()['support']);

    return $support !== '' ? $support : '';
}

/** @return array{ok: bool, error?: string, from?: string, envelope?: string} */
function ce_send_mail(string $to, string $subject, string $textBody, ?string $htmlBody = null, ?string $replyTo = null): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Ongeldig e-mailadres'];
    }

    $from = ce_mail_from_address();
    $envelope = ce_mail_envelope_address($from);
    $reply = trim((string) ($replyTo ?? ''));
    if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) {
        $reply = '';
    }

    $fromAddr = ce_mail_extract_address($from);
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $from,
        'Reply-To: ' . ($reply !== '' ? $reply : ($fromAddr !== '' ? $fromAddr : $from)),
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
    $params = $envelope !== '' ? '-f' . $envelope : '';
    $ok = $params !== ''
        ? @mail($to, $subjectEncoded, $body, implode("\r\n", $headers), $params)
        : @mail($to, $subjectEncoded, $body, implode("\r\n", $headers));

    if (!$ok) {
        return [
            'ok' => false,
            'error' => 'mail() mislukt op de server',
            'from' => $from,
            'envelope' => $envelope,
        ];
    }

    return ['ok' => true, 'from' => $from, 'envelope' => $envelope];
}
