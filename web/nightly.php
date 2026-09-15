<?php

/**
 * Nightly endpoint (GET).
 * Sleutels.kvt.nl GET’t dit bestand ’s nachts wanneer het bestaat.
 * Scant laptops/telefoons van ≥ 4 jaar + 10 maanden en mailt ict@kvt.nl één keer
 * (niet voor Reserve; die worden Onbeschikbaar + flagged).
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/moirai_data.php';

function moirai_nightly_send_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    require_once __DIR__ . '/logincheck.php';
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ($isCli ? 'GET' : 'GET')));
if (!$isCli && $method !== 'GET' && $method !== 'HEAD') {
    moirai_nightly_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!$isCli && $method === 'HEAD') {
    moirai_nightly_send_json(['ok' => true, 'skipped' => 'head']);
}

$startedAt = time();

try {
    $result = moirai_run_aging_alerts();
    $payload = [
        'ok' => true,
        'ran_at' => $startedAt,
        'duration_seconds' => time() - $startedAt,
    ] + $result;

    if ($isCli) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    moirai_nightly_send_json($payload);
} catch (Throwable $error) {
    $payload = [
        'ok' => false,
        'ran_at' => $startedAt,
        'duration_seconds' => time() - $startedAt,
        'error' => $error->getMessage(),
    ];
    if ($isCli) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(1);
    }
    moirai_nightly_send_json($payload, 500);
}
