<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-contentflow-schema.php';
require_once __DIR__ . '/uuid-helper.php';

/** @return list<array{role_name: string, cadence: string, prompt_template_id: string}> */
function ce_content_rhythm_default_roles(): array
{
    return [
        ['role_name' => 'autoriteit', 'cadence' => 'weekly', 'prompt_template_id' => 'rhythm.autoriteit'],
        ['role_name' => 'inzicht', 'cadence' => 'weekly', 'prompt_template_id' => 'rhythm.inzicht'],
        ['role_name' => 'product-of-dienst', 'cadence' => 'weekly', 'prompt_template_id' => 'rhythm.product'],
    ];
}

/** @return list<array{role_name: string, cadence: string, prompt_template_id: string}> */
function ce_content_rhythm_propose_roles(?string $archetype, ?string $brancheCode): array
{
    $roles = ce_content_rhythm_default_roles();
    $code = strtolower((string) $brancheCode);
    if (str_contains($code, 'tech') || str_contains($code, 'saas')) {
        $roles[2]['role_name'] = 'product-of-dienst';
    }
    if (in_array(strtolower((string) $archetype), ['wijze', 'magier'], true)) {
        $roles[0]['role_name'] = 'autoriteit';
        $roles[1]['role_name'] = 'inzicht';
    }

    return $roles;
}

