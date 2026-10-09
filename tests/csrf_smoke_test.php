<?php

/**
 * Rooktest voor CSRF op alle sessie-gebaseerde beheeracties, tegen een echte `php -S`-server.
 * Run: php tests/csrf_smoke_test.php
 *
 * - Kopieert web/ naar een tijdelijke map met een stub-auth.php (verzonnen key, geen secrets)
 *   en een verzonnen gebruikerscache (geen Graph-verbinding nodig).
 * - Localhost geldt in logincheck.php als vertrouwde beheerder (localtester@kvt.nl).
 * - Controleert per endpoint: zonder token 403 (error_code csrf), met fout token 403,
 *   met het token uit <meta name="moirai-csrf"> werkt de bestaande actie nog.
 * - api.php met API-key werkt zonder CSRF-token (Metis/Asclepius).
 */

declare(strict_types=1);

$failures = 0;
function expect(bool $ok, string $message): void
{
    global $failures;
    echo ($ok ? 'ok  ' : 'FAIL  ') . $message . "\n";
    if (!$ok) {
        $failures++;
    }
}

function copy_tree(string $from, string $to): void
{
    mkdir($to, 0777, true);
    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, ['data', 'cache', 'auth.php', 'cfg.php'], true)) {
            continue;
        }
        $src = $from . '/' . $entry;
        is_dir($src) ? copy_tree($src, $to . '/' . $entry) : copy($src, $to . '/' . $entry);
    }
}

function remove_tree(string $dir): void
{
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $path = $dir . '/' . $entry;
            is_dir($path) && !is_link($path) ? remove_tree($path) : @unlink($path);
        }
    }
    @rmdir($dir);
}

