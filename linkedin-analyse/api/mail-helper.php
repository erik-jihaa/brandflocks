<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

function li_mail_from_address(): string
{
    $from = trim((string) li_config('mail_from', ''));
    if ($from !== '') {
        return $from;
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'linkedin-analyse.igisnext.com');

    return 'LinkedIn Scan <noreply@' . $host . '>';
}

/** @return array{ok: bool, error?: string} */
function li_send_mail(string $to, string $subject, string $textBody, ?string $htmlBody = null): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Ongeldig e-mailadres'];
    }

    $from = li_mail_from_address();
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $from,
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    if ($htmlBody !== null && $htmlBody !== '') {
        $boundary = 'li_' . bin2hex(random_bytes(8));
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
