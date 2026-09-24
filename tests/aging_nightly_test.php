<?php

/**
 * CLI checks for aging-device nightly behaviour. Run: php tests/aging_nightly_test.php
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

$tmp = sys_get_temp_dir() . '/moirai_aging_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$GLOBALS['moirai_db_file'] = $tmp;
$GLOBALS['moirai_today'] = new DateTimeImmutable('2026-09-15');

require_once __DIR__ . '/../web/moirai_data.php';

$mails = [];
$mailShouldFail = false;
$GLOBALS['moirai_mail_sender'] = static function (array $device, string $type) use (&$mails, &$mailShouldFail): bool {
    if ($mailShouldFail) {
        return false;
    }
    $mails[] = [
        'type' => $type,
        'id' => (string) ($device['id'] ?? ''),
        'model' => (string) ($device['model'] ?? ''),
    ];
    return true;
};

function seed_laptop(string $serial, string $date, bool $assign = false, bool $unavailable = false, string $os = 'Windows'): array
{
    $saved = moirai_save_device('laptop', [
        'model' => 'ThinkPad ' . $serial,
        'serienummer' => $serial,
        'aanschafdatum' => $date,
        'os' => $os,
        'fysieke_staat' => 'netjes',
    ], [], false);

    if ($unavailable) {
        return moirai_assign_device('laptop', $serial, ['email' => MOIRAI_UNAVAILABLE_EMAIL], []);
    }
    if ($assign) {
        $user = ['id' => 'u1', 'naam' => 'Test User', 'email' => 'user@kvt.nl'];
        return moirai_assign_device('laptop', $serial, $user, [$user]);
    }

    return $saved;
}

function seed_phone(string $imei, string $date, bool $assign = false): array
{
    $saved = moirai_save_device('phone', [
        'model' => 'Pixel ' . $imei,
        'imei' => $imei,
        'aanschafdatum' => $date,
        'os' => 'Android',
        'fysieke_staat' => 'netjes',
    ], [], false);

    if ($assign) {
        $user = ['id' => 'u1', 'naam' => 'Test User', 'email' => 'user@kvt.nl'];
        return moirai_assign_device('phone', $imei, $user, [$user]);
    }

    return $saved;
}

seed_laptop('OLD-ASSIGNED', '2021-11-15', true);
seed_laptop('OLD-RESERVE', '2020-01-01', false);
seed_laptop('YOUNG-ASSIGNED', '2025-01-01', true);
seed_laptop('NO-DATE-ASSIGNED', '', true);
seed_phone('OLD-PHONE', '2021-11-15', true);
moirai_save_device('accessory', [
    'naam' => 'Muis',
    'modelnummer' => 'MX3',
    'aanschafdatum' => '2018-01-01',
    'fysieke_staat' => 'netjes',
], [], false);

$first = moirai_run_aging_alerts();
expect($first['mailed'] === 2, 'first run mails assigned laptop + phone');
expect($first['marked_unavailable'] === 1, 'first run marks reserve laptop unavailable');
expect($first['flagged'] === 3, 'first run flags three aging devices');
expect(count($mails) === 2, 'ICT mail sent twice');
$mailedIds = array_column($mails, 'id');
sort($mailedIds);
expect($mailedIds === ['OLD-ASSIGNED', 'OLD-PHONE'], 'mail only for assigned aging devices');

$assigned = moirai_get_device('laptop', 'OLD-ASSIGNED');
expect(!empty($assigned['verouderd']), 'assigned laptop listing flag on');
expect(!empty($assigned['verouderd_alert_verzonden']), 'assigned laptop alert flag on');
expect(moirai_device_status($assigned) === 'assigned', 'assigned laptop stays assigned');

$reserve = moirai_get_device('laptop', 'OLD-RESERVE');
expect(!empty($reserve['verouderd']), 'reserve laptop listing flag on');
expect(!empty($reserve['verouderd_alert_verzonden']), 'reserve laptop processed without mail');
expect(moirai_device_status($reserve) === 'unavailable', 'reserve laptop becomes unavailable');

$young = moirai_get_device('laptop', 'YOUNG-ASSIGNED');
expect(empty($young['verouderd']), 'young laptop not flagged');

$accessory = moirai_get_device('accessory', 'kvt-acc-000001');
expect($accessory !== null, 'accessory saved');
expect(empty($accessory['verouderd']), 'accessory not flagged');

$exact = moirai_device_is_aging(['aanschafdatum' => '2021-11-15'], $GLOBALS['moirai_today']);
$tooYoung = moirai_device_is_aging(['aanschafdatum' => '2021-11-16'], $GLOBALS['moirai_today']);
expect($exact, 'exactly 4y10m is aging');
expect(!$tooYoung, 'one day under 4y10m is not aging');

$linuxAtBase = moirai_device_is_aging(['aanschafdatum' => '2021-11-15', 'os' => 'Linux'], $GLOBALS['moirai_today']);
$linuxTooYoung = moirai_device_is_aging(['aanschafdatum' => '2017-01-16', 'os' => 'Linux'], $GLOBALS['moirai_today']);
$linuxExact = moirai_device_is_aging(['aanschafdatum' => '2017-01-15', 'os' => 'linux'], $GLOBALS['moirai_today']);
$osxAtBase = moirai_device_is_aging(['aanschafdatum' => '2021-11-15', 'os' => 'OSX'], $GLOBALS['moirai_today']);
expect(!$linuxAtBase, 'linux at 4y10m is not aging');
expect(!$linuxTooYoung, 'linux one day under 9y8m is not aging');
expect($linuxExact, 'linux at exactly 9y8m is aging');
expect($osxAtBase, 'osx at 4y10m stays aging');

$mails = [];
$second = moirai_run_aging_alerts();
expect($second['mailed'] === 0, 'second run does not re-mail');
expect($second['marked_unavailable'] === 0, 'second run does not re-mark unavailable');
expect($second['flagged'] === 0, 'second run does not re-flag');
expect($mails === [], 'idempotent: no extra ICT mail');

$mailShouldFail = true;
moirai_save_device('laptop', [
    'model' => 'FailPad',
    'serienummer' => 'MAIL-FAIL',
    'aanschafdatum' => '2019-06-01',
    'os' => 'Windows',
    'fysieke_staat' => 'netjes',
], [], false);
$user = ['id' => 'u1', 'naam' => 'Test User', 'email' => 'user@kvt.nl'];
moirai_assign_device('laptop', 'MAIL-FAIL', $user, [$user]);

$failed = moirai_run_aging_alerts();
expect($failed['mail_failed'] === 1, 'mail failure is counted');
expect($failed['mailed'] === 0, 'failed mail is not counted as sent');
$failDevice = moirai_get_device('laptop', 'MAIL-FAIL');
expect(!empty($failDevice['verouderd']), 'listing flag set even if mail fails');
expect(empty($failDevice['verouderd_alert_verzonden']), 'alert flag waits for successful mail');

$mailShouldFail = false;
$mails = [];
$retry = moirai_run_aging_alerts();
expect($retry['mailed'] === 1, 'mail is retried after failure');
expect(count($mails) === 1, 'retry sends one ICT mail');
$failDevice = moirai_get_device('laptop', 'MAIL-FAIL');
expect(!empty($failDevice['verouderd_alert_verzonden']), 'alert flag set after successful retry');

$corrected = moirai_save_device('laptop', [
    'original_key' => 'OLD-ASSIGNED',
    'id' => 'OLD-ASSIGNED',
    'model' => 'ThinkPad OLD-ASSIGNED',
    'serienummer' => 'OLD-ASSIGNED',
    'aanschafdatum' => '2025-01-01',
    'os' => 'Windows',
    'fysieke_staat' => 'netjes',
], [], false);
expect(empty($corrected['verouderd']), 'correcting purchase date to recent clears listing flag');
expect(empty($corrected['verouderd_alert_verzonden']), 'correcting purchase date to recent clears alert flag');

$mails = [];
$afterCorrection = moirai_run_aging_alerts();
expect($afterCorrection['mailed'] === 0, 'recent purchase date does not re-mail');
$corrected = moirai_get_device('laptop', 'OLD-ASSIGNED');
expect(empty($corrected['verouderd']), 'recent purchase stays unflagged after nightly');

$reAged = moirai_save_device('laptop', [
    'original_key' => 'OLD-ASSIGNED',
    'id' => 'OLD-ASSIGNED',
    'model' => 'ThinkPad OLD-ASSIGNED',
    'serienummer' => 'OLD-ASSIGNED',
    'aanschafdatum' => '2020-01-01',
    'os' => 'Windows',
    'fysieke_staat' => 'netjes',
], [], false);
expect(empty($reAged['verouderd']), 'flags stay clear until nightly after becoming old again');
$mails = [];
$reAgedRun = moirai_run_aging_alerts();
expect($reAgedRun['mailed'] === 1, 'aging again after date correction sends one new alert');
$reAged = moirai_get_device('laptop', 'OLD-ASSIGNED');
expect(!empty($reAged['verouderd']), 'listing flag set again after becoming old');
expect(!empty($reAged['verouderd_alert_verzonden']), 'alert flag set again after becoming old');

seed_laptop('LINUX-BASE', '2021-11-15', true, false, 'Linux');
seed_laptop('LINUX-DOUBLE', '2017-01-15', true, false, 'Linux');
$mails = [];
$linuxRun = moirai_run_aging_alerts();
expect($linuxRun['mailed'] === 1, 'linux nightly mails only the doubled-threshold device');
$linuxMailedIds = array_column($mails, 'id');
expect($linuxMailedIds === ['LINUX-DOUBLE'], 'linux mail is the 9y8m laptop');
$linuxBase = moirai_get_device('laptop', 'LINUX-BASE');
expect(empty($linuxBase['verouderd']), 'linux at 4y10m stays unflagged');
$linuxDouble = moirai_get_device('laptop', 'LINUX-DOUBLE');
expect(!empty($linuxDouble['verouderd']), 'linux at 9y8m is flagged');

$linuxCorrected = moirai_save_device('laptop', [
    'original_key' => 'LINUX-DOUBLE',
    'id' => 'LINUX-DOUBLE',
    'model' => 'ThinkPad LINUX-DOUBLE',
    'serienummer' => 'LINUX-DOUBLE',
    'aanschafdatum' => '2021-11-15',
    'os' => 'Linux',
    'fysieke_staat' => 'netjes',
], [], false);
expect(empty($linuxCorrected['verouderd']), 'linux date corrected to 4y10m clears listing flag');
expect(empty($linuxCorrected['verouderd_alert_verzonden']), 'linux date corrected to 4y10m clears alert flag');

@unlink($tmp);
@unlink(dirname($tmp) . '/aging_nightly.lock');

if ($failures > 0) {
    echo "\n{$failures} failure(s)\n";
    exit(1);
}

echo "\nAll aging nightly checks passed.\n";
exit(0);
