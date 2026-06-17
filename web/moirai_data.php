<?php

const MOIRAI_DB_FILE = __DIR__ . '/data/moirai.sqlite';
const MOIRAI_FILTER_CACHE_FILE = __DIR__ . '/data/filter_cache.json';

const MOIRAI_LAPTOP_FIELDS = [
    'model',
    'serienummer',
    'ram',
    'opslag',
    'cpu',
    'aanschafdatum',
    'os',
    'os_versie',
];

const MOIRAI_PHONE_FIELDS = [
    'model',
    'imei',
    'schermformaat',
    'opslag',
    'os',
    'os_versie',
    'aanschafdatum',
];

const MOIRAI_LAPTOP_OS_OPTIONS = [
    'Windows',
    'OSX',
    'Linux',
];

const MOIRAI_PHONE_OS_OPTIONS = [
    'Android',
    'iPhone',
];

const MOIRAI_LAPTOP_FILTER_FIELDS = [
    'os',
    'os_versie',
    'model',
    'ram',
    'opslag',
];

const MOIRAI_PHONE_FILTER_FIELDS = [
    'os',
    'os_versie',
    'model',
    'schermformaat',
    'opslag',
];

function moirai_is_admin(): bool
{
    $email = (string) ($_SESSION['user']['email'] ?? '');
    if ($email !== '' && isset($GLOBALS['ictUsers']) && is_array($GLOBALS['ictUsers'])) {
        if (function_exists('moirai_email_in_list')) {
            return moirai_email_in_list($email, $GLOBALS['ictUsers']);
        }
    }

    return !empty($_SESSION['user']['admin']);
}

function moirai_loc(string $key, mixed ...$args): string
{
    if (function_exists('LOC')) {
        return LOC($key, ...$args);
    }

    return $args !== [] ? sprintf($key, ...$args) : $key;
}

function moirai_fetch_directory_users(): array
{
    global $graphCredentials;

    if (empty($graphCredentials['tenantId']) || empty($graphCredentials['clientId']) || empty($graphCredentials['clientSecret'])) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.users_fetch'));
    }

    try {
        $users = include __DIR__ . '/getusers_fetch.php';
    } catch (Throwable) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.users_fetch'));
    }

    return is_array($users) ? $users : [];
}

function moirai_validate_byte_amount(string $value, string $errorKey): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('/^(\d+(?:[.,]\d+)?)\s*(b|byte|bytes|kb|mb|gb|tb)?$/iu', $value, $matches)) {
        throw new InvalidArgumentException(moirai_loc($errorKey));
    }

    $amount = str_replace(',', '.', $matches[1]);
    $unit = strtolower($matches[2] ?? 'b');
    if ($unit === 'byte') {
        $unit = 'b';
    }

    $unitMap = [
        'b' => 'B',
        'bytes' => 'B',
        'kb' => 'KB',
        'mb' => 'MB',
        'gb' => 'GB',
        'tb' => 'TB',
    ];

    if (!isset($unitMap[$unit])) {
        throw new InvalidArgumentException(moirai_loc($errorKey));
    }

    $normalizedAmount = rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');

    return $normalizedAmount . ' ' . $unitMap[$unit];
}

function moirai_validate_ram(string $value): string
{
    return moirai_validate_byte_amount($value, 'moirai.error.ram_invalid');
}

function moirai_validate_opslag(string $value): string
{
    return moirai_validate_byte_amount($value, 'moirai.error.opslag_invalid');
}

function moirai_validate_aanschafdatum(string $value, bool $defaultToday = false): string
{
    $value = trim($value);
    if ($value === '') {
        return $defaultToday ? date('Y-m-d') : '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.date_invalid'));
    }

    if ($date->format('Y-m-d') > date('Y-m-d')) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.date_future'));
    }

    return $date->format('Y-m-d');
}

function moirai_format_schermformaat_inches(float $amount): string
{
    $normalizedAmount = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

    return $normalizedAmount . '"';
}

function moirai_normalize_schermformaat_display(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    try {
        return moirai_validate_schermformaat($value);
    } catch (InvalidArgumentException) {
        return $value;
    }
}

