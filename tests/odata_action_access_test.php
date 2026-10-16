<?php
/**
 * odata.php-acties zijn niet anoniem, en cache_status lekt geen voorbeeldrijen.
 * Run: php tests/odata_action_access_test.php
 */

require dirname(__DIR__) . '/web/odata.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function assert_same($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fail($label . ' verwacht ' . var_export($expected, true) . ' kreeg ' . var_export($actual, true));
    }
}

function assert_contains(string $haystack, string $needle, string $label): void
{
    if (strpos($haystack, $needle) === false) {
        fail($label . ' mist "' . $needle . '"');
    }
}

function assert_not_contains(string $haystack, string $needle, string $label): void
{
    if (strpos($haystack, $needle) !== false) {
        fail($label . ' bevat "' . $needle . '"');
    }
}

if (odata_is_direct_request()) {
    fail('testrunner mag de actie-gate niet triggeren');
}

$root = dirname(__DIR__);
$odataSource = (string) file_get_contents($root . '/web/odata.php');
$dispatch = substr($odataSource, (int) strrpos($odataSource, '$odataAction = '));
$requireAt = strpos($dispatch, 'odata_require_direct_action_access()');
$statusAt = strpos($dispatch, 'odata_send_cache_status_json()');
$deleteAt = strpos($dispatch, 'odata_send_cache_delete_json()');
$clearAt = strpos($dispatch, 'odata_send_cache_clear_json()');
if ($requireAt === false || $statusAt === false || $deleteAt === false || $clearAt === false) {
    fail('actie-dispatch mist login of een cache-actie');
}
if ($requireAt > $statusAt || $requireAt > $deleteAt || $requireAt > $clearAt) {
    fail('logincheck moet vóór cache_status, cache_delete en cache_clear');
}
$gateStart = strpos($odataSource, 'function odata_require_direct_action_access');
$gateEnd = strpos($odataSource, 'function odata_release_session_lock');
if ($gateStart === false || $gateEnd === false || $gateEnd < $gateStart) {
    fail('login-gate of sessie-vrijgave ontbreekt');
}
$gate = substr($odataSource, $gateStart, $gateEnd - $gateStart);
$emailAt = strpos($gate, 'odata_email_is_allowed');
$releaseAt = strpos($gate, 'odata_release_session_lock()');
if ($emailAt === false || $releaseAt === false || $releaseAt < $emailAt) {
    fail('sessielock moet vrij na de logincheck, vóór het lezen van de cache');
}
assert_contains($gate, "moirai_resolve_login_file('lib.php')", 'login/lib.php');
assert_contains($gate, '$allowedUsers', 'whitelist');
assert_not_contains($gate, 'is_trusted_requester', 'geen localhost-bypass');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['moirai_access_probe'] = 1;
odata_release_session_lock();
if (session_status() === PHP_SESSION_ACTIVE) {
    fail('actieve sessie blijft gelockt');
}
$releaseSourceStart = strpos($odataSource, 'function odata_release_session_lock');
$releaseSource = $releaseSourceStart === false ? '' : substr($odataSource, $releaseSourceStart, 400);
assert_contains($releaseSource, 'session_write_close()', 'sessie vrijgeven');

assert_contains($odataSource, 'php_sapi_name()', 'cli-check');
assert_contains($odataSource, "=== 'cli'", 'cli-vergelijking');
assert_not_contains($odataSource, 'is_trusted_requester', 'geen localhost-bypass');
assert_not_contains($odataSource, 'odata_cache_extract_attributes_from_payload', 'geen voorbeeldrij-extractor');
assert_not_contains($odataSource, 'entry.attributes', 'widget toont geen voorbeeldrij');

