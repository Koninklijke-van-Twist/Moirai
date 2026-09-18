<?php

/**
 * CLI checks for the key-authenticated device API. Run: php tests/api_test.php
 */

declare(strict_types=1);

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
    'voorbeeldKey' => '1234-5678-1234',
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
    $_SERVER['HTTP_X_API_KEY'] = '1234-5678-1234';
    moirai_api_apply_actor('voorbeeldKey');
    $result = moirai_api_dispatch($action);
    expect(($result['body']['ok'] ?? false) === true, $action . ' ok');
    return $result;
}

reset_request();
expect(moirai_api_request_api_key() === '', 'no key presented');

$_SERVER['HTTP_X_API_KEY'] = '1234-5678-1234';
expect(moirai_api_request_api_key() === '1234-5678-1234', 'X-API-Key header');
expect((moirai_api_authenticate()['label'] ?? '') === 'voorbeeldKey', 'X-API-Key authenticates');

reset_request();
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer 1234-5678-1234';
expect(moirai_api_request_api_key() === '1234-5678-1234', 'Authorization Bearer');
expect((moirai_api_authenticate()['label'] ?? '') === 'voorbeeldKey', 'Bearer authenticates');

reset_request();
$_POST['api_key'] = '1234-5678-1234';
expect(moirai_api_request_api_key() === '1234-5678-1234', 'POST body api_key');

reset_request();
$_GET['api_key'] = '1234-5678-1234';
expect(moirai_api_request_api_key() === '', 'querystring api_key is ignored');
expect(moirai_api_query_has_api_key() === true, 'querystring api_key is detected');
expect(moirai_api_authenticate() === null, 'querystring-only key does not authenticate');

reset_request();
$_SERVER['HTTP_X_API_KEY'] = 'wrong-key';
expect(moirai_api_authenticate() === null, 'wrong key rejected');

$help = moirai_api_help();
$actionNames = array_map(static fn(array $row): string => $row['name'], $help['actions']);
foreach (['list', 'get', 'create', 'update', 'save', 'assign', 'set_condition', 'delete', 'verify_qr', 'users', 'print_label', 'label_pos'] as $required) {
    expect(in_array($required, $actionNames, true), 'spec lists ' . $required);
}

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
$_POST = ['type' => 'laptop', 'id' => 'SN-API-1', 'uitgegeven_email' => 'nobody@kvt.nl'];
moirai_api_apply_actor('voorbeeldKey');
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
$aliasPos = dispatch_ok('get_pos', [], ['type' => 'laptop', 'id' => 'SN-API-1']);
expect(($aliasPos['body']['pos']['version'] ?? 0) === 1, 'get_pos alias works');
reset_request();
$_GET = ['type' => 'laptop', 'id' => 'SN-API-1', 'download' => '1'];
$_SERVER['REQUEST_METHOD'] = 'GET';
moirai_api_apply_actor('voorbeeldKey');
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

dispatch_ok('notes_delete', [
    'type' => 'laptop',
    'id' => 'SN-API-1',
    'message_id' => (string) $noteId,
]);

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

@unlink($tmp);
@unlink($tmp . '-wal');
@unlink($tmp . '-shm');

if ($failures > 0) {
    echo "\n{$failures} failure(s)\n";
    exit(1);
}

echo "\nall tests passed\n";
