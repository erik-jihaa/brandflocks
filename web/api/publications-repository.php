<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';

function ce_ensure_agenda_scheduling_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $migration = dirname(__DIR__, 2) . '/scripts/migrations/add_agenda_scheduling.sql';
    if (!is_readable($migration)) {
        $migration = dirname(__DIR__) . '/migrations/add_agenda_scheduling.sql';
    }
    if (!ce_table_exists($pdo, 'publications') && is_readable($migration)) {
        try {
            $pdo->exec((string) file_get_contents($migration));
        } catch (Throwable) {
            // Kolommen/tabel via ALTER hieronder.
        }
    }

    $alters = [
        ['content_slots', 'publish_date', 'ALTER TABLE content_slots ADD COLUMN publish_date DATE NULL'],
        ['content_slots', 'publish_time', 'ALTER TABLE content_slots ADD COLUMN publish_time TIME NULL'],
        ['articles', 'editorial_status', 'ALTER TABLE articles ADD COLUMN editorial_status VARCHAR(24) NULL'],
    ];
    foreach ($alters as [$table, $column, $sql]) {
        if (ce_table_exists($pdo, $table) && !ce_table_has_column($pdo, $table, $column)) {
            try {
                $pdo->exec($sql);
                unset($GLOBALS['ce_table_columns'][$table]);
            } catch (Throwable) {
            }
        }
    }

    if (!ce_table_exists($pdo, 'publications')) {
        try {
            $pdo->exec(
                "CREATE TABLE publications (
                    id CHAR(36) NOT NULL PRIMARY KEY,
                    article_id CHAR(36) NOT NULL,
                    platform VARCHAR(32) NOT NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'gepland',
                    scheduled_at DATETIME NULL,
                    published_at DATETIME NULL,
                    external_id VARCHAR(128) NULL,
                    external_url TEXT NULL,
                    error_message TEXT NULL,
                    attempts TINYINT NOT NULL DEFAULT 0,
                    UNIQUE KEY uq_publication_article_platform (article_id, platform),
                    KEY idx_publication_article (article_id)
                ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
        }
    }
}

/** @return list<int> weekdag-offsets (0=ma) */
function ce_agenda_default_day_offsets(int $postsPerWeek): array
{
    $n = max(1, $postsPerWeek);
    return match (true) {
        $n === 1 => [2],
        $n === 2 => [1, 4],
        $n === 3 => [1, 3, 4],
        $n === 4 => [0, 1, 3, 4],
        default => range(0, min(6, $n - 1)),
    };
}

function ce_agenda_format_publish_time(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(\d{2}:\d{2})/', $raw, $m)) {
        return $m[1];
    }

    return null;
}

/** Artboard 21-fase: Thema / Onderzoek / Artikel / Publiceer / Mislukt / gepubliceerd */
function ce_agenda_calendar_phase(string $pipelinePhase, string $uiStatus, string $publicationStatus): string
{
    if ($publicationStatus === 'mislukt') {
        return 'failed';
    }
    if ($publicationStatus === 'gepubliceerd' || $uiStatus === 'published') {
        return 'published';
    }
    if ($uiStatus === 'approved') {
        return 'publish';
    }
    if ($pipelinePhase === 'theme' || $uiStatus === 'themed' || $pipelinePhase === 'research') {
        return 'research';
    }

    return 'article';
}

function ce_record_publication_outcome(
    PDO $pdo,
    string $articleId,
    string $platform,
    bool $ok,
    ?string $externalId = null,
    ?string $externalUrl = null,
    ?string $error = null,
): void {
    $articleId = trim($articleId);
    $platform = strtolower(trim($platform));
    if ($articleId === '' || $platform === '') {
        return;
    }
    try {
        ce_upsert_publication($pdo, $articleId, $platform, [
            'status' => $ok ? 'gepubliceerd' : 'mislukt',
            'published_at' => $ok ? date('Y-m-d H:i:s') : null,
            'external_id' => $externalId,
            'external_url' => $externalUrl,
            'error_message' => $ok ? null : $error,
            'attempts' => $ok ? 0 : 1,
        ]);
    } catch (Throwable) {
    }
}

function ce_agenda_default_publish_date(string $mondayIso, int $index, int $postsPerWeek): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mondayIso)) {
        return null;
    }
    $offsets = ce_agenda_default_day_offsets($postsPerWeek);
    $offset = $offsets[$index] ?? $offsets[count($offsets) - 1] ?? 1;
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $mondayIso);
    if (!$date) {
        return null;
    }

    return $date->modify('+' . $offset . ' days')->format('Y-m-d');
}

/** @return array<string, mixed> */
function ce_publication_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'articleId' => (string) ($row['article_id'] ?? ''),
        'platform' => (string) ($row['platform'] ?? ''),
        'status' => (string) ($row['status'] ?? 'gepland'),
        'scheduledAt' => $row['scheduled_at'] ?? null,
        'publishedAt' => $row['published_at'] ?? null,
        'externalId' => $row['external_id'] ?? null,
        'externalUrl' => $row['external_url'] ?? null,
        'errorMessage' => $row['error_message'] ?? null,
        'attempts' => (int) ($row['attempts'] ?? 0),
    ];
}

/** @return list<array<string, mixed>> */
function ce_fetch_publications_for_article(PDO $pdo, string $articleId): array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'article', $articleId);

    ce_ensure_agenda_scheduling_schema($pdo);
    if (!ce_table_exists($pdo, 'publications') || $articleId === '') {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM publications WHERE article_id = :article_id ORDER BY platform ASC'
    );
    $stmt->execute(['article_id' => $articleId]);
    $out = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        if (is_array($row)) {
            $out[] = ce_publication_dto($row);
        }
    }

    return $out;
}

