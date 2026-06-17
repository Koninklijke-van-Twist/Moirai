<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
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
                moirai_json_response(['ok' => false, 'error' => 'Apparaat niet gevonden.'], 404);
            }
            moirai_json_response(['ok' => true, 'device' => $device]);
            break;

        case 'users':
            if (!moirai_is_admin()) {
                moirai_json_response(['ok' => false, 'error' => 'Geen rechten.'], 403);
            }
            $users = include __DIR__ . '/getusers.php';
            moirai_json_response(['ok' => true, 'users' => is_array($users) ? $users : []]);
            break;

        case 'save':
            if (!moirai_is_admin()) {
                moirai_json_response(['ok' => false, 'error' => 'Geen rechten.'], 403);
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                moirai_json_response(['ok' => false, 'error' => 'Ongeldige invoer.'], 400);
            }
            $users = include __DIR__ . '/getusers.php';
            $device = moirai_save_device(
                (string) ($payload['type'] ?? ''),
                $payload,
                is_array($users) ? $users : []
            );
            moirai_json_response(['ok' => true, 'device' => $device]);
            break;

        case 'delete':
            if (!moirai_is_admin()) {
                moirai_json_response(['ok' => false, 'error' => 'Geen rechten.'], 403);
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                moirai_json_response(['ok' => false, 'error' => 'Ongeldige invoer.'], 400);
            }
            moirai_delete_device(
                (string) ($payload['type'] ?? ''),
                (string) ($payload['id'] ?? '')
            );
            moirai_json_response(['ok' => true]);
            break;

        default:
            moirai_json_response(['ok' => false, 'error' => 'Onbekende actie.'], 400);
    }
} catch (InvalidArgumentException $error) {
    moirai_json_response(['ok' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    moirai_json_response(['ok' => false, 'error' => 'Er ging iets mis. Probeer het later opnieuw.'], 500);
}
