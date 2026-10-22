<?php
require_once __DIR__ . '/kvt_protect_dirs.php';

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/moirai_voorraad.php';

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

// State-changing acties: alleen met geldig CSRF-token (header X-CSRF-Token) en als ICT-admin.
$writeActions = ['save', 'delete', 'issue', 'adjust'];
if (in_array($action, $writeActions, true)) {
    moirai_csrf_require();
    if (!moirai_is_admin()) {
        moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.forbidden')], 403);
    }
}

function moirai_voorraad_payload(): array
{
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.invalid_input')], 400);
    }

    return $payload;
}

try {
    switch ($action) {
        case 'list':
            $items = moirai_voorraad_list(trim((string) ($_GET['q'] ?? '')));
            moirai_ensure_kvt_chat();
            $keys = array_map(static fn(array $i): string => moirai_voorraad_notes_thread_key($i['id']), $items);
            $lastNotes = [];
            try {
                $lastNotes = KvtChat::lastMapped($keys, false);
            } catch (Throwable $error) {
                error_log('KvtChat lastMapped failed: ' . $error->getMessage());
            }
            foreach ($items as &$item) {
                $item['last_note'] = $lastNotes[moirai_voorraad_notes_thread_key($item['id'])] ?? null;
            }
            unset($item);
            moirai_json_response(['ok' => true, 'items' => $items]);
            break;

        case 'get':
            $item = moirai_voorraad_get((string) ($_GET['id'] ?? ''));
            if ($item === null) {
                moirai_json_response(['ok' => false, 'error' => moirai_loc('voorraad.error.not_found')], 404);
            }
            moirai_json_response(['ok' => true, 'item' => $item]);
            break;

        case 'save':
            moirai_json_response(['ok' => true, 'item' => moirai_voorraad_save(moirai_voorraad_payload())]);
            break;

        case 'issue':
            $payload = moirai_voorraad_payload();
            moirai_json_response(['ok' => true, 'item' => moirai_voorraad_issue((string) ($payload['id'] ?? ''))]);
            break;

        case 'adjust':
            $payload = moirai_voorraad_payload();
            $item = moirai_voorraad_adjust(
                (string) ($payload['id'] ?? ''),
                (string) ($payload['mode'] ?? ''),
                $payload['amount'] ?? ''
            );
            moirai_json_response(['ok' => true, 'item' => $item]);
            break;

        case 'delete':
            $payload = moirai_voorraad_payload();
            moirai_voorraad_delete((string) ($payload['id'] ?? ''));
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
