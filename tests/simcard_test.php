<?php

/**
 * CLI checks for the SIM-kaart device type. Run: php tests/simcard_test.php
 */

declare(strict_types=1);

const MOIRAI_SIM_TEST_ONLY_KEY = 'MOIRAI_SIM_TEST_ONLY_KEY';
const MOIRAI_SIM_TEST_ONLY_LABEL = 'simTestOnlyKey';

$failures = 0;
function expect(bool $ok, string $message): void
{
    global $failures;
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL  {$message}\n";
}

/**
 * @return string|null message of the InvalidArgumentException, null when nothing was thrown
 */
function invalid_message(callable $fn): ?string
{
    try {
        $fn();
    } catch (InvalidArgumentException $error) {
        return $error->getMessage();
    }

    return null;
}

$tmp = sys_get_temp_dir() . '/moirai_sim_test_' . bin2hex(random_bytes(4)) . '.sqlite';

// Bestaande productie-DB nabootsen: oud schema zonder simcards-tabel.
$legacy = new PDO('sqlite:' . $tmp);
$legacy->exec("CREATE TABLE laptops (serienummer TEXT PRIMARY KEY, naam TEXT NOT NULL, model TEXT NOT NULL DEFAULT '', ram TEXT NOT NULL DEFAULT '', cpu TEXT NOT NULL DEFAULT '', aanschafdatum TEXT NOT NULL DEFAULT '', os TEXT NOT NULL DEFAULT '', os_versie TEXT NOT NULL DEFAULT '', uitgegeven_user_id TEXT, uitgegeven_naam TEXT, uitgegeven_email TEXT, uitgegeven_sinds TEXT, historie_json TEXT NOT NULL DEFAULT '[]')");
$legacy->exec("INSERT INTO laptops (serienummer, naam, model, aanschafdatum, os) VALUES ('SN-LEGACY', 'ThinkPad', 'ThinkPad', '2025-01-01', 'Windows')");
$legacy = null;

$GLOBALS['moirai_db_file'] = $tmp;
$GLOBALS['moirai_today'] = new DateTimeImmutable('2026-10-08');
$GLOBALS['apiKeys'] = [
    MOIRAI_SIM_TEST_ONLY_LABEL => MOIRAI_SIM_TEST_ONLY_KEY,
];
$GLOBALS['moirai_api_directory_users'] = [
    ['Id' => 'u1', 'id' => 'u1', 'Naam' => 'Test User', 'naam' => 'Test User', 'Email' => 'user@kvt.nl', 'email' => 'user@kvt.nl'],
    ['Id' => 'u2', 'id' => 'u2', 'Naam' => 'Tweede User', 'naam' => 'Tweede User', 'Email' => 'user2@kvt.nl', 'email' => 'user2@kvt.nl'],
];
$users = $GLOBALS['moirai_api_directory_users'];

require_once __DIR__ . '/../web/localization.php';
require_once __DIR__ . '/../web/moirai_api.php';

function reset_request(): void
{
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_X_API_KEY'], $_SERVER['HTTP_AUTHORIZATION']);
}

function dispatch(string $action, array $post = [], array $get = []): array
{
    reset_request();
    $_GET = $get;
    $_POST = $post;
    $_SERVER['HTTP_X_API_KEY'] = MOIRAI_SIM_TEST_ONLY_KEY;
    moirai_api_apply_actor(MOIRAI_SIM_TEST_ONLY_LABEL);
    try {
        return moirai_api_dispatch($action);
    } catch (InvalidArgumentException $error) {
        return moirai_api_from_invalid_argument($error);
    }
}

// --- Type-registratie -------------------------------------------------------

foreach (['sim', 'simcard', 'simcards', 'simkaart', 'SIM-kaart', 'sim-kaarten'] as $alias) {
    expect(moirai_type_key($alias) === 'simcards', "type alias {$alias}");
}
expect(in_array('simcards', moirai_known_type_keys(), true), 'simcards is a known type');
expect(moirai_public_type('simcards') === 'simcard', 'public type simcard');
expect(moirai_device_key_field('simcards') === 'code', 'key field code');
expect(moirai_type_short_code('simcards') === 's', 'short code s');
expect(moirai_type_from_short_code('s') === 'simcard', 'short code s resolves');
expect(moirai_fields_for_type('simcards') === ['code', 'telefoonnummer', 'fysieke_staat'], 'simcard fields');
expect(!in_array('simcards', moirai_aging_type_keys(), true), 'simcards are not part of aging');

// --- Normalisatie telefoonnummer -------------------------------------------

