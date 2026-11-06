<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/strategy-document-extract.php';

const CE_SOURCE_DOCUMENT_MAX_CHUNKS = 24;
const CE_SOURCE_DOCUMENT_CHUNK_SIZE = 2200;

function ce_ensure_source_document_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'sources')) {
        return;
    }

    $sourceColumns = [
        'document_filename' => 'VARCHAR(255) NULL',
        'document_uploaded_at' => 'DATETIME NULL',
        'document_chunk_count' => 'INT NULL',
    ];
    foreach ($sourceColumns as $column => $definition) {
        if (!ce_table_has_column($pdo, 'sources', $column)) {
            try {
                $pdo->exec("ALTER TABLE sources ADD COLUMN `$column` $definition");
            } catch (Throwable) {
                // optional migration
            }
        }
    }

    if (!ce_table_exists($pdo, 'source_document_chunks')) {
        try {
            $pdo->exec(
                <<<'SQL'
                CREATE TABLE IF NOT EXISTS source_document_chunks (
                  id CHAR(36) NOT NULL PRIMARY KEY,
                  source_id CHAR(36) NOT NULL,
                  client_id CHAR(36) NOT NULL,
                  title VARCHAR(500) NOT NULL,
                  snippet TEXT NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  KEY idx_source_document_source (source_id),
                  KEY idx_source_document_client (client_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL
            );
        } catch (Throwable) {
            // optional
        }
    } else {
        try {
            $pdo->exec(
                'ALTER TABLE source_document_chunks CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (Throwable) {
            // Conversie niet nodig of geen ALTER-rechten.
        }
    }
}

function ce_source_document_storage_dir(string $clientId): string
{
    return dirname(__DIR__) . '/uploads/documents/' . $clientId;
}

function ce_source_document_shell_disabled(string $function): bool
{
    $disabled = ini_get('disable_functions');
    if (!is_string($disabled) || trim($disabled) === '') {
        return false;
    }

    foreach (explode(',', $disabled) as $name) {
        if (strcasecmp(trim($name), $function) === 0) {
            return true;
        }
    }

    return false;
}

/** @return list<string> */
function ce_source_document_pdftotext_candidates(): array
{
    $candidates = ['pdftotext'];
    foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'] as $path) {
        if (is_executable($path)) {
            $candidates[] = $path;
        }
    }

    return array_values(array_unique($candidates));
}

function ce_source_document_shell_stdout(string $command): string
{
    if (!ce_source_document_shell_disabled('shell_exec') && function_exists('shell_exec')) {
        $output = shell_exec($command);
        return is_string($output) ? $output : '';
    }

    if (ce_source_document_shell_disabled('proc_open') || !function_exists('proc_open')) {
        return '';
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return '';
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return $stdout;
}

function ce_source_document_run_pdftotext(string $path, string $flags = ''): string
{
    foreach (ce_source_document_pdftotext_candidates() as $binary) {
        $command = escapeshellarg($binary)
            . ($flags !== '' ? ' ' . $flags : '')
            . ' ' . escapeshellarg($path)
            . ' - 2>/dev/null';
        $text = ce_source_document_shell_stdout($command);
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }
    }

    return '';
}

function ce_source_document_normalize_extracted_text(string $text): string
{
    $text = str_replace("\x00", '', $text);
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
    $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    return trim($text);
}

function ce_source_document_extract_pdf_text_php(string $path): string
{
    if (!is_readable($path)) {
        return '';
    }

    require_once dirname(__DIR__) . '/vendor/autoload.php';

    try {
        $parser = new Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();
        return is_string($text) ? ce_source_document_normalize_extracted_text($text) : '';
    } catch (Throwable) {
        return '';
    }
}

function ce_source_document_extract_pdf_text(string $path): string
{
    if (!is_readable($path)) {
        return '';
    }

    foreach (['-layout', '-raw', '-enc UTF-8', ''] as $flags) {
        $text = ce_source_document_run_pdftotext($path, $flags);
        if ($text !== '') {
            return ce_source_document_normalize_extracted_text($text);
        }
    }

    $text = ce_source_document_extract_pdf_text_php($path);
    if ($text !== '') {
        return $text;
    }

    return '';
}

/** @return list<string> */
function ce_source_document_split_text(string $text, int $maxChunks = CE_SOURCE_DOCUMENT_MAX_CHUNKS): array
{
    $text = preg_replace("/\r\n?/", "\n", trim($text)) ?? '';
    if ($text === '') {
        return [];
    }

    $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
    $chunks = [];
    $current = '';

    $push = static function () use (&$chunks, &$current, $maxChunks): void {
        $piece = trim($current);
        if ($piece !== '') {
            $chunks[] = $piece;
        }
        $current = '';
    };

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $candidate = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;
        if (mb_strlen($candidate) <= CE_SOURCE_DOCUMENT_CHUNK_SIZE) {
            $current = $candidate;
            continue;
        }
        if ($current !== '') {
            $push();
        }
        if (mb_strlen($paragraph) <= CE_SOURCE_DOCUMENT_CHUNK_SIZE) {
            $current = $paragraph;
            continue;
        }
        $offset = 0;
        $len = mb_strlen($paragraph);
        while ($offset < $len && count($chunks) < $maxChunks) {
            $chunks[] = mb_substr($paragraph, $offset, CE_SOURCE_DOCUMENT_CHUNK_SIZE);
            $offset += CE_SOURCE_DOCUMENT_CHUNK_SIZE;
        }
    }

    if ($current !== '' && count($chunks) < $maxChunks) {
        $push();
    }

    return array_slice($chunks, 0, $maxChunks);
}

