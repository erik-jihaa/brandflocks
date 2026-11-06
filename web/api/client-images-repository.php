<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';

function ce_client_image_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function ce_ensure_client_images_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (ce_table_exists($pdo, 'client_images')) {
        return;
    }

    try {
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS client_images (
              id CHAR(36) NOT NULL PRIMARY KEY,
              client_id CHAR(36) NOT NULL,
              filename VARCHAR(500) NOT NULL,
              url VARCHAR(500) NOT NULL,
              alt_text VARCHAR(500) NULL,
              tags VARCHAR(500) NULL,
              uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_client_images_client (client_id)
            ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL
        );
    } catch (Throwable) {
        // Tabel bestaat al of geen CREATE-rechten.
    }
}

/** @param array<string, mixed> $row */
function ce_format_client_image_row(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'filename' => (string) ($row['filename'] ?? ''),
        'url' => (string) ($row['url'] ?? ''),
        'altText' => (string) ($row['alt_text'] ?? ''),
        'tags' => (string) ($row['tags'] ?? ''),
        'uploadedAt' => (string) ($row['uploaded_at'] ?? ''),
    ];
}

/** @return list<array<string, mixed>> */
function ce_fetch_client_images(PDO $pdo, string $clientId): array
{
    ce_ensure_client_images_schema($pdo);

    if (!ce_table_exists($pdo, 'client_images')) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM client_images WHERE client_id = :client_id ORDER BY uploaded_at DESC, id DESC'
    );
    $stmt->execute(['client_id' => $clientId]);

    return array_map('ce_format_client_image_row', $stmt->fetchAll() ?: []);
}

/** @return array<string, mixed> */
function ce_upload_client_image(PDO $pdo, string $clientId): array
{
    ce_ensure_client_images_schema($pdo);

    if (!ce_table_exists($pdo, 'client_images')) {
        throw new RuntimeException('client_images-tabel ontbreekt');
    }

    if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        throw new InvalidArgumentException('Geen afbeelding ontvangen');
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Upload mislukt');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Afbeelding mag maximaal 5 MB zijn');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $ext = match ($mime) {
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        default => null,
    };
    if ($ext === null) {
        throw new InvalidArgumentException('Alleen PNG, JPG of WEBP toegestaan');
    }

    $originalName = trim((string) ($file['name'] ?? 'image'));
    $safeName = preg_replace('/[^\w.\- ]+/u', '_', $originalName) ?: 'image';
    if (strlen($safeName) > 200) {
        $safeName = substr($safeName, 0, 200);
    }

    $imageId = ce_client_image_uuid();
    $dir = dirname(__DIR__) . '/uploads/gallery/' . $clientId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Uploadmap kon niet worden aangemaakt');
    }

    $storedFilename = $imageId . '.' . $ext;
    $target = $dir . '/' . $storedFilename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Afbeelding opslaan mislukt');
    }

    $url = '/uploads/gallery/' . $clientId . '/' . $storedFilename;
    $altText = trim((string) ($_POST['altText'] ?? $_POST['alt_text'] ?? ''));
    $tags = trim((string) ($_POST['tags'] ?? ''));

    $pdo->prepare(
        <<<'SQL'
        INSERT INTO client_images (id, client_id, filename, url, alt_text, tags)
        VALUES (:id, :client_id, :filename, :url, :alt_text, :tags)
        SQL
    )->execute([
        'id' => $imageId,
        'client_id' => $clientId,
        'filename' => $safeName,
        'url' => $url,
        'alt_text' => $altText !== '' ? $altText : null,
        'tags' => $tags !== '' ? $tags : null,
    ]);

    $stmt = $pdo->prepare('SELECT * FROM client_images WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $imageId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Afbeelding kon niet worden opgeslagen');
    }

    return ce_format_client_image_row($row);
}

