<?php
/**
 * Simuleert een onbereikbare Mímir en controleert de directe BC-fallback.
 * Run: php tests/mimir_fallback_test.php
 */

$logFile = sys_get_temp_dir() . '/moirai-mimir-fallback-test.log';
@unlink($logFile);
ini_set('error_log', $logFile);
ini_set('log_errors', '1');

$mimirApi = 'mimir_test_key_should_not_leak';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];

$calls = [];
$GLOBALS['MOIRAI_ODATA_BC_FETCH'] = static function (string $url, array $auth, int $ttl) use (&$calls): array {
    $calls[] = [
        'url' => $url,
        'user' => (string) ($auth['user'] ?? ''),
        'ttl' => $ttl,
    ];
    if (preg_match('#/ODataV4/Companies(?:\\?|$)#', $url) === 1
        || preg_match('#/ODataV4/Company(?:\\?|$)#', $url) === 1) {
        return [
            ['Name' => 'KVT Gas'],
            ['Name' => 'Hunter van Twist'],
            ['name' => 'Koninklijke van Twist'],
        ];
    }
    return [['No' => 'WO-1']];
};

require dirname(__DIR__) . '/web/odata.php';
require dirname(__DIR__) . '/web/auth_helper.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function fallback_log(): string
{
    global $logFile;
    $raw = @file_get_contents($logFile);
    return is_string($raw) ? $raw : '';
}

function fallback_count(): int
{
    return substr_count(fallback_log(), '[Moirai] Mímir failed, falling back to direct OData:');
}

if (odata_mimir_connect_timeout_seconds() !== 10) {
    fail('connect-timeout moet 10s zijn');
}
if (odata_mimir_timeout_seconds_for_sapi('cli') !== 600) {
    fail('CLI-timeout moet 600s blijven');
}
if (odata_mimir_timeout_seconds_for_sapi('fpm-fcgi') !== 90 || odata_mimir_timeout_seconds_for_sapi('apache2handler') !== 90) {
    fail('web-timeout moet ongeveer 90s zijn');
}
if (PHP_SAPI === 'cli' && odata_mimir_timeout_seconds() !== 600) {
    fail('huidige CLI-sapi moet de lange timeout gebruiken');
}

$names = odata_mimir_list_companies(null);
$expectedNames = ['Hunter van Twist', 'Koninklijke van Twist', 'KVT Gas'];
if ($names !== $expectedNames) {
    fail('company-fallback gaf ' . json_encode($names) . ' i.p.v. de gesorteerde BC-namen');
}
if (!odata_mimir_circuit_open()) {
    fail('circuit moet open na de eerste Mímir-fout');
}
if (count($calls) !== 1 || strpos($calls[0]['url'], 'https://bc.example:7148/Production/ODataV4/Company') !== 0) {
    fail('company-fallback riep de directe BC-fetch niet aan: ' . json_encode($calls));
}
if ($calls[0]['user'] !== 'bcuser') {
    fail('company-fallback gebruikte niet de BC-credentials');
}

$directCompanyUrl = odata_bc_url_from_odata_url(
    "https://mimir.invalid/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No"
);
if (strpos($directCompanyUrl, "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?") !== 0) {
    fail('synthetische Mímir-URL moet naar de oude BC-URL, kreeg: ' . $directCompanyUrl);
}
if (strpos($directCompanyUrl, 'mimir.invalid') !== false) {
    fail('synthetische host bleef staan na fallback');
}

