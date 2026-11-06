<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

const CE_TENANT_ACTIVITY_FIELDS = [
    'logins',
    'articles_created',
    'articles_published',
    'themes_reviewed',
    'api_requests',
    'errors',
];

function ce_ensure_tenant_activity_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!ce_table_exists($pdo, 'tenant_activity_daily')) {
        $migration = dirname(__DIR__, 2) . '/scripts/migrations/add_tenant_activity_daily.sql';
        if (is_readable($migration)) {
            try {
                $pdo->exec((string) file_get_contents($migration));
            } catch (Throwable) {
                // Geen CREATE-rechten — tracking mag nooit blokkeren.
            }
        }
    }
}

/** @param value-of<CE_TENANT_ACTIVITY_FIELDS> $field */
function ce_track_tenant_activity(?PDO $pdo, string $clientId, string $field, int $delta = 1): void
{
    try {
        $clientId = trim($clientId);
        if ($clientId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
            return;
        }
        if (!in_array($field, CE_TENANT_ACTIVITY_FIELDS, true)) {
            return;
        }
        $delta = max(0, $delta);
        if ($delta === 0) {
            return;
        }

        $pdo ??= ce_db();
        ce_ensure_tenant_activity_schema($pdo);
        if (!ce_table_exists($pdo, 'tenant_activity_daily')) {
            return;
        }

        $column = $field;
        $sql = <<<SQL
INSERT INTO tenant_activity_daily (client_id, activity_date, {$column})
VALUES (:client_id, CURDATE(), :delta)
ON DUPLICATE KEY UPDATE {$column} = {$column} + :delta2
SQL;
        $pdo->prepare($sql)->execute([
            'client_id' => $clientId,
            'delta' => $delta,
            'delta2' => $delta,
        ]);
    } catch (Throwable) {
        // Activiteit bijhouden mag nooit een request breken.
    }
}

/**
 * @return array<string, array{lastLogin: ?string, lastPublish: ?string, daysSinceLastLogin: ?int, daysSinceLastPublish: ?int}>
 */
function ce_tenant_activity_summaries(PDO $pdo): array
{
    ce_ensure_tenant_activity_schema($pdo);
    if (!ce_table_exists($pdo, 'tenant_activity_daily')) {
        return [];
    }

    $rows = $pdo->query(
        <<<'SQL'
        SELECT
            client_id,
            MAX(CASE WHEN logins > 0 THEN activity_date END) AS last_login,
            MAX(CASE WHEN articles_published > 0 THEN activity_date END) AS last_publish
        FROM tenant_activity_daily
        GROUP BY client_id
        SQL
    )->fetchAll() ?: [];

    $today = new DateTimeImmutable('today');
    $result = [];
    foreach ($rows as $row) {
        $clientId = (string) ($row['client_id'] ?? '');
        if ($clientId === '') {
            continue;
        }
        $lastLogin = $row['last_login'] ?? null;
        $lastPublish = $row['last_publish'] ?? null;
        $result[$clientId] = [
            'lastLogin' => is_string($lastLogin) ? $lastLogin : null,
            'lastPublish' => is_string($lastPublish) ? $lastPublish : null,
            'daysSinceLastLogin' => ce_tenant_days_since($lastLogin, $today),
            'daysSinceLastPublish' => ce_tenant_days_since($lastPublish, $today),
        ];
    }

    return $result;
}

function ce_tenant_days_since(mixed $dateValue, DateTimeImmutable $today): ?int
{
    if (!is_string($dateValue) || trim($dateValue) === '') {
        return null;
    }
    try {
        $date = new DateTimeImmutable($dateValue);
    } catch (Throwable) {
        return null;
    }

    return max(0, (int) $today->diff($date)->days);
}

/**
 * Laatste logindag vóór vandaag — voor de terugkeerregel (notitie 15 §1.1).
 * Gebruikt tenant_activity_daily; geen nieuwe kolom nodig.
 */
function ce_tenant_previous_login_date(PDO $pdo, ?string $clientId): ?string
{
    $clientId = trim((string) ($clientId ?? ''));
    if ($clientId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        return null;
    }

    ce_ensure_tenant_activity_schema($pdo);
    if (!ce_table_exists($pdo, 'tenant_activity_daily')) {
        return null;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT MAX(activity_date) AS previous_login
FROM tenant_activity_daily
WHERE client_id = :client_id
  AND logins > 0
  AND activity_date < CURDATE()
SQL
    );
    $stmt->execute(['client_id' => $clientId]);
    $value = $stmt->fetchColumn();
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    return substr($value, 0, 10);
}
