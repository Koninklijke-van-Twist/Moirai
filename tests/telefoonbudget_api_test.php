<?php

/**
 * CLI checks voor de telefoonbudget-acties in api.php (API-key). Run: php tests/telefoonbudget_api_test.php
 * Alle adressen en bedragen zijn verzonnen.
 */

declare(strict_types=1);

const MOIRAI_BUDGET_API_TEST_KEY = 'MOIRAI_BUDGET_API_TEST_ONLY_KEY';
const MOIRAI_BUDGET_API_TEST_LABEL = 'metisTest';

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

// --- Auth geweigerd: echte api.php in een subprocess (geen auth.php = geen geldige key) ---

function run_api_subprocess(array $get, array $server): array
{
    $code = '$_SERVER = array_merge($_SERVER, ' . var_export($server, true) . ');'
        . '$_GET = ' . var_export($get, true) . ';'
        . 'require ' . var_export(__DIR__ . '/../web/api.php', true) . ';';
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>/dev/null');
    $json = json_decode((string) $output, true);

    return is_array($json) ? $json : ['raw' => $output];
}

$noKey = run_api_subprocess(['action' => 'budget_get', 'email' => 'jan.jansen@kvt.nl'], ['REQUEST_METHOD' => 'GET']);
expect(($noKey['error_code'] ?? '') === 'api_key_missing', 'budget_get without key -> api_key_missing');
$wrongKey = run_api_subprocess(['action' => 'budget_get', 'email' => 'jan.jansen@kvt.nl'], ['REQUEST_METHOD' => 'GET', 'HTTP_X_API_KEY' => 'verkeerde-key']);
expect(($wrongKey['error_code'] ?? '') === 'unauthorized', 'budget_get with wrong key -> unauthorized');
$queryKey = run_api_subprocess(['action' => 'budget_add_purchase', 'api_key' => 'x'], ['REQUEST_METHOD' => 'POST']);
expect(($queryKey['error_code'] ?? '') === 'api_key_query', 'key in querystring -> api_key_query');

// --- In-process: zelfde dispatcher als api.php -------------------------------

$tmp = sys_get_temp_dir() . '/moirai_budget_api_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$GLOBALS['moirai_db_file'] = $tmp;
$GLOBALS['moirai_today'] = new DateTimeImmutable('2026-10-08');
$GLOBALS['apiKeys'] = [MOIRAI_BUDGET_API_TEST_LABEL => MOIRAI_BUDGET_API_TEST_KEY];
$GLOBALS['moirai_api_directory_users'] = [
    ['Id' => 'u1', 'Naam' => 'Jan Jansen', 'Email' => 'Jan.Jansen@kvt.nl'],
    ['Id' => 'u2', 'Naam' => 'Lies Peeters', 'Email' => 'lies.peeters@hunter.be'],
    ['Id' => 'u3', 'Naam' => 'Nog Geen Budget', 'Email' => 'nieuw@kvt.nl'],
];

require_once __DIR__ . '/../web/localization.php';
require_once __DIR__ . '/../web/moirai_api.php';

function api_call(string $action, array $params = [], string $method = 'GET'): array
{
    $_GET = $method === 'GET' ? $params : [];
    $_POST = $method === 'POST' ? $params : [];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_X_API_KEY'] = MOIRAI_BUDGET_API_TEST_KEY;
    $auth = moirai_api_authenticate();
    if ($auth === null) {
        return ['status' => 401, 'body' => ['ok' => false, 'error_code' => 'unauthorized']];
    }
    moirai_api_apply_actor((string) $auth['label']);
    try {
        return moirai_api_dispatch($action);
    } catch (InvalidArgumentException $error) {
        return moirai_api_from_invalid_argument($error);
    }
}

expect((moirai_api_authenticate(MOIRAI_BUDGET_API_TEST_KEY)['label'] ?? '') === MOIRAI_BUDGET_API_TEST_LABEL, 'same API-key mechanism authenticates');
expect(moirai_api_authenticate('fout') === null, 'wrong key rejected in-process');

$help = moirai_api_help();
$names = array_column($help['actions'], 'name');
expect(in_array('budget_get', $names, true) && in_array('budget_add_purchase', $names, true), 'help lists budget actions');

