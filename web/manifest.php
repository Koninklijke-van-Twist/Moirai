<?php

declare(strict_types=1);

/**
 * Serve the PWA manifest via PHP so static .webmanifest files cannot 403
 * after FTP deploys with restrictive file modes.
 */

$path = __DIR__ . '/site.webmanifest';
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Manifest not found.';
    exit;
}

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
readfile($path);