function moirai_validate_schermformaat(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = str_replace(',', '.', $value);
    $value = preg_replace('/[″"\']+/u', '', $value) ?? $value;
    $value = trim($value);

    if (!preg_match('/^(\d+(?:\.\d+)?)\s*(?:inch(?:es)?|in|zoll|pouces)?\.?\s*$/iu', $value, $matches)) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.screen_invalid'));
    }

    $amount = (float) $matches[1];
    if ($amount <= 0) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.screen_invalid'));
    }

    return moirai_format_schermformaat_inches($amount);
}

function moirai_normalize_filter_options(string $typeKey, array $options): array
{
    if ($typeKey !== 'phones' || !isset($options['schermformaat']) || !is_array($options['schermformaat'])) {
        return $options;
    }

    $normalized = [];
    foreach ($options['schermformaat'] as $value) {
        $formatted = moirai_normalize_schermformaat_display((string) $value);
        if ($formatted !== '') {
            $normalized[] = $formatted;
        }
    }

    $options['schermformaat'] = moirai_sort_filter_values(array_values(array_unique($normalized)));

    return $options;
}

function moirai_validate_device_fields(string $typeKey, array &$sanitized, bool $isNew): void
{
    if ($typeKey === 'laptops') {
        $sanitized['ram'] = moirai_validate_ram($sanitized['ram']);
        $sanitized['opslag'] = moirai_validate_opslag($sanitized['opslag']);
        $sanitized['aanschafdatum'] = moirai_validate_aanschafdatum($sanitized['aanschafdatum'], $isNew);
        $sanitized['os'] = moirai_normalize_laptop_os($sanitized['os']);
        return;
    }

    $sanitized['schermformaat'] = moirai_validate_schermformaat($sanitized['schermformaat']);
    $sanitized['opslag'] = moirai_validate_opslag($sanitized['opslag']);
    $sanitized['aanschafdatum'] = moirai_validate_aanschafdatum($sanitized['aanschafdatum'], $isNew);
    $sanitized['os'] = moirai_normalize_phone_os($sanitized['os']);
}

function moirai_persist_device_assignment(string $type, array $device): array
{
    $typeKey = moirai_type_key($type);
    if ($typeKey === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.unknown_type'));
    }

    $keyField = moirai_device_key_field($typeKey);
    $keyValue = trim((string) ($device['id'] ?? $device[$keyField] ?? ''));
    if ($keyValue === '') {
        throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
    }

    $assignment = moirai_assignment_columns(
        $device['uitgegeven_aan'] ?? null,
        $device['uitgegeven_sinds'] ?? null,
        $device['historie_uitgegeven'] ?? []
    );

    $table = moirai_table_name($typeKey);
    $pdo = moirai_db();
    $stmt = $pdo->prepare(
        'UPDATE ' . $table . ' SET uitgegeven_user_id = :uitgegeven_user_id, uitgegeven_naam = :uitgegeven_naam, '
        . 'uitgegeven_email = :uitgegeven_email, uitgegeven_sinds = :uitgegeven_sinds, historie_json = :historie_json '
        . 'WHERE ' . $keyField . ' = :key'
    );
    $stmt->execute($assignment + ['key' => $keyValue]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
    }

    $saved = moirai_get_device($type, $keyValue);
    if ($saved === null) {
        throw new RuntimeException(moirai_loc('moirai.error.save_failed'));
    }

    return $saved;
}

function moirai_assign_device(string $type, string $key, mixed $userInput, array $allowedUsers): array
{
    $existing = moirai_get_device($type, $key);
    if ($existing === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
    }

    $validatedUser = moirai_validate_user_assignment($userInput, $allowedUsers);
    $device = moirai_apply_assignment_history($existing, $validatedUser);

    return moirai_persist_device_assignment($type, $device);
}

function moirai_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function moirai_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function moirai_type_key(string $type): ?string
{
    $type = strtolower(trim($type));
    return match ($type) {
        'laptop', 'laptops' => 'laptops',
        'phone', 'phones', 'telefoon', 'telefoons' => 'phones',
        default => null,
    };
}

function moirai_table_name(string $typeKey): string
{
    return $typeKey === 'laptops' ? 'laptops' : 'phones';
}

function moirai_device_key_field(string $typeKey): string
{
    return $typeKey === 'laptops' ? 'serienummer' : 'imei';
}