$same = [
    '0612345678',
    '06 12345678',
    '06-12345678',
    '06-1234 5678',
    '+31612345678',
    '+31 6 1234 5678',
    '+31 (0)6 1234 5678',
    '+31-6-12-34-56-78',
    '0031612345678',
    '0031 6 12345678',
    '31612345678',
    '612345678',
    ' 06.12.34.56.78 ',
];
foreach ($same as $input) {
    expect(moirai_normalize_phone_number($input) === '+31612345678', "normalize '{$input}'");
}
expect(moirai_normalize_phone_number('020-1234567') === '+31201234567', 'normalize landline');
expect(moirai_normalize_phone_number('+32 470 12 34 56') === '+32470123456', 'normalize foreign +32');
expect(moirai_normalize_phone_number('0032 470 12 34 56') === '+32470123456', 'normalize foreign 0032');
expect(moirai_normalize_phone_number('0970 1234 5678') === '+3197012345678', 'normalize NL M2M 097 number');
expect(moirai_normalize_phone_number('') === '', 'empty phone stays empty');
foreach (['abc', '06-1234', '061234567890', '+31 6 1234', '12345', '++31612345678', '06 1234 567x'] as $bad) {
    expect(invalid_message(static fn() => moirai_normalize_phone_number($bad)) === LOC('moirai.error.phone_invalid'), "reject phone '{$bad}'");
}
expect(moirai_phone_number_national('+31612345678') === '0612345678', 'national form');
expect(moirai_normalize_simcard_code(' abc-12 34 ') === 'ABC1234', 'code normalization');

// --- Migratie ----------------------------------------------------------------

$pdo = moirai_db();
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'simcards'")->fetchAll();
expect(count($tables) === 1, 'simcards table created on first connection (legacy DB)');
expect(moirai_get_device('laptop', 'SN-LEGACY') !== null, 'legacy laptop still readable after migration');
moirai_init_schema($pdo);
moirai_init_schema($pdo);
$indexes = array_column($pdo->query("PRAGMA index_list(simcards)")->fetchAll(), 'unique', 'name');
expect(($indexes['simcards_code_norm_unique'] ?? 0) == 1, 'unique index on code_norm');
expect(($indexes['simcards_telefoonnummer_norm_unique'] ?? 0) == 1, 'unique index on telefoonnummer_norm');
expect(true, 'migration is idempotent (ran twice without error)');

// --- Aanmaken / uniciteit ----------------------------------------------------

$sim = moirai_save_device('simcard', [
    'code' => '8931 0400 1234 5678 901',
    'telefoonnummer' => '+31  6 1234 5678',
], $users);
expect($sim['code'] === '8931 0400 1234 5678 901', 'create sim: code kept readable');
expect($sim['id'] === '8931 0400 1234 5678 901', 'create sim: id is code');
expect($sim['telefoonnummer'] === '+31 6 1234 5678', 'create sim: phone shown as entered (spaces collapsed)');
expect($sim['telefoonnummer_norm'] === '+31612345678', 'create sim: normalized phone stored');
expect($sim['naam'] === '+31 6 1234 5678', 'sim display name is phone number');
expect($sim['fysieke_staat'] === MOIRAI_CONDITION_DEFAULT, 'sim default condition');
expect($sim['uitgegeven_aan'] === null, 'new sim is reserve');
expect($sim['verouderd'] === false, 'sim is never verouderd');

$dupPhone = invalid_message(static fn() => moirai_save_device('simcard', ['code' => 'SIM-2', 'telefoonnummer' => '06-12345678'], $users));
expect($dupPhone === 'Telefoonnummer +31 6 1234 5678 is al gekoppeld aan SIM-kaart 8931 0400 1234 5678 901.', 'duplicate phone (06 vs +31) gives Dutch error: ' . (string) $dupPhone);
$dupPhone2 = invalid_message(static fn() => moirai_save_device('simcard', ['code' => 'SIM-2', 'telefoonnummer' => '0031612345678'], $users));
expect($dupPhone2 !== null && str_contains($dupPhone2, 'al gekoppeld aan SIM-kaart'), 'duplicate phone (0031) rejected');
$dupCode = invalid_message(static fn() => moirai_save_device('simcard', ['code' => '8931-0400-1234-5678-901', 'telefoonnummer' => '0687654321'], $users));
expect($dupCode === 'Er bestaat al een SIM-kaart met code 8931 0400 1234 5678 901.', 'duplicate code (dashes) gives Dutch error: ' . (string) $dupCode);
$sim2 = moirai_save_device('simcard', ['code' => 'abc-1', 'telefoonnummer' => '06 87654321'], $users);
$dupCodeCase = invalid_message(static fn() => moirai_save_device('simcard', ['code' => 'ABC1', 'telefoonnummer' => '0611111111'], $users));
expect($dupCodeCase === 'Er bestaat al een SIM-kaart met code abc-1.', 'duplicate code is case-insensitive');
expect(invalid_message(static fn() => moirai_save_device('simcard', ['code' => '', 'telefoonnummer' => '0611111111'], $users)) === 'SIM-code is verplicht.', 'code required');
expect(invalid_message(static fn() => moirai_save_device('simcard', ['code' => 'X', 'telefoonnummer' => ''], $users)) === 'Telefoonnummer is verplicht.', 'phone required');
expect(invalid_message(static fn() => moirai_save_device('simcard', ['code' => 'X', 'telefoonnummer' => '12'], $users)) === LOC('moirai.error.phone_invalid'), 'invalid phone rejected');
expect(count(moirai_list_devices('simcard')) === 2, 'only two sims stored');

