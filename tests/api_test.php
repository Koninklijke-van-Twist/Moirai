<?php

/**
 * CLI checks for the key-authenticated device API. Run: php tests/api_test.php
 */

declare(strict_types=1);

const MOIRAI_API_TEST_ONLY_KEY = 'MOIRAI_API_TEST_ONLY_KEY';
const MOIRAI_API_TEST_ONLY_LABEL = 'testOnlyKey';

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

$tmp = sys_get_temp_dir() . '/moirai_api_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$GLOBALS['moirai_db_file'] = $tmp;
$GLOBALS['apiKeys'] = [
    MOIRAI_API_TEST_ONLY_LABEL => MOIRAI_API_TEST_ONLY_KEY,
];
$GLOBALS['moirai_api_directory_users'] = [
    ['Id' => 'u1', 'id' => 'u1', 'Naam' => 'Test User', 'naam' => 'Test User', 'Email' => 'user@kvt.nl', 'email' => 'user@kvt.nl'],
];

require_once __DIR__ . '/../web/moirai_api.php';

function reset_request(): void
{
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_X_API_KEY'], $_SERVER['HTTP_AUTHORIZATION']);
}

function dispatch_ok(string $action, array $post = [], array $get = []): array
{
    reset_request();
    $_GET = $get;
    $_POST = $post;
    $_SERVER['HTTP_X_API_KEY'] = MOIRAI_API_TEST_ONLY_KEY;
    moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
    $result = moirai_api_dispatch($action);
    expect(($result['body']['ok'] ?? false) === true, $action . ' ok');
    return $result;
}

reset_request();
expect(moirai_api_request_api_key() === '', 'no key presented');

$_SERVER['HTTP_X_API_KEY'] = MOIRAI_API_TEST_ONLY_KEY;
expect(moirai_api_request_api_key() === MOIRAI_API_TEST_ONLY_KEY, 'X-API-Key header');
expect((moirai_api_authenticate()['label'] ?? '') === MOIRAI_API_TEST_ONLY_LABEL, 'X-API-Key authenticates');

reset_request();
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . MOIRAI_API_TEST_ONLY_KEY;
expect(moirai_api_request_api_key() === MOIRAI_API_TEST_ONLY_KEY, 'Authorization Bearer');
expect((moirai_api_authenticate()['label'] ?? '') === MOIRAI_API_TEST_ONLY_LABEL, 'Bearer authenticates');

reset_request();
$_POST['api_key'] = MOIRAI_API_TEST_ONLY_KEY;
expect(moirai_api_request_api_key() === MOIRAI_API_TEST_ONLY_KEY, 'POST body api_key');

reset_request();
$_GET = ['action' => 'help', 'api_key' => MOIRAI_API_TEST_ONLY_KEY];
expect(moirai_api_request_api_key() === '', 'querystring api_key is ignored');
expect(moirai_api_query_has_api_key() === true, 'querystring api_key is detected');
expect(moirai_api_authenticate() === null, 'querystring-only key does not authenticate');
$helpQueryReject = moirai_api_query_key_rejection();
expect(($helpQueryReject['status'] ?? 0) === 401, 'querystring api_key rejected on help');
expect(($helpQueryReject['body']['error_code'] ?? '') === 'api_key_query', 'querystring help error_code');

reset_request();
$_GET = ['action' => 'help', 'api_key' => MOIRAI_API_TEST_ONLY_KEY];
$_SERVER['HTTP_X_API_KEY'] = MOIRAI_API_TEST_ONLY_KEY;
$headerAndQueryReject = moirai_api_query_key_rejection();
expect(moirai_api_query_has_api_key() === true, 'query api_key is still detected when a header key is also present');
expect(($headerAndQueryReject['status'] ?? 0) === 401, 'querystring api_key rejected even with header');
expect(($headerAndQueryReject['body']['error_code'] ?? '') === 'api_key_query', 'header+query error_code');

reset_request();
$_GET = ['action' => 'help'];
expect(moirai_api_query_key_rejection() === null, 'help without query key is allowed');

reset_request();
$_SERVER['HTTP_X_API_KEY'] = 'wrong-key';
expect(moirai_api_authenticate() === null, 'wrong key rejected');