function moirai_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(MOIRAI_DB_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $pdo = new PDO('sqlite:' . MOIRAI_DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    moirai_init_schema($pdo);

    return $pdo;
}

function moirai_init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS laptops (
            serienummer         TEXT PRIMARY KEY,
            naam                TEXT NOT NULL,
            model               TEXT NOT NULL DEFAULT '',
            ram                 TEXT NOT NULL DEFAULT '',
            cpu                 TEXT NOT NULL DEFAULT '',
            aanschafdatum       TEXT NOT NULL DEFAULT '',
            os                  TEXT NOT NULL DEFAULT '',
            os_versie           TEXT NOT NULL DEFAULT '',
            uitgegeven_user_id  TEXT,
            uitgegeven_naam     TEXT,
            uitgegeven_email    TEXT,
            uitgegeven_sinds    TEXT,
            historie_json       TEXT NOT NULL DEFAULT '[]'
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS phones (
            imei                TEXT PRIMARY KEY,
            naam                TEXT NOT NULL,
            model               TEXT NOT NULL DEFAULT '',
            schermformaat       TEXT NOT NULL DEFAULT '',
            os                  TEXT NOT NULL DEFAULT '',
            os_versie           TEXT NOT NULL DEFAULT '',
            aanschafdatum       TEXT NOT NULL DEFAULT '',
            uitgegeven_user_id  TEXT,
            uitgegeven_naam     TEXT,
            uitgegeven_email    TEXT,
            uitgegeven_sinds    TEXT,
            historie_json       TEXT NOT NULL DEFAULT '[]'
        )
    ");

    moirai_ensure_column($pdo, 'phones', 'os', "TEXT NOT NULL DEFAULT ''");
    moirai_ensure_column($pdo, 'laptops', 'os_versie', "TEXT NOT NULL DEFAULT ''");
    moirai_ensure_column($pdo, 'laptops', 'os', "TEXT NOT NULL DEFAULT ''");
    moirai_ensure_column($pdo, 'laptops', 'opslag', "TEXT NOT NULL DEFAULT ''");
    moirai_ensure_column($pdo, 'phones', 'opslag', "TEXT NOT NULL DEFAULT ''");
    moirai_migrate_laptop_os_column($pdo);
}

function moirai_migrate_laptop_os_column(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(laptops)')->fetchAll();
    $hasLegacy = false;
    foreach ($columns as $info) {
        if (($info['name'] ?? '') === 'besturingssysteem') {
            $hasLegacy = true;
            break;
        }
    }

    if (!$hasLegacy) {
        return;
    }

    $pdo->exec("UPDATE laptops SET os = besturingssysteem WHERE trim(os) = '' AND trim(besturingssysteem) != ''");
}

function moirai_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $info) {
        if (($info['name'] ?? '') === $column) {
            return;
        }
    }

    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function moirai_normalize_phone_os(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    foreach (MOIRAI_PHONE_OS_OPTIONS as $option) {
        if (strcasecmp($value, $option) === 0) {
            return $option;
        }
    }

    throw new InvalidArgumentException(moirai_loc('moirai.error.os_phone_invalid'));
}

function moirai_normalize_laptop_os(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    foreach (MOIRAI_LAPTOP_OS_OPTIONS as $option) {
        if (strcasecmp($value, $option) === 0) {
            return $option;
        }
    }

    throw new InvalidArgumentException(moirai_loc('moirai.error.os_laptop_invalid'));
}

function moirai_filter_fields_for_type(string $typeKey): array
{
    return $typeKey === 'laptops' ? MOIRAI_LAPTOP_FILTER_FIELDS : MOIRAI_PHONE_FILTER_FIELDS;
}

function moirai_empty_filter_cache(): array
{
    $cache = ['laptops' => [], 'phones' => []];
    foreach (MOIRAI_LAPTOP_FILTER_FIELDS as $field) {
        $cache['laptops'][$field] = [];
    }
    foreach (MOIRAI_PHONE_FILTER_FIELDS as $field) {
        $cache['phones'][$field] = [];
    }

    return $cache;
}

