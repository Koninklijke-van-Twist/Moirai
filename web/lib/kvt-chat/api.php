<?php

declare(strict_types=1);

// web/lib/kvt-chat → web/kvt_chat_boot.php (prod: /moirai/lib/kvt-chat → /moirai/)
$boot = __DIR__ . '/../../kvt_chat_boot.php';
if (!is_file($boot)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Missing kvt_chat_boot.php (looked for ' . $boot . ').',
    ]);
    exit;
}

require_once $boot;
require_once __DIR__ . '/KvtChat.php';

try {
    KvtChat::handleApi();
} catch (Throwable $error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'KvtChat API failed: ' . $error->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
