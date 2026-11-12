<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/hba-branche-seed.php';

const CE_HYGIENE_KIND_OBSERVED = 'observed';
const CE_HYGIENE_KIND_CATEGORY = 'category_condition';

/** Eerste profiel volledig — koppeling naar branche-startset voor bootstrap. */
const CE_HYGIENE_PROFILE_BRANCH_DEFAULTS = [
    'b2b_complex_sale' => 'tech_saas',
];

function ce_hygiene_normalize_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    return rtrim($url, '/');
}

function ce_ensure_hygiene_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!ce_table_exists($pdo, 'hygiene_lists')) {
        $migration = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_hygiene_factors.sql';
        if (is_readable($migration)) {
            try {
                $pdo->exec((string) file_get_contents($migration));
            } catch (Throwable) {
                // Geen CREATE-rechten — admin-migratie vereist.
            }
        }
    }

    if (ce_table_exists($pdo, 'hba_scores') && !ce_table_has_column($pdo, 'hba_scores', 'hygiene_list_version')) {
        try {
            $pdo->exec('ALTER TABLE hba_scores ADD COLUMN hygiene_list_version INT NULL');
            unset($GLOBALS['ce_table_columns']['hba_scores']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }

    if (!ce_table_exists($pdo, 'hygiene_competitors')) {
        $pipeline = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11/add_hygiene_pipeline.sql';
        if (is_readable($pipeline)) {
            try {
                $pdo->exec((string) file_get_contents($pipeline));
            } catch (Throwable) {
                // Admin-migratie vereist.
            }
        }
    }

    if (ce_table_exists($pdo, 'hygiene_factors') && !ce_table_has_column($pdo, 'hygiene_factors', 'review_status')) {
        try {
            $pdo->exec("ALTER TABLE hygiene_factors ADD COLUMN review_status VARCHAR(16) NOT NULL DEFAULT 'approved'");
            unset($GLOBALS['ce_table_columns']['hygiene_factors']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }
}

const CE_HYGIENE_CONTEXT_PROFILES = [
    'nieuw_merk',
    'herpositionering',
    'groeiend',
    'volwassen',
    'tijdelijke_salesdruk',
    'b2b_complex_sale',
];

/** @return array<string, mixed>|null */
function ce_hygiene_active_list_row(PDO $pdo, string $contextprofiel): ?array
{
    ce_ensure_hygiene_schema($pdo);
    if (!ce_table_exists($pdo, 'hygiene_lists') || $contextprofiel === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM hygiene_lists
         WHERE contextprofiel = :contextprofiel AND status = 'active'
         ORDER BY version DESC LIMIT 1"
    );
    $stmt->execute(['contextprofiel' => $contextprofiel]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/** @return list<array{phrase: string, kind: string}> */
function ce_hygiene_factors_for_list(PDO $pdo, string $listId, bool $approvedOnly = false): array
{
    if (!ce_table_exists($pdo, 'hygiene_factors')) {
        return [];
    }

    $sql = 'SELECT phrase, kind FROM hygiene_factors WHERE list_id = :list_id';
    if ($approvedOnly && ce_table_has_column($pdo, 'hygiene_factors', 'review_status')) {
        $sql .= " AND review_status = 'approved'";
    }
    $sql .= ' ORDER BY created_at ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['list_id' => $listId]);

    $rows = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $phrase = trim((string) ($row['phrase'] ?? ''));
        if ($phrase === '') {
            continue;
        }
        $rows[] = [
            'phrase' => $phrase,
            'kind' => (string) ($row['kind'] ?? CE_HYGIENE_KIND_OBSERVED),
        ];
    }

    return $rows;
}

/**
 * Scoring-context — server-side only, nooit naar client-API.
 *
 * @return array{version: int, observed: list<string>, categoryConditions: list<string>}|null
 */
function ce_hygiene_scoring_context(PDO $pdo, string $contextprofiel): ?array
{
    $list = ce_hygiene_active_list_row($pdo, $contextprofiel);
    if (!$list) {
        return null;
    }

    $listId = (string) ($list['id'] ?? '');
    if ($listId === '') {
        return null;
    }

    $factors = ce_hygiene_factors_for_list($pdo, $listId, true);
    if ($factors === []) {
        return null;
    }

    $observed = [];
    $categoryConditions = [];
    foreach ($factors as $factor) {
        if (($factor['kind'] ?? '') === CE_HYGIENE_KIND_CATEGORY) {
            $categoryConditions[] = $factor['phrase'];
        } else {
            $observed[] = $factor['phrase'];
        }
    }

    return [
        'version' => (int) ($list['version'] ?? 1),
        'observed' => $observed,
        'categoryConditions' => $categoryConditions,
    ];
}

function ce_hygiene_contextprofiel_valid(string $code): bool
{
    return in_array($code, CE_HYGIENE_CONTEXT_PROFILES, true);
}

/** @return list<string> */
function ce_hygiene_decode_json_list(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            return $value !== '' ? [trim($value)] : [];
        }
    }
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $value)));
}