function moirai_read_filter_cache(): array
{
    $dir = dirname(MOIRAI_FILTER_CACHE_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    if (!is_file(MOIRAI_FILTER_CACHE_FILE)) {
        return moirai_rebuild_filter_cache();
    }

    $data = json_decode((string) file_get_contents(MOIRAI_FILTER_CACHE_FILE), true);
    if (!is_array($data)) {
        return moirai_rebuild_filter_cache();
    }

    $cache = moirai_empty_filter_cache();
    foreach (['laptops', 'phones'] as $typeKey) {
        $fields = moirai_filter_fields_for_type($typeKey);
        foreach ($fields as $field) {
            $values = $data[$typeKey][$field] ?? [];
            if ($field === 'os' && $typeKey === 'laptops' && $values === [] && isset($data[$typeKey]['besturingssysteem'])) {
                $values = $data[$typeKey]['besturingssysteem'];
            }
            $cache[$typeKey][$field] = is_array($values) ? array_values($values) : [];
        }
    }

    if (isset($data['laptops']['besturingssysteem'])) {
        moirai_write_filter_cache($cache);
    }

    return $cache;
}

function moirai_write_filter_cache(array $cache): void
{
    $dir = dirname(MOIRAI_FILTER_CACHE_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    file_put_contents(
        MOIRAI_FILTER_CACHE_FILE,
        json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function moirai_sort_filter_values(array $values): array
{
    $values = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        $values
    ), static fn(string $value): bool => $value !== '')));
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);

    return $values;
}

function moirai_rebuild_filter_cache(): array
{
    $cache = moirai_empty_filter_cache();

    foreach (['laptops', 'phones'] as $typeKey) {
        $type = $typeKey === 'laptops' ? 'laptop' : 'phone';
        foreach (moirai_list_devices($type) as $device) {
            foreach (moirai_filter_fields_for_type($typeKey) as $field) {
                $value = trim((string) ($device[$field] ?? ''));
                if ($value === '') {
                    continue;
                }
                if (!in_array($value, $cache[$typeKey][$field], true)) {
                    $cache[$typeKey][$field][] = $value;
                }
            }
        }
        foreach (moirai_filter_fields_for_type($typeKey) as $field) {
            $cache[$typeKey][$field] = moirai_sort_filter_values($cache[$typeKey][$field]);
        }
    }

    moirai_write_filter_cache($cache);

    return $cache;
}