/** Samenvatting voor kalenderkaartje. */
function ce_publication_calendar_status(array $publications): string
{
    if ($publications === []) {
        return '';
    }
    foreach ($publications as $row) {
        if (($row['status'] ?? '') === 'mislukt') {
            return 'mislukt';
        }
    }
    foreach ($publications as $row) {
        if (($row['status'] ?? '') === 'gepubliceerd') {
            return 'gepubliceerd';
        }
    }
    foreach ($publications as $row) {
        if (($row['status'] ?? '') === 'gepland') {
            return 'gepland';
        }
    }

    return (string) ($publications[0]['status'] ?? '');
}

/** @param array<string, mixed> $data */
function ce_upsert_publication(PDO $pdo, string $articleId, string $platform, array $data): array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'article', $articleId);

    ce_ensure_agenda_scheduling_schema($pdo);
    $platform = strtolower(trim($platform));
    if ($articleId === '' || $platform === '') {
        throw new InvalidArgumentException('article_id en platform zijn verplicht');
    }

    $existing = $pdo->prepare(
        'SELECT * FROM publications WHERE article_id = :article_id AND platform = :platform LIMIT 1'
    );
    $existing->execute(['article_id' => $articleId, 'platform' => $platform]);
    $row = $existing->fetch();

    $status = trim((string) ($data['status'] ?? (is_array($row) ? ($row['status'] ?? 'gepland') : 'gepland')));
    $fields = [
        'status' => $status,
        'scheduled_at' => $data['scheduled_at'] ?? (is_array($row) ? ($row['scheduled_at'] ?? null) : null),
        'published_at' => $data['published_at'] ?? (is_array($row) ? ($row['published_at'] ?? null) : null),
        'external_id' => $data['external_id'] ?? (is_array($row) ? ($row['external_id'] ?? null) : null),
        'external_url' => $data['external_url'] ?? (is_array($row) ? ($row['external_url'] ?? null) : null),
        'error_message' => array_key_exists('error_message', $data)
            ? $data['error_message']
            : (is_array($row) ? ($row['error_message'] ?? null) : null),
        'attempts' => isset($data['attempts'])
            ? (int) $data['attempts']
            : (is_array($row) ? ((int) ($row['attempts'] ?? 0)) : 0),
    ];

    if (is_array($row)) {
        $pdo->prepare(
            'UPDATE publications SET
                status = :status, scheduled_at = :scheduled_at, published_at = :published_at,
                external_id = :external_id, external_url = :external_url,
                error_message = :error_message, attempts = :attempts
             WHERE id = :id'
        )->execute($fields + ['id' => $row['id']]);
        $id = (string) $row['id'];
    } else {
        $id = ce_uuid();
        $pdo->prepare(
            'INSERT INTO publications (
                id, article_id, platform, status, scheduled_at, published_at,
                external_id, external_url, error_message, attempts
            ) VALUES (
                :id, :article_id, :platform, :status, :scheduled_at, :published_at,
                :external_id, :external_url, :error_message, :attempts
            )'
        )->execute($fields + [
            'id' => $id,
            'article_id' => $articleId,
            'platform' => $platform,
        ]);
    }

    $fetch = $pdo->prepare('SELECT * FROM publications WHERE id = :id LIMIT 1');
    $fetch->execute(['id' => $id]);
    $saved = $fetch->fetch();

    return ce_publication_dto(is_array($saved) ? $saved : []);
}

function ce_schedule_slot(PDO $pdo, string $slotId, string $publishDate, ?string $publishTime, ?string $platform = null): array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'slot', $slotId);

    ce_ensure_agenda_scheduling_schema($pdo);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishDate)) {
        throw new InvalidArgumentException('publish_date moet Y-m-d zijn');
    }
    $time = $publishTime !== null && $publishTime !== '' ? $publishTime : null;
    if ($time !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        throw new InvalidArgumentException('publish_time moet HH:MM zijn');
    }
    if ($time !== null && strlen($time) === 5) {
        $time .= ':00';
    }

    $stmt = $pdo->prepare(
        'UPDATE content_slots SET publish_date = :publish_date, publish_time = :publish_time WHERE id = :id'
    );
    $stmt->execute([
        'id' => $slotId,
        'publish_date' => $publishDate,
        'publish_time' => $time,
    ]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM content_slots WHERE id = :id LIMIT 1');
        $check->execute(['id' => $slotId]);
        if (!$check->fetch()) {
            throw new RuntimeException('Slot niet gevonden');
        }
    }

    $platformKey = null;
    $platformRaw = strtolower(trim((string) $platform));
    if ($platformRaw !== '') {
        $platformKey = match ($platformRaw) {
            'linkedin', 'linked in' => 'linkedin',
            'instagram', 'insta' => 'instagram',
            default => '',
        };
        if ($platformKey === '') {
            throw new InvalidArgumentException('kanaal moet LinkedIn of Instagram zijn');
        }
        if (ce_table_has_column($pdo, 'content_slots', 'campaign_platform')) {
            $pdo->prepare('UPDATE content_slots SET campaign_platform = :platform WHERE id = :id')
                ->execute(['platform' => $platformKey, 'id' => $slotId]);
        }
        if (ce_table_exists($pdo, 'articles') && ce_table_has_column($pdo, 'articles', 'platform_final')) {
            $pdo->prepare('UPDATE articles SET platform_final = :platform WHERE slot_id = :id')
                ->execute(['platform' => ce_platform_to_db($platformKey), 'id' => $slotId]);
        }
    }

    return [
        'ok' => true,
        'slotId' => $slotId,
        'publishDate' => $publishDate,
        'publishTime' => $time !== null ? substr($time, 0, 5) : null,
        'platform' => $platformKey,
    ];
}