$mimirBase = 'http://192.0.2.1:9';
$loggedAfterFirst = fallback_count();
if ($loggedAfterFirst !== 1) {
    fail('alleen de eerste Mímir-fout mag gelogd worden, count=' . $loggedAfterFirst . ' log=' . fallback_log());
}
$started = microtime(true);
$rows = odata_get_all(
    "https://mimir.invalid/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    120
);
$elapsed = microtime(true) - $started;
if ($elapsed >= 2.0) {
    fail('circuit breaker sloeg Mímir niet over (' . round($elapsed, 3) . 's)');
}
if (($rows[0]['No'] ?? '') !== 'WO-1') {
    fail('entity-fallback gaf niet de gestubde BC-rijen terug');
}
$entityCall = $calls[1] ?? null;
$expectedEntityUrl = "https://bc.example:7148/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No";
if (!is_array($entityCall) || $entityCall['url'] !== $expectedEntityUrl || $entityCall['user'] !== 'bcuser' || $entityCall['ttl'] !== 120) {
    fail('entity-fallback URL/auth/ttl klopt niet: ' . json_encode($entityCall));
}
if (fallback_count() !== $loggedAfterFirst) {
    fail('na het openen van het circuit mag niet opnieuw gelogd worden, log=' . fallback_log());
}
$log = fallback_log();
if (strpos($log, 'mimir_test_key_should_not_leak') !== false || strpos($log, 'bc-secret') !== false) {
    fail('log bevat een geheim');
}
if (strpos($log, '[Moirai] Mímir failed, falling back to direct OData:') === false) {
    fail('logregel mist het verwachte prefix');
}

odata_mimir_circuit_reset();
$loggedBeforeLocal = fallback_count();
try {
    odata_mimir_or_direct(
        static function (): array {
            throw new Exception('lokale fout in de aanroeper');
        },
        static function (): array {
            return [['No' => 'SHOULD-NOT']];
        }
    );
    fail('een niet-Mímir-fout moet doorgaan');
} catch (Throwable $exception) {
    if ($exception->getMessage() !== 'lokale fout in de aanroeper') {
        fail('lokale fout werd vervangen: ' . $exception->getMessage());
    }
}
if (odata_mimir_circuit_open()) {
    fail('een niet-Mímir-fout mag het circuit niet openen');
}
if (fallback_count() !== $loggedBeforeLocal) {
    fail('een niet-Mímir-fout mag geen fallback loggen');
}

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$beforeQuery = count($calls);
$queryRows = odata_mimir_query('KVT Gas', 'AppResource', ['$select' => 'No,Name'], 60);
if (($queryRows[0]['No'] ?? '') !== 'WO-1') {
    fail('odata_mimir_query viel niet terug op de stub');
}
$queryCall = $calls[$beforeQuery] ?? null;
if (!is_array($queryCall) || strpos($queryCall['url'], "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppResource?") !== 0) {
    fail('query-fallback bouwde niet de pre-Mímir BC-URL: ' . json_encode($queryCall));
}

odata_mimir_circuit_reset();
$beforeFetch = count($calls);
$fetchRows = odata_mimir_fetch_all(
    "https://mimir.invalid/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No",
    15
);
if (($fetchRows[0]['No'] ?? '') !== 'WO-1') {
    fail('odata_mimir_fetch_all viel niet terug');
}
$fetchCall = $calls[$beforeFetch] ?? null;
if (!is_array($fetchCall) || $fetchCall['url'] !== "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No") {
    fail('fetch_all-fallback herschreef de URL niet: ' . json_encode($fetchCall));
}