$allowed = [
    'analyst@kvt.nl',
    'planner@kvt.nl' => [15, 40],
];
assert_same(true, odata_email_is_allowed('Analyst@kvt.nl', $allowed), 'lijst-gebruiker');
assert_same(true, odata_email_is_allowed('planner@kvt.nl', $allowed), 'map-gebruiker');
assert_same(false, odata_email_is_allowed('andere@kvt.nl', $allowed), 'niet op de lijst');
assert_same(false, odata_email_is_allowed('', $allowed), 'lege sessie');
assert_same(false, odata_email_is_allowed('analyst@kvt.nl', null), 'geen whitelist');

assert_same(true, odata_direct_action_skips_login('cli', '', ''), 'cli zonder geheim');
assert_same(false, odata_direct_action_skips_login('fpm-fcgi', '', ''), 'web anoniem');
assert_same(false, odata_direct_action_skips_login('fpm-fcgi', '', 'server-secret'), 'web zonder header');
assert_same(false, odata_direct_action_skips_login('fpm-fcgi', 'server-secret', ''), 'leeg servergeheim');
assert_same(false, odata_direct_action_skips_login('fpm-fcgi', 'nope', 'server-secret'), 'fout geheim');
assert_same(true, odata_direct_action_skips_login('cgi-fcgi', 'server-secret', 'server-secret'), 'bestaand servergeheim');
assert_same(false, odata_direct_action_skips_login('apache2handler', 'server-secret', 'ander-geheim'), 'geheim komt niet overeen');

$savedHeader = $_SERVER['HTTP_X_MOIRAI_SECRET'] ?? null;
$savedGetSecret = $_GET['secret'] ?? null;
$_SERVER['HTTP_X_MOIRAI_SECRET'] = '  uit-de-header  ';
$_GET['secret'] = 'uit-de-query';
assert_same('uit-de-header', odata_presented_runner_secret(), 'header, geen query');
if ($savedHeader === null) {
    unset($_SERVER['HTTP_X_MOIRAI_SECRET']);
} else {
    $_SERVER['HTTP_X_MOIRAI_SECRET'] = $savedHeader;
}
if ($savedGetSecret === null) {
    unset($_GET['secret']);
} else {
    $_GET['secret'] = $savedGetSecret;
}

$authPath = sys_get_temp_dir() . '/moirai-auth-runner-secret.php';
file_put_contents($authPath, "<?php\n\$moiraiRunnerSecret = \"auth-php-geheim\";\n\$allowedUsers = ['nacht@kvt.nl'];\n");
$previousAuthPath = $GLOBALS['MOIRAI_AUTH_PHP_PATH'] ?? null;
$previousRunner = $GLOBALS['moiraiRunnerSecret'] ?? null;
unset($GLOBALS['moiraiRunnerSecret']);
$previousEnv = getenv('MOIRAI_RUNNER_SECRET');
putenv('MOIRAI_RUNNER_SECRET');
$GLOBALS['MOIRAI_AUTH_PHP_PATH'] = $authPath;
$fromFile = odata_read_auth_file_vars();
assert_same('auth-php-geheim', odata_configured_runner_secret($fromFile), 'geheim uit auth.php');
assert_same(true, odata_email_is_allowed('nacht@kvt.nl', $fromFile['allowedUsers'] ?? null), 'whitelist uit auth.php');
putenv('MOIRAI_RUNNER_SECRET=env-geheim');
assert_same('auth-php-geheim', odata_configured_runner_secret($fromFile), 'auth.php wint van een lege global');
$GLOBALS['moiraiRunnerSecret'] = 'global-geheim';
assert_same('global-geheim', odata_configured_runner_secret($fromFile), 'global wint');
unset($GLOBALS['moiraiRunnerSecret']);
putenv('MOIRAI_RUNNER_SECRET');
if ($previousAuthPath === null) {
    unset($GLOBALS['MOIRAI_AUTH_PHP_PATH']);
} else {
    $GLOBALS['MOIRAI_AUTH_PHP_PATH'] = $previousAuthPath;
}
if ($previousRunner !== null) {
    $GLOBALS['moiraiRunnerSecret'] = $previousRunner;
}
if ($previousEnv !== false && $previousEnv !== '') {
    putenv('MOIRAI_RUNNER_SECRET=' . $previousEnv);
}
@unlink($authPath);