/** @return array{code: string, label: string, markt_archetype: string, hygienefactoren: list<string>, categorietaal: list<string>}|null */
function ce_hygiene_branche_seed_row(PDO $pdo, string $brancheCode): ?array
{
    foreach (ce_hba_branche_seed_rows() as $row) {
        if (($row['code'] ?? '') === $brancheCode) {
            return $row;
        }
    }

    if (ce_table_exists($pdo, 'branche_library')) {
        $stmt = $pdo->prepare(
            'SELECT branche_code, branche_label, markt_archetype, hygienefactoren, categorietaal
             FROM branche_library WHERE branche_code = :code LIMIT 1'
        );
        $stmt->execute(['code' => $brancheCode]);
        $row = $stmt->fetch();
        if (is_array($row)) {
            return [
                'code' => (string) ($row['branche_code'] ?? ''),
                'label' => (string) ($row['branche_label'] ?? ''),
                'markt_archetype' => (string) ($row['markt_archetype'] ?? ''),
                'hygienefactoren' => ce_hygiene_decode_json_list($row['hygienefactoren'] ?? []),
                'categorietaal' => ce_hygiene_decode_json_list($row['categorietaal'] ?? []),
            ];
        }
    }

    return null;
}

/** @return array{version: int, listId: string} */
function ce_hygiene_next_list_version(PDO $pdo, string $contextprofiel): array
{
    ce_ensure_hygiene_schema($pdo);
    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(version), 0) AS max_version FROM hygiene_lists WHERE contextprofiel = :contextprofiel'
    );
    $stmt->execute(['contextprofiel' => $contextprofiel]);
    $row = $stmt->fetch();
    $next = is_array($row) ? ((int) ($row['max_version'] ?? 0)) + 1 : 1;

    return ['version' => $next, 'listId' => ce_uuid()];
}

/** @param list<string> $phrases @param list<array<string, mixed>>|null $sources */
function ce_hygiene_insert_factors(
    PDO $pdo,
    string $listId,
    array $phrases,
    string $kind,
    ?array $sources = null,
    ?string $addedBy = null,
): int {
    if ($phrases === []) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO hygiene_factors (id, list_id, phrase, kind, sources, added_by)
         VALUES (:id, :list_id, :phrase, :kind, :sources, :added_by)'
    );

    $count = 0;
    foreach ($phrases as $phrase) {
        $text = trim((string) $phrase);
        if ($text === '') {
            continue;
        }
        $stmt->execute([
            'id' => ce_uuid(),
            'list_id' => $listId,
            'phrase' => $text,
            'kind' => $kind,
            'sources' => $sources !== null ? json_encode($sources, JSON_UNESCAPED_UNICODE) : null,
            'added_by' => $addedBy,
        ]);
        $count++;
    }

    return $count;
}

