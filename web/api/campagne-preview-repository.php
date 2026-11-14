<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/fastlane-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/publications-repository.php';
require_once __DIR__ . '/campagne-preview-templates.php';
require_once __DIR__ . '/campagne-preview-product-extract.php';
require_once __DIR__ . '/campagne-preview-plan-check.php';
require_once __DIR__ . '/campagne-preview-ankers.php';
require_once __DIR__ . '/campagne-preview-bank.php';
require_once __DIR__ . '/llm-repository.php';
require_once __DIR__ . '/theme-repository.php';

const CE_CAMPAGNE_PREVIEW_ORIGIN = 'campagne_preview';

const CE_CAMPAGNE_PREVIEW_STATUSES = ['concept', 'bevestigd', 'loopt', 'afgerond', 'verwijderd'];

const CE_CAMPAGNE_PREVIEW_CHANNEL_KEYS = ['linkedin', 'instagram', 'x', 'facebook', 'blog'];

function ce_ensure_campagne_preview_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!ce_table_exists($pdo, 'campaigns')) {
        $migration = dirname(__DIR__) . '/migrations/add_campaigns_table.sql';
        if (!is_readable($migration)) {
            $migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_campaigns_table.sql';
        }
        if (is_readable($migration)) {
            try {
                $pdo->exec((string) file_get_contents($migration));
            } catch (Throwable) {
            }
        }
    }

    $p1Migration = dirname(__DIR__) . '/migrations/add_campagne_preview_p1.sql';
    if (!is_readable($p1Migration)) {
        $p1Migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_campagne_preview_p1.sql';
    }
    if (is_readable($p1Migration)) {
        try {
            $pdo->exec((string) file_get_contents($p1Migration));
        } catch (Throwable) {
        }
    }

    $p2Migration = dirname(__DIR__) . '/migrations/add_campagne_preview_p2.sql';
    if (!is_readable($p2Migration)) {
        $p2Migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_campagne_preview_p2.sql';
    }
    if (is_readable($p2Migration)) {
        try {
            $pdo->exec((string) file_get_contents($p2Migration));
        } catch (Throwable) {
        }
    }

    $p3Migration = dirname(__DIR__) . '/migrations/add_campagne_preview_p3.sql';
    if (!is_readable($p3Migration)) {
        $p3Migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_campagne_preview_p3.sql';
    }
    if (is_readable($p3Migration)) {
        try {
            $pdo->exec((string) file_get_contents($p3Migration));
        } catch (Throwable) {
        }
    }

    $p4Migration = dirname(__DIR__) . '/migrations/add_campagne_preview_p4.sql';
    if (!is_readable($p4Migration)) {
        $p4Migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_campagne_preview_p4.sql';
    }
    if (is_readable($p4Migration)) {
        try {
            $pdo->exec((string) file_get_contents($p4Migration));
        } catch (Throwable) {
        }
    }

    $p5Migration = dirname(__DIR__) . '/migrations/add_campagne_preview_p5.sql';
    if (!is_readable($p5Migration)) {
        $p5Migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_campagne_preview_p5.sql';
    }
    if (is_readable($p5Migration)) {
        try {
            $pdo->exec((string) file_get_contents($p5Migration));
        } catch (Throwable) {
        }
    }

    ce_ensure_campagne_bank_schema($pdo);

    $alters = [
        ['campaigns', 'template_key', 'ALTER TABLE campaigns ADD COLUMN template_key VARCHAR(32) NULL'],
        ['campaigns', 'campaign_goal', 'ALTER TABLE campaigns ADD COLUMN campaign_goal VARCHAR(20) NULL'],
        ['campaigns', 'channels_json', 'ALTER TABLE campaigns ADD COLUMN channels_json JSON NULL'],
        ['campaigns', 'preview_status', "ALTER TABLE campaigns ADD COLUMN preview_status VARCHAR(20) NOT NULL DEFAULT 'concept'"],
        ['content_slots', 'campaign_id', 'ALTER TABLE content_slots ADD COLUMN campaign_id CHAR(36) NULL'],
        ['content_slots', 'campaign_slot_index', 'ALTER TABLE content_slots ADD COLUMN campaign_slot_index INT NULL'],
        ['content_slots', 'campaign_platform', 'ALTER TABLE content_slots ADD COLUMN campaign_platform VARCHAR(32) NULL'],
        ['campaigns', 'phase_plan_json', 'ALTER TABLE campaigns ADD COLUMN phase_plan_json JSON NULL'],
        ['content_slots', 'campaign_fase', 'ALTER TABLE content_slots ADD COLUMN campaign_fase VARCHAR(128) NULL'],
        ['content_slots', 'campaign_opdracht', 'ALTER TABLE content_slots ADD COLUMN campaign_opdracht TEXT NULL'],
        ['content_slots', 'campaign_rol', 'ALTER TABLE content_slots ADD COLUMN campaign_rol VARCHAR(20) NULL'],
        ['campaigns', 'product_page_url', 'ALTER TABLE campaigns ADD COLUMN product_page_url VARCHAR(2048) NULL'],
        ['campaigns', 'product_extract_json', 'ALTER TABLE campaigns ADD COLUMN product_extract_json JSON NULL'],
        ['campaigns', 'product_extract_prompt_version', 'ALTER TABLE campaigns ADD COLUMN product_extract_prompt_version VARCHAR(32) NULL'],
        ['campaigns', 'product_extract_model', 'ALTER TABLE campaigns ADD COLUMN product_extract_model VARCHAR(64) NULL'],
        ['campaigns', 'product_extract_at', 'ALTER TABLE campaigns ADD COLUMN product_extract_at TIMESTAMP NULL'],
        ['campaigns', 'plan_check_json', 'ALTER TABLE campaigns ADD COLUMN plan_check_json JSON NULL'],
        ['campaigns', 'plan_check_prompt_version', 'ALTER TABLE campaigns ADD COLUMN plan_check_prompt_version VARCHAR(32) NULL'],
        ['campaigns', 'plan_check_model', 'ALTER TABLE campaigns ADD COLUMN plan_check_model VARCHAR(64) NULL'],
        ['campaigns', 'plan_check_at', 'ALTER TABLE campaigns ADD COLUMN plan_check_at TIMESTAMP NULL'],
        ['campaigns', 'geo_anchor_ids', 'ALTER TABLE campaigns ADD COLUMN geo_anchor_ids JSON NULL'],
        ['content_slots', 'campaign_anker', 'ALTER TABLE content_slots ADD COLUMN campaign_anker JSON NULL'],
        ['campaigns', 'merk_onderdelen_json', 'ALTER TABLE campaigns ADD COLUMN merk_onderdelen_json JSON NULL'],
        ['campaigns', 'feit_anchor_ids', 'ALTER TABLE campaigns ADD COLUMN feit_anchor_ids JSON NULL'],
        ['campaigns', 'vraag_anchor_ids', 'ALTER TABLE campaigns ADD COLUMN vraag_anchor_ids JSON NULL'],
        ['content_slots', 'campaign_merk_onderdeel', 'ALTER TABLE content_slots ADD COLUMN campaign_merk_onderdeel VARCHAR(64) NULL'],
        ['campaigns', 'plan_decisions_json', 'ALTER TABLE campaigns ADD COLUMN plan_decisions_json JSON NULL'],
        ['content_slots', 'campaign_instructie', 'ALTER TABLE content_slots ADD COLUMN campaign_instructie TEXT NULL'],
        ['campaigns', 'calendar_color', 'ALTER TABLE campaigns ADD COLUMN calendar_color TINYINT NULL'],
        ['campaigns', 'extra_thoughts', 'ALTER TABLE campaigns ADD COLUMN extra_thoughts TEXT NULL'],
        ['campaigns', 'source_folder_id', 'ALTER TABLE campaigns ADD COLUMN source_folder_id CHAR(36) NULL'],
        ['campaigns', 'hashtags_json', 'ALTER TABLE campaigns ADD COLUMN hashtags_json JSON NULL'],
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

    ce_ensure_agenda_scheduling_schema($pdo);
    ce_ensure_headline_human_column($pdo);
    ce_campagne_backfill_calendar_colors($pdo);
}

/** Bestaande campagnes krijgen de kleur die de agenda nu al toont (colorIndex % 3). */
function ce_campagne_backfill_calendar_colors(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!ce_table_exists($pdo, 'campaigns') || !ce_table_has_column($pdo, 'campaigns', 'calendar_color')) {
        return;
    }
    if (!ce_table_exists($pdo, 'content_slots') || !ce_table_has_column($pdo, 'content_slots', 'campaign_id')) {
        return;
    }

    require_once __DIR__ . '/campagne-calendar-color.php';

    try {
        $clients = $pdo->query('SELECT DISTINCT client_id FROM campaigns')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) {
        return;
    }

    $slotClient = ce_table_has_column($pdo, 'content_slots', 'client_id')
        ? ' AND s.client_id = :client'
        : '';

    foreach ($clients as $clientId) {
        $clientId = trim((string) $clientId);
        if ($clientId === '') {
            continue;
        }
        $stmt = $pdo->prepare(
            'SELECT s.campaign_id
             FROM content_slots s
             INNER JOIN campaigns c ON c.id = s.campaign_id AND c.client_id = :client
             WHERE COALESCE(s.status, \'\') NOT IN (\'archived\', \'archive\')
               AND s.campaign_id IS NOT NULL'
            . $slotClient
            . ' ORDER BY s.phase_key ASC, s.theme ASC, s.id ASC'
        );
        $stmt->execute(['client' => $clientId]);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[] = (string) $id;
        }
        $colors = ce_campagne_calendar_colors_for_row_order($ids);
        $update = $pdo->prepare(
            'UPDATE campaigns
             SET calendar_color = :color
             WHERE id = :id AND client_id = :client AND calendar_color IS NULL'
        );
        foreach ($colors as $id => $color) {
            $update->execute(['color' => $color, 'id' => $id, 'client' => $clientId]);
        }
    }
}

function ce_campagne_normalize_source_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    return $url;
}

/**
 * @param list<mixed> $raw
 * @return list<array{url: string, title: string}>
 */
function ce_campagne_normalize_link_sources(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (($item['kind'] ?? 'link') === 'file') {
            continue;
        }
        $url = ce_campagne_normalize_source_url((string) ($item['url'] ?? ''));
        if ($url === '' || isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $title = trim((string) ($item['title'] ?? $item['label'] ?? ''));
        if ($title === '') {
            $title = $url;
        }
        $out[] = ['url' => $url, 'title' => $title];
    }

    return $out;
}

