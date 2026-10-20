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
$freshSql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'simcards'")->fetchColumn();
expect(!preg_match('/telefoonnummer_norm["\s]*TEXT\s+NOT NULL/i', $freshSql), 'new simcards table stores phone norm as NULL');
expect(!function_exists('moirai_ensure_simcard_phone_unique_index'), 'per-request index rebuild is gone');
expect(true, 'migration is idempotent (ran twice without error)');

// Oude productie-index plus dubbele lege nummers. De volle unieke index kan
// niet opnieuw worden aangemaakt zolang die lege strings er staan; de migratie
// mag daardoor niet gooien en zet de lege waarden om naar NULL.
$legacyFile = sys_get_temp_dir() . '/moirai_sim_legacy_' . bin2hex(random_bytes(4)) . '.sqlite';
$legacyDb = new PDO('sqlite:' . $legacyFile);
$legacyDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$legacyDb->exec("CREATE TABLE simcards (
    code TEXT PRIMARY KEY,
    code_norm TEXT NOT NULL,
    telefoonnummer TEXT NOT NULL DEFAULT '',
    telefoonnummer_norm TEXT NOT NULL,
    fysieke_staat TEXT NOT NULL DEFAULT 'uitstekend',
    uitgegeven_user_id TEXT,
    uitgegeven_naam TEXT,
    uitgegeven_email TEXT,
    uitgegeven_sinds TEXT,
    historie_json TEXT NOT NULL DEFAULT '[]',
    qr_geldig INTEGER NOT NULL DEFAULT 0
)");
$legacyDb->exec('CREATE UNIQUE INDEX simcards_code_norm_unique ON simcards (code_norm)');
$legacyDb->exec('CREATE UNIQUE INDEX simcards_telefoonnummer_norm_unique ON simcards (telefoonnummer_norm)');
$legacyDb->exec("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('MET', 'MET', '06 12345678', '+31612345678')");
$legacyDb->exec('DROP INDEX simcards_telefoonnummer_norm_unique');
$legacyDb->exec("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('LEEG1', 'LEEG1', '', '')");
$legacyDb->exec("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('LEEG2', 'LEEG2', '', '')");
$oldIndexBlocked = false;
try {
    $legacyDb->exec('CREATE UNIQUE INDEX simcards_telefoonnummer_norm_unique ON simcards (telefoonnummer_norm)');
} catch (PDOException $error) {
    $oldIndexBlocked = str_contains($error->getMessage(), 'UNIQUE constraint failed');
}
expect($oldIndexBlocked, 'old full unique index cannot be rebuilt while empty numbers are duplicated');
$legacyThrew = false;
try {
    moirai_migrate_simcards_table($legacyDb);
    moirai_migrate_simcards_table($legacyDb);
} catch (Throwable $error) {
    $legacyThrew = true;
}
expect(!$legacyThrew, 'migration does not throw on old index plus duplicate empty numbers');
$legacyNulls = (int) $legacyDb->query("SELECT COUNT(*) FROM simcards WHERE code IN ('LEEG1', 'LEEG2') AND telefoonnummer_norm IS NULL")->fetchColumn();
expect($legacyNulls === 2, 'duplicate empty numbers became NULL');
expect((string) $legacyDb->query("SELECT telefoonnummer_norm FROM simcards WHERE code = 'MET'")->fetchColumn() === '+31612345678', 'numbered sim kept its norm');
$legacyDb->exec("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('LEEG3', 'LEEG3', '', NULL)");
$legacyDupBlocked = false;
try {
    $legacyDb->exec("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('LEEG4', 'LEEG4', 'x', '+31612345678')");
} catch (PDOException $error) {
    $legacyDupBlocked = str_contains($error->getMessage(), 'UNIQUE constraint failed');
}
expect($legacyDupBlocked, 'non-empty number stays unique after blank-null migration');
$legacyIndexes = array_column($legacyDb->query('PRAGMA index_list(simcards)')->fetchAll(), 'unique', 'name');
expect(($legacyIndexes['simcards_telefoonnummer_norm_unique'] ?? 0) == 1, 'phone unique index exists after migration');
$legacyPhoneSql = (string) $legacyDb->query("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'simcards_telefoonnummer_norm_unique'")->fetchColumn();
expect($legacyPhoneSql !== '' && !preg_match('/\bWHERE\b/i', $legacyPhoneSql), 'phone unique index stays a full index, not a partial one');
expect(!moirai_sqlite_table_exists($legacyDb, 'simcards__blanknull'), 'temporary migration table is gone');