/** @return array<string, mixed> */
function ce_hygiene_seed_list_from_branche(
    PDO $pdo,
    string $contextprofiel,
    string $brancheCode,
    bool $activate = false,
    ?string $addedBy = 'bootstrap',
): array {
    ce_ensure_hygiene_schema($pdo);

    if (!ce_hygiene_contextprofiel_valid($contextprofiel)) {
        return ['ok' => false, 'error' => 'Onbekend contextprofiel: ' . $contextprofiel];
    }

    $branche = ce_hygiene_branche_seed_row($pdo, $brancheCode);
    if (!$branche) {
        return ['ok' => false, 'error' => 'Branche niet gevonden: ' . $brancheCode];
    }

    $meta = ce_hygiene_next_list_version($pdo, $contextprofiel);
    $listId = $meta['listId'];
    $version = $meta['version'];
    $status = $activate ? 'active' : 'draft';

    if ($activate) {
        $retire = $pdo->prepare(
            "UPDATE hygiene_lists SET status = 'retired'
             WHERE contextprofiel = :contextprofiel AND status = 'active'"
        );
        $retire->execute(['contextprofiel' => $contextprofiel]);
    }

    $insert = $pdo->prepare(
        'INSERT INTO hygiene_lists (id, contextprofiel, version, status)
         VALUES (:id, :contextprofiel, :version, :status)'
    );
    $insert->execute([
        'id' => $listId,
        'contextprofiel' => $contextprofiel,
        'version' => $version,
        'status' => $status,
    ]);

    $sourceMeta = [[
        'url' => 'branche_library:' . $brancheCode,
        'competitor' => 'startset',
        'note' => 'Bootstrap uit branche-bibliotheek — vervang door Apify-distillatie',
    ]];

    $observedCount = ce_hygiene_insert_factors(
        $pdo,
        $listId,
        $branche['categorietaal'] ?? [],
        CE_HYGIENE_KIND_OBSERVED,
        $sourceMeta,
        $addedBy,
    );
    $categoryCount = ce_hygiene_insert_factors(
        $pdo,
        $listId,
        $branche['hygienefactoren'] ?? [],
        CE_HYGIENE_KIND_CATEGORY,
        null,
        $addedBy,
    );

    return [
        'ok' => true,
        'listId' => $listId,
        'contextprofiel' => $contextprofiel,
        'version' => $version,
        'status' => $status,
        'brancheCode' => $brancheCode,
        'observedCount' => $observedCount,
        'categoryCount' => $categoryCount,
        'factorCount' => $observedCount + $categoryCount,
    ];
}

/** @return array<string, mixed>|null */
function ce_hygiene_draft_list_row(PDO $pdo, string $contextprofiel): ?array
{
    ce_ensure_hygiene_schema($pdo);
    if (!ce_table_exists($pdo, 'hygiene_lists')) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM hygiene_lists
         WHERE contextprofiel = :contextprofiel AND status = 'draft'
         ORDER BY version DESC LIMIT 1"
    );
    $stmt->execute(['contextprofiel' => $contextprofiel]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/** @return array<string, mixed> */
function ce_hygiene_create_draft_list(PDO $pdo, string $contextprofiel): array
{
    ce_ensure_hygiene_schema($pdo);
    if (!ce_hygiene_contextprofiel_valid($contextprofiel)) {
        return ['ok' => false, 'error' => 'Onbekend contextprofiel'];
    }

    $existing = ce_hygiene_draft_list_row($pdo, $contextprofiel);
    if ($existing) {
        return [
            'ok' => true,
            'listId' => (string) ($existing['id'] ?? ''),
            'version' => (int) ($existing['version'] ?? 0),
            'status' => 'draft',
            'reused' => true,
        ];
    }

    $meta = ce_hygiene_next_list_version($pdo, $contextprofiel);
    $pdo->prepare(
        'INSERT INTO hygiene_lists (id, contextprofiel, version, status)
         VALUES (:id, :contextprofiel, :version, :status)'
    )->execute([
        'id' => $meta['listId'],
        'contextprofiel' => $contextprofiel,
        'version' => $meta['version'],
        'status' => 'draft',
    ]);

    return [
        'ok' => true,
        'listId' => $meta['listId'],
        'version' => $meta['version'],
        'status' => 'draft',
        'reused' => false,
    ];
}

/** @param list<array{name: string, baseUrl: string}> $competitors */
function ce_hygiene_set_competitors(PDO $pdo, string $listId, array $competitors): array
{
    ce_ensure_hygiene_schema($pdo);
    $list = ce_hygiene_list_by_id($pdo, $listId);
    if (!$list) {
        return ['ok' => false, 'error' => 'Lijst niet gevonden'];
    }
    if (($list['status'] ?? '') !== 'draft') {
        return ['ok' => false, 'error' => 'Alleen draft-lijsten zijn bewerkbaar'];
    }

    $pdo->prepare('DELETE FROM hygiene_competitors WHERE list_id = :list_id')->execute(['list_id' => $listId]);
    $insert = $pdo->prepare(
        'INSERT INTO hygiene_competitors (id, list_id, name, base_url, sort_order)
         VALUES (:id, :list_id, :name, :base_url, :sort_order)'
    );

    $saved = [];
    $order = 0;
    foreach (array_slice($competitors, 0, 10) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        $url = ce_hygiene_normalize_url((string) ($row['baseUrl'] ?? $row['base_url'] ?? ''));
        if ($name === '' || $url === '') {
            continue;
        }
        $id = ce_uuid();
        $insert->execute([
            'id' => $id,
            'list_id' => $listId,
            'name' => $name,
            'base_url' => $url,
            'sort_order' => $order++,
        ]);
        $saved[] = ['id' => $id, 'name' => $name, 'baseUrl' => $url];
    }

    if (count($saved) < 3) {
        return ['ok' => false, 'error' => 'Minstens 3 concurrenten met naam en URL'];
    }

    return ['ok' => true, 'listId' => $listId, 'competitors' => $saved];
}

/** @return array<string, mixed>|null */
function ce_hygiene_list_by_id(PDO $pdo, string $listId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM hygiene_lists WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $listId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/** @return list<array<string, mixed>> */
function ce_hygiene_factor_rows(PDO $pdo, string $listId): array
{
    if (!ce_table_exists($pdo, 'hygiene_factors')) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM hygiene_factors WHERE list_id = :list_id ORDER BY kind ASC, created_at ASC'
    );
    $stmt->execute(['list_id' => $listId]);
    $rows = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $sources = json_decode((string) ($row['sources'] ?? ''), true);
        $rows[] = [
            'id' => (string) ($row['id'] ?? ''),
            'phrase' => (string) ($row['phrase'] ?? ''),
            'kind' => (string) ($row['kind'] ?? ''),
            'reviewStatus' => (string) ($row['review_status'] ?? 'approved'),
            'competitorCount' => isset($row['competitor_count']) ? (int) $row['competitor_count'] : null,
            'sources' => is_array($sources) ? $sources : [],
            'addedBy' => (string) ($row['added_by'] ?? ''),
        ];
    }

    return $rows;
}