function moirai_get_filter_options(string $type, array $activeFilters = []): array
{
    $typeKey = moirai_type_key($type);
    if ($typeKey === null) {
        return [];
    }

    $fields = moirai_filter_fields_for_type($typeKey);
    $sanitized = [];
    foreach ($fields as $field) {
        $value = trim((string) ($activeFilters[$field] ?? ''));
        if ($value !== '') {
            $sanitized[$field] = $value;
        }
    }

    if ($sanitized === []) {
        $cache = moirai_read_filter_cache();

        return moirai_normalize_filter_options($typeKey, $cache[$typeKey] ?? moirai_empty_filter_cache()[$typeKey]);
    }

    $devices = moirai_list_devices($type);
    $result = [];
    foreach ($fields as $field) {
        $result[$field] = [];
    }

    foreach ($fields as $field) {
        $otherFilters = $sanitized;
        unset($otherFilters[$field]);

        $values = [];
        foreach ($devices as $device) {
            if (!moirai_device_matches_filter($device, '', 'all', $otherFilters)) {
                continue;
            }

            $value = trim((string) ($device[$field] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        $result[$field] = moirai_sort_filter_values($values);
    }

    return moirai_normalize_filter_options($typeKey, $result);
}

function moirai_count_devices_with_field_value(string $typeKey, string $field, string $value): int
{
    if (!in_array($field, moirai_filter_fields_for_type($typeKey), true)) {
        return 0;
    }

    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $table = moirai_table_name($typeKey);
    $pdo = moirai_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$field} = :value");
    $stmt->execute(['value' => $value]);

    return (int) $stmt->fetchColumn();
}

function moirai_cache_add_filter_value(string $typeKey, string $field, string $value): void
{
    $value = trim($value);
    if ($value === '' || !in_array($field, moirai_filter_fields_for_type($typeKey), true)) {
        return;
    }

    $cache = moirai_read_filter_cache();
    $values = $cache[$typeKey][$field] ?? [];
    foreach ($values as $existing) {
        if (strcasecmp((string) $existing, $value) === 0) {
            return;
        }
    }

    $values[] = $value;
    $cache[$typeKey][$field] = moirai_sort_filter_values($values);
    moirai_write_filter_cache($cache);
}

function moirai_cache_remove_filter_value_if_unused(string $typeKey, string $field, string $value): void
{
    $value = trim($value);
    if ($value === '' || !in_array($field, moirai_filter_fields_for_type($typeKey), true)) {
        return;
    }

    if (moirai_count_devices_with_field_value($typeKey, $field, $value) > 0) {
        return;
    }

    $cache = moirai_read_filter_cache();
    $values = $cache[$typeKey][$field] ?? [];
    $remaining = [];
    foreach ($values as $existing) {
        if (strcasecmp((string) $existing, $value) !== 0) {
            $remaining[] = $existing;
        }
    }

    $cache[$typeKey][$field] = moirai_sort_filter_values($remaining);
    moirai_write_filter_cache($cache);
}

function moirai_cache_add_device_values(string $typeKey, array $device): void
{
    foreach (moirai_filter_fields_for_type($typeKey) as $field) {
        moirai_cache_add_filter_value($typeKey, $field, (string) ($device[$field] ?? ''));
    }
}

function moirai_cache_remove_device_values(string $typeKey, array $device): void
{
    foreach (moirai_filter_fields_for_type($typeKey) as $field) {
        moirai_cache_remove_filter_value_if_unused($typeKey, $field, (string) ($device[$field] ?? ''));
    }
}

function moirai_cache_update_device_values(string $typeKey, array $oldDevice, array $newDevice): void
{
    moirai_cache_remove_device_values($typeKey, $oldDevice);
    moirai_cache_add_device_values($typeKey, $newDevice);
}

function moirai_normalize_user(mixed $user): ?array
{
    if (!is_array($user)) {
        return null;
    }

    $email = strtolower(trim((string) ($user['email'] ?? $user['Email'] ?? '')));
    if ($email === '') {
        return null;
    }

    return [
        'id' => trim((string) ($user['id'] ?? $user['Id'] ?? '')),
        'naam' => trim((string) ($user['naam'] ?? $user['Naam'] ?? '')),
        'email' => $email,
    ];
}

function moirai_users_match(?array $left, ?array $right): bool
{
    if ($left === null && $right === null) {
        return true;
    }
    if ($left === null || $right === null) {
        return false;
    }

    $leftId = trim((string) ($left['id'] ?? ''));
    $rightId = trim((string) ($right['id'] ?? ''));
    if ($leftId !== '' && $rightId !== '' && strcasecmp($leftId, $rightId) === 0) {
        return true;
    }

    return strcasecmp((string) ($left['email'] ?? ''), (string) ($right['email'] ?? '')) === 0;
}

function moirai_sanitize_text_fields(array $input, array $fields): array
{
    $result = [];
    foreach ($fields as $field) {
        $result[$field] = trim((string) ($input[$field] ?? ''));
    }

    return $result;
}

function moirai_row_to_device(array $row, string $typeKey): array
{
    $keyField = moirai_device_key_field($typeKey);
    $historie = json_decode((string) ($row['historie_json'] ?? '[]'), true);
    if (!is_array($historie)) {
        $historie = [];
    }

    $uitgegeven = null;
    $email = trim((string) ($row['uitgegeven_email'] ?? ''));
    if ($email !== '') {
        $uitgegeven = [
            'id' => trim((string) ($row['uitgegeven_user_id'] ?? '')),
            'naam' => trim((string) ($row['uitgegeven_naam'] ?? '')),
            'email' => strtolower($email),
        ];
    }

    $model = trim((string) ($row['model'] ?? ''));
    $naam = trim((string) ($row['naam'] ?? ''));
    if ($model === '' && $naam !== '') {
        $model = $naam;
    }

    $device = [
        'id' => (string) ($row[$keyField] ?? ''),
        $keyField => (string) ($row[$keyField] ?? ''),
        'naam' => $model,
        'model' => $model,
        'aanschafdatum' => (string) ($row['aanschafdatum'] ?? ''),
        'uitgegeven_aan' => $uitgegeven,
        'uitgegeven_sinds' => $row['uitgegeven_sinds'] ?? null,
        'historie_uitgegeven' => $historie,
    ];

    if ($typeKey === 'laptops') {
        $device['ram'] = (string) ($row['ram'] ?? '');
        $device['opslag'] = (string) ($row['opslag'] ?? '');
        $device['cpu'] = (string) ($row['cpu'] ?? '');
        $device['os'] = (string) ($row['os'] ?? $row['besturingssysteem'] ?? '');
        $device['os_versie'] = (string) ($row['os_versie'] ?? '');
    } else {
        $device['schermformaat'] = moirai_normalize_schermformaat_display((string) ($row['schermformaat'] ?? ''));
        $device['opslag'] = (string) ($row['opslag'] ?? '');
        $device['os'] = (string) ($row['os'] ?? '');
        $device['os_versie'] = (string) ($row['os_versie'] ?? '');
    }

    return moirai_public_device($device);
}

function moirai_public_device(array $device): array
{
    $device['uitgegeven_aan'] = moirai_normalize_user($device['uitgegeven_aan'] ?? null);
    $device['historie_uitgegeven'] = is_array($device['historie_uitgegeven'] ?? null)
        ? $device['historie_uitgegeven']
        : [];

    return $device;
}

function moirai_get_device(string $type, string $key): ?array
{
    $typeKey = moirai_type_key($type);
    $key = trim($key);
    if ($typeKey === null || $key === '') {
        return null;
    }

    $table = moirai_table_name($typeKey);
    $keyField = moirai_device_key_field($typeKey);
    $pdo = moirai_db();

    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$keyField} = :key LIMIT 1");
    $stmt->execute(['key' => $key]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return null;
    }

    return moirai_row_to_device($row, $typeKey);
}

function moirai_list_devices(string $type): array
{
    $typeKey = moirai_type_key($type);
    if ($typeKey === null) {
        return [];
    }

    $table = moirai_table_name($typeKey);
    $pdo = moirai_db();
    $rows = $pdo->query("SELECT * FROM {$table} ORDER BY model COLLATE NOCASE ASC, naam COLLATE NOCASE ASC")->fetchAll();
    $devices = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $devices[] = moirai_row_to_device($row, $typeKey);
    }

    return $devices;
}