odata_mimir_circuit_reset();
$map = odata_mimir_company_environment_map(null);
if (($map['Hunter van Twist'] ?? '') !== 'Production' || ($map['KVT Gas'] ?? '') !== 'Production') {
    fail('environment-map viel niet terug op BC: ' . json_encode($map));
}

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$environment = 'mimir';
$beforeSentinel = count($calls);
$sentinelNames = odata_mimir_list_companies(null);
if ($sentinelNames !== $expectedNames) {
    fail('fallback moet BC-environment uit auth_list halen als $environment de Mímir-sentinel is');
}
$sentinelCall = $calls[$beforeSentinel] ?? null;
if (!is_array($sentinelCall) || strpos($sentinelCall['url'], 'https://bc.example:7148/Production/ODataV4/Company') !== 0) {
    fail('sentinel-environment gebruikte niet auth_list: ' . json_encode($sentinelCall));
}
$environment = 'Production';

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$startedDiscover = microtime(true);
$discovered = auth_discover_companies_across_active_environments(30);
$discoverElapsed = microtime(true) - $startedDiscover;
if ($discoverElapsed >= 2.0) {
    fail('company-discovery probeerde Mímir niet snel te verlaten (' . round($discoverElapsed, 3) . 's)');
}
// Pre-Mímir auth_fetch leest het BC-veld Name (niet de losse name-alias van de stub).
$discoveredNames = $discovered['companies'] ?? null;
if ($discoveredNames !== ['Hunter van Twist', 'KVT Gas'] || ($discovered['map']['KVT Gas'] ?? '') !== 'Production') {
    fail('discovery-fallback gaf niet de BC-bedrijven: ' . json_encode($discovered));
}
if (!odata_mimir_circuit_open()) {
    fail('discovery moet het circuit openen');
}
$context = auth_set_current_company_context('KVT Gas', 30);
if (($context['environment'] ?? '') !== 'Production' || ($context['auth']['user'] ?? '') !== 'bcuser') {
    fail('company-context na Mímir-storing zette geen BC-auth: ' . json_encode($context));
}

$auth_list = [
    'Production' => ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'],
    'Sandbox' => ['mode' => 'basic', 'user' => 'sandbox-user', 'pass' => 'sandbox-secret'],
];
$auth = $auth_list['Production'];
$environment = 'Production';
$GLOBALS['demeter_company_environment_map'] = [
    'Hunter van Twist' => 'Sandbox',
    'KVT Gas' => 'Production',
];
odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$beforeCompanyEnv = count($calls);
$companyEnvRows = odata_mimir_query('Hunter van Twist', 'AppResource', ['$select' => 'No'], 30);
if (($companyEnvRows[0]['No'] ?? '') !== 'WO-1') {
    fail('company-environment fallback gaf geen rijen');
}
$companyEnvCall = $calls[$beforeCompanyEnv] ?? null;
if (!is_array($companyEnvCall)
    || strpos((string) ($companyEnvCall['url'] ?? ''), "https://bc.example:7148/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppResource?") !== 0
    || ($companyEnvCall['user'] ?? '') !== 'sandbox-user'
) {
    fail('query gebruikte niet het environment en de auth van het bedrijf: ' . json_encode($companyEnvCall));
}

odata_mimir_circuit_reset();
$beforeUrlEnv = count($calls);
$urlEnvRows = odata_get_all(
    "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    12
);
if (($urlEnvRows[0]['No'] ?? '') !== 'WO-1') {
    fail('URL-environment fallback gaf geen rijen');
}
$urlEnvCall = $calls[$beforeUrlEnv] ?? null;
if (!is_array($urlEnvCall)
    || ($urlEnvCall['url'] ?? '') !== "https://bc.example:7148/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No"
    || ($urlEnvCall['user'] ?? '') !== 'sandbox-user'
) {
    fail('URL-segment werd vervangen door het primaire environment: ' . json_encode($urlEnvCall));
}
$cacheKey = build_cache_key(
    "https://mimir.invalid/mimir/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders",
    ['user' => 'sandbox-user', 'mode' => 'basic', 'pass' => 'sandbox-secret']
);
$cacheSuffix = '|sandbox-user|Sandbox';
if (substr($cacheKey, -strlen($cacheSuffix)) !== $cacheSuffix) {
    fail('cache-key gebruikt niet de BC-environment van het bedrijf: ' . $cacheKey);
}

odata_mimir_circuit_reset();
$beforeMapped = count($calls);
$mappedRows = odata_get_all(
    "https://mimir.invalid/mimir/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    12
);
if (($mappedRows[0]['No'] ?? '') !== 'WO-1') {
    fail('company-map fallback gaf geen rijen');
}
$mappedCall = $calls[$beforeMapped] ?? null;
if (!is_array($mappedCall)
    || strpos((string) ($mappedCall['url'] ?? ''), 'https://bc.example:7148/Sandbox/ODataV4/') !== 0
    || ($mappedCall['user'] ?? '') !== 'sandbox-user'
) {
    fail('placeholder-environment negeerde de company-map: ' . json_encode($mappedCall));
}
if (strpos(fallback_log(), 'sandbox-secret') !== false || strpos(fallback_log(), 'bc-secret') !== false) {
    fail('log bevat een geheim na company-environment fallback');
}