// Mislukte migratie mag de oude index niet weggooien en de verbinding niet breken.
$brokenFile = sys_get_temp_dir() . '/moirai_sim_broken_' . bin2hex(random_bytes(4)) . '.sqlite';
$broken = new PDO('sqlite:' . $brokenFile);
$broken->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$broken->exec("CREATE TABLE simcards (
    code TEXT PRIMARY KEY,
    code_norm TEXT NOT NULL,
    telefoonnummer TEXT NOT NULL DEFAULT '',
    telefoonnummer_norm TEXT NOT NULL,
    fysieke_staat TEXT NOT NULL DEFAULT 'uitstekend',
    historie_json TEXT NOT NULL DEFAULT '[]',
    qr_geldig INTEGER NOT NULL DEFAULT 0
)");
$broken->exec('CREATE UNIQUE INDEX simcards_telefoonnummer_norm_unique ON simcards (telefoonnummer_norm)');
$broken->exec('CREATE UNIQUE INDEX simcards_code_norm_unique ON simcards (code_norm)');
$broken->exec('DROP INDEX simcards_code_norm_unique');
$broken->exec("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('A', 'SAME', '0611111111', '+31611111111')");
$broken->exec("INSERT INTO simcards (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('B', 'SAME', '0622222222', '+31622222222')");
$brokenThrew = false;
try {
    moirai_migrate_simcards_table($broken);
} catch (Throwable $error) {
    $brokenThrew = true;
}
expect(!$brokenThrew, 'failed blank-null migration does not escape to the caller');
$brokenStill = (int) $broken->query("SELECT COUNT(*) FROM simcards")->fetchColumn();
expect($brokenStill === 2, 'failed migration keeps the existing rows');
$brokenSql = (string) $broken->query("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'simcards_telefoonnummer_norm_unique'")->fetchColumn();
expect($brokenSql !== '' && !preg_match('/\bWHERE\b/i', $brokenSql), 'old phone unique index is still the original one');
expect(moirai_simcard_phone_norm_needs_null_migration($broken), 'failed migration leaves the NOT NULL column in place');
expect(!moirai_sqlite_table_exists($broken, 'simcards__blanknull'), 'failed migration does not leave the new table behind');
expect(moirai_schema_meta_get($broken, 'simcards_blank_phone') === 'failed', 'failed migration is stored as schema version');
expect(is_file($brokenFile . '.sim-blank-phone-skip'), 'failed migration is remembered outside this PHP process');
$notNullMapped = moirai_device_save_exception(
    'simcards',
    new PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: simcards.telefoonnummer_norm'),
    ['code' => 'NIEUW-LEEG', 'telefoonnummer_norm' => null]
);
expect($notNullMapped instanceof InvalidArgumentException, 'NOT NULL on a blank phone maps to a validation error');
$notNullApi = $notNullMapped instanceof InvalidArgumentException ? moirai_api_from_invalid_argument($notNullMapped) : ['status' => 0, 'body' => []];
expect(($notNullApi['status'] ?? 0) === 400 && ($notNullApi['body']['error_code'] ?? '') === 'invalid_input', 'blank phone on the old column is HTTP 400 invalid_input');
expect(moirai_device_save_exception('laptops', new PDOException('NOT NULL constraint failed: laptops.naam'), []) === null, 'other NOT NULL errors stay database errors');
$schemaBeforeRetry = (string) $broken->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'simcards'")->fetchColumn()
    . "\n" . $brokenSql;
$broken = null;
$retryScript = sys_get_temp_dir() . '/moirai_sim_retry_' . bin2hex(random_bytes(4)) . '.php';
file_put_contents($retryScript, '<?php' . "\n"
    . 'declare(strict_types=1);' . "\n"
    . '$GLOBALS[\'moirai_db_file\'] = ' . var_export($brokenFile, true) . ";\n"
    . 'require ' . var_export(__DIR__ . '/../web/localization.php', true) . ";\n"
    . 'require ' . var_export(__DIR__ . '/../web/moirai_api.php', true) . ";\n"
    . '$pdo = moirai_db();' . "\n"
    . 'moirai_migrate_simcards_table($pdo);' . "\n"
    . 'try {' . "\n"
    . '    moirai_save_device(\'simcard\', [\'code\' => \'NIEUW-LEEG\', \'telefoonnummer\' => \'\'], []);' . "\n"
    . '    fwrite(STDOUT, "saved\n");' . "\n"
    . '} catch (InvalidArgumentException $e) {' . "\n"
    . '    $api = moirai_api_from_invalid_argument($e);' . "\n"
    . '    fwrite(STDOUT, \'invalid \' . $api[\'status\'] . \' \' . ($api[\'body\'][\'error_code\'] ?? \'\') . "\n" . $e->getMessage() . "\n");' . "\n"
    . '} catch (Throwable $e) {' . "\n"
    . '    fwrite(STDOUT, \'throw \' . get_class($e) . "\n" . $e->getMessage() . "\n");' . "\n"
    . '}' . "\n");
