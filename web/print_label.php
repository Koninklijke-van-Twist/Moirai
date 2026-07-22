<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/moirai_data.php';
require_once __DIR__ . '/moirai_print.php';

try {
    $payload = [];
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $type = trim((string) ($payload['type'] ?? $_GET['type'] ?? ''));
    $id = trim((string) ($payload['id'] ?? $_GET['id'] ?? ''));

    if ($type === '' || $id === '') {
        moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.invalid_input')], 400);
    }

    $device = moirai_get_device($type, $id);
    if ($device === null) {
        moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.device_not_found')], 404);
    }

    $url = moirai_build_device_posprint_url($device, $type);
    moirai_json_response([
        'ok' => true,
        'url' => $url,
    ]);
} catch (InvalidArgumentException $error) {
    moirai_json_response(['ok' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.print_failed')], 500);
}