reset_request();
$GLOBALS['apiKeys']['voorbeeldKey'] = 'REPLACE_WITH_A_RANDOM_API_KEY';
$_SERVER['HTTP_X_API_KEY'] = 'REPLACE_WITH_A_RANDOM_API_KEY';
expect(moirai_api_authenticate() === null, 'example placeholder is not a working key');
unset($GLOBALS['apiKeys']['voorbeeldKey']);

$help = moirai_api_help();
$actionNames = array_map(static fn(array $row): string => $row['name'], $help['actions']);
foreach (['list', 'get', 'create', 'update', 'save', 'assign', 'set_condition', 'delete', 'verify_qr', 'users', 'print_label', 'label_pos'] as $required) {
    expect(in_array($required, $actionNames, true), 'spec lists ' . $required);
}
expect(str_contains((string) ($help['practice']['lenovo_model'] ?? ''), 'ThinkBook 14 2-in-1 G6 IPL'), 'spec documents Lenovo model naming');
expect(str_contains((string) ($help['practice']['laptop_create'] ?? ''), 'ram'), 'spec documents filling optional laptop fields');

$create = dispatch_ok('create', [
    'type' => 'laptop',
    'model' => 'ThinkPad API',
    'serienummer' => 'SN-API-1',
    'os' => 'Windows',
    'ram' => '16 GB',
    'fysieke_staat' => 'uitstekend',
    'verouderd' => 1,
    'is_admin' => true,
    'qr_geldig' => 1,
]);
$device = $create['body']['device'] ?? [];
expect(($device['serienummer'] ?? '') === 'SN-API-1', 'create stores serial');
expect(empty($device['verouderd']), 'create ignores client verouderd flag');
expect(empty($device['qr_geldig']), 'create ignores client qr_geldig flag');
expect(($device['fysieke_staat'] ?? '') === 'uitstekend', 'create stores condition');

$update = dispatch_ok('update', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
    'ram' => '32 GB',
]);
expect(($update['body']['device']['ram'] ?? '') === '32 GB', 'update merges ram');
expect(($update['body']['device']['model'] ?? '') === 'ThinkPad API', 'update keeps model');

$condition = dispatch_ok('set_condition', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
    'fysieke_staat' => 'beschadigd',
]);
expect(($condition['body']['device']['fysieke_staat'] ?? '') === 'beschadigd', 'set_condition updates fysieke staat');

$assigned = dispatch_ok('assign', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
    'uitgegeven_email' => 'user@kvt.nl',
]);
expect(($assigned['body']['device']['uitgegeven_aan']['email'] ?? '') === 'user@kvt.nl', 'assign sets directory user');
expect(moirai_device_status($assigned['body']['device']) === 'assigned', 'assign status is assigned');

$reserve = dispatch_ok('set_reserve', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
]);
expect(moirai_device_status($reserve['body']['device']) === 'reserve', 'set_reserve clears assignee');

$unavailable = dispatch_ok('set_unavailable', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
]);
expect(moirai_device_status($unavailable['body']['device']) === 'unavailable', 'set_unavailable marks unavailable');

$listed = dispatch_ok('list', [], [
    'type' => 'laptop',
    'status' => 'unavailable',
    'fysieke_staat' => 'beschadigd',
]);
expect(($listed['body']['count'] ?? 0) === 1, 'list filters status + condition');

reset_request();
$_POST = ['type' => 'laptop', 'os' => ['Windows', 'macOS']];
moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
$filterCaught = false;
try {
    moirai_api_dispatch('list');
} catch (InvalidArgumentException $error) {
    $filterCaught = true;
    $mapped = moirai_api_from_invalid_argument($error);
    expect($mapped['status'] === 400, 'array filter is 400');
    expect(($mapped['body']['error_code'] ?? '') === 'invalid_input', 'array filter error_code');
}
expect($filterCaught, 'array filter is rejected');