// Database dwingt het ook af, los van de PHP-check.
$dbBlocked = false;
try {
    $pdo->prepare("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('RAW', 'RAW', '06 12 34 56 78', '+31612345678')")->execute();
} catch (PDOException $error) {
    $dbBlocked = str_contains($error->getMessage(), 'UNIQUE constraint failed');
}
expect($dbBlocked, 'database unique index blocks duplicate telefoonnummer_norm');
$dbBlocked = false;
try {
    $pdo->prepare("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('Abc-1 ', 'ABC1', 'x', '+31600000000')")->execute();
} catch (PDOException $error) {
    $dbBlocked = str_contains($error->getMessage(), 'UNIQUE constraint failed');
}
expect($dbBlocked, 'database unique index blocks duplicate code_norm');
expect(moirai_unique_violation_message('simcards', ['code' => 'ZZZ', 'telefoonnummer_norm' => '+31687654321']) === 'Telefoonnummer 06 87654321 is al gekoppeld aan SIM-kaart abc-1.', 'DB fallback message names the conflicting sim');

// --- Bewerken ----------------------------------------------------------------

$edited = moirai_save_device('simcard', [
    'original_key' => 'abc-1',
    'code' => 'abc-1',
    'telefoonnummer' => '+31 6 8765 4321',
    'fysieke_staat' => 'netjes',
], $users);
expect($edited['telefoonnummer'] === '+31 6 8765 4321', 'edit own number in other format is allowed');
expect($edited['fysieke_staat'] === 'netjes', 'edit condition');
$editDup = invalid_message(static fn() => moirai_save_device('simcard', ['original_key' => 'abc-1', 'code' => 'abc-1', 'telefoonnummer' => '0612345678'], $users));
expect($editDup !== null && str_contains($editDup, 'al gekoppeld aan SIM-kaart 8931'), 'edit to other sim number rejected');
$editDupCode = invalid_message(static fn() => moirai_save_device('simcard', ['original_key' => 'abc-1', 'code' => '893104001234 5678901', 'telefoonnummer' => '0687654321'], $users));
expect($editDupCode !== null && str_contains($editDupCode, 'Er bestaat al een SIM-kaart met code'), 'rename to existing code rejected');
$caseRename = moirai_save_device('simcard', ['original_key' => 'abc-1', 'code' => 'ABC-1', 'telefoonnummer' => '0687654321'], $users);
expect($caseRename['code'] === 'ABC-1', 'case-only rename of code is saved');
expect(moirai_get_device('simcard', 'abc-1') === null, 'old-case key gone after case-only rename');
$renamed = moirai_save_device('simcard', ['original_key' => 'ABC-1', 'code' => 'SIM-0002', 'telefoonnummer' => '0687654321'], $users);
expect($renamed['code'] === 'SIM-0002' && moirai_get_device('simcard', 'ABC-1') === null, 'rename code moves the record');

// Bestaande tabbladen: bewerken met dezelfde sleutel en hoofdletter-hernoemen blijven werken.
$laptop = moirai_save_device('laptop', ['model' => 'ThinkPad T14', 'serienummer' => 'sn-case-1', 'os' => 'Windows', 'aanschafdatum' => '2025-02-01'], $users);
$laptopEdit = moirai_save_device('laptop', ['original_key' => 'sn-case-1', 'model' => 'ThinkPad T14 G5', 'serienummer' => 'sn-case-1', 'os' => 'Windows', 'aanschafdatum' => '2025-02-01'], $users);
expect($laptopEdit['model'] === 'ThinkPad T14 G5', 'laptop in-place edit still works');
$laptopCase = moirai_save_device('laptop', ['original_key' => 'sn-case-1', 'model' => 'ThinkPad T14 G5', 'serienummer' => 'SN-CASE-1', 'os' => 'Windows', 'aanschafdatum' => '2025-02-01'], $users);
expect($laptopCase['serienummer'] === 'SN-CASE-1', 'laptop case-only serial rename is saved');
moirai_delete_device('laptop', 'SN-CASE-1');

