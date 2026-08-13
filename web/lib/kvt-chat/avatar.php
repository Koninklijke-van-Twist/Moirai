<?php

declare(strict_types=1);

$boot = __DIR__ . '/../../kvt_chat_boot.php';
if (!is_file($boot)) {
    http_response_code(500);
    exit;
}

require_once $boot;
require_once __DIR__ . '/KvtChat.php';

try {
    KvtChat::handleAvatar();
} catch (Throwable $error) {
    http_response_code(500);
    exit;
}