/** @return list<array{id: string, kind: string, label: string, url: string|null}> */
function ce_campagne_sources_for_folder(PDO $pdo, ?string $folderId): array
{
    $folderId = trim((string) $folderId);
    if ($folderId === '' || !ce_table_exists($pdo, 'sources') || !ce_table_has_column($pdo, 'sources', 'folder_id')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, category, title, url FROM sources WHERE folder_id = :folder ORDER BY title ASC'
    );
    $stmt->execute(['folder' => $folderId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $category = (string) ($row['category'] ?? '');
        $items[] = [
            'id' => (string) ($row['id'] ?? ''),
            'kind' => $category === 'document' ? 'file' : 'link',
            'label' => trim((string) ($row['title'] ?? '')) ?: trim((string) ($row['url'] ?? '')),
            'url' => trim((string) ($row['url'] ?? '')) ?: null,
        ];
    }

    return $items;
}

/**
 * @param list<array{url: string, title: string}> $links
 */
function ce_campagne_sync_link_sources(
    PDO $pdo,
    string $campaignId,
    string $clientId,
    string $title,
    array $links,
    bool $ensureFolder = false,
): ?string {
    if (!ce_table_has_column($pdo, 'campaigns', 'source_folder_id')) {
        return null;
    }
    require_once __DIR__ . '/source-folders-repository.php';
    require_once __DIR__ . '/sources-repository.php';

    $stmt = $pdo->prepare('SELECT source_folder_id FROM campaigns WHERE id = :id AND client_id = :client LIMIT 1');
    $stmt->execute(['id' => $campaignId, 'client' => $clientId]);
    $folderId = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($folderId === '' && ($links !== [] || $ensureFolder)) {
        $folder = ce_create_source_folder($pdo, $title !== '' ? $title : 'Campagne', $clientId);
        $folderId = trim((string) ($folder['id'] ?? ''));
        if ($folderId !== '') {
            $pdo->prepare('UPDATE campaigns SET source_folder_id = :folder WHERE id = :id AND client_id = :client')
                ->execute(['folder' => $folderId, 'id' => $campaignId, 'client' => $clientId]);
        }
    }
    if ($folderId === '') {
        return null;
    }

    $existing = $pdo->prepare(
        'SELECT id, url FROM sources WHERE folder_id = :folder AND category = :category'
    );
    $existing->execute(['folder' => $folderId, 'category' => 'url_rss']);
    $byUrl = [];
    foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $url = ce_campagne_normalize_source_url((string) ($row['url'] ?? ''));
        if ($url !== '') {
            $byUrl[$url] = (string) ($row['id'] ?? '');
        }
    }
    $keep = [];
    foreach ($links as $link) {
        $keep[$link['url']] = true;
        if (isset($byUrl[$link['url']])) {
            continue;
        }
        try {
            ce_create_source($pdo, 'url_rss', [
                'title' => $link['title'],
                'url' => $link['url'],
                'folderId' => $folderId,
            ]);
        } catch (Throwable) {
            // Bron niet aangemaakt — de campagne zelf blijft staan.
        }
    }
    foreach ($byUrl as $url => $sourceId) {
        if (!isset($keep[$url]) && $sourceId !== '') {
            $pdo->prepare('DELETE FROM sources WHERE id = :id AND folder_id = :folder AND category = :category')
                ->execute(['id' => $sourceId, 'folder' => $folderId, 'category' => 'url_rss']);
        }
    }

    return $folderId;
}

/** @return array<string, int> */
function ce_campagne_preview_normalize_channels(mixed $raw): array
{
    if (!is_array($raw)) {
        throw new InvalidArgumentException('channels is verplicht (object met aantallen per kanaal)');
    }

    $out = [];
    foreach ($raw as $key => $value) {
        $platform = strtolower(trim((string) $key));
        if ($platform === '') {
            continue;
        }
        if (!in_array($platform, CE_CAMPAGNE_PREVIEW_CHANNEL_KEYS, true)) {
            throw new InvalidArgumentException('Onbekend kanaal: ' . $platform);
        }
        $count = (int) $value;
        if ($count < 0) {
            throw new InvalidArgumentException('Aantal posts kan niet negatief zijn voor ' . $platform);
        }
        if ($count > 0) {
            $out[$platform] = $count;
        }
    }

    if ($out === []) {
        throw new InvalidArgumentException('Minimaal één kanaal met aantal posts > 0');
    }

    $total = array_sum($out);
    if ($total > 100) {
        throw new InvalidArgumentException('Maximaal 100 slots per campagne in preview');
    }

    return $out;
}

/** @param array<string, mixed> $row */
function ce_campagne_preview_map_campaign(array $row, int $slotCount = 0, ?PDO $pdo = null): array
{
    $channels = [];
    $raw = $row['channels_json'] ?? null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $channels = $decoded;
        }
    } elseif (is_array($raw)) {
        $channels = $raw;
    }

    $phasePlan = null;
    $phaseRaw = $row['phase_plan_json'] ?? null;
    if (is_string($phaseRaw) && $phaseRaw !== '') {
        $decoded = json_decode($phaseRaw, true);
        if (is_array($decoded)) {
            $phasePlan = $decoded;
        }
    } elseif (is_array($phaseRaw)) {
        $phasePlan = $phaseRaw;
    }

    return [
        'id' => (string) ($row['id'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'templateKey' => trim((string) ($row['template_key'] ?? '')) ?: null,
        'phasePlan' => $phasePlan,
        'goal' => trim((string) ($row['campaign_goal'] ?? '')) ?: null,
        'startDate' => trim((string) ($row['start_date'] ?? '')) ?: null,
        'endDate' => trim((string) ($row['end_date'] ?? '')) ?: null,
        'channels' => $channels,
        'previewStatus' => trim((string) ($row['preview_status'] ?? 'concept')) ?: 'concept',
        'postCount' => (int) ($row['post_count'] ?? 0),
        'slotCount' => $slotCount,
        'createdAt' => $row['created_at'] ?? null,
        'productPageUrl' => trim((string) ($row['product_page_url'] ?? '')) ?: null,
        'productExtract' => ce_campagne_preview_product_extract_from_row($row),
        'productExtractMeta' => [
            'promptVersion' => trim((string) ($row['product_extract_prompt_version'] ?? '')) ?: null,
            'model' => trim((string) ($row['product_extract_model'] ?? '')) ?: null,
            'extractedAt' => $row['product_extract_at'] ?? null,
        ],
        'planCheck' => ce_campagne_preview_plan_check_from_row($row),
        'geoQuestionIds' => ce_campagne_preview_geo_ids_from_row($row),
        'vraagIds' => ce_campagne_preview_json_ids_from_row($row, 'vraag_anchor_ids'),
        'feitIds' => ce_campagne_preview_json_ids_from_row($row, 'feit_anchor_ids'),
        'merkOnderdelen' => ce_campagne_normalize_merk_onderdelen(
            ce_campagne_preview_json_ids_from_row($row, 'merk_onderdelen_json'),
        ),
        'planDecisions' => ce_campagne_preview_plan_decisions_from_row($row),
        'calendarColor' => array_key_exists('calendar_color', $row) && $row['calendar_color'] !== null && $row['calendar_color'] !== ''
            ? (int) $row['calendar_color']
            : null,
        'extraThoughts' => trim((string) ($row['extra_thoughts'] ?? '')),
        'sourceFolderId' => trim((string) ($row['source_folder_id'] ?? '')) ?: null,
        'sources' => $pdo instanceof PDO
            ? ce_campagne_sources_for_folder($pdo, isset($row['source_folder_id']) ? (string) $row['source_folder_id'] : null)
            : [],
        'hashtags' => ce_campagne_normalize_hashtags($row['hashtags_json'] ?? []),
    ];
}

/**
 * @param mixed $raw
 * @return list<string>
 */
function ce_campagne_normalize_hashtags(mixed $raw): array
{
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : (preg_split('/[\s,]+/', $raw) ?: []);
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $item) {
        $tag = trim((string) $item);
        if ($tag === '') {
            continue;
        }
        if ($tag[0] !== '#') {
            $tag = '#' . ltrim($tag, '#');
        }
        if (!in_array($tag, $out, true)) {
            $out[] = $tag;
        }
    }

    return $out;
}

/** @param array<string, mixed> $row */
function ce_campagne_preview_plan_decisions_from_row(array $row): array
{
    $raw = $row['plan_decisions_json'] ?? null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    return is_array($raw) ? $raw : [];
}

/** @return list<string> */
function ce_campagne_preview_json_ids_from_row(array $row, string $column): array
{
    $raw = $row[$column] ?? null;
    $decoded = [];
    if (is_string($raw) && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $decoded = $json;
        }
    } elseif (is_array($raw)) {
        $decoded = $raw;
    }
    $ids = [];
    foreach ($decoded as $id) {
        $t = trim((string) $id);
        if ($t !== '') {
            $ids[] = $t;
        }
    }

    return $ids;
}

/** @return list<string> */
function ce_campagne_preview_geo_ids_from_row(array $row): array
{
    return ce_campagne_preview_json_ids_from_row($row, 'geo_anchor_ids');
}

/** @return array{soort: string, id: string, vraag: string, formulering: string, tekst: string}|null */
function ce_campagne_preview_anker_from_slot_row(array $slot): ?array
{
    $raw = $slot['campaign_anker'] ?? null;
    $decoded = null;
    if (is_string($raw) && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $decoded = $json;
        }
    } elseif (is_array($raw)) {
        $decoded = $raw;
    }
    if (!is_array($decoded)) {
        return null;
    }
    $tekst = trim((string) ($decoded['tekst'] ?? $decoded['vraag'] ?? $decoded['question'] ?? $decoded['formulering'] ?? ''));
    if ($tekst === '') {
        return null;
    }
    $soort = strtolower(trim((string) ($decoded['soort'] ?? 'vraag'))) ?: 'vraag';

    return [
        'soort' => $soort,
        'id' => (string) ($decoded['id'] ?? ''),
        'vraag' => $soort === 'vraag' ? $tekst : (string) ($decoded['vraag'] ?? ''),
        'formulering' => $soort === 'feit' ? $tekst : (string) ($decoded['formulering'] ?? ''),
        'tekst' => $tekst,
    ];
}

/** @return array<string, mixed>|null */
function ce_campagne_preview_plan_check_from_row(array $row): ?array
{
    $raw = $row['plan_check_json'] ?? null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    if (is_array($raw)) {
        return $raw;
    }

    return null;
}

/** @return array<string, mixed>|null */
function ce_campagne_preview_product_extract_from_row(array $row): ?array
{
    $raw = $row['product_extract_json'] ?? null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    if (is_array($raw)) {
        return $raw;
    }

    return null;
}

/** @return list<array<string, mixed>> */
function ce_campagne_preview_list(PDO $pdo, ?string $clientId): array
{
    ce_ensure_campagne_preview_schema($pdo);
    if (!ce_table_exists($pdo, 'campaigns')) {
        return [];
    }

    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
    $previewFilter = ce_table_has_column($pdo, 'campaigns', 'preview_status')
        ? " AND COALESCE(c.preview_status, 'concept') NOT IN ('verwijderd')"
        : '';

    $sql = <<<SQL
SELECT c.*,
       (SELECT COUNT(*) FROM content_slots s WHERE s.campaign_id = c.id) AS slot_count
FROM campaigns c
WHERE 1=1
{$previewFilter}
{$scope['sql']}
ORDER BY c.created_at DESC
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($scope['params']);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = ce_campagne_preview_map_campaign($row, (int) ($row['slot_count'] ?? 0), $pdo);
    }

    return $items;
}