// --- Toewijzen / historie / retour (zelfde flow als andere middelen) ---------

$assigned = moirai_assign_device('simcard', 'SIM-0002', ['email' => 'user@kvt.nl'], $users);
expect(($assigned['uitgegeven_aan']['email'] ?? '') === 'user@kvt.nl', 'assign sim to user');
expect($assigned['uitgegeven_sinds'] === date('Y-m-d'), 'assign sets uitgegeven_sinds');
expect(moirai_device_status($assigned) === 'assigned', 'sim status assigned');
$reassigned = moirai_assign_device('simcard', 'SIM-0002', ['email' => 'user2@kvt.nl'], $users);
expect(count($reassigned['historie_uitgegeven']) === 1, 'reassign writes history');
expect(($reassigned['historie_uitgegeven'][0]['gebruiker']['email'] ?? '') === 'user@kvt.nl', 'history has previous user');
$returned = moirai_assign_device('simcard', 'SIM-0002', null, $users);
expect($returned['uitgegeven_aan'] === null && count($returned['historie_uitgegeven']) === 2, 'return to reserve keeps history');
$unavailable = moirai_assign_device('simcard', 'SIM-0002', ['email' => MOIRAI_UNAVAILABLE_EMAIL], []);
expect(moirai_device_status($unavailable) === 'unavailable', 'sim can be marked unavailable');
moirai_assign_device('simcard', 'SIM-0002', null, []);
expect(invalid_message(static fn() => moirai_assign_device('simcard', 'SIM-0002', ['email' => 'stranger@kvt.nl'], $users)) === LOC('moirai.error.assign_invalid_user'), 'assign unknown user rejected');
$afterEdit = moirai_save_device('simcard', ['original_key' => 'SIM-0002', 'code' => 'SIM-0002', 'telefoonnummer' => '0687654321'], $users);
expect(count($afterEdit['historie_uitgegeven']) === 3, 'edit keeps assignment history');

// --- Zoeken en filteren ------------------------------------------------------

moirai_assign_device('simcard', '8931 0400 1234 5678 901', ['email' => 'user@kvt.nl'], $users);
$all = moirai_list_devices('simcard');
$find = static fn(string $q, string $status = 'all', array $attrs = []): array => array_values(array_map(
    static fn(array $d): string => $d['code'],
    array_filter($all, static fn(array $d): bool => moirai_device_matches_filter($d, $q, $status, $attrs))
));
expect($find('0612345678') === ['8931 0400 1234 5678 901'], 'search national number finds +31 notation');
expect($find('+31612345678') === ['8931 0400 1234 5678 901'], 'search E.164 finds sim');
expect($find('6 1234') === ['8931 0400 1234 5678 901'], 'search on displayed fragments');
expect($find('sim-0002') === ['SIM-0002'], 'search by code');
expect($find('Test User') === ['8931 0400 1234 5678 901'], 'search by assignee');
expect($find('', 'reserve') === ['SIM-0002'], 'status filter reserve');
expect($find('', 'assigned') === ['8931 0400 1234 5678 901'], 'status filter assigned');
expect(count($find('', 'all', ['fysieke_staat' => 'uitstekend'])) === 2, 'attribute filter fysieke_staat matches');
expect($find('', 'all', ['fysieke_staat' => 'beschadigd']) === [], 'attribute filter fysieke_staat excludes');
$filters = moirai_get_filter_options('simcard');
expect(array_keys($filters) === ['fysieke_staat'], 'simcard filter options');

// --- Printen uitgesloten -----------------------------------------------------

expect(moirai_type_can_print_label('simcard') === false, 'simcard cannot print label');
expect(moirai_type_can_print_label('laptop') && moirai_type_can_print_label('phone') && moirai_type_can_print_label('accessory'), 'other types can still print');
require_once __DIR__ . '/../web/moirai_print.php';
$printErr = invalid_message(static fn() => moirai_build_device_pos_document(moirai_get_device('simcard', 'SIM-0002') ?? [], 'simcard'));
expect($printErr === 'Voor SIM-kaarten kan geen label geprint worden.', 'pos document refused for sim');
$laptopPos = moirai_build_device_pos_document(moirai_get_device('laptop', 'SN-LEGACY') ?? [], 'laptop');
expect(str_contains($laptopPos['body'], 'ThinkPad'), 'laptop pos document still works');
foreach (['label_pos', 'print_label', 'get_pos', 'pos'] as $printAction) {
    $res = dispatch($printAction, ['type' => 'simcard', 'id' => 'SIM-0002']);
    expect($res['status'] === 400 && ($res['body']['error_code'] ?? '') === 'print_not_supported', "API {$printAction} refuses sim");
}
$laptopLabel = dispatch('label_pos', ['type' => 'laptop', 'id' => 'SN-LEGACY']);
expect(($laptopLabel['body']['ok'] ?? false) === true, 'API label_pos still works for laptop');

