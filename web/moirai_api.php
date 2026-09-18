<?php

/**
 * Includes/requires
 */

require_once __DIR__ . '/moirai_data.php';

/**
 * Constants
 */

const MOIRAI_API_PUBLIC_ACTIONS = ['', 'help', 'spec'];

/**
 * Functies
 */

function moirai_api_json(array $payload, int $status = 200): void
{
    moirai_json_response($payload, $status);
}

/**
 * @return 'ok'|'empty'|'invalid'
 */
function moirai_api_merge_json_body(): string
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        return 'empty';
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return 'empty';
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return 'invalid';
    }

    foreach ($decoded as $key => $value) {
        if (!array_key_exists($key, $_POST)) {
            $_POST[$key] = $value;
        }
    }

    return 'ok';
}

function moirai_api_request_source(): array
{
    $source = array_merge($_GET, $_POST);
    unset($source['api_key']);

    return $source;
}

function moirai_api_request_value(string $key): mixed
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }

    return null;
}

function moirai_api_request_string(string $key): string
{
    $value = moirai_api_request_value($key);
    if (is_bool($value)) {
        return $value ? '1' : '';
    }
    if (is_scalar($value)) {
        return trim((string) $value);
    }

    return '';
}

function moirai_api_request_bool(string $key): bool
{
    $value = moirai_api_request_value($key);
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) ($value ?? ''))), ['1', 'true', 'yes', 'on'], true);
}

function moirai_api_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = trim((string) ($_SERVER[$serverKey] ?? ''));
    if ($value !== '') {
        return $value;
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $header => $headerValue) {
            if (strcasecmp((string) $header, $name) === 0) {
                return trim((string) $headerValue);
            }
        }
    }

    return '';
}

function moirai_api_query_has_api_key(): bool
{
    return trim((string) ($_GET['api_key'] ?? '')) !== '';
}

/**
 * API key from header or POST body only. Querystring keys are ignored.
 */
function moirai_api_request_api_key(): string
{
    $headerKey = moirai_api_header('X-API-Key');
    if ($headerKey !== '') {
        return $headerKey;
    }

    $authorization = moirai_api_header('Authorization');
    if (preg_match('/^Bearer\s+(\S+)/i', $authorization, $matches) === 1) {
        return trim((string) $matches[1]);
    }

    if (array_key_exists('api_key', $_POST)) {
        $bodyKey = $_POST['api_key'];
        if (is_scalar($bodyKey)) {
            return trim((string) $bodyKey);
        }
    }

    return '';
}

/**
 * @return array<string, string>
 */
function moirai_api_configured_keys(): array
{
    $keys = $GLOBALS['apiKeys'] ?? [];
    if (!is_array($keys)) {
        return [];
    }

    $configured = [];
    foreach ($keys as $label => $secret) {
        if (!is_string($label) || !is_string($secret)) {
            continue;
        }
        $label = trim($label);
        $secret = trim($secret);
        if ($label === '' || $secret === '') {
            continue;
        }
        $configured[$label] = $secret;
    }

    return $configured;
}

/**
 * @return array{label: string}|null
 */
function moirai_api_authenticate(?string $presented = null): ?array
{
    $presented = trim((string) ($presented ?? moirai_api_request_api_key()));
    if ($presented === '') {
        return null;
    }

    foreach (moirai_api_configured_keys() as $label => $secret) {
        if (hash_equals($secret, $presented)) {
            return ['label' => $label];
        }
    }

    return null;
}

function moirai_api_apply_actor(string $label): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9._-]+/i', '-', $label)));
    $slug = trim($slug, '-') ?: 'api';

    $_SESSION['user'] = [
        'email' => 'api+' . $slug . '@kvt.nl',
        'name' => $label,
        'admin' => true,
    ];
}

/**
 * @return array{ok: true, api: string, auth: array, actions: list<array<string, mixed>>, types: array, fields: array, filters: array, statuses: list<string>, conditions: list<string>, errors: array}
 */