reset_request();
$_POST = ['type' => 'laptop', 'id' => 'SN-MISSING'];
moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
$assignMissing = false;
try {
    moirai_api_dispatch('assign');
} catch (InvalidArgumentException $error) {
    $assignMissing = true;
    $mapped = moirai_api_from_invalid_argument($error);
    expect($mapped['status'] === 404, 'assign missing device is 404');
    expect(($mapped['body']['error_code'] ?? '') === 'device_not_found', 'assign missing error_code');
}
expect($assignMissing, 'assign missing device throws');

reset_request();
$_POST = ['type' => 'laptop', 'id' => 'SN-API-1', 'uitgegeven_email' => 'nobody@kvt.nl'];
moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
$caught = false;
try {
    moirai_api_dispatch('assign');
} catch (InvalidArgumentException $error) {
    $caught = true;
    expect($error->getMessage() !== '', 'invalid assign throws validation error');
}
expect($caught, 'invalid assign is rejected');

$got = dispatch_ok('get', [], ['type' => 'laptop', 'id' => 'SN-API-1']);
expect(($got['body']['device']['id'] ?? '') === 'SN-API-1', 'get returns device');

$pos = dispatch_ok('label_pos', [], ['type' => 'laptop', 'id' => 'SN-API-1']);
$posDoc = $pos['body']['pos'] ?? [];
expect(($pos['body']['filename'] ?? '') === 'SN-API-1.pos', 'label_pos filename');
expect(($posDoc['version'] ?? 0) === 1, 'label_pos version');
expect(isset($posDoc['metadata']['title']), 'label_pos metadata title');
expect(is_string($posDoc['body'] ?? null) && str_contains((string) $posDoc['body'], 'ThinkPad API'), 'label_pos body has device name');
expect(str_contains((string) $posDoc['body'], '@qr '), 'label_pos body has QR');
expect(str_starts_with((string) ($pos['body']['url'] ?? ''), 'posprint://print?v=1&d='), 'label_pos url is posprint://');
expect(str_contains((string) ($pos['body']['url'] ?? ''), 'noconfirm=1'), 'label_pos url has noconfirm');
$aliasPos = dispatch_ok('get_pos', [], ['type' => 'laptop', 'id' => 'SN-API-1']);
expect(($aliasPos['body']['pos']['version'] ?? 0) === 1, 'get_pos alias works');
expect(str_starts_with((string) ($aliasPos['body']['url'] ?? ''), 'posprint://print?'), 'get_pos also returns url');
$printLabel = dispatch_ok('print_label', ['type' => 'laptop', 'id' => 'SN-API-1']);
expect(isset($printLabel['body']['pos']['body']), 'print_label returns pos document');
expect(str_starts_with((string) ($printLabel['body']['url'] ?? ''), 'posprint://print?'), 'print_label returns url');
reset_request();
$_GET = ['type' => 'laptop', 'id' => 'SN-API-1', 'download' => '1'];
$_SERVER['REQUEST_METHOD'] = 'GET';
moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
$download = moirai_api_dispatch('label_pos');
expect(isset($download['download']['content']), 'label_pos download payload');
expect(str_contains((string) ($download['download']['filename'] ?? ''), '.pos'), 'label_pos download filename');

$qr = dispatch_ok('verify_qr', ['type' => 'laptop', 'id' => 'SN-API-1']);
expect(!empty($qr['body']['device']['qr_geldig']), 'verify_qr sets qr_geldig');

$note = dispatch_ok('notes_add', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
    'message' => 'API note',
]);
$noteId = (int) ($note['body']['message']['id'] ?? 0);
expect($noteId > 0, 'notes_add stores message');

$notes = dispatch_ok('notes_list', [], ['type' => 'laptop', 'id' => 'SN-API-1']);
expect(count($notes['body']['messages'] ?? []) === 1, 'notes_list returns the note');

dispatch_ok('create', [
    'type' => 'laptop',
    'model' => 'ThinkPad Other',
    'serienummer' => 'SN-API-2',
]);

reset_request();
$_POST = [
    'type' => 'laptop',
    'id' => 'SN-API-2',
    'message_id' => (string) $noteId,
    'message' => 'cross-device edit',
];
moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
$crossEdit = moirai_api_dispatch('notes_edit');
expect(($crossEdit['status'] ?? 0) === 404, 'cross-device note edit is 404');
expect(($crossEdit['body']['error_code'] ?? '') === 'note_not_found', 'cross-device note edit error_code');

