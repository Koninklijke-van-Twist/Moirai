<?php

/**
 * Includes/requires
 */

$authFile = __DIR__ . '/auth.php';
if (is_file($authFile)) {
    require_once $authFile;
}

require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/moirai_api.php';

/**
 * Page load
 */

$jsonBodyStatus = moirai_api_merge_json_body();
if ($jsonBodyStatus === 'invalid') {
    moirai_api_json(['ok' => false, 'error' => moirai_loc('moirai.error.invalid_input'), 'error_code' => 'invalid_input'], 400);
}

$action = moirai_api_request_string('action');
$queryKeyRejection = moirai_api_query_key_rejection();
if ($queryKeyRejection !== null) {
    moirai_api_json($queryKeyRejection['body'], $queryKeyRejection['status']);
}

if (in_array($action, MOIRAI_API_PUBLIC_ACTIONS, true)) {
    moirai_api_json(moirai_api_help());
}

$auth = moirai_api_authenticate();
if ($auth === null) {
    $errorKey = moirai_api_request_api_key() === ''
        ? 'moirai.error.api_key_missing'
        : 'moirai.error.unauthorized';
    moirai_api_json([
        'ok' => false,
        'error' => moirai_loc($errorKey),
        'error_code' => $errorKey === 'moirai.error.api_key_missing' ? 'api_key_missing' : 'unauthorized',
    ], 401);
}

unset($_POST['admin'], $_POST['is_admin'], $_POST['user_is_admin'], $_GET['admin'], $_GET['is_admin']);

moirai_api_apply_actor((string) $auth['label']);

try {
    $result = moirai_api_dispatch($action);
    $download = is_array($result['download'] ?? null) ? $result['download'] : null;
    if ($download !== null) {
        $filename = trim((string) ($download['filename'] ?? 'device.pos'));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'device.pos';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        http_response_code((int) ($result['status'] ?? 200));
        echo (string) ($download['content'] ?? '');
        exit;
    }
    moirai_api_json($result['body'], $result['status']);
} catch (InvalidArgumentException $error) {
    $mapped = moirai_api_from_invalid_argument($error);
    moirai_api_json($mapped['body'], $mapped['status']);
} catch (Throwable $error) {
    error_log('Moirai API failed: ' . $error->getMessage());
    moirai_api_json(['ok' => false, 'error' => moirai_loc('moirai.error.generic'), 'error_code' => 'generic'], 500);
}