$auth_list = [
    'Production' => ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'],
];
$auth = $auth_list['Production'];
odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$callsBeforeMappedAuth = count($calls);
$mappedAuthError = null;
try {
    odata_mimir_query('Hunter van Twist', 'AppResource', ['$select' => 'No'], 30);
    fail('gemapt Sandbox-bedrijf zonder Sandbox-auth mag niet de primaire credentials gebruiken');
} catch (Throwable $exception) {
    $mappedAuthError = $exception;
}
if (!$mappedAuthError instanceof Throwable || strpos($mappedAuthError->getMessage(), 'Mímir') === false) {
    fail('gemapt environment zonder eigen auth moet de Mímir-fout teruggeven');
}
if (count($calls) !== $callsBeforeMappedAuth) {
    fail('gemapt environment zonder eigen auth mag geen BC-call doen: ' . json_encode(array_slice($calls, $callsBeforeMappedAuth)));
}

odata_mimir_circuit_reset();
$sandboxUrl = "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No";
$callsBeforeExplicit = count($calls);
$explicitAuthError = null;
try {
    odata_get_all($sandboxUrl, $auth, 12);
    fail('expliciete Sandbox-URL zonder Sandbox-auth mag niet terugvallen op $auth');
} catch (Throwable $exception) {
    $explicitAuthError = $exception;
}
if (!$explicitAuthError instanceof Throwable || strpos($explicitAuthError->getMessage(), 'Mímir') === false) {
    fail('expliciete URL zonder auth moet de Mímir-fout teruggeven');
}
if (count($calls) !== $callsBeforeExplicit) {
    fail('expliciete URL zonder auth mag geen BC-call doen');
}

odata_mimir_circuit_reset();
$callsBeforeFetchAuth = count($calls);
$fetchAuthError = null;
try {
    odata_mimir_fetch_all($sandboxUrl, 12);
    fail('fetch_all met expliciete Sandbox-URL mag niet terugvallen op andere credentials');
} catch (Throwable $exception) {
    $fetchAuthError = $exception;
}
if (!$fetchAuthError instanceof Throwable || strpos($fetchAuthError->getMessage(), 'Mímir') === false) {
    fail('fetch_all zonder Sandbox-auth moet de Mímir-fout teruggeven');
}
if (count($calls) !== $callsBeforeFetchAuth) {
    fail('fetch_all zonder Sandbox-auth mag geen BC-call doen');
}

$loggedBeforeRethrow = fallback_count();
$callsBeforeRethrow = count($calls);
odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://mimir.invalid/';
$environment = 'mimir';
$auth = [];
$auth_list = [];
$rethrown = null;
try {
    odata_get_all('https://mimir.invalid/mimir/ODataV4/Company(\'X\')/AppWerkorders', ['mode' => 'basic', 'user' => '', 'pass' => ''], 30);
    fail('zonder BC-credentials moet de oorspronkelijke Mímir-fout terugkomen');
} catch (Throwable $exception) {
    $rethrown = $exception;
}
if (!$rethrown instanceof Throwable) {
    fail('zonder BC-credentials moet een exception vallen');
}
if (strpos($rethrown->getMessage(), 'Mímir') === false) {
    fail('hergooide fout is niet de Mímir-fout: ' . $rethrown->getMessage());
}
if (stripos($rethrown->getMessage(), 'credential') !== false) {
    fail('hergooide fout maskeert Mímir met een credentials-melding: ' . $rethrown->getMessage());
}
if (count($calls) !== $callsBeforeRethrow) {
    fail('zonder BC-credentials mag de directe fetch niet starten');
}
if (fallback_count() !== $loggedBeforeRethrow) {
    fail('zonder BC-credentials mag er geen fallback gelogd worden');
}

