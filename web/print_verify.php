<?php

require_once __DIR__ . '/moirai_print.php';

$code = trim((string) ($_GET['code'] ?? ''));

if (!moirai_check_print_verify_code($code)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo 'OK';