function moirai_api_help(): array
{
    return [
        'ok' => true,
        'api' => 'moirai',
        'auth' => [
            'required_except' => ['help', 'spec'],
            'accept' => ['X-API-Key', 'Authorization: Bearer', 'POST body api_key'],
            'reject' => ['querystring api_key'],
            'keys' => 'Label => secret in local auth.php ($apiKeys). See auth.example.php. Real keys stay off git.',
        ],
        'actions' => [
            ['name' => 'help', 'alias' => ['spec'], 'auth' => false, 'method' => ['GET', 'POST'], 'description' => 'This specification.'],
            ['name' => 'whoami', 'auth' => true, 'method' => ['GET', 'POST'], 'description' => 'Authenticated API-key label.'],
            ['name' => 'lookups', 'auth' => true, 'method' => ['GET', 'POST'], 'description' => 'Types, fields, filters, statuses, physical-state values.'],
            ['name' => 'list', 'auth' => true, 'method' => ['GET', 'POST'], 'ui' => 'Device list + search + status + attribute filters', 'params' => ['type', 'q', 'status', '...type filters']],
            ['name' => 'filters', 'auth' => true, 'method' => ['GET', 'POST'], 'ui' => 'Attribute filter dropdowns', 'params' => ['type', '...active filters']],
            ['name' => 'get', 'auth' => true, 'method' => ['GET', 'POST'], 'ui' => 'Device detail modal', 'params' => ['type', 'id']],
            ['name' => 'create', 'auth' => true, 'method' => ['POST'], 'ui' => 'New device in a category', 'params' => ['type', '...type fields']],
            ['name' => 'update', 'auth' => true, 'method' => ['POST'], 'ui' => 'Edit device fields (partial allowed)', 'params' => ['type', 'id', '...fields']],
            ['name' => 'save', 'auth' => true, 'method' => ['POST'], 'ui' => 'Same as UI save (create or update)', 'params' => ['type', 'original_key|id', '...fields']],
            ['name' => 'set_condition', 'alias' => ['set_physical_state', 'set_fysieke_staat'], 'auth' => true, 'method' => ['POST'], 'ui' => 'Change fysieke staat', 'params' => ['type', 'id', 'fysieke_staat']],
            ['name' => 'assign', 'auth' => true, 'method' => ['POST'], 'ui' => 'Assign / reserve / unavailable', 'params' => ['type', 'id', 'uitgegeven_aan|uitgegeven_email']],
            ['name' => 'unassign', 'alias' => ['set_reserve'], 'auth' => true, 'method' => ['POST'], 'ui' => 'Set Reserve', 'params' => ['type', 'id']],
            ['name' => 'set_unavailable', 'auth' => true, 'method' => ['POST'], 'ui' => 'Set Onbeschikbaar', 'params' => ['type', 'id']],
            ['name' => 'delete', 'auth' => true, 'method' => ['POST'], 'ui' => 'Delete device', 'params' => ['type', 'id']],
            ['name' => 'verify_qr', 'auth' => true, 'method' => ['POST'], 'ui' => 'Mark QR verified after print scan', 'params' => ['type', 'id']],
            ['name' => 'users', 'auth' => true, 'method' => ['GET', 'POST'], 'ui' => 'Directory users for assign', 'params' => ['refresh']],
            ['name' => 'print_label', 'auth' => true, 'method' => ['POST'], 'ui' => 'Print device label', 'params' => ['type', 'id']],
            ['name' => 'notes_list', 'auth' => true, 'method' => ['GET', 'POST'], 'ui' => 'Device notes', 'params' => ['type', 'id']],
            ['name' => 'notes_add', 'auth' => true, 'method' => ['POST'], 'ui' => 'Add device note', 'params' => ['type', 'id', 'message_text|message']],
            ['name' => 'notes_edit', 'auth' => true, 'method' => ['POST'], 'ui' => 'Edit device note', 'params' => ['type', 'id', 'message_id', 'message_text|message']],
            ['name' => 'notes_delete', 'auth' => true, 'method' => ['POST'], 'ui' => 'Delete device note', 'params' => ['type', 'id', 'message_id']],
        ],
        'types' => ['laptop', 'phone', 'accessory'],
        'fields' => [
            'laptop' => MOIRAI_LAPTOP_FIELDS,
            'phone' => MOIRAI_PHONE_FIELDS,
            'accessory' => MOIRAI_ACCESSORY_FIELDS,
        ],
        'filters' => [
            'laptop' => MOIRAI_LAPTOP_FILTER_FIELDS,
            'phone' => MOIRAI_PHONE_FILTER_FIELDS,
            'accessory' => MOIRAI_ACCESSORY_FILTER_FIELDS,
        ],
        'statuses' => ['all', 'assigned', 'reserve', 'unavailable'],
        'conditions' => MOIRAI_CONDITION_OPTIONS,
        'not_settable' => [
            'verouderd' => 'Set by nightly aging, not by the UI or API clients.',
            'verouderd_alert_verzonden' => 'Internal aging-mail flag.',
            'qr_geldig' => 'Only via verify_qr.',
            'admin / is_admin' => 'Ignored. A valid API key is ICT access.',
        ],
        'errors' => [
            '401' => ['unauthorized', 'api_key_missing', 'api_key_query'],
            '400' => ['unknown_action', 'invalid_input', 'validation errors from moirai_data'],
            '403' => ['forbidden'],
            '404' => ['device_not_found', 'note_not_found'],
            '405' => ['method_not_allowed'],
            '500' => ['generic'],
        ],
    ];
}