function moirai_apply_assignment_history(array $existing, ?array $newUser): array
{
    $today = date('Y-m-d');
    $oldUser = moirai_normalize_user($existing['uitgegeven_aan'] ?? null);
    $newUser = moirai_normalize_user($newUser);
    $historie = is_array($existing['historie_uitgegeven'] ?? null) ? $existing['historie_uitgegeven'] : [];
    $uitgegevenSinds = trim((string) ($existing['uitgegeven_sinds'] ?? ''));

    if (moirai_users_match($oldUser, $newUser)) {
        $existing['uitgegeven_aan'] = $newUser;
        $existing['historie_uitgegeven'] = $historie;
        if ($newUser !== null && $uitgegevenSinds === '') {
            $existing['uitgegeven_sinds'] = $today;
        }
        if ($newUser === null) {
            $existing['uitgegeven_sinds'] = null;
        }
        return $existing;
    }

    if ($oldUser !== null) {
        $historie[] = [
            'gebruiker' => $oldUser,
            'van' => $uitgegevenSinds !== '' ? $uitgegevenSinds : $today,
            'tot' => $today,
        ];
    }

    $existing['uitgegeven_aan'] = $newUser;
    $existing['historie_uitgegeven'] = $historie;
    $existing['uitgegeven_sinds'] = $newUser !== null ? $today : null;

    return $existing;
}

function moirai_validate_user_assignment(?array $user, array $allowedUsers): ?array
{
    $user = moirai_normalize_user($user);
    if ($user === null) {
        return null;
    }

    foreach ($allowedUsers as $allowed) {
        if (!is_array($allowed)) {
            continue;
        }
        $candidate = moirai_normalize_user($allowed);
        if ($candidate !== null && moirai_users_match($user, $candidate)) {
            return $candidate;
        }
    }

    throw new InvalidArgumentException(moirai_loc('moirai.error.assign_invalid_user'));
}