// Fouten.
$r = api_call('budget_get', ['email' => 'geen-email']);
expect($r['status'] === 400 && $r['body']['error_code'] === 'invalid_email', 'invalid_email 400');
$r = api_call('budget_get', []);
expect($r['status'] === 400 && $r['body']['error_code'] === 'invalid_email', 'missing email -> invalid_email');
$r = api_call('budget_get', ['email' => 'onbekend@kvt.nl']);
expect($r['status'] === 404 && $r['body']['error_code'] === 'person_not_found', 'person_not_found 404');
// Wel in Graph/de gebruikerslijst, maar niet op de handmatige telefoonbudgetlijst.
$r = api_call('budget_get', ['email' => 'nieuw@kvt.nl']);
expect($r['status'] === 404 && $r['body']['error_code'] === 'person_not_found', 'Graph user not on the list -> person_not_found');
$r = api_call('budget_add_purchase', ['email' => 'nieuw@kvt.nl', 'prijs' => '300'], 'POST');
expect($r['status'] === 404 && $r['body']['error_code'] === 'person_not_found', 'add purchase for Graph user not on the list -> person_not_found');
expect(moirai_budget_purchase_rows('nieuw@kvt.nl') === [] && moirai_budget_listed_row('nieuw@kvt.nl') === null, 'API never adds people to the list');
moirai_budget_add_person('nieuw@kvt.nl', 'Nog Geen Budget');
$r = api_call('budget_get', ['email' => 'nieuw@kvt.nl']);
expect($r['status'] === 404 && $r['body']['error_code'] === 'no_budget', 'no_budget for listed person without start date');
$r = api_call('budget_add_purchase', ['email' => 'nieuw@kvt.nl', 'prijs' => '300'], 'POST');
expect($r['status'] === 404 && $r['body']['error_code'] === 'no_budget', 'add purchase without start date -> no_budget');
$r = api_call('budget_add_purchase', ['email' => 'jan.jansen@kvt.nl', 'prijs' => '300']);
expect($r['status'] === 405 && $r['body']['error_code'] === 'method_not_allowed', 'add purchase via GET -> 405');
$r = moirai_budget_api_get('iemand@kvt.nl', static function (): array { throw new RuntimeException('graph down'); });
expect($r['status'] === 404 && $r['body']['error_code'] === 'person_not_found', 'Graph is not consulted anymore (no 503 for budget actions)');

// Opvragen.
moirai_budget_add_person('jan.jansen@kvt.nl', 'Jan Jansen', '2024-03-01');
moirai_budget_add_person('lies.peeters@hunter.be', 'Lies Peeters', '2025-01-15');
$r = moirai_budget_api_get('jan.jansen@kvt.nl', static function (): array { throw new RuntimeException('graph down'); });
expect($r['status'] === 200, 'listed person works even when Graph is down');
$r = api_call('budget_get', ['email' => 'JAN.JANSEN@KVT.NL']);
$b = $r['body'];
expect($r['status'] === 200 && $b['email'] === 'jan.jansen@kvt.nl', 'lookup is case-insensitive');
expect($b['naam'] === 'Jan Jansen' && $b['indiensttreding'] === '2024-03-01', 'name and start date');
expect($b['startbudget_cents'] === 60000 && $b['startbudget_eur'] === '600.00', 'startbudget cents + eur');
expect($b['opbouw_per_maand_cents'] === 2500 && $b['opbouw_per_maand_eur'] === '25.00', 'monthly accrual');
expect($b['budget_cents'] === 60000 && $b['budget_eur'] === '600.00', 'budget before first purchase');
expect($b['aankopen'] === [] && $b['laatste_aankoop'] === null && $b['telefoon_waarde'] === null, 'no purchases yet');
expect($b['volgende_opbouw'] === null, 'no next accrual before first purchase');
expect(is_array($b['rekenregels']) && count($b['rekenregels']) >= 5, 'rules summary included');
$r = api_call('budget', ['email' => 'Lies.Peeters@Hunter.be']);
expect($r['status'] === 200 && $r['body']['email'] === 'lies.peeters@hunter.be', 'alias budget + other domain');

// Toevoegen.
$r = api_call('budget_add_purchase', ['email' => 'jan.jansen@kvt.nl', 'prijs' => '450,00', 'datum' => '2025-01-31', 'telefoon' => 'Voorbeeldfoon 15', 'notitie' => "Ticket #123\nZwart", 'status' => 'bevestigd'], 'POST');
$a = $r['body'];
expect($r['status'] === 201 && $a['idempotent_replay'] === false, 'add purchase -> 201');
expect($a['aankoop']['status'] === 'onbevestigd' && $a['aankoop']['status_label'] === 'Onbevestigd', 'API purchase is always Onbevestigd (status param ignored)');
expect($a['aankoop']['telefoon'] === 'Voorbeeldfoon 15' && $a['aankoop']['notitie'] === "Ticket #123\nZwart", 'telefoon + notitie stored');
expect($a['aankoop']['budget_na_cents'] === 15000 && $a['eigen_bijdrage_cents'] === 0, 'what-if-confirmed figures returned');
expect($a['aankoop']['telt_mee'] === false && $a['budget']['budget_cents'] === 60000 && $a['budget']['totaal_besteed_cents'] === 0 && $a['budget']['onbevestigd_bedrag_cents'] === 45000, 'unconfirmed API purchase has no effect on the budget');
moirai_budget_set_status((int) $a['aankoop']['id'], MOIRAI_BUDGET_STATUS_CONFIRMED); // in de UI
$r = api_call('budget_add_purchase', ['email' => 'jan.jansen@kvt.nl', 'price' => '350', 'date' => '2025-02-28', 'client_ref' => 'asclepius-4711'], 'POST');
$c = $r['body'];
expect($r['status'] === 201 && $c['aankoop']['budget_voor_cents'] === 17500, 'budget on 28 feb includes one month (31 jan anchor)');
expect($c['eigen_bijdrage_cents'] === 17500 && $c['eigen_bijdrage_eur'] === '175.00' && $c['aankoop']['budget_na_cents'] === 0, 'own contribution 175 returned');
expect($c['aankoop']['client_ref'] === 'asclepius-4711', 'client_ref stored');