function ce_campagne_preview_fetch(PDO $pdo, string $campaignId, ?string $clientId): ?array
{
    ce_ensure_campagne_preview_schema($pdo);
    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
    $sql = 'SELECT c.* FROM campaigns c WHERE c.id = :id' . $scope['sql'] . ' LIMIT 1';
    $params = array_merge(['id' => $campaignId], $scope['params']);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    ce_campagne_repair_campaign_slots($pdo, $campaignId);

    $ankerSelect = ce_table_has_column($pdo, 'content_slots', 'campaign_anker')
        ? 's.campaign_anker'
        : 'NULL AS campaign_anker';
    $onderdeelSelect = ce_table_has_column($pdo, 'content_slots', 'campaign_merk_onderdeel')
        ? 's.campaign_merk_onderdeel'
        : 'NULL AS campaign_merk_onderdeel';
    $instructieSelect = ce_table_has_column($pdo, 'content_slots', 'campaign_instructie')
        ? 's.campaign_instructie'
        : 'NULL AS campaign_instructie';
    $timeSelect = ce_table_has_column($pdo, 'content_slots', 'publish_time')
        ? 's.publish_time'
        : 'NULL AS publish_time';
    $slotStmt = $pdo->prepare(
        "SELECT s.id, s.theme, s.campaign_id, s.campaign_platform, s.campaign_slot_index, s.publish_date, {$timeSelect}, s.phase_key, s.status,
                s.campaign_fase, s.campaign_opdracht, s.campaign_rol, {$ankerSelect}, {$onderdeelSelect}, {$instructieSelect}
         FROM content_slots s WHERE s.campaign_id = :cid ORDER BY s.publish_date ASC, s.campaign_slot_index ASC"
    );
    $slotStmt->execute(['cid' => $campaignId]);
    require_once __DIR__ . '/channel-copy-constraints.php';
    $slots = [];
    foreach ($slotStmt->fetchAll(PDO::FETCH_ASSOC) as $slot) {
        $platform = trim((string) ($slot['campaign_platform'] ?? ''));
        $textvormDefault = $platform !== '' ? ce_default_textvorm_for_platform($platform) : 'post';
        $channelHints = $platform !== ''
            ? ce_channel_soft_hints_for_proposal($platform, $textvormDefault)
            : [];
        $resolvedTitle = ce_campagne_resolve_slot_title($pdo, $slot);
        $displayTitle = $resolvedTitle !== '' ? $resolvedTitle : (string) ($slot['theme'] ?? '');
        $slots[] = [
            'slotId' => (string) $slot['id'],
            'title' => $displayTitle,
            'platform' => $platform !== '' ? ce_platform_to_ui($platform) : null,
            'platformKey' => $platform !== '' ? $platform : null,
            'channelHints' => $channelHints,
            'slotIndex' => isset($slot['campaign_slot_index']) ? (int) $slot['campaign_slot_index'] : null,
            'publishDate' => trim((string) ($slot['publish_date'] ?? '')) ?: null,
            'publishTime' => preg_match('/^(\d{2}:\d{2})/', trim((string) ($slot['publish_time'] ?? '')), $timeMatch)
                ? $timeMatch[1]
                : null,
            'phaseKey' => trim((string) ($slot['phase_key'] ?? '')) ?: null,
            'status' => ce_slot_status_to_ui($slot['status'] ?? null),
            'faseKey' => trim((string) ($slot['campaign_fase'] ?? '')) ?: null,
            'faseLabel' => ce_campagne_preview_fase_label(
                trim((string) ($row['template_key'] ?? '')),
                trim((string) ($slot['campaign_fase'] ?? '')),
            ),
            'opdracht' => trim((string) ($slot['campaign_opdracht'] ?? '')) ?: null,
            'instructie' => trim((string) ($slot['campaign_instructie'] ?? '')) ?: null,
            'rol' => trim((string) ($slot['campaign_rol'] ?? '')) ?: null,
            'merkOnderdeel' => ce_campagne_normalize_merk_onderdeel((string) ($slot['campaign_merk_onderdeel'] ?? '')),
            'anker' => ce_campagne_preview_anker_from_slot_row($slot),
        ];
    }

    return [
        'campaign' => ce_campagne_preview_map_campaign($row, count($slots), $pdo),
        'slots' => $slots,
        'availableGeoQuestions' => ce_fetch_llm_questions($pdo, $clientId, 'geo'),
    ];
}

/** @param array<string, mixed> $body */
function ce_campagne_preview_create(PDO $pdo, array $body): array
{
    ce_ensure_campagne_preview_schema($pdo);

    $title = trim((string) ($body['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('title is verplicht');
    }

    $startDate = trim((string) ($body['startDate'] ?? $body['start_date'] ?? ''));
    $endDate = trim((string) ($body['endDate'] ?? $body['end_date'] ?? ''));
    if ($startDate === '' || $endDate === '') {
        throw new InvalidArgumentException('startDate en endDate zijn verplicht (Y-m-d)');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        throw new InvalidArgumentException('Datums moeten Y-m-d zijn');
    }

    $channels = ce_campagne_preview_normalize_channels($body['channels'] ?? []);
    $goal = trim((string) ($body['goal'] ?? $body['campaignGoal'] ?? ''));
    $templateKey = ce_campagne_preview_normalize_template_key(
        (string) ($body['templateKey'] ?? $body['template_key'] ?? 'lancering'),
    );
    $phasePlan = is_array($body['phasePlan'] ?? null) ? $body['phasePlan'] : null;
    $schedule = is_array($body['schedule'] ?? null) ? $body['schedule'] : null;
    if ($templateKey === 'serie' && $schedule === null) {
        $schedule = ['mode' => 'weekly', 'weekday' => 2];
    }
    $themeBrief = trim((string) ($body['theme'] ?? $body['briefing'] ?? ''));
    $productPageUrl = trim((string) ($body['productPageUrl'] ?? $body['product_page_url'] ?? ''));
    if ($productPageUrl !== '' && $templateKey !== 'lancering') {
        throw new InvalidArgumentException('productPageUrl is alleen voor sjabloon lancering (A)');
    }
    if ($productPageUrl !== '') {
        $productPageUrl = ce_campagne_product_extract_normalize_url($productPageUrl);
    }
    $geoQuestionIds = $body['geoQuestionIds'] ?? $body['geo_question_ids'] ?? null;
    $vraagIds = $body['vraagIds'] ?? $body['vraag_ids'] ?? null;
    $feitIds = $body['feitIds'] ?? $body['feit_ids'] ?? null;
    $merkOnderdelen = ce_campagne_normalize_merk_onderdelen($body['merkOnderdelen'] ?? $body['merk_onderdelen'] ?? []);
    $forceAllAnkers = filter_var($body['forceAllAnkers'] ?? false, FILTER_VALIDATE_BOOLEAN);
    require_once __DIR__ . '/campagne-calendar-color.php';
    $calendarColor = null;
    if (array_key_exists('calendarColor', $body) || array_key_exists('calendar_color', $body)) {
        $calendarColor = ce_campagne_normalize_calendar_color($body['calendarColor'] ?? $body['calendar_color']);
    }
    $extraThoughts = trim((string) ($body['extraThoughts'] ?? $body['extra_thoughts'] ?? ''));
    $hashtags = ce_campagne_normalize_hashtags($body['hashtags'] ?? []);
    $linkSources = ce_campagne_normalize_link_sources($body['sources'] ?? []);

    $explicitClient = trim((string) ($body['clientId'] ?? $body['client_id'] ?? ''));
    $clientId = ce_resolve_client_id($pdo, $explicitClient !== '' ? $explicitClient : ce_default_client_id($pdo));
    if ($clientId === null) {
        throw new RuntimeException('Geen actieve klant gevonden');
    }

    $totalPosts = array_sum($channels);
    $resolvedPhases = ce_campagne_preview_resolve_phases($templateKey, $phasePlan);
    $phaseSequence = ce_campagne_preview_allocate_phases($resolvedPhases, $totalPosts);
    $campaignId = ce_uuid_v4();
    $channelsJson = json_encode($channels, JSON_THROW_ON_ERROR);
    $phasePlanJson = $phasePlan !== null ? json_encode($phasePlan, JSON_THROW_ON_ERROR) : null;
    $generatedPlan = json_encode([
        'preview' => true,
        'p2' => true,
        'templateKey' => $templateKey,
        'phasePlan' => $phasePlan,
        'schedule' => $schedule,
        'phaseSequence' => array_map(
            static fn (array $p): string => (string) ($p['key'] ?? ''),
            $phaseSequence,
        ),
    ], JSON_THROW_ON_ERROR);

    $pdo->beginTransaction();

    try {
        $cols = ['id', 'client_id', 'title', 'theme', 'post_count', 'start_date', 'generated_plan', 'status'];
        $vals = [':id', ':client_id', ':title', ':theme', ':post_count', ':start_date', ':generated_plan', ':status'];
        $params = [
            'id' => $campaignId,
            'client_id' => $clientId,
            'title' => $title,
            'theme' => $themeBrief !== '' ? $themeBrief : $title,
            'post_count' => $totalPosts,
            'start_date' => $startDate,
            'generated_plan' => $generatedPlan,
            'status' => 'active',
        ];

        if (ce_table_has_column($pdo, 'campaigns', 'end_date')) {
            $cols[] = 'end_date';
            $vals[] = ':end_date';
            $params['end_date'] = $endDate;
        }
        if (ce_table_has_column($pdo, 'campaigns', 'channels_json')) {
            $cols[] = 'channels_json';
            $vals[] = ':channels_json';
            $params['channels_json'] = $channelsJson;
        }
        if (ce_table_has_column($pdo, 'campaigns', 'campaign_goal') && $goal !== '') {
            $cols[] = 'campaign_goal';
            $vals[] = ':campaign_goal';
            $params['campaign_goal'] = $goal;
        }
        if (ce_table_has_column($pdo, 'campaigns', 'template_key')) {
            $cols[] = 'template_key';
            $vals[] = ':template_key';
            $params['template_key'] = $templateKey;
        }
        if (ce_table_has_column($pdo, 'campaigns', 'phase_plan_json') && $phasePlanJson !== null) {
            $cols[] = 'phase_plan_json';
            $vals[] = ':phase_plan_json';
            $params['phase_plan_json'] = $phasePlanJson;
        }
        if (ce_table_has_column($pdo, 'campaigns', 'preview_status')) {
            $cols[] = 'preview_status';
            $vals[] = ':preview_status';
            $params['preview_status'] = 'concept';
        }
        if (ce_table_has_column($pdo, 'campaigns', 'merk_onderdelen_json') && $merkOnderdelen !== []) {
            $cols[] = 'merk_onderdelen_json';
            $vals[] = ':merk_onderdelen_json';
            $params['merk_onderdelen_json'] = json_encode($merkOnderdelen, JSON_THROW_ON_ERROR);
        }
        if ($calendarColor !== null && ce_table_has_column($pdo, 'campaigns', 'calendar_color')) {
            $cols[] = 'calendar_color';
            $vals[] = ':calendar_color';
            $params['calendar_color'] = $calendarColor;
        }
        if (ce_table_has_column($pdo, 'campaigns', 'extra_thoughts')) {
            $cols[] = 'extra_thoughts';
            $vals[] = ':extra_thoughts';
            $params['extra_thoughts'] = $extraThoughts !== '' ? $extraThoughts : null;
        }
        if (ce_table_has_column($pdo, 'campaigns', 'hashtags_json')) {
            $cols[] = 'hashtags_json';
            $vals[] = ':hashtags_json';
            $params['hashtags_json'] = json_encode($hashtags, JSON_THROW_ON_ERROR);
        }

        $pdo->prepare(
            'INSERT INTO campaigns (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'
        )->execute($params);

        $createdSlots = ce_campagne_preview_generate_slots(
            $pdo,
            $campaignId,
            $clientId,
            $title,
            $startDate,
            $endDate,
            $channels,
            $templateKey,
            $phaseSequence,
            $schedule,
            $merkOnderdelen,
        );

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $ensureFolder = $linkSources !== []
        || filter_var($body['ensureSourceFolder'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($ensureFolder) {
        try {
            ce_campagne_sync_link_sources($pdo, $campaignId, $clientId, $title, $linkSources, true);
        } catch (Throwable) {
            // Bronnenmap mag de campagne niet tegenhouden.
        }
    }

    $productExtractResult = null;
    if ($productPageUrl !== '') {
        $productExtractResult = ce_campagne_preview_run_product_extract(
            $pdo,
            $campaignId,
            $clientId,
            $productPageUrl,
        );
    }

    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);

    $response = [
        'ok' => true,
        'campaignId' => $campaignId,
        'slotsCreated' => $createdSlots,
        'campaign' => $detail['campaign'] ?? null,
        'slots' => $detail['slots'] ?? [],
    ];
    if ($productExtractResult !== null) {
        $response['productExtract'] = $productExtractResult;
    }
    $resolvedVraagIds = is_array($vraagIds) ? $vraagIds : (is_array($geoQuestionIds) ? $geoQuestionIds : []);
    $resolvedFeitIds = is_array($feitIds) ? $feitIds : [];
    if ($resolvedVraagIds !== [] || $resolvedFeitIds !== []) {
        try {
            ce_campagne_preview_set_bank_ankers(
                $pdo,
                $campaignId,
                $clientId,
                $resolvedVraagIds,
                $resolvedFeitIds,
                $forceAllAnkers,
            );
        } catch (InvalidArgumentException $e) {
            if (is_array($geoQuestionIds) && $geoQuestionIds !== []) {
                ce_campagne_preview_set_geo_ankers($pdo, $campaignId, $clientId, $geoQuestionIds);
            } else {
                throw $e;
            }
        }
        $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
        $response['campaign'] = $detail['campaign'] ?? $response['campaign'];
        $response['slots'] = $detail['slots'] ?? $response['slots'];
    }

    return $response;
}

/** @return array<string, mixed>|null */
function ce_campagne_marker_from_slot_row(array $slotRow): ?array
{
    $raw = trim((string) ($slotRow['input_human'] ?? ''));
    if ($raw === '') {
        return null;
    }
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return null;
    }
    if (!is_array($decoded) || empty($decoded['campagnePreview'])) {
        return null;
    }

    return $decoded;
}

/** Vul ontbrekende campaign_* kolommen uit input_human-marker (alleen in geheugen). */
function ce_campagne_enrich_slot_row(array $slotRow): array
{
    $marker = ce_campagne_marker_from_slot_row($slotRow);
    if ($marker === null) {
        return $slotRow;
    }
    if (trim((string) ($slotRow['campaign_id'] ?? '')) === '') {
        $fromMarker = trim((string) ($marker['campaignId'] ?? ''));
        if ($fromMarker !== '') {
            $slotRow['campaign_id'] = $fromMarker;
        }
    }
    if (trim((string) ($slotRow['campaign_platform'] ?? '')) === '') {
        $platform = strtolower(trim((string) ($marker['platform'] ?? '')));
        if ($platform !== '') {
            $slotRow['campaign_platform'] = $platform;
        }
    }
    if (
        !isset($slotRow['campaign_slot_index'])
        || trim((string) $slotRow['campaign_slot_index']) === ''
    ) {
        if (isset($marker['slotIndex']) && is_numeric($marker['slotIndex'])) {
            $slotRow['campaign_slot_index'] = (int) $marker['slotIndex'];
        }
    }
    if (trim((string) ($slotRow['campaign_fase'] ?? '')) === '') {
        $fase = trim((string) ($marker['faseKey'] ?? ''));
        if ($fase !== '') {
            $slotRow['campaign_fase'] = $fase;
        }
    }
    if (trim((string) ($slotRow['campaign_platform'] ?? '')) === '') {
        $fromTheme = ce_campagne_infer_platform_from_theme((string) ($slotRow['theme'] ?? ''));
        if ($fromTheme !== '') {
            $slotRow['campaign_platform'] = $fromTheme;
        }
    }

    return $slotRow;
}

function ce_campagne_infer_platform_from_theme(string $theme): string
{
    $theme = trim($theme);
    if ($theme === '') {
        return '';
    }
    require_once __DIR__ . '/mappers.php';
    foreach (['Linkedin', 'Instagram', 'Facebook', 'Blog', 'X'] as $label) {
        if (preg_match('/\b' . preg_quote($label, '/') . '\b/i', $theme)) {
            return ce_platform_to_db($label);
        }
    }

    return '';
}

/** Vul ontbrekende campaign_platform op campagne-slots (o.a. na fase-reapply zonder platform in marker). */
function ce_campagne_repair_campaign_platforms(PDO $pdo, string $campaignId): int
{
    if ($campaignId === '' || !ce_table_has_column($pdo, 'content_slots', 'campaign_platform')) {
        return 0;
    }

    $defaultPlatform = '';
    if (ce_table_exists($pdo, 'campaigns') && ce_table_has_column($pdo, 'campaigns', 'channels_json')) {
        $cStmt = $pdo->prepare('SELECT channels_json FROM campaigns WHERE id = :id LIMIT 1');
        $cStmt->execute(['id' => $campaignId]);
        $raw = $cStmt->fetchColumn();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && count($decoded) === 1) {
                $defaultPlatform = strtolower(trim((string) array_key_first($decoded)));
            }
        }
    }

    $stmt = $pdo->prepare(
        'SELECT id, theme, campaign_platform, input_human FROM content_slots WHERE campaign_id = :cid'
    );
    $stmt->execute(['cid' => $campaignId]);
    $updated = 0;
    $upd = $pdo->prepare('UPDATE content_slots SET campaign_platform = :platform WHERE id = :id');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if (trim((string) ($row['campaign_platform'] ?? '')) !== '') {
            continue;
        }
        $platform = '';
        $marker = ce_campagne_marker_from_slot_row($row);
        if ($marker !== null) {
            $platform = strtolower(trim((string) ($marker['platform'] ?? '')));
        }
        if ($platform === '') {
            $platform = ce_campagne_infer_platform_from_theme((string) ($row['theme'] ?? ''));
        }
        if ($platform === '' && $defaultPlatform !== '') {
            $platform = $defaultPlatform;
        }
        if ($platform === '') {
            continue;
        }
        $upd->execute(['platform' => $platform, 'id' => $row['id']]);
        ++$updated;
    }

    return $updated;
}