$root = sys_get_temp_dir() . '/moirai_csrf_smoke_' . bin2hex(random_bytes(4));
copy_tree(__DIR__ . '/../web', $root);
$smokeKey = 'SMOKE_ONLY_' . bin2hex(random_bytes(8));
file_put_contents($root . '/auth.php', '<?php
// Alleen voor deze rooktest: verzonnen waarden.
$apiKeys = [\'smokeTest\' => ' . var_export($smokeKey, true) . '];
$ictUsers = [\'localtester@kvt.nl\'];
$graphCredentials = [\'tenantId\' => \'smoke\', \'clientId\' => \'smoke\', \'clientSecret\' => \'smoke\'];
$GLOBALS[\'moirai_budget_directory_users\'] = [
    [\'Id\' => \'u1\', \'Naam\' => \'Anna Testpersoon\', \'Email\' => \'anna@kvt.nl\'],
    [\'Id\' => \'u2\', \'Naam\' => \'Bram Voorbeeld\', \'Email\' => \'bram@kvt.nl\'],
];
$GLOBALS[\'moirai_api_directory_users\'] = $GLOBALS[\'moirai_budget_directory_users\'];
');
mkdir($root . '/cache', 0777, true);
file_put_contents($root . '/cache/users_cache_' . date('Y-m-d') . '.json', json_encode([
    ['Id' => 'u1', 'Naam' => 'Anna Testpersoon', 'Email' => 'anna@kvt.nl', 'Telefoonnummer' => null, 'Titel' => 'Tester'],
    ['Id' => 'u2', 'Naam' => 'Bram Voorbeeld', 'Email' => 'bram@kvt.nl', 'Telefoonnummer' => null, 'Titel' => 'Tester'],
]));

$port = random_int(20000, 40000);
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
$base = 'http://127.0.0.1:' . $port . '/';
$cookieJar = $root . '/cookies.txt';

function http(string $method, string $path, $body = null, array $headers = [], bool $cookies = true): array
{
    global $base, $cookieJar;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20]);
    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    if (is_array($body) && !isset($body['__form'])) {
        $body = json_encode($body);
        $headers[] = 'Content-Type: application/json';
    } elseif (is_array($body)) {
        unset($body['__form']);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $json = json_decode($raw, true);

    return ['status' => $status, 'json' => is_array($json) ? $json : null, 'raw' => $raw];
}

try {
    for ($i = 0; $i < 50; $i++) {
        usleep(100000);
        if (@fsockopen('127.0.0.1', $port)) {
            break;
        }
    }

    $page = http('GET', 'index.php');
    preg_match('/<meta name="moirai-csrf" content="([a-f0-9]{64})">/', $page['raw'], $m);
    $token = $m[1] ?? '';
    expect($page['status'] === 200 && $token !== '', 'index.php renders CSRF meta token');
    expect(str_contains($page['raw'], "headers.set('X-CSRF-Token', token)"), 'index.php installs fetch/XHR wrapper');
    $ok = ['X-CSRF-Token: ' . $token];
    $bad = ['X-CSRF-Token: ' . str_repeat('0', 64)];
    $isCsrf = static fn(array $r): bool => $r['status'] === 403 && ($r['json']['error_code'] ?? '') === 'csrf';

    $imei = '35' . str_pad((string) random_int(0, 9999999999999), 13, '0', STR_PAD_LEFT);
    $phone = ['type' => 'phone', 'original_key' => '', 'imei' => $imei, 'model' => 'Rooktestfoon', 'aanschafdatum' => '2025-01-10', 'os' => 'Android'];

    // Zonder / met fout token: alles geweigerd, er verandert niets.
    $mutations = [
        ['devices_api.php?action=save', $phone],
        ['devices_api.php?action=assign', ['type' => 'phone', 'id' => $imei, 'uitgegeven_aan' => 'anna@kvt.nl']],
        ['devices_api.php?action=verify_qr', ['type' => 'phone', 'id' => $imei]],
        ['devices_api.php?action=delete', ['type' => 'phone', 'id' => $imei]],
        ['print_label.php', ['type' => 'phone', 'id' => $imei]],
        ['lib/kvt-chat/api.php?action=add', ['thread_key' => 'phone:' . $imei, 'message_text' => 'x']],
        ['lib/kvt-chat/api.php?action=edit', ['message_id' => 1, 'message_text' => 'x']],
        ['lib/kvt-chat/api.php?action=delete', ['message_id' => 1]],
        ['budget_api.php?action=add_person', ['email' => 'rook@hunter.be', 'indiensttreding' => '2025-01-01']],
        ['budget_api.php?action=save_settings', ['start_cents' => '600', 'monthly_cents' => '25']],
        ['budget_api.php?action=remove_person', ['email' => 'rook@hunter.be']],
    ];
    foreach ($mutations as [$path, $body]) {
        expect($isCsrf(http('POST', $path, $body)), "no token -> 403 csrf: {$path}");
        expect($isCsrf(http('POST', $path, $body, $bad)), "wrong token -> 403 csrf: {$path}");
    }
    $get = http('GET', 'devices_api.php?action=get&type=phone&id=' . $imei);
    expect(($get['json']['ok'] ?? true) === false || $get['status'] >= 400, 'refused save did not create the device');
    expect($isCsrf(http('POST', 'devices_api.php?action=save', $phone + ['__form' => 1, '_csrf' => 'fout'])), 'form field _csrf with wrong value refused');

    // Met token: bestaande acties werken nog.
    $r = http('POST', 'devices_api.php?action=save', $phone, $ok);
    expect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'add device with token works');
    $r = http('POST', 'devices_api.php?action=save', ['original_key' => $imei, 'model' => 'Rooktestfoon 2'] + $phone, $ok);
    expect($r['status'] === 200 && ($r['json']['device']['model'] ?? '') === 'Rooktestfoon 2', 'edit device with token works');
    $r = http('POST', 'devices_api.php?action=assign', ['type' => 'phone', 'id' => $imei, 'uitgegeven_aan' => ['email' => 'anna@kvt.nl', 'naam' => 'Anna Testpersoon', 'id' => 'u1']], $ok);
    expect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'assign device with token works');
    $r = http('POST', 'devices_api.php?action=verify_qr', ['type' => 'phone', 'id' => $imei], $ok);
    expect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'verify_qr with token works');
    $r = http('POST', 'print_label.php', ['type' => 'phone', 'id' => $imei], $ok);
    expect($r['status'] === 200 && str_starts_with((string) ($r['json']['url'] ?? ''), 'posprint://'), 'print label with token works');
    $r = http('POST', 'lib/kvt-chat/api.php?action=add', ['thread_key' => 'phone:' . $imei, 'message_text' => 'Rooktest notitie'], $ok);
    $noteId = (int) ($r['json']['message']['id'] ?? $r['json']['message']['message_id'] ?? 0);
    expect($r['status'] === 200 && $noteId > 0, 'add note with token works');
    $r = http('POST', 'lib/kvt-chat/api.php?action=edit', ['message_id' => $noteId, 'message_text' => 'Rooktest bewerkt'], $ok);
    expect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'edit note with token works');
    $r = http('POST', 'lib/kvt-chat/api.php?action=delete', ['message_id' => $noteId], $ok);
    expect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'delete note with token works');
    $r = http('POST', 'budget_api.php?action=add_person', ['email' => 'rook@hunter.be', 'naam' => 'Rook Extern', 'indiensttreding' => '2025-01-01'], $ok);
    expect($r['status'] === 200 && ($r['json']['person']['email'] ?? '') === 'rook@hunter.be', 'budget add_person with token works');
    $r = http('POST', 'budget_api.php?action=add_purchase', ['email' => 'rook@hunter.be', 'prijs' => '650', 'datum' => '2025-02-01'], $ok);
    expect($r['status'] === 200 && ($r['json']['purchase']['eigen_bijdrage_cents'] ?? -1) === 5000, 'budget add_purchase with token works (own contribution computed)');
    $r = http('POST', 'budget_api.php?action=remove_person', ['email' => 'rook@hunter.be'], $ok);
    expect($r['status'] === 400 && ($r['json']['ok'] ?? true) === false, 'budget remove_person with token refused while purchases exist');
    $r = http('POST', 'budget_api.php?action=add_person', ['email' => 'rook.leeg@hunter.be', 'naam' => 'Rook Leeg'], $ok);
    expect($r['status'] === 200 && ($r['json']['person']['verwijderbaar'] ?? false) === true, 'budget add_person without start date works');
    $r = http('POST', 'budget_api.php?action=remove_person', ['email' => 'rook.leeg@hunter.be'], $ok);
    expect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'budget remove_person with token works');
    $r = http('POST', 'devices_api.php?action=delete', ['type' => 'phone', 'id' => $imei], $ok);
    expect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'delete device with token works');

    // Leesacties blijven zonder token werken.
    expect(http('GET', 'devices_api.php?action=list&type=phone')['status'] === 200, 'GET list without token still works');
    expect(http('GET', 'budget_api.php?action=people')['status'] === 200, 'GET budget people without token still works');

    // API-key (Metis): geen sessie, geen CSRF-token nodig.
    $apiHeaders = ['X-API-Key: ' . $smokeKey];
    $r = http('GET', 'api.php?action=budget_get&email=rook@hunter.be', null, $apiHeaders, false);
    expect($r['status'] === 200 && ($r['json']['email'] ?? '') === 'rook@hunter.be', 'api.php budget_get with API key, no CSRF token');
    $r = http('POST', 'api.php?action=budget_add_purchase', ['__form' => 1, 'email' => 'rook@hunter.be', 'prijs' => '100', 'datum' => '2026-01-01', 'client_ref' => 'rook-1'], $apiHeaders, false);
    expect($r['status'] === 201 && ($r['json']['aankoop']['status'] ?? '') === 'onbevestigd', 'api.php budget_add_purchase with API key, no CSRF token');
    $r = http('POST', 'api.php?action=budget_add_purchase', ['__form' => 1, 'email' => 'rook@hunter.be', 'prijs' => '100'], ['Authorization: Bearer ' . $smokeKey], false);
    expect($r['status'] === 201, 'api.php with Bearer key, no CSRF token');
    $r = http('GET', 'api.php?action=budget_get&email=rook@hunter.be', null, [], false);
    expect($r['status'] === 401, 'api.php without key still refused');
} finally {
    proc_terminate($server);
    proc_close($server);
    remove_tree($root);
}

echo $failures === 0 ? "\nAll CSRF smoke checks passed.\n" : "\n{$failures} failure(s).\n";
exit($failures === 0 ? 0 : 1);