// Idempotent pad.
$countBefore = count(moirai_budget_purchase_rows('jan.jansen@kvt.nl'));
$r = api_call('budget_add_purchase', ['email' => 'Jan.Jansen@kvt.nl', 'price' => '350', 'date' => '2025-02-28', 'client_ref' => 'asclepius-4711'], 'POST');
expect($r['status'] === 200 && $r['body']['idempotent_replay'] === true && $r['body']['aankoop']['id'] === $c['aankoop']['id'], 'same client_ref returns existing purchase (200, replay)');
expect(count(moirai_budget_purchase_rows('jan.jansen@kvt.nl')) === $countBefore, 'no duplicate purchase');
$r = api_call('budget_add_purchase', ['email' => 'lies.peeters@hunter.be', 'price' => '100', 'client_ref' => 'asclepius-4711'], 'POST');
expect($r['status'] === 409 && $r['body']['error_code'] === 'client_ref_conflict', 'client_ref for other person -> 409');

// Validatie.
$r = api_call('budget_add_purchase', ['email' => 'jan.jansen@kvt.nl', 'prijs' => 'veel'], 'POST');
expect($r['status'] === 400 && $r['body']['error_code'] === 'invalid_amount', 'invalid_amount');
$r = api_call('budget_add_purchase', ['email' => 'jan.jansen@kvt.nl', 'prijs' => '10', 'datum' => '31-12-2025'], 'POST');
expect($r['status'] === 400 && $r['body']['error_code'] === 'invalid_date', 'invalid_date');
$r = api_call('budget_add_purchase', ['email' => 'lies.peeters@hunter.be', 'prijs' => '99.99'], 'POST');
expect($r['status'] === 201 && $r['body']['aankoop']['datum'] === '2026-10-08', 'date defaults to today');

// Opvragen na aankopen: c (28 feb) is nog onbevestigd en telt niet mee.
$b = api_call('budget_get', ['email' => 'jan.jansen@kvt.nl'])['body'];
expect($b['budget_cents'] === 15000 + 20 * 2500 && $b['laatste_aankoop'] === '2025-01-31' && $b['totaal_besteed_cents'] === 45000, 'budget_get ignores unconfirmed purchase (budget, accrual, last purchase)');
expect($b['onbevestigd_bedrag_cents'] === 35000 && $b['aankopen'][1]['telt_mee'] === false && $b['aankopen'][1]['eigen_bijdrage_cents'] === 17500, 'unconfirmed purchase listed with own contribution if confirmed');
expect($b['telefoon_waarde']['purchase_id'] === $a['aankoop']['id'], 'phone value only from confirmed purchases');
moirai_budget_set_status((int) $c['aankoop']['id'], MOIRAI_BUDGET_STATUS_CONFIRMED); // in de UI
$b = api_call('budget_get', ['email' => 'jan.jansen@kvt.nl'])['body'];
expect(count($b['aankopen']) === 2 && $b['laatste_aankoop'] === '2025-02-28', 'purchases listed, last purchase date');
expect($b['totaal_besteed_cents'] === 80000 && $b['totaal_besteed_eur'] === '800.00', 'total spent');
expect($b['totale_eigen_bijdrage_cents'] === 17500, 'total own contribution');
expect($b['budget_cents'] === 19 * 2500 && $b['budget_eur'] === '475.00', 'current budget: 19 whole months since 2025-02-28');
expect($b['volgende_opbouw'] === '2026-10-28', 'next accrual date');
expect($b['telefoon_waarde']['waarde_cents'] === 0 && $b['telefoon_waarde']['purchase_id'] === $c['aankoop']['id'], 'phone value from last purchase, floored at 0');
expect($b['aankopen'][0]['toestel'] === null, 'no linked device yet');

