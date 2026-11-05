<?php

/**
 * Historie fysieke staat (24u na teruggave) + notitie-assignee (12u na teruggave).
 * Run: php tests/historie_staat_notitie_assignee_test.php
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

$tmp = sys_get_temp_dir() . '/moirai_historie_staat_' . bin2hex(random_bytes(4)) . '.sqlite';
$GLOBALS['moirai_db_file'] = $tmp;

require_once __DIR__ . '/../web/moirai_data.php';

$users = [
    ['id' => 'u1', 'naam' => 'Alice', 'email' => 'alice@kvt.nl'],
    ['id' => 'u2', 'naam' => 'Bob', 'email' => 'bob@kvt.nl'],
];

$laptop = moirai_save_device('laptop', [
    'model' => 'ThinkPad State',
    'serienummer' => 'SN-STATE-1',
    'aanschafdatum' => '2024-01-01',
    'os' => 'Windows',
    'fysieke_staat' => 'uitstekend',
], $users, false);

$assigned = moirai_assign_device('laptop', 'SN-STATE-1', $users[0], $users);
expect(($assigned['uitgegeven_aan']['email'] ?? '') === 'alice@kvt.nl', 'assign Alice');
expect(($assigned['uitgegeven_staat'] ?? '') === 'uitstekend', 'receipt state stored on assign');

$returned = moirai_assign_device('laptop', 'SN-STATE-1', null, $users);
expect(($returned['uitgegeven_aan'] ?? null) === null, 'return clears assignee');
expect(($returned['uitgegeven_staat'] ?? null) === null, 'return clears receipt state');
$hist = $returned['historie_uitgegeven'] ?? [];
expect(count($hist) === 1, 'one history entry after return');
expect(($hist[0]['staat_ontvangst'] ?? '') === 'uitstekend', 'history receipt state');
expect(($hist[0]['staat_teruggave'] ?? '') === 'uitstekend', 'history return state at return');
expect(trim((string) ($hist[0]['teruggave_at'] ?? '')) !== '', 'teruggave_at set');

$within24 = moirai_save_device('laptop', [
    'original_key' => 'SN-STATE-1',
    'model' => 'ThinkPad State',
    'serienummer' => 'SN-STATE-1',
    'aanschafdatum' => '2024-01-01',
    'os' => 'Windows',
    'fysieke_staat' => 'beschadigd',
], $users, false);
$hist24 = $within24['historie_uitgegeven'] ?? [];
expect(($within24['fysieke_staat'] ?? '') === 'beschadigd', 'condition updated');
expect(($hist24[0]['staat_teruggave'] ?? '') === 'beschadigd', 'within 24h return state overwritten');
expect(($hist24[0]['staat_ontvangst'] ?? '') === 'uitstekend', 'receipt state unchanged after overwrite');

// Age teruggave_at beyond 24h and change condition again — must not overwrite.
$pdo = moirai_db();
$agedAt = (new DateTimeImmutable('now'))->modify('-25 hours')->format('c');
$histAged = $hist24;
$histAged[0]['teruggave_at'] = $agedAt;
$histAged[0]['staat_teruggave'] = 'beschadigd';
$pdo->prepare('UPDATE laptops SET historie_json = :h WHERE serienummer = :s')->execute([
    'h' => json_encode($histAged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    's' => 'SN-STATE-1',
]);

$afterWindow = moirai_save_device('laptop', [
    'original_key' => 'SN-STATE-1',
    'model' => 'ThinkPad State',
    'serienummer' => 'SN-STATE-1',
    'aanschafdatum' => '2024-01-01',
    'os' => 'Windows',
    'fysieke_staat' => 'netjes',
], $users, false);
expect(($afterWindow['fysieke_staat'] ?? '') === 'netjes', 'condition updated after window');
expect(
    ($afterWindow['historie_uitgegeven'][0]['staat_teruggave'] ?? '') === 'beschadigd',
    'after 24h return state not overwritten'
);

// Re-assign then return: new window starts.
$reassigned = moirai_assign_device('laptop', 'SN-STATE-1', $users[1], $users);
expect(($reassigned['uitgegeven_staat'] ?? '') === 'netjes', 'new receipt state on reassign');
$returned2 = moirai_assign_device('laptop', 'SN-STATE-1', null, $users);
expect(count($returned2['historie_uitgegeven'] ?? []) === 2, 'second history entry');
$last = $returned2['historie_uitgegeven'][1];
expect(($last['staat_ontvangst'] ?? '') === 'netjes', 'second receipt state');
expect(($last['gebruiker']['email'] ?? '') === 'bob@kvt.nl', 'second history user Bob');

// SIM cards: no physical-state tracking in history.
$sim = moirai_save_device('simcard', [
    'code' => 'sim-staat-1',
    'telefoonnummer' => '06 11112222',
    'fysieke_staat' => '4ff',
], $users, false);
$simAssigned = moirai_assign_device('simcard', 'sim-staat-1', $users[0], $users);
$simReturned = moirai_assign_device('simcard', 'sim-staat-1', null, $users);
$simHist = $simReturned['historie_uitgegeven'][0] ?? [];
expect(($simHist['staat_ontvangst'] ?? null) === null, 'sim history has no receipt state');
expect(($simHist['staat_teruggave'] ?? null) === null, 'sim history has no return state');
expect(($simReturned['uitgegeven_staat'] ?? null) === null, 'sim has no receipt column value');

// --- Note assignee snapshot (12h window) ---
$noteLaptop = moirai_save_device('laptop', [
    'model' => 'ThinkPad Note',
    'serienummer' => 'SN-NOTE-1',
    'aanschafdatum' => '2024-06-01',
    'os' => 'Windows',
    'fysieke_staat' => 'netjes',
], $users, false);
moirai_assign_device('laptop', 'SN-NOTE-1', $users[0], $users);
$noteReturned = moirai_assign_device('laptop', 'SN-NOTE-1', null, $users);

$snapWithin = moirai_device_assignee_snapshot_for_note($noteReturned);
expect(($snapWithin['captured'] ?? false) === true, 'snapshot captured after return');
expect(($snapWithin['email'] ?? '') === 'alice@kvt.nl', 'within 12h uses last return person');
expect(($snapWithin['naam'] ?? '') === 'Alice', 'within 12h uses last return name');

$viaThread = moirai_note_assignee_for_thread('device:laptops:SN-NOTE-1');
expect(($viaThread['email'] ?? '') === 'alice@kvt.nl', 'thread resolver within 12h');

// Age teruggave beyond 12h while still unassigned → Niet toegewezen.
$pdo = moirai_db();
$aged12 = (new DateTimeImmutable('now'))->modify('-13 hours')->format('c');
$histNote = $noteReturned['historie_uitgegeven'];
$histNote[0]['teruggave_at'] = $aged12;
$pdo->prepare('UPDATE laptops SET historie_json = :h WHERE serienummer = :s')->execute([
    'h' => json_encode($histNote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    's' => 'SN-NOTE-1',
]);
$agedDevice = moirai_get_device('laptop', 'SN-NOTE-1');
$snapAged = moirai_device_assignee_snapshot_for_note($agedDevice);
expect(($snapAged['captured'] ?? false) === true, 'aged snapshot still captured');
expect(($snapAged['naam'] ?? null) === null, 'after 12h unassigned → null naam');
expect(($snapAged['email'] ?? null) === null, 'after 12h unassigned → null email');

// Fresh return again, then reassign within 12h → current assignee wins.
moirai_assign_device('laptop', 'SN-NOTE-1', $users[0], $users);
$freshReturn = moirai_assign_device('laptop', 'SN-NOTE-1', null, $users);
$snapFresh = moirai_device_assignee_snapshot_for_note($freshReturn);
expect(($snapFresh['email'] ?? '') === 'alice@kvt.nl', 'fresh return still Alice');
$toBob = moirai_assign_device('laptop', 'SN-NOTE-1', $users[1], $users);
$snapBob = moirai_device_assignee_snapshot_for_note($toBob);
expect(($snapBob['email'] ?? '') === 'bob@kvt.nl', 'reassigned within 12h uses current assignee');

// --- KvtChat store captures assignee on add/edit ---
require_once __DIR__ . '/../web/lib/kvt-chat/KvtChat.php';
KvtChat::configure([
    'pdo' => moirai_db(),
    'viewer' => static fn(): array => ['email' => 'ict@kvt.nl', 'name' => 'ICT'],
    'is_admin' => static fn(): bool => true,
    'resolve_assignee' => static fn(string $threadKey): ?array => moirai_note_assignee_for_thread($threadKey),
    'migrate_device_notes' => false,
]);
$noteMsg = KvtChat::addMapped('device:laptops:SN-NOTE-1', 'Schermrand beschadigd');
expect(($noteMsg['assignee_captured'] ?? false) === true, 'note add captures assignee flag');
expect(($noteMsg['assignee_email'] ?? '') === 'bob@kvt.nl', 'note add stores current assignee Bob');
moirai_assign_device('laptop', 'SN-NOTE-1', null, $users);
$edited = KvtChat::editMapped((int) $noteMsg['id'], 'Schermrand beschadigd (update)');
expect(($edited['assignee_email'] ?? '') === 'bob@kvt.nl', 'note edit within 12h keeps last return person');
$legacyRow = KvtChat::store()->addMessage('device:laptops:SN-NOTE-1', 'ict@kvt.nl', 'ICT', 'legacy note', null);
$legacyMapped = KvtChat::mapMessage($legacyRow, KvtChat::viewer());
expect(($legacyMapped['assignee_captured'] ?? true) === false, 'legacy note without capture stays blank');

// Schema idempotent: second init keeps column.
moirai_init_schema(moirai_db());
$cols = moirai_db()->query('PRAGMA table_info(laptops)')->fetchAll();
$colNames = array_map(static fn(array $c): string => (string) ($c['name'] ?? ''), $cols);
expect(in_array('uitgegeven_staat', $colNames, true), 'uitgegeven_staat column present after re-init');

@unlink($tmp);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failure(s)\n");
    exit(1);
}
echo "\nAll historie/assignee checks passed.\n";