$edited = dispatch_ok('notes_edit', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
    'message_id' => (string) $noteId,
    'message' => 'API note edited',
]);
expect(($edited['body']['message']['message_text'] ?? '') === 'API note edited', 'notes_edit updates matching thread');

reset_request();
$_POST = [
    'type' => 'laptop',
    'id' => 'SN-API-2',
    'message_id' => (string) $noteId,
];
moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
$crossDelete = moirai_api_dispatch('notes_delete');
expect(($crossDelete['status'] ?? 0) === 404, 'cross-device note delete is 404');
expect(($crossDelete['body']['error_code'] ?? '') === 'note_not_found', 'cross-device note delete error_code');

$stillThere = dispatch_ok('notes_list', [], ['type' => 'laptop', 'id' => 'SN-API-1']);
expect(count($stillThere['body']['messages'] ?? []) === 1, 'cross-device delete leaves the note');

dispatch_ok('notes_delete', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
    'message_id' => (string) $noteId,
]);

reset_request();
$_POST = [
    'type' => 'laptop',
    'id' => 'SN-API-1',
    'message_id' => (string) $noteId,
];
moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
$missingNote = moirai_api_dispatch('notes_delete');
expect(($missingNote['status'] ?? 0) === 404, 'missing note delete is 404');
expect(($missingNote['body']['error_code'] ?? '') === 'note_not_found', 'missing note delete error_code');

$phone = dispatch_ok('create', [
    'type' => 'phone',
    'model' => 'Pixel API',
    'imei' => '350000000000001',
    'os' => 'Android',
    'fysieke_staat' => 'netjes',
]);
expect(($phone['body']['device']['imei'] ?? '') === '350000000000001', 'create phone');

$accessory = dispatch_ok('create', [
    'type' => 'accessory',
    'naam' => 'USB-C hub',
    'modelnummer' => 'HUB-1',
    'fysieke_staat' => 'uitstekend',
]);
$accId = (string) ($accessory['body']['device']['id'] ?? '');
expect($accId !== '', 'create accessory assigns id');

reset_request();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['type' => 'laptop', 'model' => 'Nope', 'serienummer' => 'SN-GET'];
$getCreate = moirai_api_dispatch('create');
expect(($getCreate['status'] ?? 0) === 405, 'GET create is 405');
expect(($getCreate['body']['error_code'] ?? '') === 'method_not_allowed', 'GET create error_code');

reset_request();
$_POST = ['type' => 'laptop'];
$unknown = moirai_api_dispatch('explode');
expect(($unknown['status'] ?? 0) === 400, 'unknown action is 400');
expect(($unknown['body']['error_code'] ?? '') === 'unknown_action', 'unknown action error_code');

dispatch_ok('delete', ['type' => 'laptop', 'id' => 'SN-API-1']);
$missing = dispatch_ok('list', [], ['type' => 'laptop', 'q' => 'SN-API-1']);
expect(($missing['body']['count'] ?? 1) === 0, 'delete removes laptop');

reset_request();
$_POST = ['type' => 'laptop', 'id' => 'SN-API-1'];
$gone = moirai_api_dispatch('get');
expect(($gone['status'] ?? 0) === 404, 'get after delete is 404');

reset_request();
$_POST = ['type' => 'laptop', 'id' => 'SN-API-1'];
moirai_api_apply_actor(MOIRAI_API_TEST_ONLY_LABEL);
$deleteMissing = false;
try {
    moirai_api_dispatch('delete');
} catch (InvalidArgumentException $error) {
    $deleteMissing = true;
    $mapped = moirai_api_from_invalid_argument($error);
    expect($mapped['status'] === 404, 'delete missing device is 404');
    expect(($mapped['body']['error_code'] ?? '') === 'device_not_found', 'delete missing error_code');
}
expect($deleteMissing, 'delete missing device throws');

@unlink($tmp);
@unlink($tmp . '-wal');
@unlink($tmp . '-shm');

if ($failures > 0) {
    echo "\n{$failures} failure(s)\n";
    exit(1);
}

echo "\nall tests passed\n";
