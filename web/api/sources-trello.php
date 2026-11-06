<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/trello-helper.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$boards = ce_list_trello_boards_resolved();
if ($boards !== []) {
    ce_json_response([
        'configured' => true,
        'boards' => $boards,
        'via' => ce_list_trello_boards() !== [] ? 'api' : 'n8n',
    ]);
}

ce_json_response([
    'configured' => false,
    'boards' => [],
    'note' => 'Activeer CE-Trello Boards in n8n, of zet geldige TRELLO_API_KEY + TRELLO_TOKEN in .env.',
]);