/** @return array<string, mixed> */
function ce_hygiene_platform_detail(PDO $pdo, string $contextprofiel): array
{
    ce_ensure_hygiene_schema($pdo);
    $active = ce_hygiene_active_list_row($pdo, $contextprofiel);
    $draft = ce_hygiene_draft_list_row($pdo, $contextprofiel);

    $competitors = [];
    $factors = [];
    $listId = $draft ? (string) ($draft['id'] ?? '') : ($active ? (string) ($active['id'] ?? '') : '');
    if ($listId !== '' && ce_table_exists($pdo, 'hygiene_competitors')) {
        $stmt = $pdo->prepare(
            'SELECT id, name, base_url, sort_order FROM hygiene_competitors WHERE list_id = :list_id ORDER BY sort_order ASC'
        );
        $stmt->execute(['list_id' => $listId]);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $competitors[] = [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'baseUrl' => (string) ($row['base_url'] ?? ''),
            ];
        }
        $factors = ce_hygiene_factor_rows($pdo, $listId);
    }

    return [
        'contextprofiel' => $contextprofiel,
        'active' => $active ? [
            'listId' => (string) ($active['id'] ?? ''),
            'version' => (int) ($active['version'] ?? 0),
        ] : null,
        'draft' => $draft ? [
            'listId' => (string) ($draft['id'] ?? ''),
            'version' => (int) ($draft['version'] ?? 0),
        ] : null,
        'workingListId' => $listId !== '' ? $listId : null,
        'competitors' => $competitors,
        'factors' => $factors,
        'status' => ce_hygiene_admin_status($pdo, $contextprofiel),
    ];
}

/** @return array<string, mixed> */
function ce_hygiene_update_factor(PDO $pdo, string $factorId, array $patch): array
{
    ce_ensure_hygiene_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM hygiene_factors WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $factorId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'Factor niet gevonden'];
    }

    $listId = (string) ($row['list_id'] ?? '');
    $list = ce_hygiene_list_by_id($pdo, $listId);
    if (!$list || ($list['status'] ?? '') !== 'draft') {
        return ['ok' => false, 'error' => 'Alleen factoren in draft-lijsten zijn bewerkbaar'];
    }

    $phrase = array_key_exists('phrase', $patch)
        ? trim((string) $patch['phrase'])
        : (string) ($row['phrase'] ?? '');
    $review = array_key_exists('reviewStatus', $patch)
        ? trim((string) $patch['reviewStatus'])
        : (string) ($row['review_status'] ?? 'pending');
    if (!in_array($review, ['pending', 'approved', 'rejected'], true)) {
        return ['ok' => false, 'error' => 'Ongeldige reviewStatus'];
    }
    if ($phrase === '') {
        return ['ok' => false, 'error' => 'Phrase mag niet leeg zijn'];
    }

    $pdo->prepare(
        'UPDATE hygiene_factors SET phrase = :phrase, review_status = :review_status WHERE id = :id'
    )->execute([
        'id' => $factorId,
        'phrase' => $phrase,
        'review_status' => $review,
    ]);

    return ['ok' => true, 'factorId' => $factorId, 'phrase' => $phrase, 'reviewStatus' => $review];
}