// Bevestigen / aanpassen / verwijderen zijn geen API-acties.
foreach (['budget_confirm', 'budget_update_purchase', 'budget_delete_purchase', 'confirm'] as $action) {
    $r = api_call($action, ['id' => $c['aankoop']['id']], 'POST');
    expect($r['status'] === 400 && $r['body']['error_code'] === 'unknown_action', "no API action {$action}");
}

// Persoon die alleen in de budgettabel staat (niet in de Moirai-gebruikerslijst),
// bijv. aangemaakt via de import of "Persoon toevoegen".
moirai_budget_add_person('piet.extern@hunter.be', 'Piet Extern', '2025-06-01');
$r = api_call('budget_get', ['email' => 'Piet.Extern@HUNTER.be']);
expect($r['status'] === 200 && $r['body']['naam'] === 'Piet Extern' && $r['body']['budget_cents'] === 60000, 'budget_get finds budget-table-only person');
$r = api_call('budget_add_purchase', ['email' => 'piet.extern@hunter.be', 'prijs' => '650,00', 'datum' => '2025-07-01', 'client_ref' => 'metis-piet-1'], 'POST');
expect($r['status'] === 201 && $r['body']['eigen_bijdrage_cents'] === 5000 && $r['body']['aankoop']['budget_voor_cents'] === 60000, 'budget_add_purchase for budget-table-only person, own contribution computed');
expect($r['body']['aankoop']['prijs_cents'] === 65000, 'bedrag is the full purchase price');
// Eigen bijdrage wordt niet opgeslagen: geen kolom, en een gewijzigde instelling werkt direct door.
$cols = array_column(moirai_db()->query('PRAGMA table_info(budget_purchases)')->fetchAll(), 'name');
expect(!in_array('eigen_bijdrage_cents', $cols, true) && !in_array('budget_voor_cents', $cols, true) && !in_array('budget_na_cents', $cols, true), 'no stored eigen_bijdrage/budget columns');
moirai_budget_save_settings(['start_cents' => '700', 'monthly_cents' => '25']);
$r = api_call('budget_get', ['email' => 'piet.extern@hunter.be']);
expect($r['body']['aankopen'][0]['eigen_bijdrage_cents'] === 0 && $r['body']['totale_eigen_bijdrage_cents'] === 0, 'own contribution recomputed after settings change');
moirai_budget_save_settings(['start_cents' => '600', 'monthly_cents' => '25']);

// employeeHireDate-diagnose via API: alleen aantallen.
expect(in_array('budget_hire_stats', $names, true), 'help lists budget_hire_stats');
$GLOBALS['moirai_graph_roles'] = ['User.Read.All'];
$GLOBALS['moirai_graph_fetch'] = static function (string $url): array {
    if (str_contains($url, 'employeeLeaveDateTime')) {
        throw new RuntimeException('graph_http_403:Authorization_RequestDenied');
    }
    return ['value' => [
        ['id' => 'x1', 'mail' => 'x1@kvt.nl', 'jobTitle' => 'Monteur', 'employeeHireDate' => '2015-03-01T00:00:00Z'],
        ['id' => 'x2', 'mail' => 'x2@kvt.nl', 'jobTitle' => 'Planner', 'employeeHireDate' => null],
    ]];
};
$r = api_call('budget_hire_stats');
expect($r['status'] === 200 && $r['body']['totaal'] === 2 && $r['body']['met_hire_date'] === 1 && $r['body']['jaar_min'] === 2015, 'budget_hire_stats returns counts');
expect(!preg_match('/@|\d{4}-\d{2}-\d{2}/', (string) json_encode($r['body'])), 'budget_hire_stats has no mails or dates per person');
$r = api_call('budget_hire_stats', [], 'POST');
expect($r['status'] === 200 && $r['body']['totaal'] === 2, 'budget_hire_stats also via POST (read-only, like budget_get)');
$GLOBALS['moirai_graph_fetch'] = static function (string $url): array { throw new RuntimeException('graph_http_403:Authorization_RequestDenied'); };
$r = api_call('budget_hire_stats');
expect($r['status'] === 502 && $r['body']['error_code'] === 'graph_failed' && $r['body']['ok'] === false, 'budget_hire_stats Graph failure -> 502 graph_failed');
unset($GLOBALS['moirai_graph_fetch'], $GLOBALS['moirai_graph_roles']);
@unlink($tmp . '.hire_dates.json');

expect(moirai_budget_add_months('2025-01-31', 1) === '2025-02-28' && moirai_budget_add_months('2024-01-31', 1) === '2024-02-29', 'add_months clamps to month end');

@unlink($tmp);
echo $failures === 0 ? "\nAll telefoonbudget API checks passed.\n" : "\n{$failures} failure(s).\n";
exit($failures === 0 ? 0 : 1);