function moirai_assignment_columns(?array $user, ?string $since, array $historie): array
{
    $user = moirai_normalize_user($user);

    return [
        'uitgegeven_user_id' => $user['id'] ?? null,
        'uitgegeven_naam' => $user['naam'] ?? null,
        'uitgegeven_email' => $user['email'] ?? null,
        'uitgegeven_sinds' => $user !== null ? ($since ?: date('Y-m-d')) : null,
        'historie_json' => json_encode($historie, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function moirai_save_device(string $type, array $input, array $allowedUsers, bool $updateAssignment = false): array
{
    $typeKey = moirai_type_key($type);
    if ($typeKey === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.unknown_type'));
    }

    $fields = $typeKey === 'laptops' ? MOIRAI_LAPTOP_FIELDS : MOIRAI_PHONE_FIELDS;
    $sanitized = moirai_sanitize_text_fields($input, $fields);
    $keyField = moirai_device_key_field($typeKey);
    $keyValue = $sanitized[$keyField];

    if ($sanitized['model'] === '') {
        throw new InvalidArgumentException(moirai_loc('moirai.error.model_required'));
    }
    $sanitized['naam'] = $sanitized['model'];
    if ($keyValue === '') {
        throw new InvalidArgumentException(
            $typeKey === 'laptops'
                ? moirai_loc('moirai.error.serial_required')
                : moirai_loc('moirai.error.imei_required')
        );
    }

    $originalKey = trim((string) ($input['original_key'] ?? $input['id'] ?? ''));
    $isNew = $originalKey === '';
    $existing = $isNew
        ? ['historie_uitgegeven' => [], 'uitgegeven_aan' => null, 'uitgegeven_sinds' => null]
        : moirai_get_device($type, $originalKey);

    if (!$isNew && $existing === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
    }

    moirai_validate_device_fields($typeKey, $sanitized, $isNew);

    $device = array_merge($existing ?? [], $sanitized, ['id' => $keyValue]);

    if ($updateAssignment) {
        $requestedUser = array_key_exists('uitgegeven_aan', $input)
            ? $input['uitgegeven_aan']
            : ($existing['uitgegeven_aan'] ?? null);
        $validatedUser = moirai_validate_user_assignment($requestedUser, $allowedUsers);
        $device = moirai_apply_assignment_history($device, $validatedUser);
    } elseif (is_array($existing)) {
        $device['uitgegeven_aan'] = $existing['uitgegeven_aan'] ?? null;
        $device['uitgegeven_sinds'] = $existing['uitgegeven_sinds'] ?? null;
        $device['historie_uitgegeven'] = $existing['historie_uitgegeven'] ?? [];
    }

    $assignment = moirai_assignment_columns(
        $device['uitgegeven_aan'] ?? null,
        $device['uitgegeven_sinds'] ?? null,
        $device['historie_uitgegeven'] ?? []
    );

    $pdo = moirai_db();
    $table = moirai_table_name($typeKey);

    if ($typeKey === 'laptops') {
        $params = [
            'serienummer' => $keyValue,
            'naam' => $sanitized['naam'],
            'model' => $sanitized['model'],
            'ram' => $sanitized['ram'],
            'opslag' => $sanitized['opslag'],
            'cpu' => $sanitized['cpu'],
            'aanschafdatum' => $sanitized['aanschafdatum'],
            'os' => $sanitized['os'],
            'os_versie' => $sanitized['os_versie'],
        ] + $assignment;
    } else {
        $params = [
            'imei' => $keyValue,
            'naam' => $sanitized['naam'],
            'model' => $sanitized['model'],
            'schermformaat' => $sanitized['schermformaat'],
            'opslag' => $sanitized['opslag'],
            'os' => $sanitized['os'],
            'os_versie' => $sanitized['os_versie'],
            'aanschafdatum' => $sanitized['aanschafdatum'],
        ] + $assignment;
    }

    $columns = array_keys($params);
    $placeholders = array_map(static fn($col): string => ':' . $col, $columns);
    $insertSql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

    if ($isNew) {
        try {
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute($params);
        } catch (PDOException $error) {
            if (str_contains($error->getMessage(), 'UNIQUE constraint failed')) {
                throw new InvalidArgumentException(
                    $typeKey === 'laptops'
                        ? moirai_loc('moirai.error.serial_duplicate')
                        : moirai_loc('moirai.error.imei_duplicate')
                );
            }
            throw $error;
        }
    } else {
        if (strcasecmp($originalKey, $keyValue) !== 0) {
            $pdo->beginTransaction();
            try {
                $delete = $pdo->prepare("DELETE FROM {$table} WHERE {$keyField} = :original");
                $delete->execute(['original' => $originalKey]);
                if ($delete->rowCount() === 0) {
                    throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
                }

                $insert = $pdo->prepare($insertSql);
                $insert->execute($params);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($error instanceof InvalidArgumentException) {
                    throw $error;
                }
                if ($error instanceof PDOException && str_contains($error->getMessage(), 'UNIQUE constraint failed')) {
                    throw new InvalidArgumentException(
                        $typeKey === 'laptops'
                            ? 'Dit serienummer bestaat al.'
                            : 'Deze IMEI bestaat al.'
                    );
                }
                throw $error;
            }
        } else {
            $setParts = [];
            $updateParams = ['lookup_key' => $keyValue];
            foreach ($columns as $column) {
                if ($column === $keyField) {
                    continue;
                }
                $setParts[] = $column . ' = :' . $column;
                $updateParams[$column] = $params[$column];
            }
            $updateSql = 'UPDATE ' . $table . ' SET ' . implode(', ', $setParts) . ' WHERE ' . $keyField . ' = :lookup_key';
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($updateParams);
        }
    }

    $saved = moirai_get_device($type, $keyValue);
    if ($saved === null) {
        throw new RuntimeException(moirai_loc('moirai.error.save_failed'));
    }

    if ($isNew) {
        moirai_cache_add_device_values($typeKey, $saved);
    } elseif (is_array($existing)) {
        moirai_cache_update_device_values($typeKey, $existing, $saved);
    }

    return $saved;
}

function moirai_delete_device(string $type, string $key): void
{
    $typeKey = moirai_type_key($type);
    $key = trim($key);
    if ($typeKey === null || $key === '') {
        throw new InvalidArgumentException(moirai_loc('moirai.error.unknown_type'));
    }

    $table = moirai_table_name($typeKey);
    $keyField = moirai_device_key_field($typeKey);
    $pdo = moirai_db();

    $device = moirai_get_device($type, $key);
    if ($device === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
    }

    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$keyField} = :key");
    $stmt->execute(['key' => $key]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
    }

    moirai_cache_remove_device_values($typeKey, $device);
}

function moirai_parse_list_filters(string $type): array
{
    $typeKey = moirai_type_key($type);
    if ($typeKey === null) {
        return [];
    }

    $fields = moirai_filter_fields_for_type($typeKey);
    $filters = [];
    foreach ($fields as $field) {
        $value = trim((string) ($_GET[$field] ?? ''));
        if ($value !== '') {
            $filters[$field] = $value;
        }
    }

    return $filters;
}

function moirai_device_matches_filter(array $device, string $query, string $status, array $attrs = []): bool
{
    foreach ($attrs as $field => $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        $deviceValue = trim((string) ($device[$field] ?? ''));
        if ($field === 'schermformaat') {
            $deviceValue = moirai_normalize_schermformaat_display($deviceValue);
            $value = moirai_normalize_schermformaat_display($value);
        }
        if (strcasecmp($deviceValue, $value) !== 0) {
            return false;
        }
    }

    $query = strtolower(trim($query));
    if ($query !== '') {
        $haystack = strtolower(implode(' ', [
            (string) ($device['naam'] ?? ''),
            (string) ($device['model'] ?? ''),
            (string) ($device['serienummer'] ?? ''),
            (string) ($device['imei'] ?? ''),
            (string) ($device['ram'] ?? ''),
            (string) ($device['opslag'] ?? ''),
            (string) ($device['cpu'] ?? ''),
            (string) ($device['os'] ?? ''),
            (string) ($device['os_versie'] ?? ''),
            (string) ($device['schermformaat'] ?? ''),
            (string) ($device['aanschafdatum'] ?? ''),
            (string) ($device['uitgegeven_aan']['naam'] ?? ''),
            (string) ($device['uitgegeven_aan']['email'] ?? ''),
        ]));

        foreach (preg_split('/\s+/', $query) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            if (!str_contains($haystack, $token)) {
                return false;
            }
        }
    }

    $assigned = moirai_normalize_user($device['uitgegeven_aan'] ?? null) !== null;
    if ($status === 'assigned' && !$assigned) {
        return false;
    }
    if ($status === 'reserve' && $assigned) {
        return false;
    }

    return true;
}