/**
 * Repareer campaign_id / platform op één slot vanuit marker; sync daarna titels voor de campagne.
 */
function ce_campagne_repair_slot_campaign_columns(PDO $pdo, string $slotId): bool
{
    if ($slotId === '' || !ce_table_exists($pdo, 'content_slots')) {
        return false;
    }
    $cols = ['id', 'campaign_id', 'campaign_platform', 'campaign_slot_index', 'campaign_fase', 'input_human', 'origin'];
    $stmt = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM content_slots WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $slotId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return false;
    }
    $marker = ce_campagne_marker_from_slot_row($row);
    if ($marker === null) {
        return false;
    }

    $sets = [];
    $params = ['id' => $slotId];
    $campaignId = trim((string) ($row['campaign_id'] ?? ''));
    if ($campaignId === '') {
        $fromMarker = trim((string) ($marker['campaignId'] ?? ''));
        if ($fromMarker !== '') {
            $campaignId = $fromMarker;
            if (ce_table_has_column($pdo, 'content_slots', 'campaign_id')) {
                $sets[] = 'campaign_id = :campaign_id';
                $params['campaign_id'] = $campaignId;
            }
        }
    }
    if (trim((string) ($row['campaign_platform'] ?? '')) === '') {
        $platform = strtolower(trim((string) ($marker['platform'] ?? '')));
        if ($platform !== '' && ce_table_has_column($pdo, 'content_slots', 'campaign_platform')) {
            $sets[] = 'campaign_platform = :campaign_platform';
            $params['campaign_platform'] = $platform;
        }
    }
    if (
        ce_table_has_column($pdo, 'content_slots', 'campaign_slot_index')
        && (trim((string) ($row['campaign_slot_index'] ?? '')) === '')
        && isset($marker['slotIndex'])
        && is_numeric($marker['slotIndex'])
    ) {
        $sets[] = 'campaign_slot_index = :campaign_slot_index';
        $params['campaign_slot_index'] = (int) $marker['slotIndex'];
    }
    if (
        ce_table_has_column($pdo, 'content_slots', 'campaign_fase')
        && trim((string) ($row['campaign_fase'] ?? '')) === ''
    ) {
        $fase = trim((string) ($marker['faseKey'] ?? ''));
        if ($fase !== '') {
            $sets[] = 'campaign_fase = :campaign_fase';
            $params['campaign_fase'] = $fase;
        }
    }

    $changed = $sets !== [];
    if ($changed) {
        $pdo->prepare('UPDATE content_slots SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    }
    if ($campaignId !== '') {
        ce_campagne_sync_slot_titles_for_campaign($pdo, $campaignId);

        return true;
    }

    return $changed;
}

/** Backfill ontbrekende campaign_id uit markers + herteken n/m-titels voor één campagne. */
function ce_campagne_repair_campaign_slots(PDO $pdo, string $campaignId): void
{
    if ($campaignId === '' || !ce_table_exists($pdo, 'content_slots')) {
        return;
    }

    if (ce_table_has_column($pdo, 'content_slots', 'campaign_id')) {
        $orphanStmt = $pdo->prepare(
            "SELECT id FROM content_slots
             WHERE (campaign_id IS NULL OR campaign_id = '')
             AND input_human LIKE '%\"campagnePreview\":true%'
             AND input_human LIKE :cid"
        );
        $orphanStmt->execute(['cid' => '%"campaignId":"' . $campaignId . '"%']);
        foreach ($orphanStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $orphan) {
            $oid = trim((string) ($orphan['id'] ?? ''));
            if ($oid !== '') {
                ce_campagne_repair_slot_campaign_columns($pdo, $oid);
            }
        }
    }

    ce_campagne_repair_campaign_platforms($pdo, $campaignId);
    ce_campagne_sync_slot_titles_for_campaign($pdo, $campaignId);
}

/**
 * Markeer campagne als verwijderd en archiveer gekoppelde slots + artikelen (niet hard delete).
 *
 * @return array{ok: true, campaignId: string, archivedSlotCount: int, previewStatus: string}
 */
function ce_campagne_soft_delete(PDO $pdo, string $campaignId, ?string $clientId = null): array
{
    ce_ensure_campagne_preview_schema($pdo);
    if ($campaignId === '') {
        throw new InvalidArgumentException('campaignId ontbreekt');
    }

    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
    if ($clientId === null || $clientId === '') {
        $stmt = $pdo->prepare('SELECT id FROM campaigns WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $campaignId]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM campaigns c WHERE c.id = :id' . $scope['sql'] . ' LIMIT 1');
        $stmt->execute(array_merge(['id' => $campaignId], $scope['params']));
    }
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Campagne niet gevonden');
    }

    if (ce_table_has_column($pdo, 'campaigns', 'preview_status')) {
        $pdo->prepare('UPDATE campaigns SET preview_status = :status WHERE id = :id')
            ->execute(['status' => 'verwijderd', 'id' => $campaignId]);
    }

    require_once __DIR__ . '/slot-repository.php';
    $slotArchived = ce_archive_status($pdo, 'content_slots');
    $articleArchived = ce_archive_status($pdo, 'articles');

    $slotStmt = $pdo->prepare('SELECT id FROM content_slots WHERE campaign_id = :cid');
    $slotStmt->execute(['cid' => $campaignId]);
    $slotIds = [];
    foreach ($slotStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $sid = trim((string) ($row['id'] ?? ''));
        if ($sid !== '') {
            $slotIds[] = $sid;
        }
    }

    foreach ($slotIds as $slotId) {
        $pdo->prepare('UPDATE content_slots SET status = :status WHERE id = :id')
            ->execute(['status' => $slotArchived, 'id' => $slotId]);
        $pdo->prepare('UPDATE articles SET status = :status WHERE slot_id = :slot_id')
            ->execute(['status' => $articleArchived, 'slot_id' => $slotId]);
    }

    return [
        'ok' => true,
        'campaignId' => $campaignId,
        'archivedSlotCount' => count($slotIds),
        'previewStatus' => 'verwijderd',
    ];
}

/**
 * Berekent weergavetitel voor campagne-slot (o.a. LinkedIn 2/6) op basis van volgorde per platform.
 */