/** @return array{ok: bool, message: string, itemCount: int} */
function ce_probe_document_source(PDO $pdo, string $sourceId): array
{
    ce_ensure_source_document_schema($pdo);

    $stmt = $pdo->prepare(
        'SELECT document_filename, document_uploaded_at, document_chunk_count FROM sources WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'message' => 'Bron niet gevonden.', 'itemCount' => 0];
    }

    $filename = trim((string) ($row['document_filename'] ?? ''));
    $count = (int) ($row['document_chunk_count'] ?? 0);
    if ($filename === '' || $count <= 0) {
        return ['ok' => false, 'message' => 'Nog geen PDF geüpload.', 'itemCount' => 0];
    }

    $uploaded = trim((string) ($row['document_uploaded_at'] ?? ''));

    return [
        'ok' => true,
        'message' => $count === 1
            ? "1 fragment uit {$filename}" . ($uploaded !== '' ? " ({$uploaded})" : '')
            : "{$count} fragmenten uit {$filename}" . ($uploaded !== '' ? " ({$uploaded})" : ''),
        'itemCount' => $count,
    ];
}

/** @return list<array{title: string, url: string, snippet: string}> */
function ce_fetch_document_items(PDO $pdo, string $sourceId, int $max = CE_SOURCE_DOCUMENT_MAX_CHUNKS): array
{
    ce_ensure_source_document_schema($pdo);
    if (!ce_table_exists($pdo, 'source_document_chunks')) {
        return [];
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
        SELECT title, snippet
        FROM source_document_chunks
        WHERE source_id = :source_id
        ORDER BY sort_order ASC, created_at ASC
        LIMIT 24
        SQL
    );
    $stmt->execute(['source_id' => $sourceId]);
    $rows = $stmt->fetchAll() ?: [];
    $items = [];
    foreach ($rows as $row) {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $items[] = [
            'title' => $title,
            'url' => '',
            'snippet' => trim((string) ($row['snippet'] ?? '')),
        ];
        if (count($items) >= $max) {
            break;
        }
    }

    return $items;
}

/** @return array{ok: bool, items: list<array<string, mixed>>, run: array<string, mixed>} */
function ce_run_document_source(PDO $pdo, string $sourceId): array
{
    $stmt = $pdo->prepare('SELECT id, title, category FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Source not found']];
    }
    if ((string) ($row['category'] ?? '') !== 'document') {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Geen document-bron']];
    }

    $sourceTitle = trim((string) ($row['title'] ?? 'Document'));
    $rawItems = ce_fetch_document_items($pdo, $sourceId);
    $items = [];
    foreach ($rawItems as $item) {
        $items[] = [
            'title' => $item['title'],
            'url' => $item['url'],
            'snippet' => $item['snippet'],
            'source_id' => $sourceId,
            'source_title' => $sourceTitle !== '' ? $sourceTitle : 'Document',
            'source_type' => 'document',
        ];
    }

    return [
        'ok' => true,
        'items' => $items,
        'run' => [
            'status' => $items === [] ? 'empty' : 'ok',
            'message' => $items === [] ? 'Geen documentfragmenten gevonden.' : count($items) . ' fragmenten ingelezen.',
            'items_count' => count($items),
        ],
    ];
}