function ce_content_rhythm_client_posts_per_week(PDO $pdo, string $clientId): int
{
    if (!ce_table_exists($pdo, 'clients') || !ce_table_has_column($pdo, 'clients', 'posts_per_week')) {
        return 1;
    }
    $stmt = $pdo->prepare('SELECT posts_per_week FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();

    return max(1, (int) ($row['posts_per_week'] ?? 1));
}

/** Zorg dat content_rhythms.posts_per_week gelijk blijft aan clients (bron voor Thema/Overview). */
function ce_content_rhythm_sync_posts_from_client(PDO $pdo, string $clientId): void
{
    ce_ensure_hba_contentflow_schema($pdo);
    if (!ce_table_exists($pdo, 'content_rhythms')) {
        return;
    }
    $rhythm = ce_content_rhythm_active($pdo, $clientId);
    if ($rhythm === null) {
        return;
    }
    $clientPosts = ce_content_rhythm_client_posts_per_week($pdo, $clientId);
    $rhythmPosts = (int) ($rhythm['postsPerWeek'] ?? 0);
    if ($rhythmPosts === $clientPosts) {
        return;
    }
    $id = trim((string) ($rhythm['id'] ?? ''));
    if ($id === '') {
        return;
    }
    $pdo->prepare('UPDATE content_rhythms SET posts_per_week = :posts WHERE id = :id')
        ->execute(['posts' => $clientPosts, 'id' => $id]);
}

/** @return array<string, mixed>|null */
function ce_content_rhythm_active(PDO $pdo, string $clientId): ?array
{
    ce_ensure_hba_contentflow_schema($pdo);
    if (!ce_table_exists($pdo, 'content_rhythms')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM content_rhythms WHERE client_id = :client_id AND active = 1 ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();

    return is_array($row) ? ce_content_rhythm_dto($row) : null;
}

/** @param array<string, mixed> $row */
function ce_content_rhythm_dto(array $row): array
{
    $roles = $row['roles'] ?? [];
    if (is_string($roles)) {
        $decoded = json_decode($roles, true);
        $roles = is_array($decoded) ? $decoded : [];
    }

    return [
        'id' => (string) ($row['id'] ?? ''),
        'postsPerWeek' => (int) ($row['posts_per_week'] ?? 3),
        'roles' => is_array($roles) ? $roles : [],
        'active' => (bool) ($row['active'] ?? true),
        'proposed' => (bool) ($row['proposed'] ?? true),
        'createdAt' => (string) ($row['created_at'] ?? ''),
    ];
}

function ce_content_rhythm_ensure_proposed(PDO $pdo, string $clientId, ?string $archetype = null, ?string $brancheCode = null): array
{
    $existing = ce_content_rhythm_active($pdo, $clientId);
    if ($existing !== null) {
        return $existing;
    }

    $roles = ce_content_rhythm_propose_roles($archetype, $brancheCode);
    $posts = ce_content_rhythm_client_posts_per_week($pdo, $clientId);
    $id = ce_uuid();
    $stmt = $pdo->prepare(
        'INSERT INTO content_rhythms (id, client_id, posts_per_week, roles, active, proposed)
         VALUES (:id, :client_id, :posts, :roles, 1, 1)'
    );
    $stmt->execute([
        'id' => $id,
        'client_id' => $clientId,
        'posts' => $posts,
        'roles' => json_encode($roles, JSON_UNESCAPED_UNICODE),
    ]);

    return ce_content_rhythm_active($pdo, $clientId) ?? [
        'id' => $id,
        'postsPerWeek' => $posts,
        'roles' => $roles,
        'active' => true,
        'proposed' => true,
        'createdAt' => '',
    ];
}

/** @param array<string, mixed> $body */
function ce_content_rhythm_save(PDO $pdo, string $clientId, array $body): array
{
    ce_ensure_hba_contentflow_schema($pdo);
    $roles = $body['roles'] ?? null;
    if (!is_array($roles) || $roles === []) {
        $roles = ce_content_rhythm_default_roles();
    }
    $clean = [];
    foreach ($roles as $role) {
        if (!is_array($role)) {
            continue;
        }
        $name = trim((string) ($role['role_name'] ?? $role['roleName'] ?? ''));
        if ($name === '') {
            continue;
        }
        $clean[] = [
            'role_name' => $name,
            'cadence' => trim((string) ($role['cadence'] ?? 'weekly')) ?: 'weekly',
            'prompt_template_id' => trim((string) ($role['prompt_template_id'] ?? $role['promptTemplateId'] ?? '')) ?: ('rhythm.' . $name),
        ];
    }
    if ($clean === []) {
        $clean = ce_content_rhythm_default_roles();
    }
    $posts = max(1, (int) ($body['postsPerWeek'] ?? $body['posts_per_week'] ?? count($clean)));

    if (ce_table_exists($pdo, 'content_rhythms')) {
        $pdo->prepare('UPDATE content_rhythms SET active = 0 WHERE client_id = :client_id')->execute(['client_id' => $clientId]);
    }

    $id = ce_uuid();
    $stmt = $pdo->prepare(
        'INSERT INTO content_rhythms (id, client_id, posts_per_week, roles, active, proposed)
         VALUES (:id, :client_id, :posts, :roles, 1, 0)'
    );
    $stmt->execute([
        'id' => $id,
        'client_id' => $clientId,
        'posts' => $posts,
        'roles' => json_encode($clean, JSON_UNESCAPED_UNICODE),
    ]);

    if (ce_table_exists($pdo, 'clients') && ce_table_has_column($pdo, 'clients', 'posts_per_week')) {
        $pdo->prepare('UPDATE clients SET posts_per_week = :posts WHERE id = :id')
            ->execute(['posts' => $posts, 'id' => $clientId]);
    }

    ce_content_rhythm_assign_roles_for_client($pdo, $clientId);

    return ce_content_rhythm_active($pdo, $clientId) ?? [
        'id' => $id,
        'postsPerWeek' => $posts,
        'roles' => $clean,
        'active' => true,
        'proposed' => false,
        'createdAt' => '',
    ];
}

function ce_content_rhythm_role_for_index(array $rhythm, int $index): ?string
{
    $roles = is_array($rhythm['roles'] ?? null) ? $rhythm['roles'] : [];
    if ($roles === []) {
        return null;
    }
    $role = $roles[$index % count($roles)] ?? null;
    if (!is_array($role)) {
        return null;
    }

    return (string) ($role['role_name'] ?? $role['roleName'] ?? '');
}

function ce_content_rhythm_role_label(?string $roleName): ?string
{
    $key = strtolower(trim((string) $roleName));
    if ($key === '') {
        return null;
    }

    return match ($key) {
        'autoriteit' => 'Autoriteit',
        'inzicht' => 'Inzicht',
        'product-of-dienst', 'product' => 'Product of dienst',
        default => ucfirst(str_replace('-', ' ', $key)),
    };
}

/** @return array{roles: list<array<string, mixed>>, postsPerWeek: int, summary: string} */
function ce_content_rhythm_ce01_payload(PDO $pdo, string $clientId): array
{
    $rhythm = ce_content_rhythm_active($pdo, $clientId);
    if ($rhythm === null) {
        $rhythm = ce_content_rhythm_ensure_proposed($pdo, $clientId);
    }

    $roles = is_array($rhythm['roles'] ?? null) ? $rhythm['roles'] : [];
    $lines = [];
    foreach ($roles as $index => $role) {
        if (!is_array($role)) {
            continue;
        }
        $name = trim((string) ($role['role_name'] ?? $role['roleName'] ?? ''));
        if ($name === '') {
            continue;
        }
        $label = ce_content_rhythm_role_label($name) ?? $name;
        $lines[] = ($index + 1) . '. ' . $label . ' (' . $name . ')';
    }

    $summary = $lines !== []
        ? "Vast content-ritme (" . count($roles) . " rollen per week):\n" . implode("\n", $lines)
          . "\n\nWijs per week thema 1 aan rol 1, thema 2 aan rol 2, enz. — herhaal het patroon cyclisch."
        : 'Geen content-ritme geconfigureerd — varieer tussen autoriteit, inzicht en product/dienst.';

    return [
        'roles' => $roles,
        'postsPerWeek' => (int) ($rhythm['postsPerWeek'] ?? count($roles)),
        'summary' => $summary,
    ];
}

function ce_content_rhythm_index_for_theme(PDO $pdo, string $clientId, string $themeId, string $cycleWeek): int
{
    if ($themeId === '' || $cycleWeek === '' || !ce_table_exists($pdo, 'themes')) {
        return 0;
    }

    $sql = <<<'SQL'
SELECT id FROM themes
WHERE client_id = :client_id
  AND DATE(cycle_week) = DATE(:cycle_week)
  AND COALESCE(status, 'proposed') NOT IN ('deleted', 'converted_to_slot', 'archived', 'archive')
ORDER BY created_at ASC, id ASC
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $clientId, 'cycle_week' => $cycleWeek]);
    $index = 0;
    foreach ($stmt->fetchAll() as $row) {
        if ((string) ($row['id'] ?? '') === $themeId) {
            return $index;
        }
        $index++;
    }

    return 0;
}

