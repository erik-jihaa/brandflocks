<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/mailbox-helper.php';
require_once __DIR__ . '/client-context.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

// Ops-endpoint over tenants heen: alleen met de service-token, niet met een tenantsessie.
if (!ce_reset_token_valid()) {
    ce_json_error('Reset-token vereist', 403);
}

ce_ensure_sources_schema($pdo);

$clientId = ce_resolve_client_id($pdo, $_GET['client_id'] ?? null);
$sourceId = trim((string) ($_GET['source_id'] ?? ''));

$sql = "SELECT id, title, mailbox_email, mailbox_password_ref, mailbox_imap_host
        FROM sources
        WHERE category = 'mailbox'";
$params = [];

if ($sourceId !== '') {
    $sql .= ' AND id = :source_id';
    $params['source_id'] = $sourceId;
} elseif ($clientId !== null) {
    $sql .= ' AND client_id = :client_id';
    $params['client_id'] = $clientId;
}

$sql .= ' ORDER BY title ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$results = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $id = (string) ($row['id'] ?? '');
    $email = trim((string) ($row['mailbox_email'] ?? ''));
    $imapHost = ce_mailbox_resolve_imap_host($email, (string) ($row['mailbox_imap_host'] ?? ''));

    $entry = [
        'sourceId' => $id,
        'title' => $row['title'] ?? '',
        'mailboxEmail' => $email,
        'mailboxImapHost' => $imapHost,
        'passwordConfigured' => trim((string) ($row['mailbox_password_ref'] ?? '')) !== '',
    ];

    try {
        $password = ce_credential_resolve_plaintext(null, (string) ($row['mailbox_password_ref'] ?? ''));
    } catch (Throwable $e) {
        $entry['ok'] = false;
        $entry['error'] = 'Wachtwoord decrypt mislukt: ' . $e->getMessage();
        $results[] = $entry;
        continue;
    }

    if ($email === '' || $password === '') {
        $entry['ok'] = false;
        $entry['error'] = 'E-mail of wachtwoord ontbreekt';
        $results[] = $entry;
        continue;
    }

    $entry['diagnose'] = ce_mailbox_diagnose($email, $password, $imapHost);

    if ($method === 'POST') {
        $syncDebug = [];
        $synced = ce_mailbox_sync_newsletters($pdo, $id, $email, $password, 15, $imapHost, $syncDebug);
        $entry['synced'] = $synced;
        $entry['syncDebug'] = $syncDebug;

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM newsletter_items WHERE source_id = :id');
        $countStmt->execute(['id' => $id]);
        $entry['newsletterItems'] = (int) $countStmt->fetchColumn();
    }

    if ($method === 'GET') {
        $entry['newsletterColumns'] = array_keys(ce_table_columns($pdo, 'newsletter_items'));
    }

    $results[] = $entry;
}

ce_json_response([
    'ok' => true,
    'imapExtension' => function_exists('imap_open'),
    'results' => $results,
]);