/** @param array<string, mixed> $body @return array<string, mixed>|null */
function ce_patch_client_image(PDO $pdo, string $clientId, string $imageId, array $body): ?array
{
    ce_ensure_client_images_schema($pdo);

    if (!ce_table_exists($pdo, 'client_images')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM client_images WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $imageId, 'client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $sets = [];
    $params = ['id' => $imageId, 'client_id' => $clientId];

    if (array_key_exists('altText', $body) || array_key_exists('alt_text', $body)) {
        $alt = trim((string) ($body['altText'] ?? $body['alt_text'] ?? ''));
        $sets[] = 'alt_text = :alt_text';
        $params['alt_text'] = $alt !== '' ? $alt : null;
    }
    if (array_key_exists('tags', $body)) {
        $tags = trim((string) ($body['tags'] ?? ''));
        $sets[] = 'tags = :tags';
        $params['tags'] = $tags !== '' ? $tags : null;
    }

    if ($sets === []) {
        return ce_format_client_image_row($row);
    }

    $pdo->prepare('UPDATE client_images SET ' . implode(', ', $sets) . ' WHERE id = :id AND client_id = :client_id')
        ->execute($params);

    $stmt->execute(['id' => $imageId, 'client_id' => $clientId]);
    $updated = $stmt->fetch();

    return $updated ? ce_format_client_image_row($updated) : null;
}

/** @return int Aantal gekopieerde beelden */
function ce_copy_client_gallery(PDO $pdo, string $fromClientId, string $toClientId, int $limit = 1): int
{
    ce_ensure_client_images_schema($pdo);

    if (!ce_table_exists($pdo, 'client_images')) {
        return 0;
    }

    $fromClientId = trim($fromClientId);
    $toClientId = trim($toClientId);
    if ($fromClientId === '' || $toClientId === '' || $fromClientId === $toClientId) {
        return 0;
    }

    $limit = max(1, min(20, $limit));
    $stmt = $pdo->prepare(
        'SELECT * FROM client_images WHERE client_id = :client_id ORDER BY uploaded_at DESC, id DESC LIMIT ' . $limit
    );
    $stmt->execute(['client_id' => $fromClientId]);
    $rows = $stmt->fetchAll() ?: [];
    if ($rows === []) {
        return 0;
    }

    $webRoot = dirname(__DIR__);
    $copied = 0;

    foreach ($rows as $row) {
        $sourceUrl = (string) ($row['url'] ?? '');
        if ($sourceUrl === '' || !str_starts_with($sourceUrl, '/uploads/gallery/')) {
            continue;
        }

        $sourcePath = $webRoot . $sourceUrl;
        if (!is_file($sourcePath)) {
            continue;
        }

        $imageId = ce_client_image_uuid();
        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
        $targetDir = $webRoot . '/uploads/gallery/' . $toClientId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            continue;
        }

        $storedFilename = $imageId . '.' . $ext;
        $targetPath = $targetDir . '/' . $storedFilename;
        if (!copy($sourcePath, $targetPath)) {
            continue;
        }

        $targetUrl = '/uploads/gallery/' . $toClientId . '/' . $storedFilename;
        $pdo->prepare(
            <<<'SQL'
            INSERT INTO client_images (id, client_id, filename, url, alt_text, tags)
            VALUES (:id, :client_id, :filename, :url, :alt_text, :tags)
            SQL
        )->execute([
            'id' => $imageId,
            'client_id' => $toClientId,
            'filename' => (string) ($row['filename'] ?? 'image'),
            'url' => $targetUrl,
            'alt_text' => $row['alt_text'] ?? null,
            'tags' => $row['tags'] ?? null,
        ]);
        ++$copied;
    }

    return $copied;
}

function ce_delete_client_image(PDO $pdo, string $clientId, string $imageId): bool
{
    ce_ensure_client_images_schema($pdo);

    if (!ce_table_exists($pdo, 'client_images')) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT url FROM client_images WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $imageId, 'client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    $url = (string) ($row['url'] ?? '');
    if ($url !== '' && str_starts_with($url, '/uploads/gallery/')) {
        $path = dirname(__DIR__) . $url;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $delete = $pdo->prepare('DELETE FROM client_images WHERE id = :id AND client_id = :client_id');
    $delete->execute(['id' => $imageId, 'client_id' => $clientId]);

    return $delete->rowCount() > 0;
}
