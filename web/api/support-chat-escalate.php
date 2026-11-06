<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/support-chat-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo);
if ($clientId === null) {
    ce_json_error('Geen actieve tenant', 400);
}

$body = ce_read_json_body();
$messages = $body['messages'] ?? [];
if (!is_array($messages)) {
    ce_json_error('messages moet een array zijn', 400);
}
if (count($messages) > 64) {
    ce_json_error('Te veel berichten in één request', 400);
}

$draft = trim((string) ($body['draft'] ?? ''));
$context = ce_support_chat_parse_context($body['context'] ?? null);

try {
    $result = ce_support_chat_escalate($pdo, $clientId, $messages, $context, $draft);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 400);
}

ce_json_response($result);