$retryPipes = [];
$retryProc = proc_open([PHP_BINARY, $retryScript], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $retryPipes, dirname(__DIR__));
$retryOut = is_resource($retryProc) ? stream_get_contents($retryPipes[1]) : '';
$retryErr = is_resource($retryProc) ? stream_get_contents($retryPipes[2]) : '';
if (is_resource($retryProc)) {
    fclose($retryPipes[1]);
    fclose($retryPipes[2]);
    proc_close($retryProc);
}
expect(str_starts_with($retryOut, 'invalid 400 invalid_input'), 'a later process stores a blank SIM as 400, not 500: ' . trim($retryOut . ' ' . $retryErr));
expect(str_contains($retryOut, 'Een leeg telefoonnummer kan nog niet worden opgeslagen.'), 'blank save explains that an empty number cannot be stored yet');
expect(!str_contains($retryErr, 'UNIQUE constraint failed') && !str_contains($retryErr, 'simcards__blanknull'), 'a later process does not retry the failed rebuild');
$broken = new PDO('sqlite:' . $brokenFile);
$broken->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$schemaAfterRetry = (string) $broken->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'simcards'")->fetchColumn()
    . "\n" . (string) $broken->query("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'simcards_telefoonnummer_norm_unique'")->fetchColumn();
expect($schemaAfterRetry === $schemaBeforeRetry, 'a later process leaves the old simcards schema untouched');
expect((int) $broken->query('SELECT COUNT(*) FROM simcards')->fetchColumn() === 2, 'a later process does not insert or drop the existing rows');
$broken = null;
$legacyDb = null;
@unlink($legacyFile);
@unlink($brokenFile);
@unlink($brokenFile . '.sim-blank-phone-skip');
@unlink($brokenFile . '.sim-blank-phone.lock');
@unlink($retryScript);

// Afgebroken wissel: alleen de tijdelijke tabel bestaat nog. Niet een lege simcards aanmaken.
$orphanFile = sys_get_temp_dir() . '/moirai_sim_orphan_' . bin2hex(random_bytes(4)) . '.sqlite';
$orphan = new PDO('sqlite:' . $orphanFile);
$orphan->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$orphan->exec("CREATE TABLE simcards__blanknull (
    code TEXT PRIMARY KEY,
    code_norm TEXT NOT NULL,
    telefoonnummer TEXT NOT NULL DEFAULT '',
    telefoonnummer_norm TEXT,
    fysieke_staat TEXT NOT NULL DEFAULT 'uitstekend',
    historie_json TEXT NOT NULL DEFAULT '[]',
    qr_geldig INTEGER NOT NULL DEFAULT 0
)");
$orphan->exec("INSERT INTO simcards__blanknull (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('KEEP', 'KEEP', '', NULL)");
$orphanThrew = false;
try {
    moirai_migrate_simcards_table($orphan);
} catch (Throwable $error) {
    $orphanThrew = true;
}
expect(!$orphanThrew, 'recovering a leftover temp table does not throw');
expect(moirai_sqlite_table_exists($orphan, 'simcards'), 'leftover temp table is renamed back to simcards');
expect(!moirai_sqlite_table_exists($orphan, 'simcards__blanknull'), 'leftover temp table name is gone after rename');
expect((int) $orphan->query("SELECT COUNT(*) FROM simcards WHERE code = 'KEEP' AND telefoonnummer_norm IS NULL")->fetchColumn() === 1, 'recovered sim keeps its NULL phone');
$orphan = null;
@unlink($orphanFile);

