<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/event-log-repository.php';
require_once __DIR__ . '/support-chat-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

$clientId = ce_resolve_client_id($pdo);
if ($clientId === null) {
    ce_json_error('Geen actieve tenant', 400);
}

if ($method === 'GET') {
    ce_json_response(['budget' => ce_support_chat_budget_status($pdo, $clientId)]);
}

if ($method !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$messages = $body['messages'] ?? null;
if (!is_array($messages) || $messages === []) {
    ce_json_error('messages is verplicht', 400);
}
if (count($messages) > 64) {
    ce_json_error('Te veel berichten in één request', 400);
}

try {
    $context = ce_support_chat_parse_context($body['context'] ?? null);
    $result = ce_support_chat_answer($pdo, $clientId, $messages, $context);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 502);
}

if ($result['budget']['blocked'] && $result['reply'] === '') {
    ce_json_response([
        'reply' => '',
        'budget' => $result['budget'],
        'error' => 'Het chatbudget voor deze tenant is bereikt. Mail ' . ce_support_email() . ' voor verdere hulp.',
    ], 429);
}

ce_json_response($result);