function ce_campagne_resolve_slot_title(PDO $pdo, array $slotRow): string
{
    $slotRow = ce_campagne_enrich_slot_row($slotRow);
    $slotId = trim((string) ($slotRow['id'] ?? ''));
    $campaignId = trim((string) ($slotRow['campaign_id'] ?? ''));
    $platform = strtolower(trim((string) ($slotRow['campaign_platform'] ?? '')));
    if ($slotId === '' || $campaignId === '' || $platform === '') {
        return trim((string) ($slotRow['theme'] ?? ''));
    }

    $cStmt = $pdo->prepare('SELECT title, template_key FROM campaigns WHERE id = :id LIMIT 1');
    $cStmt->execute(['id' => $campaignId]);
    $campaign = $cStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($campaign)) {
        return trim((string) ($slotRow['theme'] ?? ''));
    }

    $campaignTitle = trim((string) ($campaign['title'] ?? ''));
    $templateKey = trim((string) ($campaign['template_key'] ?? ''));
    if ($campaignTitle === '') {
        return trim((string) ($slotRow['theme'] ?? ''));
    }

    $orderSql = 'SELECT id FROM content_slots WHERE campaign_id = :cid AND campaign_platform = :platform';
    if (ce_table_has_column($pdo, 'content_slots', 'publish_date')) {
        $orderSql .= ' ORDER BY publish_date ASC, campaign_slot_index ASC';
    } else {
        $orderSql .= ' ORDER BY campaign_slot_index ASC';
    }
    $sStmt = $pdo->prepare($orderSql);
    $sStmt->execute(['cid' => $campaignId, 'platform' => $platform]);
    $platformSlotIds = [];
    foreach ($sStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '') {
            $platformSlotIds[] = $id;
        }
    }
    if ($platformSlotIds === []) {
        return trim((string) ($slotRow['theme'] ?? ''));
    }

    $indexOnPlatform = array_search($slotId, $platformSlotIds, true);
    if ($indexOnPlatform === false) {
        return trim((string) ($slotRow['theme'] ?? ''));
    }

    $totalOnPlatform = count($platformSlotIds);
    $indexOnPlatform = (int) $indexOnPlatform + 1;
    $platformLabel = ce_platform_to_ui($platform);
    $title = $campaignTitle . ' — ' . $platformLabel . ' ' . $indexOnPlatform . '/' . $totalOnPlatform;

    $faseKey = trim((string) ($slotRow['campaign_fase'] ?? ''));
    if ($faseKey !== '') {
        $faseLabel = ce_campagne_preview_fase_label($templateKey !== '' ? $templateKey : null, $faseKey);
        if (is_string($faseLabel) && trim($faseLabel) !== '') {
            $title = trim($faseLabel) . ' — ' . $title;
        }
    }

    return $title;
}

/** Zet content_slots.theme + gekoppelde themes.title gelijk aan ce_campagne_resolve_slot_title. */
function ce_campagne_sync_slot_titles_for_campaign(PDO $pdo, string $campaignId): void
{
    if ($campaignId === '' || !ce_table_exists($pdo, 'content_slots')) {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT id, theme_id, theme, campaign_id, campaign_platform, campaign_fase, campaign_slot_index, publish_date
         FROM content_slots WHERE campaign_id = :cid'
    );
    $stmt->execute(['cid' => $campaignId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $slotRow) {
        $title = ce_campagne_resolve_slot_title($pdo, $slotRow);
        if ($title === '') {
            continue;
        }
        $slotId = (string) ($slotRow['id'] ?? '');
        $pdo->prepare('UPDATE content_slots SET theme = :theme WHERE id = :id')
            ->execute(['theme' => $title, 'id' => $slotId]);
        $themeId = trim((string) ($slotRow['theme_id'] ?? ''));
        if ($themeId !== '' && ce_table_exists($pdo, 'themes')) {
            $pdo->prepare('UPDATE themes SET title = :title WHERE id = :id')
                ->execute(['title' => $title, 'id' => $themeId]);
        }
        if (ce_table_exists($pdo, 'articles')) {
            $oldTheme = trim((string) ($slotRow['theme'] ?? ''));
            $artStmt = $pdo->prepare(
                'SELECT id, headline, headline_human FROM articles WHERE slot_id = :sid ORDER BY id DESC LIMIT 1'
            );
            $artStmt->execute(['sid' => $slotId]);
            $art = $artStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($art)) {
                $headline = trim((string) ($art['headline'] ?? ''));
                $headlineHuman = trim((string) ($art['headline_human'] ?? ''));
                $looksLikeSlotLabel = $headline === ''
                    || $headline === $oldTheme
                    || preg_match('/\b\d+\/\d+\b/', $headline) === 1
                    || str_contains(mb_strtolower($headline), 'acceptatie campagne');
                $shouldSyncHeadline = $headlineHuman === '' && $looksLikeSlotLabel;
                if ($shouldSyncHeadline) {
                    $pdo->prepare(
                        'UPDATE articles SET headline = :title WHERE id = :id'
                    )->execute(['title' => $title, 'id' => (string) ($art['id'] ?? '')]);
                }
            }
        }
    }
}

/**
 * @param array<string, int> $channels
 * @param list<array{key: string, label: string, share: float, rol: string, merkOnderdeel: string|null, opdracht: string}> $phaseSequence
 * @param array<string, mixed>|null $schedule
 * @param list<string> $merkOnderdelen
 * @return list<array{slotId: string, themeId: string|null, platform: string, publishDate: string, faseKey: string|null}>
 */
function ce_campagne_preview_generate_slots(
    PDO $pdo,
    string $campaignId,
    string $clientId,
    string $campaignTitle,
    string $startDate,
    string $endDate,
    array $channels,
    string $templateKey = 'lancering',
    array $phaseSequence = [],
    ?array $schedule = null,
    array $merkOnderdelen = [],
): array {
    ce_ensure_themes_schema($pdo);
    $stubs = ce_campagne_preview_build_slot_schedule($startDate, $endDate, $channels, $schedule);
    $created = [];

    foreach ($stubs as $globalIndex => $stub) {
        $platform = (string) $stub['platform'];
        $publishDate = (string) $stub['publishDate'];
        $slotIndex = (int) $stub['slotIndexOnPlatform'];
        $count = (int) $stub['totalOnPlatform'];
        $platformLabel = ce_platform_to_ui($platform);
        $phase = $phaseSequence[$globalIndex] ?? null;
        $faseKey = is_array($phase) ? (string) ($phase['key'] ?? '') : '';
        $faseLabel = is_array($phase) ? (string) ($phase['label'] ?? '') : '';
        $opdracht = is_array($phase) ? (string) ($phase['opdracht'] ?? '') : '';
        if ($opdracht !== '' && function_exists('ce_campagne_copy_mode_line')) {
            $opdracht = trim($opdracht . "\n\n" . ce_campagne_copy_mode_line($templateKey));
        }
        $rol = is_array($phase) ? (string) ($phase['rol'] ?? '') : '';
        $faseOnderdeel = is_array($phase)
            ? ce_campagne_normalize_merk_onderdeel((string) ($phase['merkOnderdeel'] ?? ''))
            : null;
        $merkOnderdeel = $faseOnderdeel;
        if ($merkOnderdelen !== []) {
            if ($faseOnderdeel !== null && in_array($faseOnderdeel, $merkOnderdelen, true)) {
                $merkOnderdeel = $faseOnderdeel;
            } elseif ($faseKey === 'waarom' && in_array('waar_je_tegen_bent', $merkOnderdelen, true)) {
                $merkOnderdeel = 'waar_je_tegen_bent';
            } elseif ($faseOnderdeel === null || !in_array($faseOnderdeel, $merkOnderdelen, true)) {
                $merkOnderdeel = $merkOnderdelen[0] ?? $faseOnderdeel;
            }
        }

        $phaseKey = ce_phase_key_from_date($publishDate) ?? (string) ce_week_start();
        $slotTitle = $campaignTitle . ' — ' . $platformLabel . ' ' . $slotIndex . '/' . $count;
        if ($faseLabel !== '') {
            $slotTitle = $faseLabel . ' — ' . $slotTitle;
        }

        $slotId = ce_uuid_v4();

        $themeId = null;
        $cycleWeekMonday = ce_monday_of_calendar_date($publishDate);
        $themePhaseKey = ce_phase_key_from_date($cycleWeekMonday) ?? $phaseKey;

        if (ce_table_exists($pdo, 'themes')) {
            $themeId = ce_uuid_v4();
            $tCols = ['id', 'client_id', 'title'];
            $tVals = [':tid', ':client_id', ':title'];
            $tParams = [
                'tid' => $themeId,
                'client_id' => $clientId,
                'title' => $slotTitle,
            ];
            if (ce_table_has_column($pdo, 'themes', 'rationale')) {
                $tCols[] = 'rationale';
                $tVals[] = ':rationale';
                $tParams['rationale'] = $opdracht !== '' ? $opdracht : null;
            }
            if (ce_table_has_column($pdo, 'themes', 'cycle_week')) {
                $tCols[] = 'cycle_week';
                $tVals[] = ':cycle_week';
                $tParams['cycle_week'] = $cycleWeekMonday;
            }
            if (ce_table_has_column($pdo, 'themes', 'phase_key')) {
                $tCols[] = 'phase_key';
                $tVals[] = ':theme_phase_key';
                $tParams['theme_phase_key'] = $themePhaseKey;
            }
            if (ce_table_has_column($pdo, 'themes', 'status')) {
                $tCols[] = 'status';
                $tVals[] = ':theme_status';
                $tParams['theme_status'] = ce_pick_status_value($pdo, 'themes', ['proposed', 'submitted']);
            }
            if (ce_table_has_column($pdo, 'themes', 'feedback')) {
                $tCols[] = 'feedback';
                $tVals[] = ':theme_feedback';
                $tParams['theme_feedback'] = 'none';
            }
            require_once __DIR__ . '/copy-modus.php';
            ce_ensure_theme_copy_modus_columns($pdo);
            $themeCopyModus = ce_copy_modus_from_template_key($templateKey);
            if (ce_table_has_column($pdo, 'themes', 'copy_modus')) {
                $tCols[] = 'copy_modus';
                $tVals[] = ':copy_modus';
                $tParams['copy_modus'] = $themeCopyModus;
            }
            if (ce_table_has_column($pdo, 'themes', 'copy_modus_override')) {
                $tCols[] = 'copy_modus_override';
                $tVals[] = ':copy_modus_override';
                $tParams['copy_modus_override'] = 0;
            }
            $pdo->prepare(
                'INSERT INTO themes (' . implode(', ', $tCols) . ') VALUES (' . implode(', ', $tVals) . ')'
            )->execute($tParams);
        }

        $marker = json_encode([
            'campagnePreview' => true,
            'campaignId' => $campaignId,
            'templateKey' => $templateKey,
            'themeId' => $themeId,
            'platform' => $platform,
            'slotIndex' => $slotIndex,
            'totalForPlatform' => $count,
            'faseKey' => $faseKey !== '' ? $faseKey : null,
            'rol' => $rol !== '' ? $rol : null,
            'opdracht' => $opdracht !== '' ? $opdracht : null,
        ], JSON_THROW_ON_ERROR);

        $slotCols = ['id', 'client_id', 'theme'];
        $slotVals = [':id', ':client_id', ':theme'];
        $slotParams = [
            'id' => $slotId,
            'client_id' => $clientId,
            'theme' => $slotTitle,
        ];
        if ($themeId !== null && ce_table_has_column($pdo, 'content_slots', 'theme_id')) {
            $slotCols[] = 'theme_id';
            $slotVals[] = ':theme_id';
            $slotParams['theme_id'] = $themeId;
        }

        if (ce_table_has_column($pdo, 'content_slots', 'status')) {
            $slotCols[] = 'status';
            $slotVals[] = ':status';
            $slotParams['status'] = ce_pick_status_value($pdo, 'content_slots', ['planned', 'open', 'proposed', 'draft']);
        }
        if (ce_table_has_column($pdo, 'content_slots', 'phase_key')) {
            $slotCols[] = 'phase_key';
            $slotVals[] = ':phase_key';
            $slotParams['phase_key'] = $phaseKey;
        }
        if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
            $slotCols[] = 'origin';
            $slotVals[] = ':origin';
            $slotParams['origin'] = CE_CAMPAGNE_PREVIEW_ORIGIN;
        }
        if (ce_table_has_column($pdo, 'content_slots', 'input_human')) {
            $slotCols[] = 'input_human';
            $slotVals[] = ':input_human';
            $slotParams['input_human'] = $marker;
        }
        if (ce_table_has_column($pdo, 'content_slots', 'campaign_id')) {
            $slotCols[] = 'campaign_id';
            $slotVals[] = ':campaign_id';
            $slotParams['campaign_id'] = $campaignId;
        }
        if (ce_table_has_column($pdo, 'content_slots', 'campaign_slot_index')) {
            $slotCols[] = 'campaign_slot_index';
            $slotVals[] = ':campaign_slot_index';
            $slotParams['campaign_slot_index'] = $globalIndex + 1;
        }
        if (ce_table_has_column($pdo, 'content_slots', 'campaign_platform')) {
            $slotCols[] = 'campaign_platform';
            $slotVals[] = ':campaign_platform';
            $slotParams['campaign_platform'] = $platform;
        }
        if (ce_table_has_column($pdo, 'content_slots', 'publish_date')) {
            $slotCols[] = 'publish_date';
            $slotVals[] = ':publish_date';
            $slotParams['publish_date'] = $publishDate;
        }
        if ($faseKey !== '' && ce_table_has_column($pdo, 'content_slots', 'campaign_fase')) {
            $slotCols[] = 'campaign_fase';
            $slotVals[] = ':campaign_fase';
            $slotParams['campaign_fase'] = $faseKey;
        }
        if ($opdracht !== '' && ce_table_has_column($pdo, 'content_slots', 'campaign_opdracht')) {
            $slotCols[] = 'campaign_opdracht';
            $slotVals[] = ':campaign_opdracht';
            $slotParams['campaign_opdracht'] = $opdracht;
        }
        if ($rol !== '' && ce_table_has_column($pdo, 'content_slots', 'campaign_rol')) {
            $slotCols[] = 'campaign_rol';
            $slotVals[] = ':campaign_rol';
            $slotParams['campaign_rol'] = $rol;
        }
        if ($merkOnderdeel !== null && ce_table_has_column($pdo, 'content_slots', 'campaign_merk_onderdeel')) {
            $slotCols[] = 'campaign_merk_onderdeel';
            $slotVals[] = ':campaign_merk_onderdeel';
            $slotParams['campaign_merk_onderdeel'] = $merkOnderdeel;
        }

        $pdo->prepare(
            'INSERT INTO content_slots (' . implode(', ', $slotCols) . ') VALUES (' . implode(', ', $slotVals) . ')'
        )->execute($slotParams);

        $created[] = [
            'slotId' => $slotId,
            'themeId' => $themeId,
            'platform' => $platform,
            'publishDate' => $publishDate,
            'faseKey' => $faseKey !== '' ? $faseKey : null,
        ];
    }

    ce_campagne_sync_slot_titles_for_campaign($pdo, $campaignId);

    return $created;
}