/** @return array<string, mixed> */
function ce_hygiene_add_category_factor(PDO $pdo, string $listId, string $phrase, ?string $addedBy = null): array
{
    ce_ensure_hygiene_schema($pdo);
    $list = ce_hygiene_list_by_id($pdo, $listId);
    if (!$list || ($list['status'] ?? '') !== 'draft') {
        return ['ok' => false, 'error' => 'Alleen draft-lijsten zijn bewerkbaar'];
    }
    $phrase = trim($phrase);
    if ($phrase === '') {
        return ['ok' => false, 'error' => 'Phrase is verplicht'];
    }

    $id = ce_uuid();
    $pdo->prepare(
        'INSERT INTO hygiene_factors (id, list_id, phrase, kind, sources, added_by, review_status)
         VALUES (:id, :list_id, :phrase, :kind, NULL, :added_by, :review_status)'
    )->execute([
        'id' => $id,
        'list_id' => $listId,
        'phrase' => $phrase,
        'kind' => CE_HYGIENE_KIND_CATEGORY,
        'added_by' => $addedBy ?? 'strateeg',
        'review_status' => 'approved',
    ]);

    return ['ok' => true, 'factorId' => $id, 'phrase' => $phrase];
}

/** @return array<string, mixed> */
function ce_hygiene_activate_list(PDO $pdo, string $listId): array
{
    ce_ensure_hygiene_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM hygiene_lists WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $listId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'Lijst niet gevonden'];
    }

    if (($row['status'] ?? '') !== 'draft') {
        return ['ok' => false, 'error' => 'Alleen draft-lijsten kunnen geactiveerd worden'];
    }

    if (ce_table_has_column($pdo, 'hygiene_factors', 'review_status')) {
        $pending = $pdo->prepare(
            "SELECT COUNT(*) FROM hygiene_factors
             WHERE list_id = :list_id AND kind = :kind AND review_status = 'pending'"
        );
        $pending->execute(['list_id' => $listId, 'kind' => CE_HYGIENE_KIND_OBSERVED]);
        $pendingCount = (int) ($pending->fetchColumn() ?: 0);
        if ($pendingCount > 0) {
            return ['ok' => false, 'error' => 'Bevestig eerst alle waargenomen regels (' . $pendingCount . ' open)'];
        }

        $approved = $pdo->prepare(
            "SELECT COUNT(*) FROM hygiene_factors
             WHERE list_id = :list_id AND review_status = 'approved'"
        );
        $approved->execute(['list_id' => $listId]);
        if ((int) ($approved->fetchColumn() ?: 0) === 0) {
            return ['ok' => false, 'error' => 'Lijst heeft geen goedgekeurde factoren'];
        }
    }

    $contextprofiel = (string) ($row['contextprofiel'] ?? '');
    $retire = $pdo->prepare(
        "UPDATE hygiene_lists SET status = 'retired'
         WHERE contextprofiel = :contextprofiel AND status = 'active'"
    );
    $retire->execute(['contextprofiel' => $contextprofiel]);

    $activate = $pdo->prepare("UPDATE hygiene_lists SET status = 'active' WHERE id = :id");
    $activate->execute(['id' => $listId]);

    return [
        'ok' => true,
        'listId' => $listId,
        'contextprofiel' => $contextprofiel,
        'version' => (int) ($row['version'] ?? 0),
        'status' => 'active',
    ];
}

/** @return array<string, mixed> */
function ce_hygiene_admin_status(PDO $pdo, string $contextprofiel): array
{
    ce_ensure_hygiene_schema($pdo);

    $active = ce_hygiene_active_list_row($pdo, $contextprofiel);
    $factorCount = 0;
    if ($active) {
        $factors = ce_hygiene_factors_for_list($pdo, (string) ($active['id'] ?? ''));
        $factorCount = count($factors);
    }

    $stmt = $pdo->prepare(
        'SELECT id, version, status, created_at FROM hygiene_lists
         WHERE contextprofiel = :contextprofiel ORDER BY version DESC LIMIT 5'
    );
    $stmt->execute(['contextprofiel' => $contextprofiel]);
    $recent = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $recent[] = [
            'listId' => (string) ($row['id'] ?? ''),
            'version' => (int) ($row['version'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }

    return [
        'contextprofiel' => $contextprofiel,
        'activeVersion' => $active ? (int) ($active['version'] ?? 0) : null,
        'activeListId' => $active ? (string) ($active['id'] ?? '') : null,
        'factorCount' => $factorCount,
        'recentLists' => $recent,
    ];
}
