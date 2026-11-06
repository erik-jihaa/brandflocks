<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/uuid-helper.php';

function ce_source_folders_client_id(PDO $pdo, ?string $clientId = null): ?string
{
    if ($clientId !== null && $clientId !== '') {
        return $clientId;
    }

    return ce_resolve_client_id($pdo);
}

function ce_ensure_source_folders_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'source_folders')) {
        try {
            $pdo->exec(
                <<<'SQL'
                CREATE TABLE IF NOT EXISTS source_folders (
                  id CHAR(36) NOT NULL PRIMARY KEY,
                  client_id CHAR(36) NOT NULL,
                  name VARCHAR(255) NOT NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  KEY idx_client_sort (client_id, sort_order, name)
                )
                SQL
            );
        } catch (Throwable) {
            // Tabel bestaat al.
        }
    }

    if (ce_table_exists($pdo, 'sources') && !ce_table_has_column($pdo, 'sources', 'folder_id')) {
        try {
            $pdo->exec('ALTER TABLE sources ADD COLUMN folder_id CHAR(36) NULL');
            unset($GLOBALS['ce_table_columns']['sources']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }
}

/** @return array<string, mixed> */
function ce_source_folder_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'sortOrder' => (int) ($row['sort_order'] ?? 0),
        'objectiveId' => trim((string) ($row['objective_id'] ?? '')) ?: null,
        'objectiveLabel' => trim((string) ($row['objective_label'] ?? '')) ?: null,
    ];
}

/** @return array<string, mixed>|null */
function ce_fetch_source_folder_by_id(PDO $pdo, string $folderId, ?string $clientId = null): ?array
{
    ce_ensure_source_folders_schema($pdo);
    if (!ce_table_exists($pdo, 'source_folders')) {
        return null;
    }

    if ($clientId === null || $clientId === '') {
        $clientId = ce_source_folders_client_id($pdo);
    }
    if ($clientId === null) {
        return null;
    }

    $joinObjectives = ce_table_exists($pdo, 'objectives')
        && ce_table_has_column($pdo, 'objectives', 'source_folder_id');

    if ($joinObjectives) {
        $stmt = $pdo->prepare(
            'SELECT sf.id, sf.name, sf.sort_order, o.id AS objective_id, o.label AS objective_label
             FROM source_folders sf
             LEFT JOIN objectives o
               ON o.source_folder_id = sf.id AND o.client_id = sf.client_id
             WHERE sf.id = :id AND sf.client_id = :client_id
             LIMIT 1'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, sort_order FROM source_folders
             WHERE id = :id AND client_id = :client_id
             LIMIT 1'
        );
    }

    $stmt->execute(['id' => $folderId, 'client_id' => $clientId]);
    $row = $stmt->fetch();

    return $row ? ce_source_folder_dto($row) : null;
}

/** Koppel één doel aan een map (CE-02 zoekt eerst in deze map). */
function ce_link_objective_to_source_folder(
    PDO $pdo,
    string $clientId,
    ?string $objectiveId,
    string $folderId,
): void {
    require_once __DIR__ . '/objectives-repository.php';
    ce_ensure_objectives_schema($pdo);

    if (!ce_table_exists($pdo, 'objectives') || !ce_table_has_column($pdo, 'objectives', 'source_folder_id')) {
        return;
    }

    $folderId = trim($folderId);
    if ($folderId === '') {
        return;
    }

    $objectiveId = trim((string) ($objectiveId ?? ''));

    $pdo->prepare(
        'UPDATE objectives SET source_folder_id = NULL
         WHERE client_id = :client_id AND source_folder_id = :folder_id'
    )->execute([
        'client_id' => $clientId,
        'folder_id' => $folderId,
    ]);

    if ($objectiveId === '') {
        return;
    }

    $objective = ce_fetch_objective_by_id($pdo, $objectiveId, $clientId);
    if (!$objective) {
        throw new InvalidArgumentException('Doel niet gevonden.');
    }

    $pdo->prepare(
        'UPDATE objectives SET source_folder_id = :folder_id
         WHERE id = :id AND client_id = :client_id'
    )->execute([
        'folder_id' => $folderId,
        'id' => $objectiveId,
        'client_id' => $clientId,
    ]);
}

function ce_unlink_objectives_from_source_folder(PDO $pdo, string $clientId, string $folderId): void
{
    if (!ce_table_exists($pdo, 'objectives') || !ce_table_has_column($pdo, 'objectives', 'source_folder_id')) {
        return;
    }

    $pdo->prepare(
        'UPDATE objectives SET source_folder_id = NULL
         WHERE client_id = :client_id AND source_folder_id = :folder_id'
    )->execute([
        'client_id' => $clientId,
        'folder_id' => $folderId,
    ]);
}