odata_mimir_circuit_reset();
$mimirApi = '';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];
$loggedBeforeDirect = fallback_count();
$directOnlyUrl = 'https://mimir.invalid/Production/ODataV4/Company(\'KVT%20Gas\')/AppWerkorders?$select=No';
$directRows = odata_get_all($directOnlyUrl, $auth, 45);
if (odata_mimir_circuit_open()) {
    fail('lege $mimirApi mag Mímir niet proberen');
}
if (fallback_count() !== $loggedBeforeDirect) {
    fail('lege $mimirApi mag geen Mímir-fallback loggen');
}
$directCall = $calls[count($calls) - 1] ?? null;
if (($directRows[0]['No'] ?? '') !== 'WO-1' || !is_array($directCall) || $directCall['url'] !== $directOnlyUrl) {
    fail('lege $mimirApi moet de oude directe route ongewijzigd gebruiken: ' . json_encode($directCall));
}

$tmpAuth = sys_get_temp_dir() . '/moirai-auth-fallback-' . getmypid() . '.php';
file_put_contents($tmpAuth, <<<'PHP'
<?php
$baseUrl = 'https://loaded-bc.example:7148/';
$base = 'https://loaded-base-only.example:7148/';
$environment = 'LoadedEnv';
$auth_list = [
    'LoadedEnv' => ['mode' => 'basic', 'user' => 'loaded-user', 'pass' => 'loaded-secret'],
];
$auth = $auth_list['LoadedEnv'];
PHP);
$baseUrl = 'https://mimir.invalid/';
$environment = 'mimir';
$auth = [];
$auth_list = [];
unset($GLOBALS['MOIRAI_BC_AUTH_LOAD_TRIED']);
$GLOBALS['MOIRAI_AUTH_PHP_PATH'] = $tmpAuth;
odata_bc_ensure_auth_loaded();
$loadedBase = odata_bc_base_url();
$loadedUser = (string) ($GLOBALS['auth_list']['LoadedEnv']['user'] ?? '');
$loadedEnv = odata_bc_environment();
require_once $tmpAuth;
$baseAfterSecondInclude = odata_bc_base_url();
if ($loadedBase !== 'https://loaded-bc.example:7148/') {
    fail('auth.php-variabelen bleven buiten $GLOBALS, base=' . var_export($loadedBase, true));
}
if ($loadedUser !== 'loaded-user' || $loadedEnv !== 'LoadedEnv') {
    fail('auth_list/environment uit auth.php zijn niet globaal: user=' . $loadedUser . ' env=' . var_export($loadedEnv, true));
}
if ($baseAfterSecondInclude !== 'https://loaded-bc.example:7148/') {
    fail('tweede require_once maakte de BC-globals weer leeg');
}
if ((string) ($GLOBALS['base'] ?? '') !== 'https://loaded-base-only.example:7148/') {
    fail('variabele $base werd niet naar $GLOBALS gekopieerd');
}

$baseUrl = 'https://keep.example:7148/';
$environment = 'mimir';
$auth = [];
$auth_list = [];
unset($GLOBALS['MOIRAI_BC_AUTH_LOAD_TRIED']);
odata_bc_ensure_auth_loaded();
if (odata_bc_base_url() !== 'https://keep.example:7148/') {
    fail('een gezette baseUrl werd overschreven: ' . var_export(odata_bc_base_url(), true));
}
if (odata_bc_environment() !== 'LoadedEnv' || (string) ($GLOBALS['auth']['user'] ?? '') !== 'loaded-user') {
    fail('ontbrekende BC-globals werden niet aangevuld');
}
@unlink($tmpAuth);
unset($GLOBALS['MOIRAI_AUTH_PHP_PATH']);
if (strpos(fallback_log(), 'loaded-secret') !== false) {
    fail('log bevat het wachtwoord uit auth.php');
}

echo "OK\n";