// Lege simcards naast een gevulde tijdelijke tabel: de lege tabel niet als bron gebruiken.
$shadowFile = sys_get_temp_dir() . '/moirai_sim_shadow_' . bin2hex(random_bytes(4)) . '.sqlite';
$shadow = new PDO('sqlite:' . $shadowFile);
$shadow->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$shadow->exec("CREATE TABLE simcards (
    code TEXT PRIMARY KEY,
    code_norm TEXT NOT NULL,
    telefoonnummer TEXT NOT NULL DEFAULT '',
    telefoonnummer_norm TEXT NOT NULL
)");
$shadow->exec("CREATE TABLE simcards__blanknull (
    code TEXT PRIMARY KEY,
    code_norm TEXT NOT NULL,
    telefoonnummer TEXT NOT NULL DEFAULT '',
    telefoonnummer_norm TEXT
)");
$shadow->exec("INSERT INTO simcards__blanknull (code, code_norm, telefoonnummer, telefoonnummer_norm) VALUES ('KEEP2', 'KEEP2', '', NULL)");
$shadowThrew = false;
try {
    moirai_migrate_simcards_table($shadow);
} catch (Throwable $error) {
    $shadowThrew = true;
}
expect(!$shadowThrew, 'shadowed empty simcards table does not throw');
expect((int) $shadow->query("SELECT COUNT(*) FROM simcards WHERE code = 'KEEP2' AND telefoonnummer_norm IS NULL")->fetchColumn() === 1, 'filled temp table replaces the empty simcards table');
expect(!moirai_sqlite_table_exists($shadow, 'simcards__blanknull'), 'temp table is gone after replacing the empty simcards table');
$shadow = null;
@unlink($shadowFile);

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
$blankEarly = moirai_save_device('simcard', ['code' => 'X', 'telefoonnummer' => ''], $users);
expect($blankEarly['telefoonnummer'] === '' && $blankEarly['telefoonnummer_norm'] === '' && $blankEarly['naam'] === '', 'blank sim allowed on create');
$blankStored = $pdo->query("SELECT telefoonnummer_norm FROM simcards WHERE code = 'X'")->fetchColumn();
expect($blankStored === null, 'blank phone norm is stored as NULL');
moirai_delete_device('simcard', 'X');
expect(invalid_message(static fn() => moirai_save_device('simcard', ['code' => 'X', 'telefoonnummer' => '12'], $users)) === LOC('moirai.error.phone_invalid'), 'invalid phone rejected');
$blankSpace = moirai_save_device('simcard', ['code' => 'X', 'telefoonnummer' => '   '], $users);
expect($blankSpace['telefoonnummer'] === '' && $pdo->query("SELECT telefoonnummer_norm FROM simcards WHERE code = 'X'")->fetchColumn() === null, 'whitespace-only phone counts as empty');
moirai_delete_device('simcard', 'X');
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

$apiBlank = dispatch('create', ['type' => 'simcard', 'code' => 'API-BLANK']);
expect($apiBlank['status'] === 201 && ($apiBlank['body']['device']['telefoonnummer'] ?? 'x') === '' && ($apiBlank['body']['device']['telefoonnummer_norm'] ?? 'x') === '', 'API create blank sim without phone');
$apiBlank2 = dispatch('create', ['type' => 'simcard', 'code' => 'API-BLANK-2', 'telefoonnummer' => '']);
expect($apiBlank2['status'] === 201, 'API create second blank sim');
$apiBadPhone = dispatch('create', ['type' => 'simcard', 'code' => 'API-BAD', 'telefoonnummer' => '12']);
expect($apiBadPhone['status'] === 400 && ($apiBadPhone['body']['error_code'] ?? '') === 'invalid_input' && ($apiBadPhone['body']['error'] ?? '') === LOC('moirai.error.phone_invalid'), 'API rejects invalid phone on blank-capable sim');
$apiFill = dispatch('update', ['type' => 'simcard', 'id' => 'API-BLANK', 'telefoonnummer' => '06 55 66 77 88']);
expect($apiFill['status'] === 200 && ($apiFill['body']['device']['telefoonnummer_norm'] ?? '') === '+31655667788', 'API update fills phone later');
$apiKeep = dispatch('update', ['type' => 'simcard', 'id' => 'API-BLANK', 'fysieke_staat' => 'netjes']);
expect(($apiKeep['body']['device']['telefoonnummer'] ?? '') === '06 55 66 77 88', 'API partial update keeps a later-filled phone');
$apiClear = dispatch('update', ['type' => 'simcard', 'id' => 'API-BLANK', 'telefoonnummer' => '']);
expect($apiClear['status'] === 200 && ($apiClear['body']['device']['telefoonnummer'] ?? 'x') === '', 'API update can clear the phone again');
$apiReuse = dispatch('create', ['type' => 'simcard', 'code' => 'API-REUSE', 'telefoonnummer' => '0655667788']);
expect($apiReuse['status'] === 201, 'cleared number can be reused on another sim');
$apiDupBlank = dispatch('create', ['type' => 'simcard', 'code' => 'API-BLANK', 'telefoonnummer' => '']);
expect($apiDupBlank['status'] === 400 && str_contains((string) ($apiDupBlank['body']['error'] ?? ''), 'Er bestaat al een SIM-kaart met code'), 'blank sim still rejects a duplicate code');