/** @return list<array<string, mixed>> */
function ce_fetch_source_folders(PDO $pdo, ?string $clientId = null): array
{
    ce_ensure_source_folders_schema($pdo);

    if (!ce_table_exists($pdo, 'source_folders')) {
        return [];
    }

    if ($clientId === null || $clientId === '') {
        $clientId = ce_source_folders_client_id($pdo);
    }
    if ($clientId === null) {
        return [];
    }

    $joinObjectives = ce_table_exists($pdo, 'objectives')
        && ce_table_has_column($pdo, 'objectives', 'source_folder_id');

    if ($joinObjectives) {
        $stmt = $pdo->prepare(
            'SELECT sf.id, sf.name, sf.sort_order, o.id AS objective_id, o.label AS objective_label
             FROM source_folders sf
             LEFT JOIN objectives o
               ON o.source_folder_id = sf.id AND o.client_id = sf.client_id
             WHERE sf.client_id = :client_id
             ORDER BY sf.sort_order ASC, sf.name ASC'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, sort_order FROM source_folders
             WHERE client_id = :client_id
             ORDER BY sort_order ASC, name ASC'
        );
    }

    $stmt->execute(['client_id' => $clientId]);

    return array_map(
        static fn (array $row): array => ce_source_folder_dto($row),
        $stmt->fetchAll() ?: [],
    );
}

/** @return array<string, mixed> */
function ce_create_source_folder(
    PDO $pdo,
    string $name,
    ?string $clientId = null,
    ?string $objectiveId = null,
): array {
    ce_ensure_source_folders_schema($pdo);

    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Mapnaam is verplicht.');
    }

    if ($clientId === null || $clientId === '') {
        $clientId = ce_source_folders_client_id($pdo);
    }
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden.');
    }

    $sortStmt = $pdo->prepare(
        'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM source_folders WHERE client_id = :client_id'
    );
    $sortStmt->execute(['client_id' => $clientId]);
    $sortOrder = (int) ($sortStmt->fetchColumn() ?: 0);

    $id = ce_uuid_v4();
    $pdo->prepare(
        'INSERT INTO source_folders (id, client_id, name, sort_order) VALUES (:id, :client_id, :name, :sort_order)'
    )->execute([
        'id' => $id,
        'client_id' => $clientId,
        'name' => $name,
        'sort_order' => $sortOrder,
    ]);

    $objectiveId = trim((string) ($objectiveId ?? ''));
    if ($objectiveId !== '') {
        ce_link_objective_to_source_folder($pdo, $clientId, $objectiveId, $id);
    }

    return ce_fetch_source_folder_by_id($pdo, $id, $clientId)
        ?? ['id' => $id, 'name' => $name, 'sortOrder' => $sortOrder, 'objectiveId' => null, 'objectiveLabel' => null];
}

/** @param array<string, mixed> $body @return array<string, mixed>|null */
function ce_update_source_folder(PDO $pdo, string $folderId, array $body): ?array
{
    ce_ensure_source_folders_schema($pdo);

    $clientId = ce_source_folders_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden.');
    }

    $fields = [];
    $params = ['id' => $folderId, 'client_id' => $clientId];

    if (array_key_exists('name', $body)) {
        $name = trim((string) $body['name']);
        if ($name === '') {
            throw new InvalidArgumentException('Mapnaam is verplicht.');
        }
        $fields[] = 'name = :name';
        $params['name'] = $name;
    }

    if (array_key_exists('sortOrder', $body)) {
        $fields[] = 'sort_order = :sort_order';
        $params['sort_order'] = (int) $body['sortOrder'];
    }

    if ($fields !== []) {
        $pdo->prepare(
            'UPDATE source_folders SET ' . implode(', ', $fields) . ' WHERE id = :id AND client_id = :client_id'
        )->execute($params);
    }

    if (array_key_exists('objectiveId', $body) || array_key_exists('objective_id', $body)) {
        $objectiveId = trim((string) ($body['objectiveId'] ?? $body['objective_id'] ?? ''));
        ce_link_objective_to_source_folder($pdo, $clientId, $objectiveId !== '' ? $objectiveId : null, $folderId);
    }

    if ($fields === [] && !array_key_exists('objectiveId', $body) && !array_key_exists('objective_id', $body)) {
        throw new InvalidArgumentException('Geen velden om bij te werken.');
    }

    return ce_fetch_source_folder_by_id($pdo, $folderId, $clientId);
}

function ce_delete_source_folder(PDO $pdo, string $folderId): bool
{
    ce_ensure_source_folders_schema($pdo);

    $clientId = ce_source_folders_client_id($pdo);
    if ($clientId === null) {
        return false;
    }

    ce_unlink_objectives_from_source_folder($pdo, $clientId, $folderId);

    if (ce_table_has_column($pdo, 'sources', 'folder_id')) {
        $pdo->prepare(
            'UPDATE sources SET folder_id = NULL WHERE folder_id = :folder_id AND client_id = :client_id'
        )->execute([
            'folder_id' => $folderId,
            'client_id' => $clientId,
        ]);
    }

    $stmt = $pdo->prepare('DELETE FROM source_folders WHERE id = :id AND client_id = :client_id');
    $stmt->execute(['id' => $folderId, 'client_id' => $clientId]);

    return $stmt->rowCount() > 0;
}

/** @return array<string, string> folder_id => name */
function ce_source_folder_name_map(PDO $pdo, ?string $clientId = null): array
{
    $map = [];
    foreach (ce_fetch_source_folders($pdo, $clientId) as $folder) {
        $map[(string) ($folder['id'] ?? '')] = (string) ($folder['name'] ?? '');
    }

    return $map;
}