// --- API ---------------------------------------------------------------------

$help = moirai_api_help();
expect(in_array('simcard', $help['types'], true), 'help lists simcard type');
$lookups = dispatch('lookups', [], []);
expect(in_array('simcard', array_column($lookups['body']['types'] ?? [], 'type'), true), 'lookups lists simcard');
expect(!in_array('simcard', $lookups['body']['label_types'] ?? ['simcard'], true), 'lookups: simcard not a label type');
$created = dispatch('create', ['type' => 'simcard', 'code' => 'API-SIM-1', 'telefoonnummer' => '06 11 22 33 44']);
expect($created['status'] === 201 && ($created['body']['device']['code'] ?? '') === 'API-SIM-1', 'API create simcard');
$apiDup = dispatch('create', ['type' => 'sim', 'code' => 'API-SIM-2', 'telefoonnummer' => '+31611223344']);
expect($apiDup['status'] === 400 && str_contains((string) ($apiDup['body']['error'] ?? ''), 'al gekoppeld aan SIM-kaart API-SIM-1'), 'API duplicate phone 400 with Dutch error');
$apiUpdate = dispatch('update', ['type' => 'simcard', 'id' => 'API-SIM-1', 'fysieke_staat' => 'beschadigd']);
expect(($apiUpdate['body']['device']['fysieke_staat'] ?? '') === 'beschadigd' && ($apiUpdate['body']['device']['telefoonnummer'] ?? '') === '06 11 22 33 44', 'API partial update keeps phone');
$apiAssign = dispatch('assign', ['type' => 'simcard', 'id' => 'API-SIM-1', 'uitgegeven_email' => 'user@kvt.nl']);
expect(($apiAssign['body']['device']['uitgegeven_aan']['email'] ?? '') === 'user@kvt.nl', 'API assign simcard');
$apiList = dispatch('list', [], ['type' => 'simcard', 'q' => '0611223344']);
expect(($apiList['body']['count'] ?? 0) === 1, 'API list search by national number');
$apiDelete = dispatch('delete', ['type' => 'simcard', 'id' => 'API-SIM-1']);
expect(($apiDelete['body']['ok'] ?? false) === true && moirai_get_device('simcard', 'API-SIM-1') === null, 'API delete simcard');

// --- Nightly / aging raakt SIM's niet ----------------------------------------

$mails = [];
$result = moirai_run_aging_alerts(static function (array $device, string $type) use (&$mails): bool {
    $mails[] = $type;
    return true;
});
expect(!in_array('simcard', $mails, true), 'nightly never mails about sims');
foreach ($result['devices'] as $entry) {
    expect(($entry['type'] ?? '') !== 'simcard', 'nightly result has no sims');
}
expect(moirai_get_device('simcard', 'SIM-0002')['uitgegeven_aan'] === null, 'nightly leaves reserve sim untouched');

// --- Deep link ---------------------------------------------------------------

$_GET = ['t' => 's', 'd' => 'SIM-0002'];
$_SERVER['REQUEST_URI'] = '/moirai/index.php?t=s&d=SIM-0002';
$link = moirai_parse_deep_link_from_request();
expect(($link['type'] ?? '') === 'simcard' && ($link['deviceId'] ?? '') === 'SIM-0002', 'deep link t=s resolves to simcard');

// --- Verwijderen -------------------------------------------------------------

moirai_delete_device('simcard', 'SIM-0002');
expect(moirai_get_device('simcard', 'SIM-0002') === null, 'delete sim');
$reuse = moirai_save_device('simcard', ['code' => 'SIM-0003', 'telefoonnummer' => '0687654321'], $users);
expect($reuse['telefoonnummer_norm'] === '+31687654321', 'number is free again after delete');

$_GET = [];
unset($_SERVER['REQUEST_URI']);
@unlink($tmp);
@unlink($tmp . '-wal');
@unlink($tmp . '-shm');

if ($failures > 0) {
    echo "\n{$failures} failure(s)\n";
    exit(1);
}

echo "\nall simcard tests passed\n";