$blankA = moirai_save_device('simcard', ['code' => 'BLANK-B', 'telefoonnummer' => ''], $users);
$blankB = moirai_save_device('simcard', ['code' => 'BLANK-A', 'telefoonnummer' => ''], $users);
expect($blankA['code'] === 'BLANK-B' && $blankB['telefoonnummer_norm'] === '', 'two blank sims stored');
$codes = array_column(moirai_list_devices('simcard'), 'code');
$pos = static fn(string $code): int|false => array_search($code, $codes, true);
expect($pos('BLANK-A') !== false && $pos('BLANK-B') !== false && $pos('BLANK-A') < $pos('BLANK-B'), 'blank sims sort by code');
expect($pos('BLANK-B') < $pos('SIM-0002'), 'blank sims sort before numbered sims');
$listed = moirai_list_devices('simcard');
$findBlank = static fn(string $q): array => array_values(array_map(
    static fn(array $d): string => $d['code'],
    array_filter($listed, static fn(array $d): bool => moirai_device_matches_filter($d, $q, 'all', []))
));
expect($findBlank('blank-a') === ['BLANK-A'], 'search finds a blank sim by code');
expect($findBlank('0612345678') === ['8931 0400 1234 5678 901'], 'phone search does not match blank sims');
$filledLater = moirai_save_device('simcard', ['original_key' => 'BLANK-A', 'code' => 'BLANK-A', 'telefoonnummer' => '06-11111111'], $users);
expect($filledLater['telefoonnummer_norm'] === '+31611111111', 'UI save can fill a number later');
expect(invalid_message(static fn() => moirai_save_device('simcard', ['code' => 'BLANK-C', 'telefoonnummer' => '0611111111'], $users)) !== null, 'filled number stays unique');
$assignedBlank = moirai_assign_device('simcard', 'BLANK-B', ['email' => 'user@kvt.nl'], $users);
expect(moirai_device_status($assignedBlank) === 'assigned' && ($assignedBlank['telefoonnummer'] ?? 'x') === '', 'assign works without a phone number');
expect(moirai_device_display_name($assignedBlank) === 'BLANK-B', 'blank sim display name falls back to code');
$_GET = ['t' => 's', 'd' => 'BLANK-B'];
$_SERVER['REQUEST_URI'] = '/moirai/index.php?t=s&d=BLANK-B';
$blankLink = moirai_parse_deep_link_from_request();
expect(($blankLink['type'] ?? '') === 'simcard' && ($blankLink['deviceId'] ?? '') === 'BLANK-B', 'deep link resolves a blank sim by code');

$_SESSION['user'] = ['email' => 'ict@kvt.nl', 'name' => 'ICT Beheer', 'admin' => true];
moirai_budget_add_person('user@kvt.nl', 'Test User', '2024-01-01');
$purchase = moirai_budget_add_purchase('user@kvt.nl', ['datum' => '2026-01-15', 'prijs' => '100', 'telefoon' => '']);
moirai_save_device('phone', ['model' => 'Pixel', 'imei' => '350000000000099', 'aanschafdatum' => '2025-01-01'], $users);
moirai_assign_device('phone', '350000000000099', ['email' => 'user@kvt.nl'], $users);
$linked = moirai_budget_link_phone('350000000000099', (int) $purchase['id']);
expect(($linked['linked']['phone_imei'] ?? '') === '350000000000099', 'budget link still works while a blank sim exists');
moirai_budget_phone_key_changed('', '350000000000099');
expect(moirai_budget_phone_options('350000000000099')['linked']['phone_imei'] === '350000000000099', 'empty phone-key change does not drop a budget link');
moirai_save_device('phone', [
    'original_key' => '350000000000099',
    'model' => 'Pixel',
    'imei' => '350000000000100',
    'aanschafdatum' => '2025-01-01',
], $users);
expect(moirai_budget_phone_options('350000000000100')['linked']['phone_imei'] === '350000000000100', 'IMEI rename keeps the budget link next to blank sims');

$ui = (string) file_get_contents(__DIR__ . '/../web/index.php');
expect(preg_match("/name: 'telefoonnummer'[^\\n]*required:\\s*true/", $ui) !== 1, 'sim form does not require telefoonnummer');
expect(str_contains($ui, "phone || '—'"), 'empty sim number is shown as an em dash');
expect(str_contains((string) ($help['practice']['simcard'] ?? ''), 'optional'), 'API help describes telefoonnummer as optional');

foreach (['API-BLANK', 'API-BLANK-2', 'API-REUSE', 'BLANK-A', 'BLANK-B'] as $code) {
    if (moirai_get_device('simcard', $code) !== null) {
        moirai_delete_device('simcard', $code);
    }
}
moirai_delete_device('phone', '350000000000100');

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