/**
 * @return array{status: int, body: array}
 */
function moirai_api_error(string $errorKey, int $status, ?string $errorCode = null, ?string $override = null): array
{
    $code = $errorCode ?? preg_replace('/^moirai\.error\./', '', $errorKey);
    return [
        'status' => $status,
        'body' => [
            'ok' => false,
            'error' => $override ?? moirai_loc($errorKey),
            'error_code' => $code,
        ],
    ];
}

/**
 * @return array{status: int, body: array}
 */
function moirai_api_ok(array $extra, int $status = 200): array
{
    return [
        'status' => $status,
        'body' => ['ok' => true] + $extra,
    ];
}

function moirai_api_require_type(): string
{
    $type = moirai_api_request_string('type');
    if ($type === '') {
        throw new InvalidArgumentException(moirai_loc('moirai.error.type_required'));
    }
    if (moirai_type_key($type) === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.unknown_type'));
    }

    return $type;
}

function moirai_api_require_id(): string
{
    $id = moirai_api_request_string('id');
    if ($id === '') {
        $id = moirai_api_request_string('original_key');
    }
    if ($id === '') {
        throw new InvalidArgumentException(moirai_loc('moirai.error.id_required'));
    }

    return $id;
}

/**
 * Drop client-supplied privilege / derived flags. Business rules stay in moirai_data.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function moirai_api_client_device_input(array $payload): array
{
    unset(
        $payload['verouderd'],
        $payload['verouderd_alert_verzonden'],
        $payload['qr_geldig'],
        $payload['admin'],
        $payload['is_admin'],
        $payload['user_is_admin'],
        $payload['historie_uitgegeven'],
        $payload['uitgegeven_sinds'],
        $payload['api_key']
    );

    return $payload;
}

/**
 * @param list<array<string, mixed>> $devices
 * @return list<array<string, mixed>>
 */
function moirai_api_attach_last_notes(array $devices, string $type): array
{
    moirai_api_ensure_chat();
    $typeKey = moirai_type_key($type) ?? 'laptops';
    $keyField = moirai_device_key_field($typeKey);
    $threadKeys = [];
    foreach ($devices as $device) {
        $deviceKey = trim((string) ($device[$keyField] ?? $device['id'] ?? ''));
        if ($deviceKey !== '') {
            $threadKeys[] = moirai_device_notes_thread_key($typeKey, $deviceKey);
        }
    }

    $lastNotes = [];
    try {
        $lastNotes = KvtChat::lastMapped($threadKeys, false);
    } catch (Throwable $error) {
        error_log('KvtChat lastMapped failed: ' . $error->getMessage());
    }

    foreach ($devices as &$device) {
        $deviceKey = trim((string) ($device[$keyField] ?? $device['id'] ?? ''));
        $threadKey = $deviceKey !== '' ? moirai_device_notes_thread_key($typeKey, $deviceKey) : '';
        $device['last_note'] = ($threadKey !== '' && isset($lastNotes[$threadKey]))
            ? $lastNotes[$threadKey]
            : null;
        $device['status'] = moirai_device_status($device);
    }
    unset($device);

    return $devices;
}