/** @param array<string, mixed> $body */
function ce_campagne_preview_patch(PDO $pdo, string $campaignId, array $body, ?string $clientId): array
{
    ce_ensure_campagne_preview_schema($pdo);
    $existing = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($existing === null) {
        throw new RuntimeException('Campagne niet gevonden');
    }

    $updates = [];
    $params = ['id' => $campaignId];

    if (isset($body['title'])) {
        $title = trim((string) $body['title']);
        if ($title === '') {
            throw new InvalidArgumentException('title mag niet leeg zijn');
        }
        $updates[] = 'title = :title';
        $params['title'] = $title;
    }

    if (isset($body['previewStatus']) || isset($body['preview_status'])) {
        $status = strtolower(trim((string) ($body['previewStatus'] ?? $body['preview_status'] ?? '')));
        if (!in_array($status, CE_CAMPAGNE_PREVIEW_STATUSES, true)) {
            throw new InvalidArgumentException('previewStatus moet concept, bevestigd, loopt, afgerond of verwijderd zijn');
        }
        if (ce_table_has_column($pdo, 'campaigns', 'preview_status')) {
            $updates[] = 'preview_status = :preview_status';
            $params['preview_status'] = $status;
        }
    }

    $reapplyPhases = filter_var($body['reapplyPhases'] ?? $body['reapply_phases'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (array_key_exists('phasePlan', $body)) {
        if (!is_array($body['phasePlan']) && $body['phasePlan'] !== null) {
            throw new InvalidArgumentException('phasePlan moet een object zijn');
        }
        if (ce_table_has_column($pdo, 'campaigns', 'phase_plan_json')) {
            $updates[] = 'phase_plan_json = :phase_plan_json';
            $params['phase_plan_json'] = json_encode($body['phasePlan'] ?? [], JSON_THROW_ON_ERROR);
        }
    }

    $setGeo = array_key_exists('geoQuestionIds', $body) || array_key_exists('geo_question_ids', $body);
    $setBank = array_key_exists('vraagIds', $body) || array_key_exists('feitIds', $body)
        || array_key_exists('vraag_ids', $body) || array_key_exists('feit_ids', $body);
    $setSlotOnderdeel = is_array($body['slotMerkOnderdeel'] ?? null);
    $setDecision = is_array($body['planDecision'] ?? null);
    $setInstructie = is_array($body['slotInstructie'] ?? null);
    $recalculate = filter_var($body['recalculate'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $setColor = array_key_exists('calendarColor', $body) || array_key_exists('calendar_color', $body);
    $setThoughts = array_key_exists('extraThoughts', $body) || array_key_exists('extra_thoughts', $body);
    $setHashtags = array_key_exists('hashtags', $body);
    $setSources = array_key_exists('sources', $body);
    if ($setColor && ce_table_has_column($pdo, 'campaigns', 'calendar_color')) {
        require_once __DIR__ . '/campagne-calendar-color.php';
        $updates[] = 'calendar_color = :calendar_color';
        $params['calendar_color'] = ce_campagne_normalize_calendar_color($body['calendarColor'] ?? $body['calendar_color']);
    }
    if ($setThoughts && ce_table_has_column($pdo, 'campaigns', 'extra_thoughts')) {
        $thoughts = trim((string) ($body['extraThoughts'] ?? $body['extra_thoughts'] ?? ''));
        $updates[] = 'extra_thoughts = :extra_thoughts';
        $params['extra_thoughts'] = $thoughts !== '' ? $thoughts : null;
    }
    if ($setHashtags && ce_table_has_column($pdo, 'campaigns', 'hashtags_json')) {
        $updates[] = 'hashtags_json = :hashtags_json';
        $params['hashtags_json'] = json_encode(
            ce_campagne_normalize_hashtags($body['hashtags'] ?? []),
            JSON_THROW_ON_ERROR,
        );
    }
    if (array_key_exists('merkOnderdelen', $body) || array_key_exists('merk_onderdelen', $body)) {
        $onderdelen = ce_campagne_normalize_merk_onderdelen($body['merkOnderdelen'] ?? $body['merk_onderdelen'] ?? []);
        if (ce_table_has_column($pdo, 'campaigns', 'merk_onderdelen_json')) {
            $updates[] = 'merk_onderdelen_json = :merk_onderdelen_json';
            $params['merk_onderdelen_json'] = json_encode($onderdelen, JSON_THROW_ON_ERROR);
        }
    }
    if ($updates === [] && !$reapplyPhases && !$setGeo && !$setBank && !$setSlotOnderdeel && !$setDecision && !$setInstructie && !$recalculate && !$setSources && !$setHashtags) {
        return $existing;
    }

    if ($updates !== []) {
        $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
        $sql = 'UPDATE campaigns c SET ' . implode(', ', $updates) . ' WHERE c.id = :id' . $scope['sql'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($params, $scope['params']));
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT 1 FROM campaigns c WHERE c.id = :id' . $scope['sql'] . ' LIMIT 1');
            $check->execute(array_merge(['id' => $campaignId], $scope['params']));
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Campagne niet gevonden');
            }
        }
    }

    if ($reapplyPhases) {
        ce_campagne_preview_reapply_phases($pdo, $campaignId, $clientId);
    }

    if (array_key_exists('geoQuestionIds', $body) || array_key_exists('geo_question_ids', $body)) {
        $ids = $body['geoQuestionIds'] ?? $body['geo_question_ids'] ?? [];
        if (!is_array($ids)) {
            throw new InvalidArgumentException('geoQuestionIds moet een array zijn');
        }
        ce_campagne_preview_set_geo_ankers($pdo, $campaignId, $clientId, $ids);
    }

    if ($setBank) {
        $vragen = $body['vraagIds'] ?? $body['vraag_ids'] ?? [];
        $feiten = $body['feitIds'] ?? $body['feit_ids'] ?? [];
        if (!is_array($vragen) || !is_array($feiten)) {
            throw new InvalidArgumentException('vraagIds en feitIds moeten arrays zijn');
        }
        ce_campagne_preview_set_bank_ankers($pdo, $campaignId, $clientId, $vragen, $feiten);
    }

    if ($setSlotOnderdeel) {
        $slotId = trim((string) ($body['slotMerkOnderdeel']['slotId'] ?? ''));
        $onderdeel = ce_campagne_normalize_merk_onderdeel(
            (string) ($body['slotMerkOnderdeel']['merkOnderdeel'] ?? ''),
        );
        if ($slotId === '') {
            throw new InvalidArgumentException('slotMerkOnderdeel.slotId is verplicht');
        }
        if (!ce_table_has_column($pdo, 'content_slots', 'campaign_merk_onderdeel')) {
            throw new InvalidArgumentException('Merkonderdeel per post is niet beschikbaar');
        }
        $upd = $pdo->prepare(
            'UPDATE content_slots SET campaign_merk_onderdeel = :onderdeel WHERE id = :id AND campaign_id = :cid'
        );
        $upd->execute(['onderdeel' => $onderdeel, 'id' => $slotId, 'cid' => $campaignId]);
        if ($upd->rowCount() === 0) {
            throw new InvalidArgumentException('Post niet gevonden in deze campagne');
        }
    }

    if ($recalculate) {
        ce_campagne_preview_recalculate_plan($pdo, $campaignId, $clientId);
    }

    if ($setInstructie) {
        ce_campagne_preview_append_slot_instructie(
            $pdo,
            $campaignId,
            $body['slotInstructie']['slotIds'] ?? [],
            (string) ($body['slotInstructie']['text'] ?? ''),
        );
    }

    if ($setDecision) {
        ce_campagne_preview_store_plan_decision($pdo, $campaignId, $clientId, $body['planDecision']);
    }

    if ($setSources && $clientId !== null && $clientId !== '') {
        $links = ce_campagne_normalize_link_sources($body['sources'] ?? []);
        $title = trim((string) ($existing['campaign']['title'] ?? ''));
        try {
            ce_campagne_sync_link_sources($pdo, $campaignId, $clientId, $title, $links);
        } catch (Throwable) {
        }
    }

    $refreshed = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($refreshed === null) {
        throw new RuntimeException('Campagne niet gevonden');
    }

    return $refreshed;
}

function ce_campagne_preview_recalculate_plan(PDO $pdo, string $campaignId, ?string $clientId): void
{
    ce_campagne_preview_reapply_phases($pdo, $campaignId, $clientId);
    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($detail === null) {
        return;
    }
    $vragen = $detail['campaign']['vraagIds'] ?? [];
    $feiten = $detail['campaign']['feitIds'] ?? [];
    if (!is_array($vragen)) {
        $vragen = [];
    }
    if (!is_array($feiten)) {
        $feiten = [];
    }
    if ($vragen === [] && $feiten === []) {
        return;
    }
    ce_campagne_preview_set_bank_ankers($pdo, $campaignId, $clientId, $vragen, $feiten);
}

/**
 * @param list<mixed> $slotIds
 */
function ce_campagne_preview_append_slot_instructie(PDO $pdo, string $campaignId, array $slotIds, string $text): void
{
    $text = trim($text);
    $ids = [];
    foreach ($slotIds as $slotId) {
        $id = trim((string) $slotId);
        if ($id !== '' && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    if ($text === '' || $ids === []) {
        throw new InvalidArgumentException('Kies minstens één thema en vul een instructie in.');
    }
    if (!ce_table_has_column($pdo, 'content_slots', 'campaign_instructie')) {
        throw new InvalidArgumentException('Aanvullende instructie is niet beschikbaar');
    }
    $select = $pdo->prepare(
        'SELECT campaign_instructie FROM content_slots WHERE id = :id AND campaign_id = :cid LIMIT 1'
    );
    $update = $pdo->prepare(
        'UPDATE content_slots SET campaign_instructie = :instructie WHERE id = :id AND campaign_id = :cid'
    );
    foreach ($ids as $id) {
        $select->execute(['id' => $id, 'cid' => $campaignId]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Thema niet gevonden in deze campagne');
        }
        $existing = trim((string) ($row['campaign_instructie'] ?? ''));
        $next = $existing === '' || str_contains($existing, $text) ? ($existing === '' ? $text : $existing) : $existing . "\n" . $text;
        $update->execute(['instructie' => $next, 'id' => $id, 'cid' => $campaignId]);
    }
}

/** @param array<string, mixed> $decision */
function ce_campagne_preview_store_plan_decision(PDO $pdo, string $campaignId, ?string $clientId, array $decision): void
{
    if (!ce_table_has_column($pdo, 'campaigns', 'plan_decisions_json')) {
        throw new InvalidArgumentException('Keuzes op het plan zijn niet beschikbaar');
    }
    $target = strtolower(trim((string) ($decision['target'] ?? '')));
    $choice = strtolower(trim((string) ($decision['choice'] ?? '')));
    $soort = strtolower(trim((string) ($decision['soort'] ?? '')));
    if ($target === 'advies' && !in_array($choice, ['volgen', 'niet_volgen', 'zelf'], true)) {
        throw new InvalidArgumentException('choice voor advies moet volgen, niet_volgen of zelf zijn');
    }
    if ($target === 'signaal' && !in_array($choice, ['akkoord', 'aanpassen', 'negeren', 'volgen', 'niet_volgen'], true)) {
        throw new InvalidArgumentException('choice voor een signaal moet akkoord, aanpassen, negeren, volgen of niet_volgen zijn');
    }
    if (!in_array($target, ['advies', 'signaal'], true)) {
        throw new InvalidArgumentException('planDecision.target moet advies of signaal zijn');
    }
    if ($target === 'signaal' && !in_array($soort, CE_CAMPAGNE_PLAN_CHECK_SOORTEN, true)) {
        throw new InvalidArgumentException('Onbekend signaal');
    }
    if ($choice === 'volgen' && $target === 'signaal' && !in_array($soort, ['balans', 'dekking'], true)) {
        throw new InvalidArgumentException('Volgen herberekent het plan alleen bij balans of dekking');
    }

    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($detail === null) {
        throw new RuntimeException('Campagne niet gevonden');
    }
    $stored = is_array($detail['campaign']['planDecisions'] ?? null) ? $detail['campaign']['planDecisions'] : [];
    $entry = [
        'choice' => $choice,
        'text' => trim((string) ($decision['text'] ?? '')),
        'slotIds' => array_values(array_filter(array_map(
            static fn ($id): string => trim((string) $id),
            is_array($decision['slotIds'] ?? null) ? $decision['slotIds'] : [],
        ))),
    ];
    if ($target === 'advies') {
        $stored['advies'] = $entry;
    } else {
        $signalen = is_array($stored['signalen'] ?? null) ? $stored['signalen'] : [];
        $signalen[$soort] = $entry;
        $stored['signalen'] = $signalen;
    }

    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
    $sql = 'UPDATE campaigns c SET plan_decisions_json = :json WHERE c.id = :id' . $scope['sql'];
    $pdo->prepare($sql)->execute(array_merge([
        'id' => $campaignId,
        'json' => json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ], $scope['params']));
}

function ce_campagne_preview_reapply_phases(PDO $pdo, string $campaignId, ?string $clientId): void
{
    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
    $stmt = $pdo->prepare('SELECT * FROM campaigns c WHERE c.id = :id' . $scope['sql'] . ' LIMIT 1');
    $stmt->execute(array_merge(['id' => $campaignId], $scope['params']));
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        throw new RuntimeException('Campagne niet gevonden');
    }

    $templateKey = ce_campagne_preview_normalize_template_key((string) ($campaign['template_key'] ?? 'lancering'));
    $phasePlan = null;
    $rawPlan = $campaign['phase_plan_json'] ?? null;
    if (is_string($rawPlan) && $rawPlan !== '') {
        $decoded = json_decode($rawPlan, true);
        if (is_array($decoded)) {
            $phasePlan = $decoded;
        }
    }

    $slotStmt = $pdo->prepare(
        'SELECT id, theme, campaign_slot_index, campaign_platform, input_human FROM content_slots WHERE campaign_id = :cid ORDER BY campaign_slot_index ASC, publish_date ASC'
    );
    $slotStmt->execute(['cid' => $campaignId]);
    $slots = $slotStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($slots === []) {
        return;
    }

    $resolved = ce_campagne_preview_resolve_phases($templateKey, $phasePlan);
    $sequence = ce_campagne_preview_allocate_phases($resolved, count($slots));
    $title = (string) ($campaign['title'] ?? 'Campagne');

    $update = $pdo->prepare(
        'UPDATE content_slots SET campaign_fase = :fase, campaign_opdracht = :opdracht, campaign_rol = :rol, theme = :theme, input_human = :input_human WHERE id = :id'
    );

    foreach ($slots as $i => $slot) {
        $phase = $sequence[$i] ?? null;
        if (!is_array($phase)) {
            continue;
        }
        $faseKey = (string) ($phase['key'] ?? '');
        $faseLabel = (string) ($phase['label'] ?? '');
        $opdracht = (string) ($phase['opdracht'] ?? '');
        $rol = (string) ($phase['rol'] ?? '');
        $theme = (string) ($slot['theme'] ?? '');
        if ($faseLabel !== '' && !str_starts_with($theme, $faseLabel . ' —')) {
            $theme = preg_replace('/^[^—]+ — /u', '', $theme) ?? $theme;
            $theme = $faseLabel . ' — ' . $theme;
        }

        $existingMarker = ce_campagne_marker_from_slot_row($slot);
        $platform = strtolower(trim((string) ($slot['campaign_platform'] ?? '')));
        if ($platform === '' && is_array($existingMarker)) {
            $platform = strtolower(trim((string) ($existingMarker['platform'] ?? '')));
        }
        if ($platform === '') {
            $platform = ce_campagne_infer_platform_from_theme((string) ($slot['theme'] ?? ''));
        }
        $slotIndex = isset($slot['campaign_slot_index']) ? (int) $slot['campaign_slot_index'] : null;
        if (($slotIndex === null || $slotIndex <= 0) && is_array($existingMarker) && isset($existingMarker['slotIndex'])) {
            $slotIndex = (int) $existingMarker['slotIndex'];
        }
        $marker = json_encode(array_filter([
            'campagnePreview' => true,
            'campaignId' => $campaignId,
            'templateKey' => $templateKey,
            'faseKey' => $faseKey !== '' ? $faseKey : null,
            'rol' => $rol !== '' ? $rol : null,
            'opdracht' => $opdracht !== '' ? $opdracht : null,
            'platform' => $platform !== '' ? $platform : null,
            'slotIndex' => $slotIndex,
            'reapplied' => true,
        ], static fn ($v) => $v !== null && $v !== ''), JSON_THROW_ON_ERROR);

        $update->execute([
            'id' => $slot['id'],
            'fase' => $faseKey !== '' ? $faseKey : null,
            'opdracht' => $opdracht !== '' ? $opdracht : null,
            'rol' => $rol !== '' ? $rol : null,
            'theme' => $theme,
            'input_human' => $marker,
        ]);
    }

    ce_campagne_sync_slot_titles_for_campaign($pdo, $campaignId);
    ce_campagne_preview_reapply_stored_geo_ankers($pdo, $campaignId, $clientId);
}

/**
 * @return array{ok: bool, extract?: array<string, mixed>, error?: string, model?: string, promptVersion?: string}
 */
function ce_campagne_preview_run_product_extract(
    PDO $pdo,
    string $campaignId,
    ?string $clientId,
    string $productPageUrl,
): array {
    ce_ensure_campagne_preview_schema($pdo);
    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($detail === null) {
        return ['ok' => false, 'error' => 'Campagne niet gevonden'];
    }

    $templateKey = ce_campagne_preview_normalize_template_key(
        (string) ($detail['campaign']['templateKey'] ?? 'lancering'),
    );
    if ($templateKey !== 'lancering') {
        return ['ok' => false, 'error' => 'Productextractie is alleen voor sjabloon lancering (A)'];
    }

    try {
        $url = ce_campagne_product_extract_normalize_url($productPageUrl);
    } catch (InvalidArgumentException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    ce_campagne_preview_persist_product_page_url($pdo, $campaignId, $clientId, $url);

    $architecture = ($clientId !== null && $clientId !== '')
        ? ce_campagne_product_extract_architecture_for_client($pdo, $clientId)
        : [];
    $llm = ce_campagne_product_extract_from_url($url, $architecture);
    if (($llm['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'error' => (string) ($llm['error'] ?? 'Extract mislukt'),
            'pageUrl' => $url,
        ];
    }

    $extract = $llm['extract'] ?? [];
    $model = (string) ($llm['model'] ?? '');
    $promptVersion = (string) ($llm['promptVersion'] ?? CE_CAMPAGNE_PRODUCT_EXTRACT_PROMPT_VERSION);

    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
    $sql = <<<'SQL'
UPDATE campaigns c SET
  product_page_url = :url,
  product_extract_json = :extract_json,
  product_extract_prompt_version = :prompt_version,
  product_extract_model = :model,
  product_extract_at = CURRENT_TIMESTAMP
WHERE c.id = :id
SQL;
    $sql .= $scope['sql'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([
        'id' => $campaignId,
        'url' => $url,
        'extract_json' => json_encode($extract, JSON_THROW_ON_ERROR),
        'prompt_version' => $promptVersion,
        'model' => $model,
    ], $scope['params']));

    ce_campagne_preview_apply_product_extract_to_slots($pdo, $campaignId, $extract, $templateKey, $clientId);

    return [
        'ok' => true,
        'extract' => $extract,
        'model' => $model,
        'promptVersion' => $promptVersion,
        'pageUrl' => $url,
        'ontbreektTip' => $extract['ontbreekt'] ?? [],
    ];
}

function ce_campagne_preview_persist_product_page_url(
    PDO $pdo,
    string $campaignId,
    ?string $clientId,
    string $url,
): void {
    if (!ce_table_has_column($pdo, 'campaigns', 'product_page_url')) {
        return;
    }
    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
    $sql = 'UPDATE campaigns c SET product_page_url = :url WHERE c.id = :id' . $scope['sql'];
    $pdo->prepare($sql)->execute(array_merge([
        'id' => $campaignId,
        'url' => $url,
    ], $scope['params']));
}

/** @param array{wat: string, anders: list<array{citaat: string, branchetaal: bool}>, bewijs: list<array{citaat: string}>, ontbreekt: list<string>} $extract */
function ce_campagne_preview_apply_product_extract_to_slots(
    PDO $pdo,
    string $campaignId,
    array $extract,
    string $templateKey,
    ?string $clientId = null,
): void {
    $phases = ce_campagne_preview_resolve_phases($templateKey, null);
    $byKey = [];
    foreach ($phases as $phase) {
        $byKey[(string) ($phase['key'] ?? '')] = $phase;
    }

    $slotStmt = $pdo->prepare(
        'SELECT id, campaign_fase FROM content_slots WHERE campaign_id = :cid ORDER BY campaign_slot_index ASC'
    );
    $slotStmt->execute(['cid' => $campaignId]);
    $updateSlot = $pdo->prepare(
        'UPDATE content_slots SET campaign_opdracht = :opdracht WHERE id = :id'
    );
    $updateArticle = ce_table_has_column($pdo, 'articles', 'rationale')
        ? $pdo->prepare('UPDATE articles SET rationale = :rationale WHERE slot_id = :slot_id')
        : null;

    foreach ($slotStmt->fetchAll(PDO::FETCH_ASSOC) as $slot) {
        $faseKey = trim((string) ($slot['campaign_fase'] ?? ''));
        if ($faseKey === '') {
            continue;
        }
        $base = trim((string) ($byKey[$faseKey]['opdracht'] ?? ''));
        $snippet = ce_campagne_product_extract_snippet_for_fase($faseKey, $extract);
        $full = $base;
        if ($snippet !== '') {
            $full = $full !== '' ? $full . "\n\n" . $snippet : $snippet;
        }
        if ($full === '') {
            continue;
        }
        $updateSlot->execute(['id' => $slot['id'], 'opdracht' => $full]);
        if ($updateArticle !== null) {
            $updateArticle->execute(['slot_id' => $slot['id'], 'rationale' => $full]);
        }
    }

    ce_campagne_preview_reapply_stored_geo_ankers($pdo, $campaignId, $clientId);
}

/** @return array<string, mixed> */
function ce_campagne_preview_run_plan_check(PDO $pdo, string $campaignId, ?string $clientId, ?string $stage = null): array
{
    ce_ensure_campagne_preview_schema($pdo);
    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($detail === null) {
        return ['ok' => false, 'error' => 'Campagne niet gevonden'];
    }

    $campaign = is_array($detail['campaign'] ?? null) ? $detail['campaign'] : [];
    $slots = is_array($detail['slots'] ?? null) ? $detail['slots'] : [];
    $templateKey = ce_campagne_preview_normalize_template_key((string) ($campaign['templateKey'] ?? 'lancering'));
    $extract = is_array($campaign['productExtract'] ?? null) ? $campaign['productExtract'] : null;

    $architecture = ($clientId !== null && $clientId !== '')
        ? ce_campagne_product_extract_architecture_for_client($pdo, $clientId)
        : [];

    $phpSignalen = ce_campagne_plan_check_php_signalen(
        $architecture,
        $extract,
        $slots,
        $campaign,
        $templateKey,
        ce_campagne_normalize_merk_onderdelen($campaign['merkOnderdelen'] ?? []),
    );

    if ($stage === 'check') {
        foreach ($phpSignalen as $index => $sig) {
            if (($sig['soort'] ?? '') !== 'branchehygiene') {
                continue;
            }
            $phpSignalen[$index]['status'] = 'wacht';
            $phpSignalen[$index]['signaal'] = false;
            $phpSignalen[$index]['tekst'] = 'Branchetaal wordt beoordeeld samen met de posts.';
        }
        $payload = [
            'ok' => true,
            'progress' => [
                'key' => 'check',
                'label' => ce_campagne_plan_progress_label('check'),
            ],
            'promptVersion' => CE_CAMPAGNE_PLAN_CHECK_PROMPT_VERSION,
            'llmOk' => false,
            'advies' => '',
            'postOordelen' => [],
            'signalen' => $phpSignalen,
        ];
        ce_campagne_preview_store_plan_check($pdo, $campaignId, $clientId, $payload, null);

        return $payload;
    }

    $llmNormalized = [
        'advies' => '',
        'postOordelen' => [],
        'signalen' => [],
    ];
    $llmOk = false;
    $llmError = '';
    $model = ce_hba_model_for(CE_HBA_FN_CAMPAGNE_PLAN_CHECK);

    $filled = ce_hba_v2_fill_prompt(
        ce_campagne_plan_check_system_prompt(),
        ce_campagne_plan_check_user_vars($architecture, $extract, $slots),
    );
    $llm = ce_hba_v2_call_json_for(
        CE_HBA_FN_CAMPAGNE_PLAN_CHECK,
        $filled,
        'Geef nu de gevraagde JSON-output.',
        0.1,
        4096,
    );
    require_once __DIR__ . '/hba-client-output-guard.php';
    $llm = ce_hba_apply_client_forbidden_guard_to_llm(
        $pdo,
        $clientId,
        'campagne_plan_check',
        $llm,
        static fn (string $suffix): array => ce_hba_v2_call_json_for(
            CE_HBA_FN_CAMPAGNE_PLAN_CHECK,
            $filled,
            'Geef nu de gevraagde JSON-output.' . $suffix,
            0.1,
            4096,
        ),
    );
    if (($llm['ok'] ?? false) === true && is_array($llm['data'] ?? null)) {
        $llmNormalized = ce_campagne_plan_check_normalize_llm($llm['data'], $slots);
        $llmOk = true;
        $model = (string) ($llm['model'] ?? $model);
    } else {
        $llmError = (string) ($llm['error'] ?? 'Plan-check LLM mislukt');
    }

    $phpBySoort = [];
    foreach ($phpSignalen as $sig) {
        $phpBySoort[(string) ($sig['soort'] ?? '')] = $sig;
    }
    foreach ($llmNormalized['signalen'] as $sig) {
        $soort = (string) ($sig['soort'] ?? '');
        if ($soort === '') {
            continue;
        }
        $row = function_exists('ce_campagne_plan_check_with_status')
            ? ce_campagne_plan_check_with_status($sig)
            : $sig;
        // PHP houdt de telbare §5-signalen; het taalmodel vult alleen branchehygiëne.
        if ($soort === 'branchehygiene') {
            $phpBySoort[$soort] = $row;
            continue;
        }
        if (isset($phpBySoort[$soort])) {
            continue;
        }
        $phpBySoort[$soort] = $row;
    }
    $mergedSignalen = [];
    foreach (CE_CAMPAGNE_PLAN_CHECK_SOORTEN as $soort) {
        if (isset($phpBySoort[$soort])) {
            $mergedSignalen[] = $phpBySoort[$soort];
        }
    }

    $payload = [
        'ok' => true,
        'progress' => [
            'key' => 'posts',
            'label' => ce_campagne_plan_progress_label('posts'),
        ],
        'promptVersion' => CE_CAMPAGNE_PLAN_CHECK_PROMPT_VERSION,
        'model' => $model,
        'llmOk' => $llmOk,
        'advies' => $llmNormalized['advies'],
        'postOordelen' => $llmNormalized['postOordelen'],
        'signalen' => $mergedSignalen,
    ];
    if ($llmError !== '') {
        $payload['llmError'] = $llmError;
    }

    ce_campagne_preview_store_plan_check($pdo, $campaignId, $clientId, $payload, $model);

    return $payload;
}

/** @param array<string, mixed> $payload */
function ce_campagne_preview_store_plan_check(
    PDO $pdo,
    string $campaignId,
    ?string $clientId,
    array $payload,
    ?string $model,
): void {
    if (!ce_table_has_column($pdo, 'campaigns', 'plan_check_json')) {
        return;
    }
    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
    $sql = <<<'SQL'
UPDATE campaigns c SET
  plan_check_json = :json,
  plan_check_prompt_version = :prompt_version,
  plan_check_model = :model,
  plan_check_at = CURRENT_TIMESTAMP
WHERE c.id = :id
SQL;
    $sql .= $scope['sql'];
    $pdo->prepare($sql)->execute(array_merge([
        'id' => $campaignId,
        'json' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'prompt_version' => CE_CAMPAGNE_PLAN_CHECK_PROMPT_VERSION,
        'model' => $model,
    ], $scope['params']));
}

function ce_campagne_preview_reapply_stored_geo_ankers(PDO $pdo, string $campaignId, ?string $clientId): void
{
    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($detail === null) {
        return;
    }
    $ids = $detail['campaign']['geoQuestionIds'] ?? [];
    if (!is_array($ids) || $ids === []) {
        return;
    }
    ce_campagne_preview_set_geo_ankers($pdo, $campaignId, $clientId, $ids);
}

/**
 * @param list<mixed> $ids
 */
function ce_campagne_preview_set_geo_ankers(PDO $pdo, string $campaignId, ?string $clientId, array $ids): void
{
    ce_ensure_campagne_preview_schema($pdo);
    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($detail === null) {
        throw new RuntimeException('Campagne niet gevonden');
    }

    $cleanIds = [];
    foreach ($ids as $id) {
        $t = trim((string) $id);
        if ($t !== '' && !in_array($t, $cleanIds, true)) {
            $cleanIds[] = $t;
        }
    }
    if (count($cleanIds) > CE_CAMPAGNE_GEO_ANKER_MAX) {
        throw new InvalidArgumentException('Kies 1 tot ' . CE_CAMPAGNE_GEO_ANKER_MAX . ' GEO-vragen als anker');
    }

    $questions = [];
    if ($cleanIds !== []) {
        $available = ce_fetch_llm_questions($pdo, $clientId, 'geo');
        $byId = [];
        foreach ($available as $q) {
            $qid = (string) ($q['id'] ?? '');
            if ($qid !== '' && ($q['active'] ?? true)) {
                $byId[$qid] = [
                    'id' => $qid,
                    'question' => (string) ($q['question'] ?? ''),
                ];
            }
        }
        foreach ($cleanIds as $id) {
            if (!isset($byId[$id])) {
                throw new InvalidArgumentException('GEO-vraag niet gevonden of niet actief: ' . $id);
            }
            $questions[] = $byId[$id];
        }
    }

    if (ce_table_has_column($pdo, 'campaigns', 'geo_anchor_ids')) {
        $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
        $sql = 'UPDATE campaigns c SET geo_anchor_ids = :ids WHERE c.id = :id' . $scope['sql'];
        $pdo->prepare($sql)->execute(array_merge([
            'id' => $campaignId,
            'ids' => json_encode($cleanIds, JSON_THROW_ON_ERROR),
        ], $scope['params']));
    }

    $slots = is_array($detail['slots'] ?? null) ? $detail['slots'] : [];
    $assignments = ce_campagne_preview_allocate_geo_ankers($slots, $questions);
    $bySlot = [];
    foreach ($assignments as $item) {
        $bySlot[$item['slotId']] = $item;
    }

    $hasAnkerCol = ce_table_has_column($pdo, 'content_slots', 'campaign_anker');
    $update = $hasAnkerCol
        ? $pdo->prepare('UPDATE content_slots SET campaign_opdracht = :opdracht, campaign_anker = :anker WHERE id = :id')
        : $pdo->prepare('UPDATE content_slots SET campaign_opdracht = :opdracht WHERE id = :id');
    $updateArticle = ce_table_has_column($pdo, 'articles', 'rationale')
        ? $pdo->prepare('UPDATE articles SET rationale = :rationale WHERE slot_id = :slot_id')
        : null;

    foreach ($slots as $slot) {
        $slotId = (string) ($slot['slotId'] ?? '');
        if ($slotId === '') {
            continue;
        }
        $base = ce_campagne_preview_strip_geo_anker((string) ($slot['opdracht'] ?? ''));
        $assignment = $bySlot[$slotId] ?? null;
        $opdracht = $base;
        $ankerJson = null;
        if ($assignment !== null) {
            $opdracht = ce_campagne_preview_opdracht_with_geo_anker($base, (string) $assignment['question']);
            $ankerJson = json_encode([
                'soort' => 'vraag',
                'id' => $assignment['questionId'],
                'vraag' => $assignment['question'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        $params = ['id' => $slotId, 'opdracht' => $opdracht !== '' ? $opdracht : null];
        if ($hasAnkerCol) {
            $params['anker'] = $ankerJson;
        }
        $update->execute($params);
        if ($updateArticle !== null) {
            $updateArticle->execute(['slot_id' => $slotId, 'rationale' => $opdracht !== '' ? $opdracht : null]);
        }
    }
}
