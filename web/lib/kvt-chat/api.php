<?php

declare(strict_types=1);

$boot = dirname(__DIR__, 2) . '/kvt_chat_boot.php';
if (!is_file($boot)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Missing kvt_chat_boot.php in web root.']);
    exit;
}

require_once $boot;
require_once __DIR__ . '/KvtChat.php';

KvtChat::handleApi();
