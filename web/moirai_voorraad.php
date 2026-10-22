<?php
require_once __DIR__ . '/kvt_protect_dirs.php';

/**
 * Voorraad: categorieën (geen losse items) met een aantal op voorraad.
 * Opslag in dezelfde SQLite-database als de andere tabbladen (data/moirai.sqlite).
 * Notities via KvtChat met thread "device:voorraad:<id>" (zelfde opslag als apparaatnotities).
 */

require_once __DIR__ . '/moirai_data.php';

const MOIRAI_VOORRAAD_TYPE = 'voorraad';
const MOIRAI_VOORRAAD_SHORT_CODE = 'v';
const MOIRAI_VOORRAAD_NAME_MAX = 120;
const MOIRAI_VOORRAAD_PROPS_MAX = 2000;
const MOIRAI_VOORRAAD_QTY_MAX = 1000000;

function moirai_voorraad_db(): PDO
{
    static $initialized = [];
    $pdo = moirai_db();
    $id = spl_object_id($pdo);
    if (!isset($initialized[$id])) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS voorraad (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                naam           TEXT NOT NULL,
                naam_norm      TEXT NOT NULL UNIQUE,
                eigenschappen  TEXT NOT NULL DEFAULT '',
                aantal         INTEGER NOT NULL DEFAULT 0 CHECK (aantal >= 0),
                aangemaakt     TEXT NOT NULL DEFAULT '',
                bijgewerkt     TEXT NOT NULL DEFAULT ''
            )
        ");
        $initialized[$id] = true;
    }

    return $pdo;
}

function moirai_voorraad_clean_name(string $name): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $name));
}

/** Hoofdletterongevoelige sleutel voor de uniciteitscontrole. */
function moirai_voorraad_name_norm(string $name): string
{
    return mb_strtolower(moirai_voorraad_clean_name($name), 'UTF-8');
}

function moirai_voorraad_parse_quantity(mixed $raw): int
{
    if (is_int($raw)) {
        $value = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text === '' || !preg_match('/^-?\d+$/', $text)) {
            throw new InvalidArgumentException(moirai_loc('voorraad.error.quantity_invalid'));
        }
        $value = (int) $text;
    }
    if ($value < 0) {
        throw new InvalidArgumentException(moirai_loc('voorraad.error.quantity_negative'));
    }
    if ($value > MOIRAI_VOORRAAD_QTY_MAX) {
        throw new InvalidArgumentException(moirai_loc('voorraad.error.quantity_invalid'));
    }

    return $value;
}

function moirai_voorraad_now(): string
{
    return date('c');
}

function moirai_voorraad_row(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'naam' => (string) $row['naam'],
        'eigenschappen' => (string) $row['eigenschappen'],
        'aantal' => (int) $row['aantal'],
        'aangemaakt' => (string) $row['aangemaakt'],
        'bijgewerkt' => (string) $row['bijgewerkt'],
    ];
}

function moirai_voorraad_get(string $id): ?array
{
    $id = trim($id);
    if ($id === '' || !ctype_digit($id)) {
        return null;
    }
    $stmt = moirai_voorraad_db()->prepare('SELECT * FROM voorraad WHERE id = :id');
    $stmt->execute(['id' => (int) $id]);
    $row = $stmt->fetch();

    return is_array($row) ? moirai_voorraad_row($row) : null;
}

function moirai_voorraad_list(string $query = ''): array
{
    $rows = moirai_voorraad_db()->query('SELECT * FROM voorraad ORDER BY naam COLLATE NOCASE ASC, id ASC')->fetchAll();
    $items = array_map('moirai_voorraad_row', $rows);
    $query = mb_strtolower(trim($query), 'UTF-8');
    if ($query === '') {
        return $items;
    }

    return array_values(array_filter($items, static function (array $item) use ($query): bool {
        $haystack = mb_strtolower($item['naam'] . "\n" . $item['eigenschappen'], 'UTF-8');
        return str_contains($haystack, $query);
    }));
}

function moirai_voorraad_find_by_name(string $name, string $excludeId = ''): ?array
{
    $stmt = moirai_voorraad_db()->prepare('SELECT * FROM voorraad WHERE naam_norm = :norm AND id != :exclude');
    $stmt->execute(['norm' => moirai_voorraad_name_norm($name), 'exclude' => $excludeId === '' ? 0 : (int) $excludeId]);
    $row = $stmt->fetch();

    return is_array($row) ? moirai_voorraad_row($row) : null;
}

function moirai_voorraad_duplicate_message(string $name): string
{
    return moirai_loc('voorraad.error.name_exists', moirai_voorraad_clean_name($name));
}

/**
 * Nieuw (zonder id) of bijwerken (met id). Velden: naam, eigenschappen, aantal.
 */