$cacheDir = cache_base_dir();
$fixtureName = 'moirai-access-test-' . bin2hex(random_bytes(8)) . '.json';
$fixturePath = $cacheDir . '/' . $fixtureName;
$serialNo = 'SN-SECRET-44021';
$userEmail = 'geheim.voorbeeld@kvt.nl';
$documentNo = 'VF-998877';
$sourceUrl = "https://bc.example:7148/Production/ODataV4/Company('KVT')/Fixed_Assets";
$fixture = [
    '_meta' => [
        'cached_at' => time(),
        'expires_at' => time() + 86400,
        'source_url' => $sourceUrl,
        'fetched' => true,
    ],
    'data' => [[
        'Serial_No' => $serialNo,
        'User_Email' => $userEmail,
        'Document_No' => $documentNo,
        'Amount' => 12345.67,
        '@odata.etag' => 'W/"example"',
    ]],
];
file_put_contents($fixturePath, json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

try {
    $payload = odata_cache_status_payload();
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        fail('cache_status payload is geen JSON');
    }
    assert_not_contains($encoded, $serialNo, 'cache_status');
    assert_not_contains($encoded, $userEmail, 'cache_status');
    assert_not_contains($encoded, $documentNo, 'cache_status');
    assert_not_contains($encoded, '12345.67', 'cache_status');
    assert_not_contains($encoded, 'attributes', 'cache_status');
    assert_not_contains($encoded, 'User_Email', 'cache_status');

    $match = null;
    foreach ($payload['entries'] as $entry) {
        if (($entry['id'] ?? '') === $fixtureName) {
            $match = $entry;
            break;
        }
    }
    if (!is_array($match)) {
        fail('fixture ontbreekt in cache_status');
    }
    if (array_key_exists('attributes', $match) || array_key_exists('data', $match)) {
        fail('cache_status-entry bevat rijdata');
    }
    assert_same($sourceUrl, $match['url'] ?? '', 'bron-url voor ingelogde widget');
    if ((int) ($match['size_bytes'] ?? 0) <= 0) {
        fail('cache_status mist bestandsgrootte');
    }

    $cliScript = sys_get_temp_dir() . '/moirai-odata-cli-' . bin2hex(random_bytes(4)) . '.php';
    $odataFile = $root . '/web/odata.php';
    file_put_contents($cliScript, "<?php\n"
        . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($odataFile, true) . ";\n"
        . '$_SERVER["SCRIPT_NAME"] = "/moirai/odata.php";' . "\n"
        . '$_SERVER["PHP_SELF"] = "/moirai/odata.php";' . "\n"
        . '$_GET["action"] = "cache_status";' . "\n"
        . 'require ' . var_export($odataFile, true) . ";\n");
    $cli = proc_open([PHP_BINARY, $cliScript], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $cliPipes, $root);
    if (!is_resource($cli)) {
        fail('cli-actie startte niet');
    }
    $cliOut = stream_get_contents($cliPipes[1]);
    $cliErr = stream_get_contents($cliPipes[2]);
    fclose($cliPipes[1]);
    fclose($cliPipes[2]);
    $cliCode = proc_close($cli);
    @unlink($cliScript);
    $cliOut = is_string($cliOut) ? $cliOut : '';
    $cliErr = is_string($cliErr) ? $cliErr : '';
    if ($cliCode !== 0) {
        fail('cli-actie exit ' . $cliCode . ' ' . $cliErr . ' ' . $cliOut);
    }
    assert_contains($cliOut, '"bytes"', 'cli-actie');
    assert_not_contains($cliOut, $serialNo, 'cli-actie');
    assert_not_contains($cliOut, $userEmail, 'cli-actie');
    assert_not_contains($cliOut, 'attributes', 'cli-actie');
} finally {
    @unlink($fixturePath);
}