function ce_content_rhythm_role_for_theme(PDO $pdo, string $clientId, array $themeRow): ?string
{
    $rhythm = ce_content_rhythm_active($pdo, $clientId);
    if ($rhythm === null) {
        return null;
    }

    $themeId = trim((string) ($themeRow['id'] ?? ''));
    $cycleWeek = trim((string) ($themeRow['cycle_week'] ?? ''));
    if ($themeId === '' || $cycleWeek === '') {
        return null;
    }

    $index = ce_content_rhythm_index_for_theme($pdo, $clientId, $themeId, $cycleWeek);

    return ce_content_rhythm_role_for_index($rhythm, $index);
}

function ce_content_rhythm_assign_roles_for_week(PDO $pdo, string $clientId, string $cycleWeek): int
{
    if (
        $cycleWeek === ''
        || !ce_table_exists($pdo, 'content_slots')
        || !ce_table_has_column($pdo, 'content_slots', 'rhythm_role')
        || !ce_table_exists($pdo, 'themes')
    ) {
        return 0;
    }

    $cycleWeek = substr(trim($cycleWeek), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cycleWeek)) {
        return 0;
    }

    $rhythm = ce_content_rhythm_active($pdo, $clientId);
    if ($rhythm === null) {
        return 0;
    }

    $sql = <<<'SQL'
SELECT s.id
FROM content_slots s
INNER JOIN themes t ON t.id = s.theme_id
WHERE s.client_id = :client_id
  AND COALESCE(s.status, '') NOT IN ('deleted', 'archived', 'archive')
  AND t.cycle_week IS NOT NULL
  AND DATE(t.cycle_week) = DATE(:cycle_week)
ORDER BY s.created_at ASC, s.id ASC
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $clientId, 'cycle_week' => $cycleWeek]);
    $rows = $stmt->fetchAll() ?: [];

    $updated = 0;
    foreach ($rows as $index => $row) {
        $slotId = trim((string) ($row['id'] ?? ''));
        $role = ce_content_rhythm_role_for_index($rhythm, $index);
        if ($slotId === '' || $role === null || $role === '') {
            continue;
        }
        $pdo->prepare('UPDATE content_slots SET rhythm_role = :role WHERE id = :id')
            ->execute(['role' => $role, 'id' => $slotId]);
        $updated++;
    }

    return $updated;
}

/** @return list<string> */
function ce_content_rhythm_collect_client_cycle_weeks(PDO $pdo, string $clientId): array
{
    $weeks = [];
    if (ce_table_exists($pdo, 'themes') && ce_table_has_column($pdo, 'themes', 'cycle_week')) {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT DATE(cycle_week) AS cycle_week FROM themes
             WHERE client_id = :client_id AND cycle_week IS NOT NULL AND TRIM(cycle_week) <> ''"
        );
        $stmt->execute(['client_id' => $clientId]);
        foreach ($stmt->fetchAll() as $row) {
            $week = substr(trim((string) ($row['cycle_week'] ?? '')), 0, 10);
            if ($week !== '') {
                $weeks[$week] = true;
            }
        }
    }

    if (ce_table_exists($pdo, 'content_slots') && ce_table_exists($pdo, 'themes')) {
        $stmt = $pdo->prepare(
            <<<'SQL'
SELECT DISTINCT DATE(t.cycle_week) AS cycle_week
FROM content_slots s
INNER JOIN themes t ON t.id = s.theme_id
WHERE s.client_id = :client_id
  AND t.cycle_week IS NOT NULL
  AND TRIM(t.cycle_week) <> ''
SQL
        );
        $stmt->execute(['client_id' => $clientId]);
        foreach ($stmt->fetchAll() as $row) {
            $week = substr(trim((string) ($row['cycle_week'] ?? '')), 0, 10);
            if ($week !== '') {
                $weeks[$week] = true;
            }
        }
    }

    $keys = array_keys($weeks);
    sort($keys);

    return $keys;
}

/** @param list<string>|null $cycleWeeks */
function ce_content_rhythm_assign_roles_for_client(PDO $pdo, string $clientId, ?array $cycleWeeks = null): int
{
    if ($cycleWeeks === null) {
        $cycleWeeks = ce_content_rhythm_collect_client_cycle_weeks($pdo, $clientId);
    }

    $total = 0;
    foreach ($cycleWeeks as $cycleWeek) {
        $cycleWeek = substr(trim((string) $cycleWeek), 0, 10);
        if ($cycleWeek === '') {
            continue;
        }
        $total += ce_content_rhythm_assign_roles_for_week($pdo, $clientId, $cycleWeek);
    }

    return $total;
}
