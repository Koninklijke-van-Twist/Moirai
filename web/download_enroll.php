<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/moirai_data.php';

if (!moirai_is_admin()) {
    http_response_code(403);
    exit('Geen rechten.');
}

$file = __DIR__ . '/enroll.sh';
if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit('Bestand niet gevonden.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="enroll.sh"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($file);
exit;