$widget = injectTimerHtml([
    'statusUrl' => 'odata.php?action=cache_status',
]);
assert_not_contains($widget, 'attributes', 'widget-html');
assert_contains($widget, 'odata.php?action=cache_status', 'widget houdt status-url');

$guardDirs = [
    $root . '/web/cache',
    $root . '/web/cache/odata',
    $root . '/web/data',
    $root . '/web/data/user_avatars',
    $root . '/web/data/user_prefs',
];
foreach ($guardDirs as $guardDir) {
    $htaccess = $guardDir . '/.htaccess';
    $rules = (string) file_get_contents($htaccess);
    assert_contains($rules, 'Options -Indexes', $htaccess);
    assert_contains($rules, 'Require all denied', $htaccess);
    assert_contains($rules, 'Deny from all', $htaccess);
    assert_contains($rules, '<IfModule mod_authz_core.c>', $htaccess);
    assert_contains($rules, '<IfModule !mod_authz_core.c>', $htaccess);
    foreach (['index.html', 'index.php'] as $indexName) {
        $indexFile = $guardDir . '/' . $indexName;
        if (!is_file($indexFile)) {
            fail('ontbrekend indexbestand ' . $indexFile);
        }
        $indexBody = (string) file_get_contents($indexFile);
        if (strlen($indexBody) > 16) {
            fail('indexbestand is niet leeg: ' . $indexFile);
        }
        assert_not_contains($indexBody, 'Customer', $indexFile);
    }
}

$workflow = (string) file_get_contents($root . '/.github/workflows/deploy-ftp.yml');
$includeCacheAt = strpos($workflow, '--include-glob cache/.htaccess');
$includeDataAt = strpos($workflow, '--include-glob data/.htaccess');
$excludeCacheAt = strpos($workflow, '--exclude-glob cache/**');
$excludeOdataAt = strpos($workflow, '--exclude-glob cache/odata/**');
$excludeDataAt = strpos($workflow, '--exclude-glob data/**');
if ($includeCacheAt === false || $includeDataAt === false || $excludeCacheAt === false || $excludeOdataAt === false || $excludeDataAt === false) {
    fail('ftp-deploy mist data- of cache-patronen');
}
if ($includeCacheAt < $excludeCacheAt || $includeCacheAt < $excludeOdataAt) {
    fail('cache/.htaccess moet ná de cache-excludes staan, anders uploadt lftp hem niet');
}
if ($includeDataAt < $excludeDataAt) {
    fail('data/.htaccess moet ná de data-excludes staan, anders uploadt lftp hem niet');
}
foreach ([
    '--include-glob cache/index.html',
    '--include-glob cache/index.php',
    '--include-glob cache/odata/.htaccess',
    '--include-glob cache/odata/index.html',
    '--include-glob cache/odata/index.php',
    '--include-glob data/index.html',
    '--include-glob data/index.php',
    '--include-glob data/user_avatars/.htaccess',
    '--include-glob data/user_avatars/index.html',
    '--include-glob data/user_avatars/index.php',
    '--include-glob data/user_prefs/.htaccess',
    '--include-glob data/user_prefs/index.html',
    '--include-glob data/user_prefs/index.php',
    '--exclude-glob data/**',
    '--exclude-glob auth.php',
    '--exclude-glob analytics/*.sqlite',
] as $needle) {
    assert_contains($workflow, $needle, 'ftp-deploy');
}
assert_not_contains($workflow, '--include-glob data/*.sqlite', 'ftp-deploy overschrijft geen sqlite');
assert_not_contains($workflow, '--include-glob data/*.json', 'ftp-deploy overschrijft geen data-json');

$apiSource = (string) file_get_contents($root . '/web/api.php');
assert_contains($apiSource, 'moirai_api_authenticate()', 'api.php houdt eigen key-check');
assert_not_contains($apiSource, 'odata_require_direct_action_access', 'api.php gaat niet via de odata-login');
assert_not_contains($apiSource, 'logincheck.php', 'api.php blijft key-authenticated');