/** @return array{ok: bool, message: string, itemCount: int} */
function ce_source_document_store_upload(PDO $pdo, string $sourceId, string $clientId, array $file): array
{
    ce_ensure_source_document_schema($pdo);

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload mislukt.', 'itemCount' => 0];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $original = trim((string) ($file['name'] ?? 'document.pdf'));
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'message' => 'Ongeldig uploadbestand.', 'itemCount' => 0];
    }

    $ext = ce_strategy_document_extension_from_filename($original);
    if (!ce_strategy_document_extension_allowed($ext)) {
        return [
            'ok' => false,
            'message' => 'Alleen PDF, Word (.docx) en PowerPoint (.pptx) worden ondersteund.',
            'itemCount' => 0,
        ];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > CE_STRATEGY_DOCUMENT_MAX_BYTES) {
        return ['ok' => false, 'message' => 'Bestand is te groot (max. 20 MB).', 'itemCount' => 0];
    }

    $text = ce_strategy_document_extract_text($tmp, $ext);
    if ($text === '') {
        return [
            'ok' => false,
            'message' => $ext === 'pdf'
                ? 'Geen tekst gevonden in deze PDF. Controleer of het bestand geen wachtwoord heeft; gescande PDF\'s zonder tekstlaag (OCR) worden nog niet ondersteund.'
                : 'Geen tekst gevonden in dit document.',
            'itemCount' => 0,
        ];
    }

    $chunks = ce_source_document_split_text($text);
    if ($chunks === []) {
        return ['ok' => false, 'message' => 'Document bevat geen leesbare tekst.', 'itemCount' => 0];
    }

    $dir = ce_source_document_storage_dir($clientId);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'Opslagmap kon niet worden aangemaakt.', 'itemCount' => 0];
    }

    foreach (glob($dir . '/' . $sourceId . '.*') ?: [] as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $storedName = $sourceId . '.' . $ext;
    $target = $dir . '/' . $storedName;
    if (!move_uploaded_file($tmp, $target)) {
        return ['ok' => false, 'message' => 'Document kon niet worden opgeslagen.', 'itemCount' => 0];
    }

    $pdo->prepare('DELETE FROM source_document_chunks WHERE source_id = :source_id')
        ->execute(['source_id' => $sourceId]);

    $sourceTitleStmt = $pdo->prepare('SELECT title FROM sources WHERE id = :id LIMIT 1');
    $sourceTitleStmt->execute(['id' => $sourceId]);
    $sourceTitle = trim((string) ($sourceTitleStmt->fetchColumn() ?: 'Document'));

    $insert = $pdo->prepare(
        <<<'SQL'
        INSERT INTO source_document_chunks (id, source_id, client_id, title, snippet, sort_order)
        VALUES (:id, :source_id, :client_id, :title, :snippet, :sort_order)
        SQL
    );

    $sort = 0;
    foreach ($chunks as $chunk) {
        $sort++;
        $chunkTitle = count($chunks) === 1
            ? $sourceTitle
            : $sourceTitle . ' — deel ' . $sort;
        $insert->execute([
            'id' => ce_uuid_v4(),
            'source_id' => $sourceId,
            'client_id' => $clientId,
            'title' => mb_substr($chunkTitle, 0, 500),
            'snippet' => mb_substr($chunk, 0, 4000),
            'sort_order' => $sort,
        ]);
    }

    $count = count($chunks);
    $pdo->prepare(
        <<<'SQL'
        UPDATE sources
        SET document_filename = :filename,
            document_uploaded_at = NOW(),
            document_chunk_count = :count,
            url_status = 'reachable',
            last_checked_at = NOW()
        WHERE id = :id
        SQL
    )->execute([
        'filename' => mb_substr($original, 0, 255),
        'count' => $count,
        'id' => $sourceId,
    ]);

    return [
        'ok' => true,
        'message' => $count === 1 ? '1 fragment uit document opgeslagen.' : "{$count} fragmenten uit document opgeslagen.",
        'itemCount' => $count,
    ];
}