function moirai_api_ensure_chat(): void
{
    if (!class_exists('KvtChat')) {
        require_once __DIR__ . '/lib/kvt-chat/KvtChat.php';
    }

    KvtChat::configure([
        'pdo' => moirai_db(),
        'avatar_dir' => __DIR__ . '/data/user_avatars',
        'viewer' => static fn(): array => [
            'email' => moirai_current_user_email(),
            'name' => moirai_current_user_name(),
        ],
        'is_admin' => static fn(): bool => true,
        'can_edit' => 'own',
        'can_delete' => 'own',
        'admin_bypass' => true,
        'migrate_device_notes' => true,
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function moirai_api_directory_users(bool $refresh = false): array
{
    if (isset($GLOBALS['moirai_api_directory_users']) && is_array($GLOBALS['moirai_api_directory_users'])) {
        return $GLOBALS['moirai_api_directory_users'];
    }

    return moirai_fetch_directory_users($refresh);
}

function moirai_api_assignee_from_request(): mixed
{
    $source = moirai_api_request_source();
    if (array_key_exists('uitgegeven_aan', $source)) {
        $value = $source['uitgegeven_aan'];
        if ($value === null || $value === '' || $value === 'reserve') {
            return null;
        }
        if (is_string($value)) {
            $email = strtolower(trim($value));
            if (in_array($email, [MOIRAI_UNAVAILABLE_EMAIL, 'unavailable'], true)) {
                return ['email' => MOIRAI_UNAVAILABLE_EMAIL];
            }

            return ['email' => $email];
        }
        if (is_array($value)) {
            return $value;
        }
    }

    $email = strtolower(trim((string) ($source['uitgegeven_email'] ?? $source['user_email'] ?? $source['assignee'] ?? '')));
    if ($email === '' || $email === 'reserve') {
        return null;
    }
    if (in_array($email, [MOIRAI_UNAVAILABLE_EMAIL, 'unavailable'], true)) {
        return ['email' => MOIRAI_UNAVAILABLE_EMAIL];
    }

    $user = ['email' => $email];
    $naam = trim((string) ($source['uitgegeven_naam'] ?? $source['user_name'] ?? $source['naam'] ?? ''));
    $userId = trim((string) ($source['uitgegeven_user_id'] ?? $source['user_id'] ?? ''));
    if ($naam !== '') {
        $user['naam'] = $naam;
    }
    if ($userId !== '') {
        $user['id'] = $userId;
    }

    return $user;
}

/**
 * @param array<string, mixed> $existing
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function moirai_api_merge_device_input(string $type, array $existing, array $input): array
{
    $typeKey = moirai_type_key($type);
    if ($typeKey === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.unknown_type'));
    }

    $fields = moirai_fields_for_type($typeKey);
    $merged = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $input)) {
            $merged[$field] = $input[$field];
        } else {
            $merged[$field] = $existing[$field] ?? '';
        }
    }

    $keyField = moirai_device_key_field($typeKey);
    $existingId = trim((string) ($existing['id'] ?? $existing[$keyField] ?? ''));
    $merged['original_key'] = $existingId;
    $merged['id'] = $existingId;
    if (array_key_exists($keyField, $input)) {
        $merged[$keyField] = $input[$keyField];
    } else {
        $merged[$keyField] = $existing[$keyField] ?? $existingId;
    }

    return $merged;
}

/**
 * @return array{status: int, body: array}
 */
function moirai_api_dispatch(string $action): array
{
    $action = trim($action);
    if (in_array($action, MOIRAI_API_PUBLIC_ACTIONS, true)) {
        return moirai_api_ok(moirai_api_help());
    }

    $mutations = [
        'create', 'update', 'save', 'set_condition', 'set_physical_state', 'set_fysieke_staat',
        'assign', 'unassign', 'set_reserve', 'set_unavailable', 'delete', 'verify_qr',
        'print_label', 'notes_add', 'notes_edit', 'notes_delete',
    ];
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($action, $mutations, true) && !in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        return moirai_api_error('moirai.error.invalid_input', 405, 'method_not_allowed');
    }

    switch ($action) {
        case 'whoami':
            return moirai_api_ok([
                'key_label' => (string) ($_SESSION['user']['name'] ?? ''),
                'auth' => 'api_key',
            ]);

        case 'lookups':
            return moirai_api_ok([
                'types' => [
                    ['type' => 'laptop', 'key' => 'laptops', 'id_field' => 'serienummer'],
                    ['type' => 'phone', 'key' => 'phones', 'id_field' => 'imei'],
                    ['type' => 'accessory', 'key' => 'accessories', 'id_field' => 'accessory_id'],
                ],
                'fields' => [
                    'laptop' => MOIRAI_LAPTOP_FIELDS,
                    'phone' => MOIRAI_PHONE_FIELDS,
                    'accessory' => MOIRAI_ACCESSORY_FIELDS,
                ],
                'filters' => [
                    'laptop' => MOIRAI_LAPTOP_FILTER_FIELDS,
                    'phone' => MOIRAI_PHONE_FILTER_FIELDS,
                    'accessory' => MOIRAI_ACCESSORY_FILTER_FIELDS,
                ],
                'statuses' => ['all', 'assigned', 'reserve', 'unavailable'],
                'conditions' => MOIRAI_CONDITION_OPTIONS,
                'laptop_os' => MOIRAI_LAPTOP_OS_OPTIONS,
                'phone_os' => MOIRAI_PHONE_OS_OPTIONS,
                'laptop_keyboards' => MOIRAI_LAPTOP_KEYBOARD_OPTIONS,
            ]);

        case 'list':
            $type = moirai_api_require_type();
            $query = moirai_api_request_string('q');
            $status = moirai_api_request_string('status');
            if ($status === '') {
                $status = 'all';
            }
            $attrs = moirai_parse_list_filters($type, moirai_api_request_source());
            $devices = array_values(array_filter(
                moirai_list_devices($type),
                static fn(array $device): bool => moirai_device_matches_filter($device, $query, $status, $attrs)
            ));
            $devices = moirai_api_attach_last_notes($devices, $type);

            return moirai_api_ok(['devices' => $devices, 'count' => count($devices)]);

        case 'filters':
            $type = moirai_api_require_type();
            $active = moirai_parse_list_filters($type, moirai_api_request_source());

            return moirai_api_ok(['filters' => moirai_get_filter_options($type, $active)]);

        case 'get':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $device = moirai_get_device($type, $id);
            if ($device === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }
            $device['status'] = moirai_device_status($device);

            return moirai_api_ok(['device' => $device]);

        case 'create':
            $type = moirai_api_require_type();
            $payload = moirai_api_client_device_input(moirai_api_request_source());
            unset($payload['original_key'], $payload['id']);
            $device = moirai_save_device($type, $payload, [], false);

            return moirai_api_ok(['device' => $device], 201);

        case 'update':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $existing = moirai_get_device($type, $id);
            if ($existing === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }
            $payload = moirai_api_merge_device_input(
                $type,
                $existing,
                moirai_api_client_device_input(moirai_api_request_source())
            );
            $device = moirai_save_device($type, $payload, [], false);

            return moirai_api_ok(['device' => $device]);

        case 'save':
            $type = moirai_api_require_type();
            $payload = moirai_api_client_device_input(moirai_api_request_source());
            $original = trim((string) ($payload['original_key'] ?? $payload['id'] ?? ''));
            if ($original !== '') {
                $existing = moirai_get_device($type, $original);
                if ($existing === null) {
                    return moirai_api_error('moirai.error.device_not_found', 404);
                }
                $payload = moirai_api_merge_device_input($type, $existing, $payload);
            }
            $device = moirai_save_device($type, $payload, [], false);

            return moirai_api_ok(['device' => $device], $original === '' ? 201 : 200);

        case 'set_condition':
        case 'set_physical_state':
        case 'set_fysieke_staat':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $condition = moirai_api_request_string('fysieke_staat');
            if ($condition === '') {
                throw new InvalidArgumentException(moirai_loc('moirai.error.condition_invalid'));
            }
            $existing = moirai_get_device($type, $id);
            if ($existing === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }
            $payload = moirai_api_merge_device_input($type, $existing, ['fysieke_staat' => $condition]);
            $device = moirai_save_device($type, $payload, [], false);

            return moirai_api_ok(['device' => $device]);

        case 'assign':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $assignee = moirai_api_assignee_from_request();
            $users = [];
            if ($assignee !== null && !moirai_is_unavailable_user(moirai_normalize_user($assignee))) {
                $users = moirai_api_directory_users();
            }
            $device = moirai_assign_device($type, $id, $assignee, $users);

            return moirai_api_ok(['device' => $device]);

        case 'unassign':
        case 'set_reserve':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $device = moirai_assign_device($type, $id, null, []);

            return moirai_api_ok(['device' => $device]);

        case 'set_unavailable':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $device = moirai_assign_device($type, $id, ['email' => MOIRAI_UNAVAILABLE_EMAIL], []);

            return moirai_api_ok(['device' => $device]);

        case 'delete':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            moirai_delete_device($type, $id);

            return moirai_api_ok([]);

        case 'verify_qr':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $device = moirai_mark_qr_verified($type, $id);
            if ($device === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }

            return moirai_api_ok(['device' => $device]);

        case 'users':
            $users = moirai_api_directory_users(moirai_api_request_bool('refresh'));

            return moirai_api_ok(['users' => $users]);

        case 'print_label':
            require_once __DIR__ . '/moirai_print.php';
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $device = moirai_get_device($type, $id);
            if ($device === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }
            $url = moirai_build_device_posprint_url($device, $type);

            return moirai_api_ok(['url' => $url]);

        case 'notes_list':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $device = moirai_get_device($type, $id);
            if ($device === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }
            moirai_api_ensure_chat();
            $typeKey = moirai_type_key($type);
            $messages = KvtChat::listMapped(moirai_device_notes_thread_key((string) $typeKey, $id));

            return moirai_api_ok(['messages' => $messages]);

        case 'notes_add':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $text = moirai_api_request_string('message_text');
            if ($text === '') {
                $text = moirai_api_request_string('message');
            }
            $device = moirai_get_device($type, $id);
            if ($device === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }
            moirai_api_ensure_chat();
            $typeKey = moirai_type_key($type);
            $message = KvtChat::addMapped(moirai_device_notes_thread_key((string) $typeKey, $id), $text);

            return moirai_api_ok(['message' => $message]);

        case 'notes_edit':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $messageId = (int) moirai_api_request_string('message_id');
            $text = moirai_api_request_string('message_text');
            if ($text === '') {
                $text = moirai_api_request_string('message');
            }
            $device = moirai_get_device($type, $id);
            if ($device === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }
            moirai_api_ensure_chat();
            $message = KvtChat::editMapped($messageId, $text);

            return moirai_api_ok(['message' => $message]);

        case 'notes_delete':
            $type = moirai_api_require_type();
            $id = moirai_api_require_id();
            $messageId = (int) moirai_api_request_string('message_id');
            $device = moirai_get_device($type, $id);
            if ($device === null) {
                return moirai_api_error('moirai.error.device_not_found', 404);
            }
            moirai_api_ensure_chat();
            KvtChat::deleteMapped($messageId);

            return moirai_api_ok([]);

        default:
            return moirai_api_error('moirai.error.unknown_action', 400);
    }
}
