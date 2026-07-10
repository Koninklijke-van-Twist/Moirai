<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/moirai_data.php';

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

try {
    switch ($action) {
        case 'list':
            $type = trim((string) ($_GET['type'] ?? ''));
            $query = trim((string) ($_GET['q'] ?? ''));
            $status = trim((string) ($_GET['status'] ?? 'all'));
            $attrs = moirai_parse_list_filters($type);
            $devices = moirai_list_devices($type);
            $devices = array_values(array_filter(
                $devices,
                static fn(array $device): bool => moirai_device_matches_filter($device, $query, $status, $attrs)
            ));
            moirai_json_response(['ok' => true, 'devices' => $devices]);
            break;

        case 'filters':
            $type = trim((string) ($_GET['type'] ?? ''));
            $active = moirai_parse_list_filters($type);
            moirai_json_response(['ok' => true, 'filters' => moirai_get_filter_options($type, $active)]);
            break;

        case 'get':
            $type = trim((string) ($_GET['type'] ?? ''));
            $id = trim((string) ($_GET['id'] ?? ''));
            $device = moirai_get_device($type, $id);
            if ($device === null) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.device_not_found')], 404);
            }
            moirai_json_response(['ok' => true, 'device' => $device]);
            break;

        case 'verify_qr':
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.invalid_input')], 400);
            }
            $type = trim((string) ($payload['type'] ?? ''));
            $id = trim((string) ($payload['id'] ?? ''));
            $device = moirai_mark_qr_verified($type, $id);
            if ($device === null) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.device_not_found')], 404);
            }
            moirai_json_response(['ok' => true, 'device' => $device]);
            break;

        case 'users':
            if (!moirai_is_admin()) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.forbidden')], 403);
            }
            moirai_json_response(['ok' => true, 'users' => moirai_fetch_directory_users()]);
            break;

        case 'save':
            if (!moirai_is_admin()) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.forbidden')], 403);
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.invalid_input')], 400);
            }
            $device = moirai_save_device((string) ($payload['type'] ?? ''), $payload, [], false);
            moirai_json_response(['ok' => true, 'device' => $device]);
            break;

        case 'assign':
            if (!moirai_is_admin()) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.forbidden')], 403);
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.invalid_input')], 400);
            }
            $users = moirai_fetch_directory_users();
            $userInput = array_key_exists('uitgegeven_aan', $payload) ? $payload['uitgegeven_aan'] : null;
            $device = moirai_assign_device(
                (string) ($payload['type'] ?? ''),
                (string) ($payload['id'] ?? ''),
                $userInput,
                $users
            );
            moirai_json_response(['ok' => true, 'device' => $device]);
            break;

        case 'delete':
            if (!moirai_is_admin()) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.forbidden')], 403);
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.invalid_input')], 400);
            }
            moirai_delete_device(
                (string) ($payload['type'] ?? ''),
                (string) ($payload['id'] ?? '')
            );
            moirai_json_response(['ok' => true]);
            break;

        default:
            moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.unknown_action')], 400);
    }
} catch (InvalidArgumentException $error) {
    moirai_json_response(['ok' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.generic')], 500);
}
