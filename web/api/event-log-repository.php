<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/schema-helper.php';

const CE_EVENT_SEVERITIES = ['info', 'warn', 'error'];

const CE_EVENT_CATEGORIES = ['publish', 'n8n', 'oauth', 'scraper', 'mailbox', 'auth', 'schema', 'analytics'];

/** Retentie: info 30 dagen, warn/error 12 maanden. */
const CE_EVENT_RETENTION_DAYS = ['info' => 30, 'warn' => 365, 'error' => 365];

/** Zelfde event + client maximaal 1× per uur mailen. */
const CE_EVENT_MAIL_COOLDOWN_MINUTES = 60;

function ce_ensure_event_log_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    try {
        $pdo->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS event_log (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NULL,
    severity VARCHAR(10) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event VARCHAR(100) NOT NULL,
    message TEXT NULL,
    context JSON NULL,
    environment VARCHAR(20) NOT NULL,
    notified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_client_created (client_id, created_at),
    KEY idx_severity_created (severity, created_at),
    KEY idx_category (category),
    KEY idx_event_client (event, client_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
    } catch (Throwable) {
        // Geen CREATE-rechten of tabel bestaat al met ander charset — loggen mag nooit blokkeren.
    }
}

/**
 * Strip PII en secrets uit een foutmelding van een externe API.
 * Regel uit het architectuurbrief: IDs en statuscodes bewaren, geen e-mails, namen of tokens.
 */
function ce_event_log_sanitize_message(?string $message): ?string
{
    if ($message === null) {
        return null;
    }

    $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');
    if ($message === '') {
        return null;
    }

    $patterns = [
        // E-mailadressen
        '/[\w.+-]+@[\w-]+\.[\w.-]+/i' => '[email]',
        // Bearer/Basic-headers en token-query's
        '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i' => '$1 [redacted]',
        '/\b(token|access_token|refresh_token|api_key|apikey|password|secret|client_secret)=[^&\s"\']+/i' => '$1=[redacted]',
        '/\b(token|access_token|refresh_token|api_key|apikey|password|secret|client_secret)"\s*:\s*"[^"]+"/i' => '$1":"[redacted]"',
        // Losse Apify/Anthropic/LinkedIn-achtige sleutels
        '/\b(apify_api|sk-ant-|sk-|AQ[A-Za-z0-9])[A-Za-z0-9._-]{12,}/' => '[redacted]',
    ];

    foreach ($patterns as $pattern => $replacement) {
        $message = preg_replace($pattern, $replacement, $message) ?? $message;
    }

    return mb_substr($message, 0, 1000);
}

/** Alleen IDs, statuscodes en endpoint-namen — geen headlines, namen of inhoud. */
function ce_event_log_sanitize_context(array $context): array
{
    $blocked = ['email', 'contactemail', 'revieweremail', 'name', 'company', 'headline',
        'title', 'content', 'body', 'token', 'password', 'secret', 'apikey'];

    $clean = [];
    foreach ($context as $key => $value) {
        $normalized = strtolower(str_replace(['-', '_'], '', (string) $key));
        if (in_array($normalized, $blocked, true)) {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $clean[(string) $key] = is_string($value)
                ? mb_substr(ce_event_log_sanitize_message($value) ?? '', 0, 200)
                : $value;
            continue;
        }
        if (is_array($value)) {
            $clean[(string) $key] = ce_event_log_sanitize_context($value);
        }
    }

    return $clean;
}

/**
 * Schrijf een event weg. Faalt nooit hard: een kapotte logregel mag geen request breken.
 *
 * @param array<string, mixed> $context clientId/slotId/httpStatus/endpoint — geen PII
 */
function ce_log_event(
    ?PDO $pdo,
    string $severity,
    string $category,
    string $event,
    ?string $message = null,
    array $context = [],
): void {
    try {
        $severity = in_array($severity, CE_EVENT_SEVERITIES, true) ? $severity : 'info';
        $category = substr(trim($category), 0, 50) ?: 'schema';
        $event = substr(trim($event), 0, 100);
        if ($event === '') {
            return;
        }

        $pdo ??= ce_db_for_logging();
        if ($pdo === null) {
            return;
        }
        ce_ensure_event_log_schema($pdo);

        $clientId = trim((string) ($context['clientId'] ?? $context['client_id'] ?? ''));
        unset($context['clientId'], $context['client_id']);

        $safeMessage = ce_event_log_sanitize_message($message);
        $safeContext = ce_event_log_sanitize_context($context);

        $stmt = $pdo->prepare(
            'INSERT INTO event_log (id, client_id, severity, category, event, message, context, environment)
             VALUES (:id, :client_id, :severity, :category, :event, :message, :context, :environment)'
        );
        $stmt->execute([
            'id' => ce_event_uuid_v4(),
            'client_id' => $clientId !== '' ? $clientId : null,
            'severity' => $severity,
            'category' => $category,
            'event' => $event,
            'message' => $safeMessage,
            'context' => $safeContext === [] ? null : json_encode($safeContext, JSON_UNESCAPED_UNICODE),
            'environment' => ce_env(),
        ]);

        if ($severity === 'error') {
            ce_event_log_notify_error($pdo, $event, $category, $clientId, $safeMessage, $safeContext);
            if ($clientId !== '') {
                require_once __DIR__ . '/tenant-activity-repository.php';
                ce_track_tenant_activity($pdo, $clientId, 'errors');
            }
        }
    } catch (Throwable) {
        // Bewust stil: het loggen zelf mag de applicatie niet laten vallen.
    }
}

/**
 * Eigen verbinding voor het loggen. `ce_db()` stuurt bij een ontbrekende configuratie een
 * 503 en roept `exit` aan — geen Throwable, dus niet te vangen. Dat mag het loggen niet doen.
 */
function ce_db_for_logging(): ?PDO
{
    $config = ce_db_config();
    if ($config['host'] === '' || $config['name'] === '' || $config['user'] === '') {
        return null;
    }

    try {
        return ce_db();
    } catch (Throwable) {
        return null;
    }
}

function ce_event_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function ce_support_email(): string
{
    $email = ce_config_value('CE_SUPPORT_EMAIL', 'support_email');
    if ($email === '') {
        $email = ce_config_value('CE_REVIEW_EMAIL', 'review_email', ce_install_contact_defaults()['support']);
    }

    return $email;
}

/**
 * Mail bij severity=error, met dedupe: zelfde event+client maximaal 1× per uur.
 * Zonder cooldown maakt één kapotte webhook de meldingen onbruikbaar.
 */
function ce_event_log_notify_error(
    PDO $pdo,
    string $event,
    string $category,
    string $clientId,
    ?string $message,
    array $context,
): void {
    if (ce_env() === 'local') {
        return;
    }

    $to = ce_support_email();
    if ($to === '') {
        return;
    }

    // Interval als literal: een placeholder binnen INTERVAL ? MINUTE gedraagt zich
    // verschillend met en zonder emulated prepares. De waarde is een eigen constante.
    $cooldown = (int) CE_EVENT_MAIL_COOLDOWN_MINUTES;

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM event_log
         WHERE event = :event
           AND COALESCE(client_id, \'\') = :client_id
           AND notified_at IS NOT NULL
           AND notified_at >= DATE_SUB(NOW(), INTERVAL ' . $cooldown . ' MINUTE)'
    );
    $stmt->execute(['event' => $event, 'client_id' => $clientId]);

    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    require_once __DIR__ . '/mail-helper.php';

    $env = ce_env();
    $product = ce_product_name();
    $subject = sprintf('[%s %s] %s', $product, strtoupper($env), $event);
    $lines = [
        $product . ' meldt een fout.',
        '',
        'Event:       ' . $event,
        'Categorie:   ' . $category,
        'Omgeving:    ' . $env,
        'Tenant:      ' . ($clientId !== '' ? $clientId : 'platform-breed'),
        'Tijd:        ' . date('Y-m-d H:i:s'),
        '',
        'Melding:     ' . ($message ?? '(geen)'),
    ];
    if ($context !== []) {
        $lines[] = '';
        $lines[] = 'Context:     ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $lines[] = '';
    $lines[] = 'Volgende melding voor dit event pas na ' . CE_EVENT_MAIL_COOLDOWN_MINUTES . ' minuten.';

    $sent = ce_send_mail($to, $subject, implode("\n", $lines));
    if (!empty($sent['ok'])) {
        $pdo->prepare(
            'UPDATE event_log SET notified_at = NOW()
             WHERE event = :event AND COALESCE(client_id, \'\') = :client_id AND notified_at IS NULL
             ORDER BY created_at DESC LIMIT 1'
        )->execute(['event' => $event, 'client_id' => $clientId]);
    }
}

/**
 * @param array{severity?: string, category?: string, clientId?: string, days?: int, limit?: int} $filters
 * @return list<array<string, mixed>>
 */
function ce_fetch_events(PDO $pdo, array $filters = []): array
{
    ce_ensure_event_log_schema($pdo);
    if (!ce_table_exists($pdo, 'event_log')) {
        return [];
    }

    $where = [];
    $params = [];

    $severity = trim((string) ($filters['severity'] ?? ''));
    if (in_array($severity, CE_EVENT_SEVERITIES, true)) {
        $where[] = 'severity = :severity';
        $params['severity'] = $severity;
    }

    $category = trim((string) ($filters['category'] ?? ''));
    if ($category !== '') {
        $where[] = 'category = :category';
        $params['category'] = $category;
    }

    $clientId = trim((string) ($filters['clientId'] ?? ''));
    if ($clientId !== '') {
        $where[] = 'client_id = :client_id';
        $params['client_id'] = $clientId;
    }

    $days = max(1, min(365, (int) ($filters['days'] ?? 7)));
    $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));

    $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';

    $sql = 'SELECT id, client_id, severity, category, event, message, context, environment, created_at
            FROM event_log
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY created_at DESC
            LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map(static function (array $row): array {
        $context = $row['context'] ?? null;
        $row['context'] = is_string($context) ? json_decode($context, true) : null;

        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

/** Retentie afdwingen — bedoeld voor een dagelijkse cron. @return array<string, int> */
function ce_prune_event_log(PDO $pdo): array
{
    ce_ensure_event_log_schema($pdo);
    if (!ce_table_exists($pdo, 'event_log')) {
        return [];
    }

    $deleted = [];
    foreach (CE_EVENT_RETENTION_DAYS as $severity => $days) {
        $stmt = $pdo->prepare(
            'DELETE FROM event_log
             WHERE severity = :severity
               AND created_at < DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)'
        );
        $stmt->execute(['severity' => $severity]);
        $deleted[$severity] = $stmt->rowCount();
    }

    return $deleted;
}