$phpCgi = trim((string) shell_exec('command -v php-cgi 2>/dev/null'));
if ($phpCgi !== '') {
        $runCgi = static function (string $action, ?string $secret, ?string $serverSecret = null) use ($root, $phpCgi): array {
        $odataFile = $root . '/web/odata.php';
        $script = sys_get_temp_dir() . '/moirai-odata-cgi-' . bin2hex(random_bytes(4)) . '.php';
        $secretLine = $secret === null
            ? ''
            : '$_SERVER["HTTP_X_MOIRAI_SECRET"] = ' . var_export($secret, true) . ";\n";
        $serverLine = $serverSecret === null
            ? ''
            : '$GLOBALS["moiraiRunnerSecret"] = ' . var_export($serverSecret, true) . ";\n";
        file_put_contents($script, "<?php\n"
            . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($odataFile, true) . ";\n"
            . '$_SERVER["SCRIPT_NAME"] = "/moirai/odata.php";' . "\n"
            . '$_SERVER["PHP_SELF"] = "/moirai/odata.php";' . "\n"
            . '$_SERVER["REMOTE_ADDR"] = "203.0.113.50";' . "\n"
            . '$_SERVER["SERVER_ADDR"] = "198.51.100.10";' . "\n"
            . $serverLine
            . $secretLine
            . '$_GET["action"] = ' . var_export($action, true) . ";\n"
            . 'require ' . var_export($odataFile, true) . ";\n");
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }
        unset($env['MOIRAI_RUNNER_SECRET']);
        $process = proc_open([$phpCgi, '-q', '-d', 'display_errors=0', $script], $descriptor, $pipes, $root, $env);
        if (!is_resource($process)) {
            @unlink($script);
            fail('php-cgi startte niet');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        @unlink($script);

        return [
            'code' => $exitCode,
            'out' => is_string($stdout) ? $stdout : '',
            'err' => is_string($stderr) ? $stderr : '',
        ];
    };

    $anonymous = $runCgi('cache_status', null);
    if ($anonymous['code'] !== 0 && strpos($anonymous['out'], 'Geen toegang') === false) {
        fail('anonieme cgi-actie exit ' . $anonymous['code'] . ' ' . $anonymous['err'] . ' ' . $anonymous['out']);
    }
    assert_contains($anonymous['out'], 'Geen toegang', 'anonieme cgi-actie');
    assert_not_contains($anonymous['out'], $serialNo, 'anonieme cgi-actie');
    assert_not_contains($anonymous['out'], $userEmail, 'anonieme cgi-actie');
    assert_not_contains($anonymous['out'], 'Fixed_Assets', 'anonieme cgi-actie');

    $unknown = $runCgi('nope', null);
    assert_contains($unknown['out'], 'Geen toegang', 'anonieme onbekende actie');
    assert_not_contains($unknown['out'], 'Onbekende actie', 'anonieme onbekende actie');

    $wrongSecret = $runCgi('cache_clear', 'niet-het-geheim', 'server-secret');
    assert_contains($wrongSecret['out'], 'Geen toegang', 'fout geheim');
    assert_not_contains($wrongSecret['out'], 'deleted_count', 'fout geheim wist geen cache');

    $withSecret = $runCgi('cache_status', 'server-secret', 'server-secret');
    assert_contains($withSecret['out'], '"bytes"', 'header-geheim');
    assert_not_contains($withSecret['out'], 'Geen toegang', 'header-geheim');
    assert_not_contains($withSecret['out'], 'attributes', 'header-geheim');

    $unknownAuthed = $runCgi('nope', 'server-secret', 'server-secret');
    assert_contains($unknownAuthed['out'], 'Onbekende actie', 'geheim opent de actie');
    assert_not_contains($unknownAuthed['out'], 'Geen toegang', 'geheim opent de actie');
}

echo "OK odata action access\n";
