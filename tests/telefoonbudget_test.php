<?php

/**
 * CLI checks voor het tabblad Telefoonbudget. Run: php tests/telefoonbudget_test.php
 * Alle data is verzonnen.
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

function invalid_message(callable $fn): ?string
{
    try {
        $fn();
    } catch (InvalidArgumentException $error) {
        return $error->getMessage();
    }

    return null;
}

$tmp = sys_get_temp_dir() . '/moirai_budget_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$GLOBALS['moirai_db_file'] = $tmp;
$GLOBALS['moirai_today'] = new DateTimeImmutable('2026-10-08');
$GLOBALS['ictUsers'] = ['ict@kvt.nl'];
$GLOBALS['moirai_budget_directory_users'] = [
    ['Id' => 'u1', 'Naam' => 'Anna Testpersoon', 'Email' => 'anna@kvt.nl'],
    ['Id' => 'u2', 'Naam' => 'Bram Voorbeeld', 'Email' => 'bram@kvt.nl'],
    ['Id' => 'u3', 'Naam' => 'Carla Proef', 'Email' => 'carla@kvt.nl'],
    ['Id' => 'u4', 'Naam' => 'Dirk Fictief', 'Email' => 'dirk@kvt.nl'],
];

require_once __DIR__ . '/../web/localization.php';
require_once __DIR__ . '/../web/moirai_data.php';

// Na de requires: localization.php start een sessie.
// Zonder logincheck.php valt moirai_is_admin() terug op de admin-vlag in de sessie.
$_SESSION = ['user' => ['email' => 'ict@kvt.nl', 'name' => 'ICT Beheer', 'admin' => true]];

$defaults = [
    'start_cents' => 60000,
    'monthly_cents' => 2500,
    'max_cents' => 0,
    'depreciation_cents' => 2500,
];

// --- Hulpfuncties ------------------------------------------------------------

expect(moirai_budget_parse_cents('350') === 35000, 'parse 350');
expect(moirai_budget_parse_cents('349,95') === 34995, 'parse 349,95');
expect(moirai_budget_parse_cents('€ 1.234,5') === 123450, 'parse € 1.234,5');
expect(moirai_budget_parse_cents('1,234.56') === 123456, 'parse 1,234.56');
expect(moirai_budget_parse_cents(420.5) === 42050, 'parse spreadsheet float');
expect(moirai_budget_parse_cents(0.1 + 0.2) === 30, 'float rounding to cents');
foreach (['', 'abc', '-5', '0', '12,345,6x'] as $bad) {
    expect(invalid_message(static fn() => moirai_budget_parse_cents($bad)) !== null, "reject amount '{$bad}'");
}
expect(moirai_budget_format_cents(123456) === '€ 1.234,56', 'format cents');

expect(moirai_budget_months_between('2026-01-15', '2026-02-14') === 0, 'month: day not reached');
expect(moirai_budget_months_between('2026-01-15', '2026-02-15') === 1, 'month: day reached');
expect(moirai_budget_months_between('2026-01-31', '2026-02-28') === 1, 'month: 31st counts at end of February');
expect(moirai_budget_months_between('2025-11-10', '2026-10-09') === 10, 'month: across year');
expect(moirai_budget_months_between('2026-05-01', '2026-04-01') === 0, 'month: never negative');

// --- Pure budgetberekening ---------------------------------------------------

$tl = moirai_budget_timeline('2024-01-01', [], $defaults);
expect(moirai_budget_available_on('2024-01-01', [], '2026-10-08', $defaults) === 60000, 'start budget 600 without purchases');
expect(moirai_budget_available_on('2024-01-01', [], '2030-01-01', $defaults) === 60000, 'no accrual before first purchase');

$p = [['id' => 1, 'datum' => '2025-01-10', 'prijs_cents' => 40000, 'status' => 'bevestigd']];
$tl = moirai_budget_timeline('2024-01-01', $p, $defaults)['purchases'];
expect($tl[0]['eigen_bijdrage_cents'] === 0 && $tl[0]['budget_na_cents'] === 20000, 'purchase under budget: 600-400=200, no own contribution');
expect(moirai_budget_available_on('2024-01-01', $p, '2025-02-09', $defaults) === 20000, 'no accrual before month complete');
expect(moirai_budget_available_on('2024-01-01', $p, '2025-02-10', $defaults) === 22500, '+25 after one month');
expect(moirai_budget_available_on('2024-01-01', $p, '2025-05-10', $defaults) === 30000, '+25 x 4 months');

// Tims voorbeeld: budget 300, telefoon 350 -> eigen bijdrage 50, budget 0.
$p2 = [
    ['id' => 1, 'datum' => '2025-01-10', 'prijs_cents' => 30000, 'status' => 'bevestigd'],
    ['id' => 2, 'datum' => '2025-01-20', 'prijs_cents' => 35000, 'status' => 'bevestigd'],
];
$tl = moirai_budget_timeline('2024-01-01', $p2, $defaults)['purchases'];
expect($tl[1]['budget_voor_cents'] === 30000, 'example: budget 300 before second purchase');
expect($tl[1]['eigen_bijdrage_cents'] === 5000 && $tl[1]['budget_na_cents'] === 0, 'example: own contribution 50, budget 0');
expect(moirai_budget_available_on('2024-01-01', $p2, '2025-03-20', $defaults) === 5000, 'accrual restarts after last purchase (2 months)');

$capped = $defaults;
$capped['max_cents'] = 21000;
expect(moirai_budget_available_on('2024-01-01', $p, '2026-10-08', $capped) === 21000, 'max budget caps accrual');
expect(moirai_budget_available_on('2024-01-01', [], '2026-10-08', $capped) === 60000, 'max does not lower existing start budget');

// Datum in het verleden: preview rekent met het budget op die datum.
$pv = moirai_budget_preview('2024-01-01', $p, 25000, '2025-03-10', $defaults);
expect($pv['budget_voor_cents'] === 25000 && $pv['eigen_bijdrage_cents'] === 0 && $pv['budget_na_cents'] === 0, 'preview on past date uses budget on that date');
$pv = moirai_budget_preview('2024-01-01', $p, 70000, '2024-12-01', $defaults);
expect($pv['budget_voor_cents'] === 60000 && $pv['eigen_bijdrage_cents'] === 10000, 'preview before existing purchase uses start budget');
$pv = moirai_budget_preview('2024-01-01', $p, 10000, '2025-01-10', $defaults, 1);
expect($pv['budget_voor_cents'] === 60000, 'preview for edit excludes the purchase itself');

// Huidige waarde telefoon.
$v = moirai_budget_phone_value($p, '2025-05-10', $defaults);
expect($v['value_cents'] === 30000 && $v['months'] === 4, 'phone value: 400 - 4 x 25 = 300');
$v = moirai_budget_phone_value($p, '2040-01-01', $defaults);
expect($v['value_cents'] === 0, 'phone value never below 0');
expect(moirai_budget_phone_value([], '2025-01-01', $defaults) === null, 'phone value null without purchases');
$v = moirai_budget_phone_value($p2, '2025-01-20', $defaults);
expect($v['purchase_id'] === 2 && $v['value_cents'] === 35000, 'phone value uses latest purchase');

// --- Migratie ----------------------------------------------------------------

$pdo = moirai_db();
foreach (['budget_settings', 'budget_people', 'budget_purchases'] as $table) {
    $found = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchAll();
    expect(count($found) === 1, "table {$table} created on first connection");
}
moirai_init_schema($pdo);
moirai_init_schema($pdo);
expect(true, 'budget migration is idempotent');
expect((int) $pdo->query('SELECT COUNT(*) FROM budget_list')->fetchColumn() === 0, 'manual list starts empty on a fresh DB (no Graph import)');
expect(moirai_budget_list_people()['total'] === 0, 'Graph users are NOT shown automatically');
expect(invalid_message(static fn() => moirai_budget_set_start('anna@kvt.nl', '2024-01-01')) === LOC('budget.error.not_listed'), 'set_start refused for someone not on the list');
// De vier verzonnen collega's handmatig op de lijst zetten (zoals Tim via Persoon toevoegen).
foreach ($GLOBALS['moirai_budget_directory_users'] as $u) {
    moirai_budget_list_insert($u['Email'], $u['Naam']);
}
expect(moirai_budget_list_people()['total'] === 4, 'manually listed people are shown');
$seq = $pdo->query("SELECT sql FROM sqlite_master WHERE name = 'budget_purchases'")->fetchColumn();
expect(str_contains((string) $seq, 'AUTOINCREMENT'), 'purchases use AUTOINCREMENT id');

// --- Opslag + herberekening --------------------------------------------------

expect(invalid_message(static fn() => moirai_budget_add_purchase('anna@kvt.nl', ['prijs' => '100'])) === LOC('budget.error.no_start'), 'purchase requires start date');
$person = moirai_budget_set_start('anna@kvt.nl', '2024-01-01', 'Anna Testpersoon');
expect($person['budget_cents'] === 60000, 'start date gives 600');

$a = moirai_budget_add_purchase('anna@kvt.nl', ['prijs' => '400', 'datum' => '2025-01-10', 'telefoon' => 'Testfoon A1', 'notitie' => "Regel 1\nRegel 2"]);
expect($a['status'] === MOIRAI_BUDGET_STATUS_UNCONFIRMED, 'new purchase is unconfirmed');
expect($a['telefoon'] === 'Testfoon A1' && $a['notitie'] === "Regel 1\nRegel 2", 'telefoon and multi-line notitie stored');
expect($a['telt_mee'] === false && moirai_budget_person('anna@kvt.nl')['budget_cents'] === 60000, 'unconfirmed purchase has no effect on the budget');
expect((int) $a['eigen_bijdrage_cents'] === 0 && (int) $a['budget_na_cents'] === 20000, 'unconfirmed purchase shows what-if-confirmed figures');
expect(moirai_budget_person('anna@kvt.nl')['telefoon_waarde'] === null && moirai_budget_person('anna@kvt.nl')['laatste_aankoop'] === null, 'unconfirmed purchase: no phone value, no last purchase');
$a = moirai_budget_set_status((int) $a['id'], MOIRAI_BUDGET_STATUS_CONFIRMED);
expect($a['telt_mee'] === true && moirai_budget_person('anna@kvt.nl')['budget_cents'] === 20000 + 20 * 2500, 'confirmed purchase lowers the budget and restarts accrual');
$b = moirai_budget_add_purchase('anna@kvt.nl', ['prijs' => '350', 'datum' => '2025-03-10']);
expect((int) $b['id'] === (int) $a['id'] + 1, 'auto-increment id');
expect(moirai_budget_person('anna@kvt.nl')['budget_cents'] === 20000 + 20 * 2500, 'unconfirmed second purchase does not reset accrual');
$b = moirai_budget_set_status((int) $b['id'], MOIRAI_BUDGET_STATUS_CONFIRMED);
expect((int) $b['budget_voor_cents'] === 25000 && (int) $b['eigen_bijdrage_cents'] === 10000, 'stored own contribution 100 (budget 250, price 350)');
$default = moirai_budget_add_purchase('anna@kvt.nl', ['prijs' => '1']);
expect($default['datum'] === '2026-10-08', 'date defaults to today');
moirai_budget_delete_purchase((int) $default['id']);

// Eerdere aankoop aanpassen -> latere aankoop wordt herberekend.
moirai_budget_update_purchase((int) $a['id'], ['prijs' => '300']);
$b2 = moirai_budget_computed_purchase((int) $b['id']);
expect((int) $b2['budget_voor_cents'] === 35000 && (int) $b2['eigen_bijdrage_cents'] === 0, 'editing earlier purchase recalculates later one');
$a2 = moirai_budget_update_purchase((int) $a['id'], ['telefoon' => 'Testfoon A1 Pro', 'notitie' => '']);
expect($a2['telefoon'] === 'Testfoon A1 Pro' && $a2['notitie'] === '' && (int) $a2['prijs_cents'] === 30000, 'edit only text fields keeps price');

// Aankoop met datum in het verleden, vóór bestaande aankopen.
$early = moirai_budget_add_purchase('anna@kvt.nl', ['prijs' => '500', 'datum' => '2024-06-01']);
expect((int) moirai_budget_computed_purchase((int) $a['id'])['budget_voor_cents'] === 60000, 'unconfirmed past purchase does not change later purchases');
$early = moirai_budget_set_status((int) $early['id'], MOIRAI_BUDGET_STATUS_CONFIRMED);
$a3 = moirai_budget_computed_purchase((int) $a['id']);
expect((int) $early['budget_voor_cents'] === 60000 && (int) $early['budget_na_cents'] === 10000, 'past purchase uses budget on its date');
expect((int) $a3['budget_voor_cents'] === 10000 + 7 * 2500, 'later purchase recalculated after inserting past purchase');

// Verwijderen -> herberekening.
moirai_budget_delete_purchase((int) $early['id']);
$a4 = moirai_budget_computed_purchase((int) $a['id']);
expect((int) $a4['budget_voor_cents'] === 60000, 'deleting purchase recalculates timeline');
expect(invalid_message(static fn() => moirai_budget_purchase_row((int) $early['id'])) === LOC('budget.error.purchase_not_found'), 'deleted purchase is gone');

// Indiensttreding aanpassen kan altijd.
$person = moirai_budget_set_start('anna@kvt.nl', '2023-05-01');
expect($person['indiensttreding'] === '2023-05-01' && $person['naam'] === 'Anna Testpersoon', 'start date editable, name kept');
expect($person['budget_cents'] === 18 * 2500, 'current budget: rest 0 after 2025-03-10 + 18 whole months');
expect($person['telefoon_waarde']['value_cents'] === max(0, 35000 - 18 * 2500), 'person phone value');

// --- Statusovergangen --------------------------------------------------------

$id = (int) $a['id'];
moirai_budget_set_status($id, MOIRAI_BUDGET_STATUS_UNCONFIRMED);
expect(moirai_budget_set_status($id, MOIRAI_BUDGET_STATUS_CONFIRMED)['status'] === 'bevestigd', 'confirm: onbevestigd -> bevestigd');
expect(invalid_message(static fn() => moirai_budget_set_status($id, MOIRAI_BUDGET_STATUS_CONFIRMED)) === LOC('budget.error.status_transition'), 'cannot confirm twice');
expect(moirai_budget_set_status($id, MOIRAI_BUDGET_STATUS_UNCONFIRMED)['status'] === 'onbevestigd', 'unconfirm: bevestigd -> onbevestigd');
expect(invalid_message(static fn() => moirai_budget_set_status($id, 'gek')) === LOC('budget.error.status_transition'), 'unknown status rejected');
// Met a onbevestigd telt alleen b (350 op 2025-03-10, budget 600 -> rest 250): 250 + 18 x 25.
expect(moirai_budget_person('anna@kvt.nl')['budget_cents'] === 25000 + 18 * 2500, 'unconfirmed purchase excluded from the budget');
moirai_budget_set_status($id, MOIRAI_BUDGET_STATUS_CONFIRMED);
$before = moirai_budget_person('anna@kvt.nl')['budget_cents'];
expect($before === 18 * 2500, 'after confirming, the purchase counts again');

// --- Rechten + CSRF ----------------------------------------------------------

$csrf = moirai_budget_csrf_token();
[$status] = moirai_budget_api_dispatch('people', 'GET', [], '');
expect($status === 200, 'admin can list people');
$_SESSION['user'] = ['email' => 'gewoon@kvt.nl', 'name' => 'Geen Admin'];
foreach (['people', 'person', 'add_purchase', 'confirm', 'delete_purchase', 'import_commit', 'save_settings', 'link_phone', 'hire_suggestion', 'hire_diagnose'] as $action) {
    [$status, $body] = moirai_budget_api_dispatch($action, 'POST', ['email' => 'anna@kvt.nl', 'id' => $id, 'prijs' => '10'], $csrf);
    expect($status === 403 && $body['ok'] === false, "non-admin forbidden: {$action}");
}
$count = (int) $pdo->query('SELECT COUNT(*) FROM budget_purchases')->fetchColumn();
expect($count === 2, 'non-admin changed nothing');
$_SESSION['user'] = ['email' => 'ict@kvt.nl', 'name' => 'ICT Beheer', 'admin' => true];
[$status] = moirai_budget_api_dispatch('add_purchase', 'POST', ['email' => 'anna@kvt.nl', 'prijs' => '10'], 'fout-token');
expect($status === 403, 'write without valid CSRF token rejected');
[$status] = moirai_budget_api_dispatch('add_purchase', 'GET', ['email' => 'anna@kvt.nl', 'prijs' => '10'], $csrf);
expect($status === 403, 'write via GET rejected');
[$status, $body] = moirai_budget_api_dispatch('add_purchase', 'POST', ['email' => 'anna@kvt.nl', 'prijs' => '10', 'datum' => '2026-10-01', 'telefoon' => 'X', 'notitie' => 'Y'], $csrf);
expect($status === 200 && $body['purchase']['status'] === 'onbevestigd', 'admin with CSRF can add purchase');
[$status] = moirai_budget_api_dispatch('delete_purchase', 'POST', ['id' => $body['purchase']['id']], $csrf);
expect($status === 200, 'admin can delete purchase');
[$status, $body] = moirai_budget_api_dispatch('preview', 'GET', ['email' => 'anna@kvt.nl', 'prijs' => '1000', 'datum' => '2026-10-08'], '');
expect($status === 200 && $body['preview']['eigen_bijdrage_cents'] === 100000 - $before, 'preview endpoint');
[$status, $body] = moirai_budget_api_dispatch('people', 'GET', ['q' => 'bram'], '');
expect($body['total'] === 1 && $body['items'][0]['indiensttreding'] === null, 'search finds person without start date');
[$status, $body] = moirai_budget_api_dispatch('people', 'GET', ['filter' => 'with'], '');
expect($body['total'] === 1 && $body['items'][0]['email'] === 'anna@kvt.nl', 'filter with budget');
$page = moirai_budget_list_people('', 2, 3);
expect($page['page'] === 2 && $page['pages'] === 2 && count($page['items']) === 1, 'pagination');

// Instellingen globaal aanpasbaar + herberekening.
[$status, $body] = moirai_budget_api_dispatch('save_settings', 'POST', ['start_cents' => '650', 'monthly_cents' => '30,00'], $csrf);
expect($status === 200 && $body['settings']['start_cents'] === 65000 && $body['settings']['monthly_cents'] === 3000, 'settings saved in cents');
expect((int) moirai_budget_computed_purchase($id)['budget_voor_cents'] === 65000, 'settings change recalculates all purchases');
moirai_budget_save_settings(['start_cents' => '600', 'monthly_cents' => '25']);

// --- Telefoon koppelen -------------------------------------------------------

$users = $GLOBALS['moirai_budget_directory_users'];
moirai_save_device('phone', ['imei' => '350000000000001', 'model' => 'Testfoon A1', 'aanschafdatum' => '2025-01-10', 'os' => 'Android'], $users);
$opts = moirai_budget_phone_options('350000000000001');
expect($opts['email'] === '' && $opts['options'] === [], 'unassigned phone has no options');
moirai_assign_device('phone', '350000000000001', ['email' => 'anna@kvt.nl', 'naam' => 'Anna Testpersoon', 'id' => 'u1'], $users);
$opts = moirai_budget_phone_options('350000000000001');
expect(count($opts['options']) === 2 && $opts['options'][0]['id'] === (int) $b['id'], 'options: purchases of assignee, newest first');
moirai_budget_link_phone('350000000000001', $id);
expect(moirai_budget_purchase_row($id)['phone_imei'] === '350000000000001', 'phone linked to purchase');
moirai_budget_link_phone('350000000000001', (int) $b['id']);
expect(moirai_budget_purchase_row($id)['phone_imei'] === null && moirai_budget_purchase_row((int) $b['id'])['phone_imei'] === '350000000000001', 'one phone links to at most one purchase');
moirai_save_device('phone', ['imei' => '350000000000002', 'model' => 'Testfoon B', 'aanschafdatum' => '2025-01-10', 'os' => 'Android'], $users);
moirai_assign_device('phone', '350000000000002', ['email' => 'anna@kvt.nl', 'naam' => 'Anna Testpersoon', 'id' => 'u1'], $users);
expect(invalid_message(static fn() => moirai_budget_link_phone('350000000000002', (int) $b['id'])) === LOC('budget.error.link_taken'), 'one purchase links to at most one phone');
moirai_budget_set_start('bram@kvt.nl', '2024-01-01');
$bram = moirai_budget_add_purchase('bram@kvt.nl', ['prijs' => '100', 'datum' => '2025-01-01']);
expect(invalid_message(static fn() => moirai_budget_link_phone('350000000000002', (int) $bram['id'])) === LOC('budget.error.link_wrong_person'), 'purchase must belong to assignee');
$detail = moirai_budget_person('anna@kvt.nl');
$linkedRow = array_values(array_filter($detail['purchases'], static fn(array $p): bool => $p['phone_imei'] !== null))[0];
expect(str_contains((string) $linkedRow['phone_label'], 'Testfoon A1'), 'person modal shows linked phone');
moirai_save_device('phone', ['original_key' => '350000000000001', 'imei' => '350000000000009', 'model' => 'Testfoon A1', 'aanschafdatum' => '2025-01-10', 'os' => 'Android'], $users);
expect(moirai_budget_purchase_row((int) $b['id'])['phone_imei'] === '350000000000009', 'link follows IMEI change');
[$status] = moirai_budget_api_dispatch('link_phone', 'POST', ['imei' => '350000000000009', 'purchase_id' => 0], $csrf);
expect($status === 200 && moirai_budget_purchase_row((int) $b['id'])['phone_imei'] === null, 'unlink via purchase_id 0');
moirai_budget_link_phone('350000000000009', (int) $b['id']);
moirai_delete_device('phone', '350000000000009');
expect(moirai_budget_purchase_row((int) $b['id'])['phone_imei'] === null, 'deleting phone unlinks purchase');

// --- Importer (verzonnen fixture) -------------------------------------------

$fixture = __DIR__ . '/fixtures/telefoonbudget_fictief.xls';
$sheet = moirai_spreadsheet_read($fixture, 'telefoonbudget_fictief.xls');
expect(trim((string) $sheet[0][1]) === 'persoon' && count($sheet) === 7, 'xls reader reads header and rows');
$rows = moirai_budget_import_parse($sheet);
expect(count($rows) === 6, 'import parses 6 rows');
expect($rows[0]['datum'] === '2023-01-10' && $rows[0]['prijs_cents'] === 34900 && $rows[0]['status'] === 'bevestigd', 'row: date, cents, default status bevestigd');
expect($rows[1]['prijs_cents'] === 42050 && $rows[1]['telefoon'] === 'Testfoon A2' && $rows[1]['notitie'] === 'Hoesje en screenprotector erbij', 'row: telefoon + notitie from Excel');
expect($rows[2]['persoon'] === 'Bram Voorbeeld', 'person name whitespace normalized');
expect($rows[3]['hash'] !== $rows[4]['hash'], 'identical rows get distinct hashes (occurrence)');

// xlsx-variant van dezelfde structuur, met statuskolom.
$xlsx = sys_get_temp_dir() . '/moirai_budget_' . bin2hex(random_bytes(4)) . '.xlsx';
$zip = new ZipArchive();
$zip->open($xlsx, ZipArchive::CREATE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Blad1" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>datum</t></si><si><t>persoon</t></si><si><t>bedrag</t></si><si><t>status</t></si><si><t>carla@kvt.nl</t></si><si><t>onbevestigd</t></si></sst>');
$zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
    . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
    . '<row r="2"><c r="A2"><v>45658</v></c><c r="B2" t="s"><v>4</v></c><c r="C2"><v>123.4</v></c><c r="D2" t="s"><v>5</v></c></row>'
    . '<row r="3"><c r="A3" t="inlineStr"><is><t>15-02-2025</t></is></c><c r="B3" t="s"><v>4</v></c><c r="C3" t="inlineStr"><is><t>abc</t></is></c></row>'
    . '</sheetData></worksheet>');
$zip->close();
$xrows = moirai_budget_import_parse(moirai_spreadsheet_read($xlsx, 'x.xlsx'));
expect($xrows[0]['datum'] === '2025-01-01' && $xrows[0]['prijs_cents'] === 12340 && $xrows[0]['status'] === 'onbevestigd', 'xlsx: serial date, amount, status column respected');
expect($xrows[1]['error'] === 'bedrag', 'xlsx: invalid amount flagged');
@unlink($xlsx);
expect(invalid_message(static fn() => moirai_budget_import_parse([['naam', 'iets']])) !== null, 'missing required columns rejected');

// Matching.
$people = moirai_budget_all_people();
$m = moirai_budget_match_person('Anna Testpersoon', $people);
expect($m['zeker'] && $m['email'] === 'anna@kvt.nl', 'exact name match is certain');
$m = moirai_budget_match_person('CARLA@kvt.nl', $people);
expect($m['zeker'] && $m['email'] === 'carla@kvt.nl', 'email match is certain');
$m = moirai_budget_match_person('Brem Voorbeeldt', $people);
expect(!$m['zeker'] && $m['kandidaten'][0] === 'bram@kvt.nl', 'fuzzy name gives candidate, needs manual choice');
$m = moirai_budget_match_person('Kees Onbekend', $people);
expect(!$m['zeker'] && $m['email'] === null, 'unknown person not matched');

$preview = moirai_budget_import_preview($rows);
expect($preview['tellingen']['nieuw'] === 6 && $preview['tellingen']['fout'] === 0, 'preview counts');
$byKey = array_column($preview['personen'], null, 'key');
expect($byKey['anna testpersoon']['zeker'] && $byKey['anna testpersoon']['aantal'] === 2, 'preview groups rows per person');
expect($byKey['kees onbekend']['email'] === null, 'preview lists unrecognised person');
expect($byKey['anna testpersoon']['indiensttreding'] === '2023-05-01', 'preview shows existing start date');

$mapping = [
    'anna testpersoon' => 'anna@kvt.nl',
    'bram voorbeeld' => 'bram@kvt.nl',
    'brem voorbeeldt' => 'bram@kvt.nl',
    'kees onbekend' => 'dirk@kvt.nl',
];
expect(str_contains((string) invalid_message(static fn() => moirai_budget_import_commit($rows, $mapping, [])), 'dirk@kvt.nl'), 'commit requires start date for new person');
$beforeCount = (int) $pdo->query('SELECT COUNT(*) FROM budget_purchases')->fetchColumn();
expect($beforeCount === 3, 'nothing saved when start date missing');
$result = moirai_budget_import_commit($rows, $mapping, ['dirk@kvt.nl' => '2022-09-01']);
expect($result['toegevoegd'] === 6 && $result['personen'] === 3, 'import adds 6 purchases for 3 people');
$dirk = moirai_budget_person('dirk@kvt.nl');
expect($dirk['indiensttreding'] === '2022-09-01' && count($dirk['purchases']) === 2, 'new person created with asked start date');
expect($dirk['purchases'][0]['status'] === 'bevestigd' && $dirk['purchases'][0]['geimporteerd'], 'imported purchases are bevestigd');
expect($dirk['purchases'][1]['eigen_bijdrage_cents'] === 0 && $dirk['purchases'][1]['budget_voor_cents'] === 35000, 'imported purchases recalculated');
$again = moirai_budget_import_commit(moirai_budget_import_parse($sheet), $mapping, []);
expect($again['toegevoegd'] === 0 && $again['overgeslagen'] === 6, 'second import adds nothing (idempotent)');
expect(moirai_budget_import_preview($rows)['tellingen']['al_geimporteerd'] === 6, 'preview marks already imported rows');
$skip = moirai_budget_import_commit($rows, ['anna testpersoon' => ''], []);
expect($skip['toegevoegd'] === 0, 'skipped person imports nothing');

// Import via API: sessie-token + CSRF.
$upload = sys_get_temp_dir() . '/moirai_budget_upload_' . bin2hex(random_bytes(4));
copy($fixture, $upload);
[$status, $body] = moirai_budget_api_dispatch('import_preview', 'POST', [], $csrf, ['file' => ['name' => 'fictief.xls', 'tmp_name' => $upload, 'error' => UPLOAD_ERR_OK]]);
expect($status === 200 && $body['tellingen']['al_geimporteerd'] === 6 && is_string($body['token']), 'import_preview endpoint');
[$status] = moirai_budget_api_dispatch('import_commit', 'POST', ['token' => 'verkeerd', 'mapping' => $mapping], $csrf);
expect($status === 400, 'import_commit with wrong token rejected');
[$status, $commit] = moirai_budget_api_dispatch('import_commit', 'POST', ['token' => $body['token'], 'mapping' => $mapping], $csrf);
expect($status === 200 && $commit['result']['toegevoegd'] === 0, 'import_commit endpoint is idempotent');
[$status] = moirai_budget_api_dispatch('import_preview', 'POST', [], $csrf, ['file' => ['name' => 'evil.php', 'tmp_name' => $upload, 'error' => UPLOAD_ERR_OK]]);
expect($status === 400, 'only .xls/.xlsx accepted');
@unlink($upload);

// --- Naammatching: spaties/hoofdletters, tussenvoegsels, volgorde ----------

$names = [
    'jan.berg@kvt.nl' => ['email' => 'jan.berg@kvt.nl', 'naam' => 'Jan van den Berg'],
    'jose@kvt.nl' => ['email' => 'jose@kvt.nl', 'naam' => 'José Müller'],
    'tom.vries@kvt.nl' => ['email' => 'tom.vries@kvt.nl', 'naam' => "Tom de Vries"],
    'tom.vries2@kvt.nl' => ['email' => 'tom.vries2@kvt.nl', 'naam' => "Tom van Vries"],
];
expect(moirai_budget_name_key('Berg, Jan van den') === moirai_budget_name_key('jan  VAN DEN berg'), 'name key ignores particles, case, spaces and order');
foreach (['jan  VAN DEN berg', 'Berg, Jan van den', 'Berg van den Jan', 'Jan v.d. Berg', 'Jan Berg'] as $variant) {
    $m = moirai_budget_match_person($variant, $names);
    expect($m['zeker'] && $m['email'] === 'jan.berg@kvt.nl', "certain match: {$variant}");
}
$m = moirai_budget_match_person('Jose Muller', $names);
expect($m['zeker'] && $m['email'] === 'jose@kvt.nl', 'accents normalised');
$m = moirai_budget_match_person('Vries, Tom', $names);
expect(!$m['zeker'] && $m['email'] === null && count(array_intersect($m['kandidaten'], ['tom.vries@kvt.nl', 'tom.vries2@kvt.nl'])) === 2, 'ambiguous after dropping particles -> unsure with both candidates');
$m = moirai_budget_match_person('Tom de Vries', $names);
expect($m['zeker'] && $m['email'] === 'tom.vries@kvt.nl', 'exact name wins over particle-less ambiguity');
$m = moirai_budget_match_person('Jan', $names);
expect(!$m['zeker'], 'first name only is never certain');
$m = moirai_budget_match_person('Lies.Peeters@Hunter.be', $names);
expect(!$m['zeker'] && $m['email'] === null && $m['kandidaten'] === ['lies.peeters@hunter.be'], 'email in Excel not on the list is asked (never auto-added)');
$m = moirai_budget_match_person('Jan.Berg@KVT.nl', $names);
expect($m['zeker'] && $m['email'] === 'jan.berg@kvt.nl', 'email in Excel on the list is certain');
$m = moirai_budget_match_person('Willem Graaf', $names, ['willem@kvt.nl' => ['email' => 'willem@kvt.nl', 'naam' => 'Willem Graaf']]);
expect(!$m['zeker'] && $m['email'] === null && $m['kandidaten'] === ['willem@kvt.nl'], 'Graph-only user is a candidate, never certain');

// --- Personen buiten de gebruikerslijst ---------------------------------------

$extRows = moirai_budget_import_parse([
    ['datum', 'persoon', 'soort telefoon', 'bedrag'],
    ['2025-03-01', 'Lies  Peeters', 'Voorbeeldfoon 12', '650,00'],
    ['2025-09-01', 'Zonder Functie', 'Voorbeeldfoon 13', '200'],
]);
$extPreview = moirai_budget_import_preview($extRows);
$extByKey = array_column($extPreview['personen'], null, 'key');
expect(!$extByKey['lies peeters']['zeker'] && $extByKey['lies peeters']['email'] === null, 'unknown name is asked, not guessed');
expect(str_contains((string) invalid_message(static fn() => moirai_budget_import_commit($extRows, ['lies peeters' => 'lies.peeters@hunter.be'], [])), 'lies.peeters@hunter.be'), 'new outside person needs a start date');
expect(invalid_message(static fn() => moirai_budget_import_commit($extRows, ['lies peeters' => 'geen-email'], ['geen-email' => '2024-01-01'])) === LOC('budget.error.email_invalid'), 'invalid email in mapping rejected');
$ext = moirai_budget_import_commit($extRows, ['lies peeters' => 'Lies.Peeters@hunter.be', 'zonder functie' => ''], ['lies.peeters@hunter.be' => '2024-02-01']);
expect($ext['toegevoegd'] === 1 && $ext['overgeslagen'] === 1, 'outside person imported, skipped person not');
$lies = moirai_budget_person('lies.peeters@hunter.be');
expect($lies['naam'] === 'Lies Peeters' && $lies['indiensttreding'] === '2024-02-01', 'outside person created with Excel name and start date');
expect($lies['purchases'][0]['prijs_cents'] === 65000 && $lies['purchases'][0]['eigen_bijdrage_cents'] === 5000 && $lies['purchases'][0]['budget_na_cents'] === 0, 'own contribution computed: max(0, price - budget)');
$listed = moirai_budget_list_people('peeters');
expect($listed['total'] === 1 && !isset($listed['items'][0]['in_directory']) && $listed['items'][0]['indiensttreding'] === '2024-02-01', 'outside person appears in the list');
$m = moirai_budget_match_person('Peeters, Lies', moirai_budget_all_people());
expect($m['zeker'] && $m['email'] === 'lies.peeters@hunter.be', 're-import matches the created outside person by name');

// Handmatig toevoegen in de UI.
[$status, $body] = moirai_budget_api_dispatch('add_person', 'POST', ['email' => 'nieuw.extern@hunter.be', 'naam' => '', 'indiensttreding' => '2025-01-01'], $csrf);
expect($status === 400 && $body['error'] === LOC('budget.error.name_required'), 'add_person requires a name');
[$status, $body] = moirai_budget_api_dispatch('add_person', 'POST', ['email' => 'geen email', 'indiensttreding' => '2025-01-01'], $csrf);
expect($status === 400 && $body['error'] === LOC('budget.error.email_invalid'), 'add_person rejects invalid email');
[$status] = moirai_budget_api_dispatch('add_person', 'POST', ['email' => 'nieuw.extern@hunter.be', 'naam' => 'Nieuw Extern', 'indiensttreding' => '2025-01-01'], 'fout');
expect($status === 403, 'add_person without valid CSRF token refused');
[$status, $body] = moirai_budget_api_dispatch('add_person', 'POST', ['email' => 'Nieuw.Extern@hunter.be', 'naam' => 'Nieuw Extern', 'indiensttreding' => '2025-01-01'], $csrf);
expect($status === 200 && $body['person']['email'] === 'nieuw.extern@hunter.be' && $body['person']['budget_cents'] === 60000, 'add_person creates outside person');
[$status, $body] = moirai_budget_api_dispatch('add_person', 'POST', ['email' => 'nieuw.extern@hunter.be', 'naam' => 'Nieuw Extern'], $csrf);
expect($status === 400 && $body['error'] === LOC('budget.error.already_listed', 'nieuw.extern@hunter.be'), 'add_person refuses a duplicate');
[$status, $body] = moirai_budget_api_dispatch('add_person', 'POST', ['email' => 'Zonder.Start@hunter.be', 'naam' => 'Zonder Start'], $csrf);
expect($status === 200 && $body['person']['indiensttreding'] === null && $body['person']['verwijderbaar'] === true, 'add_person without start date (optional)');

// --- Handmatige lijst: verwijderen, zoekhulp, geen automatische toevoeging ----

$GLOBALS['moirai_budget_directory_users'][] = ['Id' => 'u5', 'Naam' => 'Eva Alleengraph', 'Email' => 'eva@kvt.nl'];
expect(moirai_budget_list_people('eva')['total'] === 0, 'new Graph user does not appear in the list');
[$status, $body] = moirai_budget_api_dispatch('directory_search', 'GET', ['q' => 'eva'], '');
expect($status === 200 && array_column($body['items'], 'email') === ['eva@kvt.nl'], 'directory_search suggests Graph users');
[$status, $body] = moirai_budget_api_dispatch('directory_search', 'GET', ['q' => 'anna'], '');
expect($status === 200 && $body['items'] === [], 'directory_search hides people already on the list');
expect((int) $pdo->query("SELECT COUNT(*) FROM budget_list WHERE email = 'eva@kvt.nl'")->fetchColumn() === 0, 'directory_search saves nothing');
[$status, $body] = moirai_budget_api_dispatch('person', 'GET', ['email' => 'eva@kvt.nl'], '');
expect($status === 400 && $body['error'] === LOC('budget.error.not_listed'), 'person detail refused for unlisted Graph user');
$evaPreview = moirai_budget_import_preview(moirai_budget_import_parse([['datum', 'persoon', 'bedrag'], ['2025-01-01', 'Eva Alleengraph', '100'], ['2025-01-02', 'eva@kvt.nl', '100']]));
foreach ($evaPreview['personen'] as $ep) {
    expect(!$ep['zeker'] && $ep['email'] === null && $ep['kandidaten'][0]['email'] === 'eva@kvt.nl' && $ep['kandidaten'][0]['op_lijst'] === false, 'import: Graph-only person is asked, not auto-matched (' . $ep['bron'] . ')');
}

$annaDetail = moirai_budget_person('anna@kvt.nl');
expect($annaDetail['verwijderbaar'] === false && $annaDetail['aantal_aankopen'] > 0, 'person with purchases is not removable');
[$status] = moirai_budget_api_dispatch('remove_person', 'POST', ['email' => 'zonder.start@hunter.be'], 'fout');
expect($status === 403, 'remove_person without CSRF refused');
[$status] = moirai_budget_api_dispatch('remove_person', 'GET', ['email' => 'zonder.start@hunter.be'], $csrf);
expect($status === 403, 'remove_person via GET refused');
[$status, $body] = moirai_budget_api_dispatch('remove_person', 'POST', ['email' => 'anna@kvt.nl'], $csrf);
expect($status === 400 && $body['error'] === LOC('budget.error.remove_has_purchases') && moirai_budget_listed_row('anna@kvt.nl') !== null, 'server refuses removing someone with purchases');
moirai_budget_list_insert('onbevestigd@kvt.nl', 'Alleen Onbevestigd');
moirai_budget_set_start('onbevestigd@kvt.nl', '2024-01-01');
$onlyUnconfirmed = moirai_budget_add_purchase('onbevestigd@kvt.nl', ['prijs' => '10', 'datum' => '2025-01-01']);
expect(moirai_budget_person('onbevestigd@kvt.nl')['verwijderbaar'] === false, 'an unconfirmed purchase also blocks removal (UI flag)');
[$status] = moirai_budget_api_dispatch('remove_person', 'POST', ['email' => 'onbevestigd@kvt.nl'], $csrf);
expect($status === 400 && moirai_budget_listed_row('onbevestigd@kvt.nl') !== null, 'server refuses removal with only an unconfirmed purchase');
moirai_budget_delete_purchase((int) $onlyUnconfirmed['id']);
[$status, $body] = moirai_budget_api_dispatch('remove_person', 'POST', ['email' => 'Onbevestigd@kvt.nl'], $csrf);
expect($status === 200 && moirai_budget_listed_row('onbevestigd@kvt.nl') === null && moirai_budget_person_row('onbevestigd@kvt.nl') === null, 'remove_person removes list entry and start date');
[$status, $body] = moirai_budget_api_dispatch('remove_person', 'POST', ['email' => 'zonder.start@hunter.be'], $csrf);
expect($status === 200 && moirai_budget_list_people('zonder')['total'] === 0, 'removed person disappears from the list');
moirai_init_schema($pdo);
moirai_migrate_budget_tables($pdo);
expect(moirai_budget_listed_row('zonder.start@hunter.be') === null && moirai_budget_listed_row('onbevestigd@kvt.nl') === null, 'removed person does not come back after migration/seed');
[$status, $body] = moirai_budget_api_dispatch('remove_person', 'POST', ['email' => 'zonder.start@hunter.be'], $csrf);
expect($status === 400 && $body['error'] === LOC('budget.error.not_listed'), 'removing twice gives not_listed');

// --- employeeHireDate uit Microsoft Graph: alleen voorstel + diagnose ---------

expect(moirai_budget_graph_date('2019-03-31T22:00:00Z') === '2019-04-01', 'graph date converted to Europe/Amsterdam');
expect(moirai_budget_graph_date('2021-06-01T00:00:00Z') === '2021-06-01', 'graph date at UTC midnight keeps day');
expect(moirai_budget_graph_date('') === null && moirai_budget_graph_date(null) === null, 'empty graph date is null');
expect(moirai_budget_graph_date('geen datum') === null, 'invalid graph date is null');
expect(moirai_budget_graph_date('0001-01-01T00:00:00Z') === null, 'graph placeholder date ignored');

$graphUrls = [];
$GLOBALS['moirai_graph_fetch'] = static function (string $url) use (&$graphUrls): array {
    $graphUrls[] = $url;
    if (str_contains($url, 'skiptoken')) {
        return ['value' => [['id' => 'u3', 'mail' => 'carla@kvt.nl', 'employeeHireDate' => null]]];
    }
    return [
        'value' => [
            ['id' => 'u2', 'mail' => 'Bram@KVT.nl', 'employeeHireDate' => '2018-08-31T22:00:00Z'],
            ['id' => 'u4', 'mail' => '', 'employeeHireDate' => '2020-01-01T00:00:00Z'],
        ],
        '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users?$skiptoken=fictief',
    ];
};
$hireDates = moirai_budget_hire_dates(true);
expect($hireDates === ['bram@kvt.nl' => '2018-09-01'], 'hire dates: paged, lowercased, only filled values with mail');
expect(str_contains($graphUrls[0], '$select=id,mail,employeeHireDate') && str_contains($graphUrls[0], 'accountEnabled'), 'hire dates request selects employeeHireDate');
expect(count($graphUrls) === 2, 'hire dates follows nextLink');
$graphUrls = [];
moirai_budget_hire_dates();
expect($graphUrls === [], 'hire dates memoised (no extra Graph call)');
expect(is_file(moirai_budget_hire_cache_file()), 'hire dates cached next to the database');

$bramBefore = $pdo->query("SELECT indiensttreding FROM budget_people WHERE email = 'bram@kvt.nl'")->fetchColumn();
[$status, $body] = moirai_budget_api_dispatch('hire_suggestion', 'GET', ['email' => ' BRAM@kvt.nl '], '');
expect($status === 200 && $body['voorstel'] === '2018-09-01' && $body['email'] === 'bram@kvt.nl', 'hire_suggestion returns proposal (no CSRF needed for GET)');
[$status, $body] = moirai_budget_api_dispatch('hire_suggestion', 'GET', ['email' => 'carla@kvt.nl'], '');
expect($status === 200 && $body['voorstel'] === null, 'hire_suggestion null when field empty');
[$status, $body] = moirai_budget_api_dispatch('hire_suggestion', 'GET', ['email' => 'geen email'], '');
expect($status === 200 && $body['ok'] === true && $body['voorstel'] === null, 'hire_suggestion never errors on bad input (silently no proposal)');
[$status, $body] = moirai_budget_api_dispatch('person', 'GET', ['email' => 'bram@kvt.nl'], '');
expect($status === 200 && $body['person']['indiensttreding_voorstel'] === '2018-09-01', 'person detail carries indiensttreding_voorstel');
$bramAfter = $pdo->query("SELECT indiensttreding FROM budget_people WHERE email = 'bram@kvt.nl'")->fetchColumn();
expect($bramBefore === $bramAfter, 'proposal is never saved automatically');
[$status, $body] = moirai_budget_api_dispatch('set_start', 'POST', ['email' => 'bram@kvt.nl', 'naam' => 'Bram Voorbeeld', 'indiensttreding' => '2019-02-01'], $csrf);
expect($status === 200 && $body['person']['indiensttreding'] === '2019-02-01' && $body['person']['indiensttreding_voorstel'] === '2018-09-01', 'set_start saves the chosen date, proposal stays a proposal');

// Graph niet bereikbaar: geen voorstel, niets breekt.
$GLOBALS['moirai_graph_fetch'] = static function (string $url): array { throw new RuntimeException('graph_http_403:Authorization_RequestDenied'); };
expect(moirai_budget_hire_dates(true) === [], 'Graph failure gives empty proposals');
[$status, $body] = moirai_budget_api_dispatch('person', 'GET', ['email' => 'carla@kvt.nl'], '');
expect($status === 200 && $body['person']['indiensttreding_voorstel'] === null, 'person detail still works when Graph fails');
$cache = json_decode((string) file_get_contents(moirai_budget_hire_cache_file()), true);
expect(is_array($cache) && $cache['fout'] === 'graph_http_403:Authorization_RequestDenied' && $cache['datums'] === [], 'failure cached briefly (no retry storm)');
[$status, $body] = moirai_budget_api_dispatch('hire_suggestion', 'GET', ['email' => 'bram@kvt.nl'], '');
expect($status === 200 && $body['ok'] === true && $body['voorstel'] === null && !isset($body['error']), 'hire_suggestion silent when Graph fails');
$GLOBALS['moirai_graph_fetch'] = static function (string $url): array { return ['value' => [['id' => 'u2', 'mail' => 'bram@kvt.nl']]]; };
expect(moirai_budget_hire_dates(true) === [], 'missing employeeHireDate field gives no proposal');
$GLOBALS['moirai_graph_fetch'] = static function (string $url): array { return ['error' => ['code' => 'Request_Timeout']]; };
expect(moirai_budget_hire_dates(true) === [], 'unexpected Graph response gives no proposal');

// Diagnose: alleen aantallen.
$diagUsers = [
    ['id' => 'd1', 'mail' => 'een@kvt.nl', 'jobTitle' => 'Monteur', 'employeeHireDate' => '2004-02-29T23:00:00Z'],
    ['id' => 'd2', 'mail' => 'twee@kvt.nl', 'jobTitle' => 'Planner', 'employeeHireDate' => '2022-05-01T00:00:00Z'],
    ['id' => 'd3', 'mail' => 'drie@kvt.nl', 'jobTitle' => 'Verkoper', 'employeeHireDate' => null],
    ['id' => 'd4', 'mail' => 'vier@kvt.nl', 'jobTitle' => '', 'employeeHireDate' => '1999-01-01T00:00:00Z'],
];
$GLOBALS['moirai_graph_roles'] = ['User.Read.All'];
$GLOBALS['moirai_graph_fetch'] = static function (string $url) use ($diagUsers): array {
    if (str_contains($url, 'employeeLeaveDateTime')) {
        throw new RuntimeException('graph_http_403:Authorization_RequestDenied');
    }
    return ['value' => $diagUsers];
};
$diag = moirai_budget_hire_diagnose();
expect($diag['ok'] && $diag['totaal'] === 3 && $diag['met_hire_date'] === 2, 'diagnose counts only users Moirai shows (with job title)');
expect($diag['jaar_min'] === 2004 && $diag['jaar_max'] === 2022, 'diagnose reports year range (Amsterdam date)');
expect($diag['leave']['leesbaar'] === false && str_starts_with((string) $diag['leave']['fout'], 'graph_http_403'), 'diagnose: employeeLeaveDateTime 403 reported as not readable');
expect($diag['rechten'] === ['User.Read.All'], 'diagnose shows app roles');
$diagJson = json_encode($diag);
expect(!preg_match('/@|\d{4}-\d{2}-\d{2}|Monteur|d1/', $diagJson), 'diagnose output has no names, mails or dates per person');
expect(!is_file(moirai_budget_hire_cache_file()), 'diagnose clears the proposal cache');
[$status, $body] = moirai_budget_api_dispatch('hire_diagnose', 'GET', [], '');
expect($status === 200 && $body['ok'] && $body['totaal'] === 3, 'hire_diagnose via dispatcher (GET, admin)');

$GLOBALS['moirai_graph_fetch'] = static function (string $url) use ($diagUsers): array {
    if (str_contains($url, 'employeeLeaveDateTime')) {
        return ['value' => [['id' => 'd1', 'employeeLeaveDateTime' => '2027-01-01T00:00:00Z'], ['id' => 'd4', 'employeeLeaveDateTime' => '2027-01-01T00:00:00Z']]];
    }
    return ['value' => $diagUsers];
};
$diag = moirai_budget_hire_diagnose();
expect($diag['leave']['leesbaar'] === false && $diag['leave']['fout'] === 'missing_role:User-LifeCycleInfo.Read.All', 'diagnose: leave not trusted without lifecycle role');
$GLOBALS['moirai_graph_roles'] = ['User.Read.All', 'User-LifeCycleInfo.Read.All'];
$diag = moirai_budget_hire_diagnose();
expect($diag['leave']['leesbaar'] === true && $diag['leave']['gevuld'] === 1, 'diagnose: leave counted for shown users with lifecycle role');

$GLOBALS['moirai_graph_fetch'] = static function (string $url): array { throw new RuntimeException('graph_not_configured'); };
[$status, $body] = moirai_budget_api_dispatch('hire_diagnose', 'GET', [], '');
expect($status === 200 && $body['ok'] === false && $body['fout'] === 'graph_not_configured' && str_contains($body['error'], 'graph_not_configured'), 'hire_diagnose reports Graph failure');
unset($GLOBALS['moirai_graph_fetch'], $GLOBALS['moirai_graph_roles']);
@unlink(moirai_budget_hire_cache_file());

// --- Migratie: eenmalige seed van de handmatige lijst -------------------------

$seedDb = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$seedDb->exec("CREATE TABLE budget_people (email TEXT PRIMARY KEY, naam TEXT NOT NULL DEFAULT '', indiensttreding TEXT NOT NULL, bijgewerkt TEXT)");
$seedDb->exec("CREATE TABLE budget_purchases (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, datum TEXT NOT NULL, prijs_cents INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'onbevestigd', telefoon TEXT NOT NULL DEFAULT '', notitie TEXT NOT NULL DEFAULT '', phone_imei TEXT, import_hash TEXT, aangemaakt TEXT, aangemaakt_door TEXT)");
$seedDb->exec("INSERT INTO budget_people (email, naam, indiensttreding) VALUES ('start@kvt.nl', 'Met Start', '2024-01-01'), ('beide@kvt.nl', 'Beide', '2023-01-01')");
$seedDb->exec("INSERT INTO budget_purchases (email, datum, prijs_cents) VALUES ('beide@kvt.nl', '2025-01-01', 100), ('wees@kvt.nl', '2025-02-01', 200), ('wees@kvt.nl', '2025-03-01', 300)");
moirai_migrate_budget_tables($seedDb);
$seeded = array_column($seedDb->query('SELECT email FROM budget_list ORDER BY email')->fetchAll(), 'email');
expect($seeded === ['beide@kvt.nl', 'start@kvt.nl', 'wees@kvt.nl'], 'seed: everyone with a start date or purchases, no duplicates, no Graph-only users');
expect($seedDb->query("SELECT naam FROM budget_list WHERE email = 'start@kvt.nl'")->fetchColumn() === 'Met Start', 'seed keeps the stored name');
expect($seedDb->query("SELECT value FROM budget_meta WHERE name = 'manual_list_seed_count'")->fetchColumn() === '3', 'seed count recorded');
$seedDb->exec("DELETE FROM budget_list WHERE email = 'start@kvt.nl'");
$seedDb->exec("INSERT INTO budget_people (email, naam, indiensttreding) VALUES ('later@kvt.nl', 'Later', '2024-01-01')");
moirai_migrate_budget_tables($seedDb);
expect(array_column($seedDb->query('SELECT email FROM budget_list ORDER BY email')->fetchAll(), 'email') === ['beide@kvt.nl', 'wees@kvt.nl'], 'seed runs only once: removed person stays removed, nothing auto-added later');

// --- Migratie: oude opgeslagen kolommen worden veilig verwijderd --------------

$legacy = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$legacy->exec('CREATE TABLE budget_purchases (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, datum TEXT NOT NULL, prijs_cents INTEGER NOT NULL,
    eigen_bijdrage_cents INTEGER NOT NULL DEFAULT 0, budget_voor_cents INTEGER NOT NULL DEFAULT 0, budget_na_cents INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT \'onbevestigd\', telefoon TEXT NOT NULL DEFAULT \'\', notitie TEXT NOT NULL DEFAULT \'\', phone_imei TEXT, import_hash TEXT,
    aangemaakt TEXT NOT NULL DEFAULT \'\', aangemaakt_door TEXT NOT NULL DEFAULT \'\')');
$legacy->exec("INSERT INTO budget_purchases (email, datum, prijs_cents, eigen_bijdrage_cents) VALUES ('anna@kvt.nl', '2025-01-01', 70000, 99999)");
moirai_migrate_budget_tables($legacy);
moirai_migrate_budget_tables($legacy);
$legacyCols = array_column($legacy->query('PRAGMA table_info(budget_purchases)')->fetchAll(), 'name');
expect(!array_intersect(['eigen_bijdrage_cents', 'budget_voor_cents', 'budget_na_cents'], $legacyCols) && in_array('client_ref', $legacyCols, true), 'migration drops stored columns (idempotent)');
expect((int) $legacy->query('SELECT prijs_cents FROM budget_purchases')->fetchColumn() === 70000, 'migration keeps purchase data');

@unlink($tmp);
@unlink($tmp . '.hire_dates.json');
echo $failures === 0 ? "\nAll telefoonbudget checks passed.\n" : "\n{$failures} failure(s).\n";
exit($failures === 0 ? 0 : 1);