function moirai_voorraad_save(array $input): array
{
    $id = trim((string) ($input['id'] ?? ''));
    $existing = null;
    if ($id !== '') {
        $existing = moirai_voorraad_get($id);
        if ($existing === null) {
            throw new InvalidArgumentException(moirai_loc('voorraad.error.not_found'));
        }
    }

    $naam = moirai_voorraad_clean_name((string) ($input['naam'] ?? ''));
    if ($naam === '') {
        throw new InvalidArgumentException(moirai_loc('voorraad.error.name_required'));
    }
    if (mb_strlen($naam, 'UTF-8') > MOIRAI_VOORRAAD_NAME_MAX) {
        throw new InvalidArgumentException(moirai_loc('voorraad.error.name_too_long', MOIRAI_VOORRAAD_NAME_MAX));
    }
    $eigenschappen = trim(str_replace("\r\n", "\n", (string) ($input['eigenschappen'] ?? '')));
    if (mb_strlen($eigenschappen, 'UTF-8') > MOIRAI_VOORRAAD_PROPS_MAX) {
        $eigenschappen = mb_substr($eigenschappen, 0, MOIRAI_VOORRAAD_PROPS_MAX, 'UTF-8');
    }
    $aantal = array_key_exists('aantal', $input)
        ? moirai_voorraad_parse_quantity($input['aantal'])
        : (int) ($existing['aantal'] ?? 0);

    if (moirai_voorraad_find_by_name($naam, $id) !== null) {
        throw new InvalidArgumentException(moirai_voorraad_duplicate_message($naam));
    }

    $pdo = moirai_voorraad_db();
    $params = [
        'naam' => $naam,
        'norm' => moirai_voorraad_name_norm($naam),
        'eig' => $eigenschappen,
        'aantal' => $aantal,
        'now' => moirai_voorraad_now(),
    ];
    try {
        if ($existing === null) {
            $stmt = $pdo->prepare('INSERT INTO voorraad (naam, naam_norm, eigenschappen, aantal, aangemaakt, bijgewerkt) VALUES (:naam, :norm, :eig, :aantal, :now, :now)');
            $stmt->execute($params);
            $id = (string) $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare('UPDATE voorraad SET naam = :naam, naam_norm = :norm, eigenschappen = :eig, aantal = :aantal, bijgewerkt = :now WHERE id = :id');
            $stmt->execute($params + ['id' => (int) $id]);
        }
    } catch (PDOException $error) {
        // Race: UNIQUE-index op naam_norm vangt gelijktijdige dubbele namen af.
        if (str_contains($error->getMessage(), 'UNIQUE')) {
            throw new InvalidArgumentException(moirai_voorraad_duplicate_message($naam));
        }
        throw $error;
    }

    return moirai_voorraad_get($id) ?? throw new RuntimeException('voorraad save failed');
}

/** Uitgeven: aantal met 1 verlagen, nooit onder 0 (atomair in SQL). */
function moirai_voorraad_issue(string $id): array
{
    if (moirai_voorraad_get($id) === null) {
        throw new InvalidArgumentException(moirai_loc('voorraad.error.not_found'));
    }
    $stmt = moirai_voorraad_db()->prepare('UPDATE voorraad SET aantal = aantal - 1, bijgewerkt = :now WHERE id = :id AND aantal > 0');
    $stmt->execute(['id' => (int) $id, 'now' => moirai_voorraad_now()]);
    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException(moirai_loc('voorraad.error.out_of_stock'));
    }

    return moirai_voorraad_get($id);
}

/**
 * Aantal aanpassen. mode "add": optellen (mag negatief, resultaat >= 0); mode "set": exact zetten (>= 0).
 */
function moirai_voorraad_adjust(string $id, string $mode, mixed $amount): array
{
    $item = moirai_voorraad_get($id);
    if ($item === null) {
        throw new InvalidArgumentException(moirai_loc('voorraad.error.not_found'));
    }
    $pdo = moirai_voorraad_db();
    if ($mode === 'set') {
        $value = moirai_voorraad_parse_quantity($amount);
        $stmt = $pdo->prepare('UPDATE voorraad SET aantal = :v, bijgewerkt = :now WHERE id = :id');
        $stmt->execute(['v' => $value, 'id' => (int) $id, 'now' => moirai_voorraad_now()]);
    } elseif ($mode === 'add') {
        $text = trim((string) $amount);
        if (!preg_match('/^[+-]?\d+$/', $text) || abs((int) $text) > MOIRAI_VOORRAAD_QTY_MAX) {
            throw new InvalidArgumentException(moirai_loc('voorraad.error.quantity_invalid'));
        }
        $delta = (int) $text;
        $stmt = $pdo->prepare('UPDATE voorraad SET aantal = aantal + :d, bijgewerkt = :now WHERE id = :id AND aantal + :d >= 0');
        $stmt->execute(['d' => $delta, 'id' => (int) $id, 'now' => moirai_voorraad_now()]);
        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException(moirai_loc('voorraad.error.quantity_negative'));
        }
    } else {
        throw new InvalidArgumentException(moirai_loc('moirai.error.invalid_input'));
    }

    return moirai_voorraad_get($id);
}

function moirai_voorraad_delete(string $id): void
{
    if (moirai_voorraad_get($id) === null) {
        throw new InvalidArgumentException(moirai_loc('voorraad.error.not_found'));
    }
    $stmt = moirai_voorraad_db()->prepare('DELETE FROM voorraad WHERE id = :id');
    $stmt->execute(['id' => (int) $id]);
    moirai_delete_device_notes_thread(MOIRAI_VOORRAAD_TYPE, $id);
}

function moirai_voorraad_notes_thread_key(string $id): string
{
    return moirai_device_notes_thread_key(MOIRAI_VOORRAAD_TYPE, $id);
}
