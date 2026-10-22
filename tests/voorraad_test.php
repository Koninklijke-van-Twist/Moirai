<?php

/**
 * CLI checks voor het tabblad Voorraad (categorieën met aantal). Run: php tests/voorraad_test.php
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

$tmp = sys_get_temp_dir() . '/moirai_voorraad_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$GLOBALS['moirai_db_file'] = $tmp;
$_SESSION = ['user' => ['email' => 'tester@kvt.nl', 'name' => 'Tester', 'admin' => true]];

require_once __DIR__ . '/../web/localization.php';
require_once __DIR__ . '/../web/moirai_voorraad.php';
require_once __DIR__ . '/../web/moirai_print.php';

// --- Aanmaken -------------------------------------------------------------
$dp = moirai_voorraad_save(['naam' => '  DisplayPort   kabel ', 'eigenschappen' => "2 meter lang\nBlauwe uitvoering", 'aantal' => '5']);
expect($dp['naam'] === 'DisplayPort kabel', 'name is trimmed and whitespace collapsed');
expect($dp['aantal'] === 5, 'quantity stored as int');
expect($dp['eigenschappen'] === "2 meter lang\nBlauwe uitvoering", 'properties stored');

// --- Uniciteit (hoofdletterongevoelig) ------------------------------------
$dup = invalid_message(static fn() => moirai_voorraad_save(['naam' => 'displayport KABEL', 'aantal' => 1]));
expect($dup !== null, 'duplicate name (other case) is refused');
expect($dup !== null && str_contains($dup, 'displayport KABEL') && str_contains($dup, 'bestaat al'), 'duplicate error is a clear message: ' . (string) $dup);
$dup2 = invalid_message(static fn() => moirai_voorraad_save(['naam' => 'DisplayPort kabel', 'aantal' => 1]));
expect($dup2 !== null, 'exact duplicate name is refused');
$hdmi = moirai_voorraad_save(['naam' => 'HDMI kabel', 'eigenschappen' => '2 meter lang', 'aantal' => 0]);
expect($hdmi['id'] !== $dp['id'], 'other name with same properties is allowed (properties not unique)');
$rename = invalid_message(static fn() => moirai_voorraad_save(['id' => $hdmi['id'], 'naam' => 'DISPLAYPORT kabel']));
expect($rename !== null, 'renaming onto an existing name (other case) is refused');
$self = moirai_voorraad_save(['id' => $dp['id'], 'naam' => 'Displayport Kabel', 'eigenschappen' => $dp['eigenschappen']]);
expect($self['naam'] === 'Displayport Kabel' && $self['aantal'] === 5, 'changing case of own name is allowed and keeps quantity');
$rawDup = false;
try {
    moirai_voorraad_db()->prepare("INSERT INTO voorraad (naam, naam_norm) VALUES ('x', 'hdmi kabel')")->execute();
} catch (PDOException $e) {
    $rawDup = true;
}
expect($rawDup && count(moirai_voorraad_list()) === 2, 'db-level UNIQUE index blocks duplicate norm');
expect(invalid_message(static fn() => moirai_voorraad_save(['naam' => '   '])) !== null, 'empty name is refused');

// --- Aantal nooit onder 0 -------------------------------------------------
expect(invalid_message(static fn() => moirai_voorraad_save(['naam' => 'Muis', 'aantal' => '-1'])) !== null, 'negative quantity on create is refused');
expect(invalid_message(static fn() => moirai_voorraad_save(['naam' => 'Muis', 'aantal' => '1.5'])) !== null, 'non-integer quantity is refused');
$one = moirai_voorraad_save(['naam' => 'USB-C lader', 'eigenschappen' => '65W', 'aantal' => 1]);
$after = moirai_voorraad_issue($one['id']);
expect($after['aantal'] === 0, 'issue lowers quantity by 1');
$err = invalid_message(static fn() => moirai_voorraad_issue($one['id']));
expect($err !== null, 'issue at 0 is refused');
expect(moirai_voorraad_get($one['id'])['aantal'] === 0, 'quantity stays 0 after refused issue');
for ($i = 0; $i < 3; $i++) {
    invalid_message(static fn() => moirai_voorraad_issue($one['id']));
}
expect(moirai_voorraad_get($one['id'])['aantal'] === 0, 'repeated issue never goes below 0');
expect(moirai_voorraad_adjust($one['id'], 'add', '10')['aantal'] === 10, 'top up adds');
expect(invalid_message(static fn() => moirai_voorraad_adjust($one['id'], 'add', '-11')) !== null, 'adjust that would go below 0 is refused');
expect(moirai_voorraad_get($one['id'])['aantal'] === 10, 'quantity unchanged after refused adjust');
expect(moirai_voorraad_adjust($one['id'], 'add', '-10')['aantal'] === 0, 'adjust down to exactly 0 is allowed');
expect(moirai_voorraad_adjust($one['id'], 'set', '7')['aantal'] === 7, 'set quantity');
expect(invalid_message(static fn() => moirai_voorraad_adjust($one['id'], 'set', '-3')) !== null, 'set negative is refused');
expect(invalid_message(static fn() => moirai_voorraad_save(['id' => $one['id'], 'naam' => 'USB-C lader', 'aantal' => -2])) !== null, 'edit with negative quantity is refused');
$rawNeg = false;
try {
    moirai_voorraad_db()->exec('UPDATE voorraad SET aantal = -1 WHERE id = ' . (int) $one['id']);
} catch (PDOException $e) {
    $rawNeg = true;
}
expect($rawNeg, 'db CHECK constraint blocks negative quantity');

// --- Knoppen −1 / +1 (zoals de UI ze aanroept) ---------------------------
$step = moirai_voorraad_save(['naam' => 'Stap-test', 'aantal' => 0]);
expect(moirai_voorraad_adjust($step['id'], 'add', '1')['aantal'] === 1, '+1 increments 0 -> 1');
expect(moirai_voorraad_adjust($step['id'], 'add', '1')['aantal'] === 2, '+1 increments 1 -> 2');
expect(moirai_voorraad_issue($step['id'])['aantal'] === 1, '-1 decrements 2 -> 1');
expect(moirai_voorraad_issue($step['id'])['aantal'] === 0, '-1 decrements 1 -> 0');
expect(invalid_message(static fn() => moirai_voorraad_issue($step['id'])) !== null, '-1 at 0 is refused');
expect(moirai_voorraad_get($step['id'])['aantal'] === 0, '-1 never goes below 0');
expect(moirai_voorraad_adjust($step['id'], 'add', '1')['aantal'] === 1, '+1 works again after reaching 0');
moirai_voorraad_delete($step['id']);

// --- Zoeken, label, verwijderen -------------------------------------------
expect(count(moirai_voorraad_list('65w')) === 1, 'search matches properties case-insensitively');
$_SERVER['HTTP_HOST'] = 'sleutels.kvt.nl';
$_SERVER['SCRIPT_NAME'] = '/moirai/print_label.php';
$doc = moirai_build_voorraad_pos_document(moirai_voorraad_get($dp['id']));
expect(str_contains($doc['body'], '# Displayport Kabel'), 'label contains the name');
expect(str_contains($doc['body'], '@qr http://sleutels.kvt.nl/moirai/index.php?t=v&d=' . $dp['id']), 'label QR links directly to the category');
expect(str_contains($doc['body'], 'Blauwe uitvoering'), 'label lists properties');

$_SESSION = ['user' => ['email' => 'tester@kvt.nl', 'name' => 'Tester', 'admin' => true]];
moirai_ensure_kvt_chat();
KvtChat::addMapped(moirai_voorraad_notes_thread_key($dp['id']), 'Ligt in kast 3');
expect(count(KvtChat::listMapped(moirai_voorraad_notes_thread_key($dp['id']))) === 1, 'notes stored in shared notes store');
moirai_voorraad_delete($dp['id']);
expect(moirai_voorraad_get($dp['id']) === null, 'delete removes the category');
expect(KvtChat::listMapped(moirai_voorraad_notes_thread_key($dp['id'])) === [], 'delete removes the notes thread');
expect(invalid_message(static fn() => moirai_voorraad_delete($dp['id'])) !== null, 'delete unknown id is refused');

@unlink($tmp);
echo $failures === 0 ? "\nAll voorraad tests passed.\n" : "\n{$failures} failure(s).\n";
exit($failures === 0 ? 0 : 1);
