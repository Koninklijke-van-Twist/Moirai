<?php

/**
 * Telefoonbudget: indiensttreding, aankopen en budgetberekening (alles in centen).
 *
 * Rekenregels (zie ook README):
 * - Vanaf de indiensttreding is het budget het startbudget (instelling, standaard 60000 ct).
 * - Een aankoop gaat van het budget af; het budget komt nooit onder 0. Het tekort is
 *   de eigen bijdrage en wordt bij de aankoop opgeslagen.
 * - Na een aankoop komt er per hele maand het maandbedrag bij (instelling, standaard 2500 ct).
 * - Hele maand: zie moirai_budget_months_between() (dag-van-de-maand bereikt).
 * - Vóór de eerste aankoop: geen opbouw (MOIRAI_BUDGET_ACCRUE_BEFORE_FIRST_PURCHASE).
 * - Maximum: instelling max_cents, 0 = geen maximum.
 * - Onbevestigde aankopen tellen mee (MOIRAI_BUDGET_COUNT_UNCONFIRMED).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/moirai_spreadsheet.php';

const MOIRAI_BUDGET_DEFAULT_START_CENTS = 60000;
const MOIRAI_BUDGET_DEFAULT_MONTHLY_CENTS = 2500;
const MOIRAI_BUDGET_DEFAULT_MAX_CENTS = 0;
const MOIRAI_BUDGET_DEFAULT_DEPRECIATION_CENTS = 2500;
/** Letterlijke lezing van Tim: vóór de eerste aankoop blijft het budget het startbudget. */
const MOIRAI_BUDGET_ACCRUE_BEFORE_FIRST_PURCHASE = false;
/** Onbevestigde aankopen tellen mee in het budget (voorkomt dubbel bestellen). */
const MOIRAI_BUDGET_COUNT_UNCONFIRMED = true;
const MOIRAI_BUDGET_STATUS_UNCONFIRMED = 'onbevestigd';
const MOIRAI_BUDGET_STATUS_CONFIRMED = 'bevestigd';
const MOIRAI_BUDGET_PAGE_SIZE = 25;
const MOIRAI_BUDGET_TEXT_MAX = 200;
const MOIRAI_BUDGET_NOTE_MAX = 4000;

const MOIRAI_BUDGET_SETTING_KEYS = [
    'start_cents' => MOIRAI_BUDGET_DEFAULT_START_CENTS,
    'monthly_cents' => MOIRAI_BUDGET_DEFAULT_MONTHLY_CENTS,
    'max_cents' => MOIRAI_BUDGET_DEFAULT_MAX_CENTS,
    'depreciation_cents' => MOIRAI_BUDGET_DEFAULT_DEPRECIATION_CENTS,
];

/* ------------------------------------------------------------- schema -- */

/** Idempotent; wordt aangeroepen vanuit moirai_init_schema() bij de eerste DB-connectie. */
function moirai_migrate_budget_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS budget_settings (
            name  TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS budget_people (
            email            TEXT PRIMARY KEY,
            naam             TEXT NOT NULL DEFAULT '',
            indiensttreding  TEXT NOT NULL,
            bijgewerkt       TEXT
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS budget_purchases (
            id                    INTEGER PRIMARY KEY AUTOINCREMENT,
            email                 TEXT NOT NULL,
            datum                 TEXT NOT NULL,
            prijs_cents           INTEGER NOT NULL,
            eigen_bijdrage_cents  INTEGER NOT NULL DEFAULT 0,
            budget_voor_cents     INTEGER NOT NULL DEFAULT 0,
            budget_na_cents       INTEGER NOT NULL DEFAULT 0,
            status                TEXT NOT NULL DEFAULT 'onbevestigd',
            telefoon              TEXT NOT NULL DEFAULT '',
            notitie               TEXT NOT NULL DEFAULT '',
            phone_imei            TEXT,
            import_hash           TEXT,
            aangemaakt            TEXT,
            aangemaakt_door       TEXT
        )
    ");
    moirai_ensure_column($pdo, 'budget_purchases', 'telefoon', "TEXT NOT NULL DEFAULT ''");
    moirai_ensure_column($pdo, 'budget_purchases', 'notitie', "TEXT NOT NULL DEFAULT ''");
    $pdo->exec('CREATE INDEX IF NOT EXISTS budget_purchases_email ON budget_purchases (email)');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS budget_purchases_phone_unique ON budget_purchases (phone_imei)');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS budget_purchases_import_unique ON budget_purchases (import_hash)');
}

/* ---------------------------------------------------------- settings -- */

function moirai_budget_settings(): array
{
    $settings = MOIRAI_BUDGET_SETTING_KEYS;
    foreach (moirai_db()->query('SELECT name, value FROM budget_settings')->fetchAll() as $row) {
        if (array_key_exists($row['name'], $settings)) {
            $settings[$row['name']] = (int) $row['value'];
        }
    }

    return $settings;
}

function moirai_budget_save_settings(array $input): array
{
    $pdo = moirai_db();
    $stmt = $pdo->prepare('INSERT INTO budget_settings (name, value) VALUES (:n, :v) ON CONFLICT(name) DO UPDATE SET value = excluded.value');
    foreach (array_keys(MOIRAI_BUDGET_SETTING_KEYS) as $name) {
        if (!array_key_exists($name, $input)) {
            continue;
        }
        $cents = moirai_budget_parse_cents($input[$name], true);
        $stmt->execute(['n' => $name, 'v' => (string) $cents]);
    }

    return moirai_budget_settings();
}

/* ------------------------------------------------------------ helpers -- */

/**
 * Bedrag naar centen zonder floats: "350", "350,5", "1.234,56", "€ 1,234.56", 35000 (int = euro's).
 */
function moirai_budget_parse_cents(mixed $value, bool $allowZero = false): int
{
    if (is_int($value)) {
        $cents = $value * 100;
    } elseif (is_float($value)) {
        // Alleen voor spreadsheet-getallen: via string afronden op hele centen.
        $cents = moirai_budget_parse_cents(sprintf('%.2f', round($value, 2)), true);
    } else {
        $raw = preg_replace('/[\s€]|EUR/iu', '', trim((string) $value)) ?? '';
        if ($raw === '' || !preg_match('/^\d[\d.,]*$/', $raw)) {
            throw new InvalidArgumentException(moirai_loc('budget.error.amount_invalid'));
        }
        $lastSep = max((int) strrpos($raw, ','), (int) strrpos($raw, '.'));
        $hasSep = strpbrk($raw, ',.') !== false;
        $decimals = '';
        $whole = $raw;
        if ($hasSep && strlen($raw) - $lastSep - 1 <= 2) {
            $decimals = substr($raw, $lastSep + 1);
            $whole = substr($raw, 0, $lastSep);
        }
        $whole = str_replace([',', '.'], '', $whole);
        if ($whole === '' || !ctype_digit($whole) || ($decimals !== '' && !ctype_digit($decimals))) {
            throw new InvalidArgumentException(moirai_loc('budget.error.amount_invalid'));
        }
        $cents = (int) $whole * 100 + (int) str_pad($decimals, 2, '0');
    }
    if ($cents < 0 || (!$allowZero && $cents === 0) || $cents > 100000000) {
        throw new InvalidArgumentException(moirai_loc('budget.error.amount_invalid'));
    }

    return $cents;
}

function moirai_budget_format_cents(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    $cents = abs($cents);

    return $sign . '€ ' . number_format(intdiv($cents, 100), 0, ',', '.') . ',' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function moirai_budget_validate_date(mixed $value, bool $defaultToday = false): string
{
    $value = trim((string) $value);
    if ($value === '' && $defaultToday) {
        return moirai_today()->format('Y-m-d');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException(moirai_loc('budget.error.date_invalid'));
    }

    return $value;
}

function moirai_budget_normalize_email(mixed $value): string
{
    $email = strtolower(trim((string) $value));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(moirai_loc('budget.error.person_invalid'));
    }

    return $email;
}

function moirai_budget_clean_text(mixed $value, int $max, bool $multiline = false): string
{
    $text = str_replace("\r\n", "\n", (string) $value);
    $text = $multiline ? trim($text) : trim(preg_replace('/\s+/u', ' ', $text) ?? '');

    return mb_substr($text, 0, $max);
}

/**
 * Hele maanden tussen twee datums: een maand telt mee zodra de dag-van-de-maand van
 * $from is bereikt. Bestaat die dag niet (bv. 31 → februari), dan telt de laatste
 * dag van die maand.
 */
function moirai_budget_months_between(string $from, string $to): int
{
    if ($to <= $from) {
        return 0;
    }
    [$fy, $fm, $fd] = array_map('intval', explode('-', $from));
    [$ty, $tm, $td] = array_map('intval', explode('-', $to));
    $months = ($ty - $fy) * 12 + ($tm - $fm);
    $anchor = min($fd, (int) moirai_budget_days_in_month($ty, $tm));
    if ($td < $anchor) {
        $months--;
    }

    return max(0, $months);
}

function moirai_budget_days_in_month(int $year, int $month): int
{
    return (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
}

/* -------------------------------------------------------- berekening -- */

/**
 * Pure tijdlijnberekening. $purchases: lijst met minimaal id, datum, prijs_cents, status.
 * Geeft de aankopen terug (gesorteerd op datum, id) met budget_voor/na en eigen bijdrage,
 * plus een closure-vrije toestand om het budget op een datum te bepalen.
 *
 * @return array{purchases: list<array>, last_date: ?string, last_rest: ?int}
 */
function moirai_budget_timeline(?string $startDate, array $purchases, array $settings): array
{
    usort($purchases, static fn(array $a, array $b): int => [$a['datum'], (int) ($a['id'] ?? PHP_INT_MAX)] <=> [$b['datum'], (int) ($b['id'] ?? PHP_INT_MAX)]);
    $lastDate = null;
    $lastRest = null;
    $out = [];
    foreach ($purchases as $purchase) {
        $available = moirai_budget_available_from_state($startDate, $lastDate, $lastRest, (string) $purchase['datum'], $settings);
        $price = (int) $purchase['prijs_cents'];
        $own = max(0, $price - $available);
        $rest = max(0, $available - $price);
        $purchase['budget_voor_cents'] = $available;
        $purchase['eigen_bijdrage_cents'] = $own;
        $purchase['budget_na_cents'] = $rest;
        $counts = MOIRAI_BUDGET_COUNT_UNCONFIRMED || ($purchase['status'] ?? '') === MOIRAI_BUDGET_STATUS_CONFIRMED;
        $purchase['telt_mee'] = $counts;
        if ($counts) {
            $lastDate = (string) $purchase['datum'];
            $lastRest = $rest;
        }
        $out[] = $purchase;
    }

    return ['purchases' => $out, 'last_date' => $lastDate, 'last_rest' => $lastRest];
}

function moirai_budget_available_from_state(?string $startDate, ?string $lastDate, ?int $lastRest, string $onDate, array $settings): int
{
    $start = (int) $settings['start_cents'];
    $monthly = (int) $settings['monthly_cents'];
    $max = (int) $settings['max_cents'];
    if ($lastDate === null) {
        $base = $start;
        $accrual = (MOIRAI_BUDGET_ACCRUE_BEFORE_FIRST_PURCHASE && $startDate !== null)
            ? $monthly * moirai_budget_months_between($startDate, $onDate)
            : 0;
    } else {
        $base = (int) $lastRest;
        $accrual = $monthly * moirai_budget_months_between($lastDate, $onDate);
    }
    $available = $base + $accrual;
    if ($max > 0 && $accrual > 0) {
        // Opbouw stopt bij het maximum, maar een hoger bestaand saldo wordt niet verlaagd.
        $available = min($available, max($max, $base));
    }

    return $available;
}

/** Budget op een datum, uitgaande van de aankopen tot en met die datum. */
function moirai_budget_available_on(?string $startDate, array $purchases, string $onDate, array $settings): int
{
    $before = array_values(array_filter($purchases, static fn(array $p): bool => (string) $p['datum'] <= $onDate));
    $timeline = moirai_budget_timeline($startDate, $before, $settings);

    return moirai_budget_available_from_state($startDate, $timeline['last_date'], $timeline['last_rest'], $onDate, $settings);
}

/** Informatief: prijs laatste aankoop min afschrijving per hele maand, minimaal 0. */
function moirai_budget_phone_value(array $purchases, string $onDate, array $settings): ?array
{
    if ($purchases === []) {
        return null;
    }
    usort($purchases, static fn(array $a, array $b): int => [$b['datum'], (int) ($b['id'] ?? 0)] <=> [$a['datum'], (int) ($a['id'] ?? 0)]);
    $last = $purchases[0];
    $months = moirai_budget_months_between((string) $last['datum'], $onDate);
    $value = max(0, (int) $last['prijs_cents'] - $months * (int) $settings['depreciation_cents']);

    return ['purchase_id' => (int) ($last['id'] ?? 0), 'value_cents' => $value, 'months' => $months];
}

/** Preview voor de aankoopmodal: tijdlijn inclusief een (virtuele) aankoop. */
function moirai_budget_preview(?string $startDate, array $purchases, int $priceCents, string $date, array $settings, ?int $excludeId = null): array
{
    $others = array_values(array_filter($purchases, static fn(array $p): bool => $excludeId === null || (int) $p['id'] !== $excludeId));
    $available = moirai_budget_available_on($startDate, $others, $date, $settings);

    return [
        'budget_voor_cents' => $available,
        'eigen_bijdrage_cents' => max(0, $priceCents - $available),
        'budget_na_cents' => max(0, $available - $priceCents),
    ];
}

/* ------------------------------------------------------------- opslag -- */

function moirai_budget_person_row(string $email): ?array
{
    $stmt = moirai_db()->prepare('SELECT * FROM budget_people WHERE email = :e');
    $stmt->execute(['e' => $email]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function moirai_budget_purchase_rows(string $email): array
{
    $stmt = moirai_db()->prepare('SELECT * FROM budget_purchases WHERE email = :e ORDER BY datum, id');
    $stmt->execute(['e' => $email]);

    return $stmt->fetchAll();
}

function moirai_budget_purchase_row(int $id): array
{
    $stmt = moirai_db()->prepare('SELECT * FROM budget_purchases WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new InvalidArgumentException(moirai_loc('budget.error.purchase_not_found'));
    }

    return $row;
}

function moirai_budget_set_start(string $email, string $date, string $name = ''): array
{
    $email = moirai_budget_normalize_email($email);
    $date = moirai_budget_validate_date($date);
    $name = moirai_budget_clean_text($name, MOIRAI_BUDGET_TEXT_MAX);
    $stmt = moirai_db()->prepare(
        'INSERT INTO budget_people (email, naam, indiensttreding, bijgewerkt) VALUES (:e, :n, :d, :t)
         ON CONFLICT(email) DO UPDATE SET indiensttreding = excluded.indiensttreding,
            naam = CASE WHEN excluded.naam <> \'\' THEN excluded.naam ELSE budget_people.naam END,
            bijgewerkt = excluded.bijgewerkt'
    );
    $stmt->execute(['e' => $email, 'n' => $name, 'd' => $date, 't' => date('c')]);
    moirai_budget_recalculate($email);

    return moirai_budget_person($email);
}

/** Herberekent en bewaart budget_voor/na en eigen bijdrage over de hele tijdlijn. */
function moirai_budget_recalculate(string $email): void
{
    $person = moirai_budget_person_row($email);
    $timeline = moirai_budget_timeline($person['indiensttreding'] ?? null, moirai_budget_purchase_rows($email), moirai_budget_settings());
    $stmt = moirai_db()->prepare('UPDATE budget_purchases SET eigen_bijdrage_cents = :o, budget_voor_cents = :v, budget_na_cents = :n WHERE id = :id');
    foreach ($timeline['purchases'] as $p) {
        $stmt->execute(['o' => $p['eigen_bijdrage_cents'], 'v' => $p['budget_voor_cents'], 'n' => $p['budget_na_cents'], 'id' => $p['id']]);
    }
}

function moirai_budget_recalculate_all(): void
{
    foreach (moirai_db()->query('SELECT email FROM budget_people')->fetchAll() as $row) {
        moirai_budget_recalculate((string) $row['email']);
    }
}

function moirai_budget_require_start(string $email): array
{
    $person = moirai_budget_person_row($email);
    if ($person === null) {
        throw new InvalidArgumentException(moirai_loc('budget.error.no_start'));
    }

    return $person;
}

function moirai_budget_add_purchase(string $email, array $input): array
{
    $email = moirai_budget_normalize_email($email);
    moirai_budget_require_start($email);
    $stmt = moirai_db()->prepare(
        'INSERT INTO budget_purchases (email, datum, prijs_cents, status, telefoon, notitie, aangemaakt, aangemaakt_door)
         VALUES (:e, :d, :p, :s, :t, :n, :c, :by)'
    );
    $stmt->execute([
        'e' => $email,
        'd' => moirai_budget_validate_date($input['datum'] ?? '', true),
        'p' => moirai_budget_parse_cents($input['prijs'] ?? ''),
        's' => MOIRAI_BUDGET_STATUS_UNCONFIRMED,
        't' => moirai_budget_clean_text($input['telefoon'] ?? '', MOIRAI_BUDGET_TEXT_MAX),
        'n' => moirai_budget_clean_text($input['notitie'] ?? '', MOIRAI_BUDGET_NOTE_MAX, true),
        'c' => date('c'),
        'by' => moirai_current_user_email(),
    ]);
    $id = (int) moirai_db()->lastInsertId();
    moirai_budget_recalculate($email);

    return moirai_budget_purchase_row($id);
}

function moirai_budget_update_purchase(int $id, array $input): array
{
    $row = moirai_budget_purchase_row($id);
    $stmt = moirai_db()->prepare('UPDATE budget_purchases SET datum = :d, prijs_cents = :p, telefoon = :t, notitie = :n WHERE id = :id');
    $stmt->execute([
        'd' => array_key_exists('datum', $input) ? moirai_budget_validate_date($input['datum']) : $row['datum'],
        'p' => array_key_exists('prijs', $input) ? moirai_budget_parse_cents($input['prijs']) : (int) $row['prijs_cents'],
        't' => array_key_exists('telefoon', $input) ? moirai_budget_clean_text($input['telefoon'], MOIRAI_BUDGET_TEXT_MAX) : $row['telefoon'],
        'n' => array_key_exists('notitie', $input) ? moirai_budget_clean_text($input['notitie'], MOIRAI_BUDGET_NOTE_MAX, true) : $row['notitie'],
        'id' => $id,
    ]);
    moirai_budget_recalculate((string) $row['email']);

    return moirai_budget_purchase_row($id);
}

/** Statusovergang: alleen onbevestigd → bevestigd en bevestigd → onbevestigd. */
function moirai_budget_set_status(int $id, string $status): array
{
    $row = moirai_budget_purchase_row($id);
    $allowed = [
        MOIRAI_BUDGET_STATUS_UNCONFIRMED => MOIRAI_BUDGET_STATUS_CONFIRMED,
        MOIRAI_BUDGET_STATUS_CONFIRMED => MOIRAI_BUDGET_STATUS_UNCONFIRMED,
    ];
    if (($allowed[$row['status']] ?? null) !== $status) {
        throw new InvalidArgumentException(moirai_loc('budget.error.status_transition'));
    }
    moirai_db()->prepare('UPDATE budget_purchases SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
    moirai_budget_recalculate((string) $row['email']);

    return moirai_budget_purchase_row($id);
}

function moirai_budget_delete_purchase(int $id): void
{
    $row = moirai_budget_purchase_row($id);
    moirai_db()->prepare('DELETE FROM budget_purchases WHERE id = :id')->execute(['id' => $id]);
    moirai_budget_recalculate((string) $row['email']);
}

function moirai_budget_public_purchase(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'email' => (string) $row['email'],
        'datum' => (string) $row['datum'],
        'prijs_cents' => (int) $row['prijs_cents'],
        'eigen_bijdrage_cents' => (int) $row['eigen_bijdrage_cents'],
        'budget_voor_cents' => (int) $row['budget_voor_cents'],
        'budget_na_cents' => (int) $row['budget_na_cents'],
        'status' => (string) $row['status'],
        'telefoon' => (string) ($row['telefoon'] ?? ''),
        'notitie' => (string) ($row['notitie'] ?? ''),
        'phone_imei' => $row['phone_imei'] !== null ? (string) $row['phone_imei'] : null,
        'geimporteerd' => !empty($row['import_hash']),
    ];
}

/** Volledig persoonsoverzicht voor de persoonsmodal. */
function moirai_budget_person(string $email, string $fallbackName = ''): array
{
    $email = moirai_budget_normalize_email($email);
    $person = moirai_budget_person_row($email);
    $rows = moirai_budget_purchase_rows($email);
    $settings = moirai_budget_settings();
    $today = moirai_today()->format('Y-m-d');
    $start = $person['indiensttreding'] ?? null;
    $current = $person !== null ? moirai_budget_available_on($start, $rows, $today, $settings) : null;
    $purchases = array_map('moirai_budget_public_purchase', $rows);
    $phoneValue = moirai_budget_phone_value($rows, $today, $settings);
    $phones = [];
    foreach ($purchases as $p) {
        if ($p['phone_imei'] !== null) {
            $phones[$p['phone_imei']] = moirai_get_device('phone', $p['phone_imei']);
        }
    }
    foreach ($purchases as &$p) {
        $device = $p['phone_imei'] !== null ? ($phones[$p['phone_imei']] ?? null) : null;
        $p['phone_label'] = $device !== null ? trim(($device['model'] ?? '') . ' · ' . $p['phone_imei'], ' ·') : $p['phone_imei'];
    }
    unset($p);
    $last = $rows !== [] ? (string) end($rows)['datum'] : null;

    return [
        'email' => $email,
        'naam' => (string) ($person['naam'] ?? '') !== '' ? (string) $person['naam'] : $fallbackName,
        'indiensttreding' => $start,
        'budget_cents' => $current,
        'laatste_aankoop' => $last,
        'telefoon_waarde' => $phoneValue,
        'onbevestigd' => count(array_filter($purchases, static fn(array $p): bool => $p['status'] === MOIRAI_BUDGET_STATUS_UNCONFIRMED)),
        'purchases' => $purchases,
        'settings' => $settings,
    ];
}

/* --------------------------------------------------------- personen -- */

function moirai_budget_directory_users(): array
{
    foreach (['moirai_budget_directory_users', 'moirai_api_directory_users'] as $global) {
        if (isset($GLOBALS[$global]) && is_array($GLOBALS[$global])) {
            return $GLOBALS[$global];
        }
    }

    return moirai_fetch_directory_users();
}

/** Directory-gebruikers + personen met een indiensttreding (ook als ze uit de directory zijn). */
function moirai_budget_all_people(): array
{
    $people = [];
    foreach (moirai_budget_directory_users() as $user) {
        $normalized = moirai_normalize_user($user);
        if ($normalized === null || moirai_is_unavailable_user($normalized)) {
            continue;
        }
        $people[$normalized['email']] = ['email' => $normalized['email'], 'naam' => $normalized['naam'], 'in_directory' => true];
    }
    foreach (moirai_db()->query('SELECT email, naam FROM budget_people')->fetchAll() as $row) {
        if (!isset($people[$row['email']])) {
            $people[$row['email']] = ['email' => (string) $row['email'], 'naam' => (string) $row['naam'], 'in_directory' => false];
        }
    }

    return $people;
}

function moirai_budget_list_people(string $query = '', int $page = 1, int $perPage = MOIRAI_BUDGET_PAGE_SIZE, string $filter = 'all'): array
{
    $people = moirai_budget_all_people();
    $starts = [];
    foreach (moirai_db()->query('SELECT email, indiensttreding FROM budget_people')->fetchAll() as $row) {
        $starts[$row['email']] = $row['indiensttreding'];
    }
    $tokens = preg_split('/\s+/', mb_strtolower(trim($query))) ?: [];
    $list = [];
    foreach ($people as $person) {
        $haystack = mb_strtolower($person['naam'] . ' ' . $person['email']);
        foreach ($tokens as $token) {
            if ($token !== '' && !str_contains($haystack, $token)) {
                continue 2;
            }
        }
        $hasStart = isset($starts[$person['email']]);
        if (($filter === 'with' && !$hasStart) || ($filter === 'without' && $hasStart)) {
            continue;
        }
        $list[] = $person + ['has_start' => $hasStart];
    }
    usort($list, static fn(array $a, array $b): int => [!$a['has_start'], mb_strtolower($a['naam'] ?: $a['email'])] <=> [!$b['has_start'], mb_strtolower($b['naam'] ?: $b['email'])]);

    $total = count($list);
    $perPage = max(1, min(100, $perPage));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, $page));
    $slice = array_slice($list, ($page - 1) * $perPage, $perPage);
    $items = [];
    foreach ($slice as $person) {
        $detail = moirai_budget_person($person['email'], $person['naam']);
        $items[] = [
            'email' => $person['email'],
            'naam' => $detail['naam'] !== '' ? $detail['naam'] : $person['naam'],
            'in_directory' => $person['in_directory'],
            'indiensttreding' => $detail['indiensttreding'],
            'budget_cents' => $detail['budget_cents'],
            'laatste_aankoop' => $detail['laatste_aankoop'],
            'onbevestigd' => $detail['onbevestigd'],
        ];
    }

    return ['items' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $perPage];
}

/* ---------------------------------------------------- telefoon-koppeling -- */

/** Opties voor de combobox in het telefoon-detailvenster: aankopen van de huidige gebruiker. */
function moirai_budget_phone_options(string $imei): array
{
    $device = moirai_get_device('phone', $imei);
    if ($device === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
    }
    $stmt = moirai_db()->prepare('SELECT * FROM budget_purchases WHERE phone_imei = :i');
    $stmt->execute(['i' => $imei]);
    $linked = $stmt->fetch() ?: null;
    $email = strtolower((string) ($device['uitgegeven_aan']['email'] ?? ''));
    $options = [];
    if ($email !== '' && $email !== MOIRAI_UNAVAILABLE_EMAIL) {
        foreach (array_reverse(moirai_budget_purchase_rows($email)) as $row) {
            $options[] = moirai_budget_public_purchase($row) + [
                'beschikbaar' => $row['phone_imei'] === null || $row['phone_imei'] === $imei,
            ];
        }
    }

    return [
        'imei' => $imei,
        'email' => $email,
        'linked' => $linked ? moirai_budget_public_purchase($linked) : null,
        'options' => $options,
    ];
}

function moirai_budget_link_phone(string $imei, int $purchaseId): array
{
    $device = moirai_get_device('phone', $imei);
    if ($device === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.device_not_found'));
    }
    $purchase = moirai_budget_purchase_row($purchaseId);
    $email = strtolower((string) ($device['uitgegeven_aan']['email'] ?? ''));
    if ($email === '' || $email !== $purchase['email']) {
        throw new InvalidArgumentException(moirai_loc('budget.error.link_wrong_person'));
    }
    if ($purchase['phone_imei'] !== null && $purchase['phone_imei'] !== $imei) {
        throw new InvalidArgumentException(moirai_loc('budget.error.link_taken'));
    }
    $pdo = moirai_db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE budget_purchases SET phone_imei = NULL WHERE phone_imei = :i')->execute(['i' => $imei]);
        $pdo->prepare('UPDATE budget_purchases SET phone_imei = :i WHERE id = :id')->execute(['i' => $imei, 'id' => $purchaseId]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return moirai_budget_phone_options($imei);
}

function moirai_budget_unlink_phone(string $imei): void
{
    moirai_db()->prepare('UPDATE budget_purchases SET phone_imei = NULL WHERE phone_imei = :i')->execute(['i' => $imei]);
}

/** Houdt koppelingen kloppend bij hernoemen (IMEI-wijziging) of verwijderen van een telefoon. */
function moirai_budget_phone_key_changed(string $oldImei, ?string $newImei): void
{
    if ($oldImei === '' || $oldImei === $newImei) {
        return;
    }
    moirai_db()->prepare('UPDATE budget_purchases SET phone_imei = :n WHERE phone_imei = :o')->execute(['n' => $newImei, 'o' => $oldImei]);
}

/* -------------------------------------------------------------- import -- */

const MOIRAI_BUDGET_IMPORT_HEADERS = [
    'datum' => ['datum', 'date', 'aankoopdatum', 'besteldatum'],
    'persoon' => ['persoon', 'naam', 'medewerker', 'gebruiker', 'name', 'person', 'email', 'e-mail'],
    'telefoon' => ['soort telefoon', 'telefoon', 'toestel', 'model', 'phone'],
    'bedrag' => ['bedrag', 'prijs', 'price', 'amount', 'kosten'],
    'status' => ['status'],
    'notitie' => ['notitie', 'opmerking', 'opmerkingen', 'note', 'notes'],
];

function moirai_budget_normalize_name(string $name): string
{
    $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? ''));
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);

    return is_string($ascii) && $ascii !== '' ? preg_replace('/[^a-z0-9@. ]/', '', $ascii) ?? $name : $name;
}

/**
 * Leest de spreadsheet en zet de rijen om naar importregels.
 * Herkende kolommen (Tims referentiebestand): datum | persoon | soort telefoon | bedrag | (naamloos = notitie).
 *
 * @return list<array{row:int, datum:string, persoon:string, prijs_cents:int, telefoon:string, notitie:string, status:string, hash:string}>
 */
function moirai_budget_import_parse(array $sheetRows): array
{
    $header = array_map(static fn($v): string => moirai_budget_normalize_name((string) $v), $sheetRows[0] ?? []);
    $map = [];
    foreach ($header as $index => $label) {
        foreach (MOIRAI_BUDGET_IMPORT_HEADERS as $field => $aliases) {
            if (!isset($map[$field]) && in_array($label, $aliases, true)) {
                $map[$field] = $index;
                continue 2;
            }
        }
    }
    foreach (['datum', 'persoon', 'bedrag'] as $required) {
        if (!isset($map[$required])) {
            throw new InvalidArgumentException(moirai_loc('budget.import.error.columns', implode(', ', ['datum', 'persoon', 'bedrag'])));
        }
    }
    if (!isset($map['notitie'])) {
        // Naamloze kolom rechts van de bekende kolommen = notitie (zo staat het in Tims bestand).
        $width = max(array_map('count', $sheetRows) ?: [0]);
        for ($i = 0; $i < $width; $i++) {
            if (($header[$i] ?? '') === '' && !in_array($i, $map, true)) {
                $map['notitie'] = $i;
                break;
            }
        }
    }

    $rows = [];
    $seen = [];
    foreach (array_slice($sheetRows, 1, null, true) as $index => $cells) {
        $get = static fn(string $field) => isset($map[$field]) ? ($cells[$map[$field]] ?? null) : null;
        $person = trim(preg_replace('/\s+/u', ' ', (string) $get('persoon')) ?? '');
        $rawDate = $get('datum');
        $rawAmount = $get('bedrag');
        if ($person === '' && ($rawDate === null || $rawDate === '') && ($rawAmount === null || $rawAmount === '')) {
            continue;
        }
        $error = null;
        $date = '';
        $cents = 0;
        try {
            $date = moirai_budget_import_date($rawDate);
        } catch (InvalidArgumentException) {
            $error = 'datum';
        }
        try {
            $cents = moirai_budget_parse_cents(is_string($rawAmount) ? $rawAmount : ($rawAmount ?? ''));
        } catch (InvalidArgumentException) {
            $error = $error ?? 'bedrag';
        }
        if ($person === '') {
            $error = $error ?? 'persoon';
        }
        $statusRaw = moirai_budget_normalize_name((string) $get('status'));
        $status = in_array($statusRaw, ['onbevestigd', 'unconfirmed', 'nee', 'open', 'besteld'], true)
            ? MOIRAI_BUDGET_STATUS_UNCONFIRMED
            : MOIRAI_BUDGET_STATUS_CONFIRMED;
        $telefoon = moirai_budget_clean_text($get('telefoon') ?? '', MOIRAI_BUDGET_TEXT_MAX);
        $notitie = moirai_budget_clean_text($get('notitie') ?? '', MOIRAI_BUDGET_NOTE_MAX, true);
        $base = implode('|', [moirai_budget_normalize_name($person), $date, $cents, moirai_budget_normalize_name($telefoon)]);
        $seen[$base] = ($seen[$base] ?? 0) + 1;
        $rows[] = [
            'row' => $index + 1,
            'datum' => $date,
            'persoon' => $person,
            'prijs_cents' => $cents,
            'telefoon' => $telefoon,
            'notitie' => $notitie,
            'status' => $status,
            'error' => $error,
            // Idempotentie: inhoud + volgnummer van identieke regels (niet het rijnummer,
            // zodat een gesorteerde of aangevulde Excel geen dubbelingen geeft).
            'hash' => sha1($base . '|' . $seen[$base]),
        ];
    }

    return $rows;
}

function moirai_budget_import_date(mixed $value): string
{
    if (is_int($value) || is_float($value)) {
        if ($value < 1 || $value > 2958465) {
            throw new InvalidArgumentException('datum');
        }
        return moirai_spreadsheet_serial_to_date((float) $value);
    }
    $value = trim((string) $value);
    foreach (['!Y-m-d', '!d-m-Y', '!j-n-Y', '!d/m/Y', '!j/n/Y', '!d-m-y', '!j-n-y', '!d.m.Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date !== false && DateTimeImmutable::getLastErrors() === false) {
            return $date->format('Y-m-d');
        }
    }
    throw new InvalidArgumentException('datum');
}

/**
 * Koppelt een naam/e-mail uit de Excel aan een persoon.
 * zeker: exacte e-mail of exacte (genormaliseerde) unieke naam. Anders kandidaten.
 */
function moirai_budget_match_person(string $raw, array $people): array
{
    $norm = moirai_budget_normalize_name($raw);
    if (str_contains($raw, '@')) {
        $email = strtolower(trim($raw));
        if (isset($people[$email])) {
            return ['email' => $email, 'zeker' => true, 'kandidaten' => [$email]];
        }
    }
    $exact = [];
    $scored = [];
    $rawTokens = array_values(array_filter(explode(' ', $norm)));
    foreach ($people as $email => $person) {
        $name = moirai_budget_normalize_name((string) $person['naam']);
        if ($name === '') {
            continue;
        }
        if ($name === $norm) {
            $exact[] = $email;
            continue;
        }
        $tokens = array_values(array_filter(explode(' ', $name)));
        $common = count(array_intersect($rawTokens, $tokens));
        similar_text($norm, $name, $pct);
        $score = $common * 30 + $pct;
        if ($common > 0 || $pct >= 70) {
            $scored[$email] = $score;
        }
    }
    if (count($exact) === 1) {
        return ['email' => $exact[0], 'zeker' => true, 'kandidaten' => $exact];
    }
    arsort($scored);
    $candidates = array_merge($exact, array_slice(array_keys($scored), 0, 5));

    return ['email' => null, 'zeker' => false, 'kandidaten' => array_values(array_unique($candidates))];
}

/** Preview: wat wordt er geïmporteerd, wat is al binnen en welke personen zijn (on)zeker. */
function moirai_budget_import_preview(array $rows): array
{
    $people = moirai_budget_all_people();
    $existing = [];
    foreach (moirai_db()->query('SELECT import_hash FROM budget_purchases WHERE import_hash IS NOT NULL')->fetchAll() as $r) {
        $existing[$r['import_hash']] = true;
    }
    $starts = [];
    foreach (moirai_db()->query('SELECT email, indiensttreding FROM budget_people')->fetchAll() as $r) {
        $starts[$r['email']] = $r['indiensttreding'];
    }
    $persons = [];
    $counts = ['totaal' => count($rows), 'nieuw' => 0, 'al_geimporteerd' => 0, 'fout' => 0];
    foreach ($rows as &$row) {
        $row['al_geimporteerd'] = isset($existing[$row['hash']]);
        if ($row['error'] !== null) {
            $counts['fout']++;
            continue;
        }
        $counts[$row['al_geimporteerd'] ? 'al_geimporteerd' : 'nieuw']++;
        $key = moirai_budget_normalize_name($row['persoon']);
        if (!isset($persons[$key])) {
            $match = moirai_budget_match_person($row['persoon'], $people);
            $persons[$key] = [
                'key' => $key,
                'bron' => $row['persoon'],
                'email' => $match['email'],
                'zeker' => $match['zeker'],
                'kandidaten' => array_map(static fn(string $e): array => ['email' => $e, 'naam' => (string) ($people[$e]['naam'] ?? '')], $match['kandidaten']),
                'aantal' => 0,
                'nieuw' => 0,
                'eerste_datum' => $row['datum'],
            ];
        }
        $persons[$key]['aantal']++;
        if (!$row['al_geimporteerd']) {
            $persons[$key]['nieuw']++;
        }
        $persons[$key]['eerste_datum'] = min($persons[$key]['eerste_datum'], $row['datum']);
    }
    unset($row);
    foreach ($persons as &$p) {
        $p['indiensttreding'] = $p['email'] !== null ? ($starts[$p['email']] ?? null) : null;
    }
    unset($p);

    return [
        'rows' => $rows,
        'personen' => array_values($persons),
        'tellingen' => $counts,
        'mensen' => array_values(array_map(static fn(array $p): array => ['email' => $p['email'], 'naam' => $p['naam'], 'indiensttreding' => $starts[$p['email']] ?? null], $people)),
    ];
}

/**
 * Slaat de import definitief op. $mapping: bron-key => e-mail ('' = overslaan).
 * $starts: e-mail => indiensttreding voor personen die er nog geen hebben (verplicht).
 * Idempotent via import_hash (INSERT OR IGNORE).
 */
function moirai_budget_import_commit(array $rows, array $mapping, array $starts, array $names = []): array
{
    $people = moirai_budget_all_people();
    $needed = [];
    foreach ($rows as $row) {
        if ($row['error'] !== null) {
            continue;
        }
        $email = strtolower(trim((string) ($mapping[moirai_budget_normalize_name($row['persoon'])] ?? '')));
        if ($email === '') {
            continue;
        }
        if (!isset($people[$email])) {
            throw new InvalidArgumentException(moirai_loc('budget.error.person_invalid'));
        }
        $needed[$email] = true;
    }
    $missing = [];
    foreach (array_keys($needed) as $email) {
        if (moirai_budget_person_row($email) === null) {
            $start = trim((string) ($starts[$email] ?? ''));
            if ($start === '') {
                $missing[] = $email;
                continue;
            }
            moirai_budget_validate_date($start);
        }
    }
    if ($missing !== []) {
        throw new InvalidArgumentException(moirai_loc('budget.import.error.missing_start', implode(', ', $missing)));
    }

    $pdo = moirai_db();
    $pdo->beginTransaction();
    $inserted = 0;
    $skipped = 0;
    try {
        foreach (array_keys($needed) as $email) {
            if (moirai_budget_person_row($email) === null) {
                $pdo->prepare('INSERT INTO budget_people (email, naam, indiensttreding, bijgewerkt) VALUES (:e, :n, :d, :t)')
                    ->execute(['e' => $email, 'n' => (string) ($people[$email]['naam'] ?? ($names[$email] ?? '')), 'd' => $starts[$email], 't' => date('c')]);
            }
        }
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO budget_purchases (email, datum, prijs_cents, status, telefoon, notitie, import_hash, aangemaakt, aangemaakt_door)
             VALUES (:e, :d, :p, :s, :t, :n, :h, :c, :by)'
        );
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($mapping[moirai_budget_normalize_name($row['persoon'])] ?? '')));
            if ($row['error'] !== null || $email === '') {
                $skipped++;
                continue;
            }
            $stmt->execute([
                'e' => $email,
                'd' => $row['datum'],
                'p' => (int) $row['prijs_cents'],
                's' => $row['status'] === MOIRAI_BUDGET_STATUS_UNCONFIRMED ? MOIRAI_BUDGET_STATUS_UNCONFIRMED : MOIRAI_BUDGET_STATUS_CONFIRMED,
                't' => (string) $row['telefoon'],
                'n' => (string) $row['notitie'],
                'h' => (string) $row['hash'],
                'c' => date('c'),
                'by' => moirai_current_user_email(),
            ]);
            if ($stmt->rowCount() > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    foreach (array_keys($needed) as $email) {
        moirai_budget_recalculate($email);
    }

    return ['toegevoegd' => $inserted, 'overgeslagen' => $skipped, 'personen' => count($needed)];
}

/* ----------------------------------------------------------------- API -- */

/**
 * CSRF-token per sessie. localization.php sluit de sessie al vroeg (session_write_close),
 * dus heropenen we hem kort om het token te bewaren. Roep dit aan vóór er output is.
 */
function moirai_budget_csrf_token(): string
{
    if (!empty($_SESSION['moirai_csrf']) && is_string($_SESSION['moirai_csrf'])) {
        return $_SESSION['moirai_csrf'];
    }
    $reopened = false;
    if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        $reopened = @session_start();
    }
    if (empty($_SESSION['moirai_csrf']) || !is_string($_SESSION['moirai_csrf'])) {
        $_SESSION['moirai_csrf'] = bin2hex(random_bytes(32));
    }
    $token = $_SESSION['moirai_csrf'];
    if ($reopened) {
        session_write_close();
    }

    return $token;
}

function moirai_budget_csrf_valid(string $presented): bool
{
    $expected = (string) ($_SESSION['moirai_csrf'] ?? '');

    return $expected !== '' && $presented !== '' && hash_equals($expected, $presented);
}

const MOIRAI_BUDGET_READ_ACTIONS = ['people', 'person', 'preview', 'settings', 'phone_options'];

/**
 * Server-side dispatcher. Alle acties: alleen ICT-admins (moirai_is_admin()).
 * Schrijfacties: alleen POST met geldig CSRF-token.
 *
 * @return array{0:int, 1:array} [HTTP-status, payload]
 */
function moirai_budget_api_dispatch(string $action, string $method, array $payload, string $csrf, array $files = []): array
{
    if (!moirai_is_admin()) {
        return [403, ['ok' => false, 'error' => moirai_loc('moirai.error.forbidden')]];
    }
    $isRead = in_array($action, MOIRAI_BUDGET_READ_ACTIONS, true);
    if (!$isRead && (strtoupper($method) !== 'POST' || !moirai_budget_csrf_valid($csrf))) {
        return [403, ['ok' => false, 'error' => moirai_loc('budget.error.csrf')]];
    }
    $str = static fn(string $k): string => trim((string) ($payload[$k] ?? ''));
    $id = static fn(): int => (int) ($payload['id'] ?? 0);

    try {
        switch ($action) {
            case 'people':
                return [200, ['ok' => true] + moirai_budget_list_people($str('q'), max(1, (int) ($payload['page'] ?? 1)), MOIRAI_BUDGET_PAGE_SIZE, $str('filter') ?: 'all')];
            case 'person':
                return [200, ['ok' => true, 'person' => moirai_budget_person($str('email'), $str('naam'))]];
            case 'preview':
                $email = moirai_budget_normalize_email($str('email'));
                $person = moirai_budget_require_start($email);
                $exclude = $id() > 0 ? $id() : null;
                return [200, ['ok' => true, 'preview' => moirai_budget_preview(
                    $person['indiensttreding'],
                    moirai_budget_purchase_rows($email),
                    moirai_budget_parse_cents($payload['prijs'] ?? ''),
                    moirai_budget_validate_date($payload['datum'] ?? '', true),
                    moirai_budget_settings(),
                    $exclude
                )]];
            case 'settings':
                return [200, ['ok' => true, 'settings' => moirai_budget_settings()]];
            case 'save_settings':
                $settings = moirai_budget_save_settings($payload);
                moirai_budget_recalculate_all();
                return [200, ['ok' => true, 'settings' => $settings]];
            case 'set_start':
                return [200, ['ok' => true, 'person' => moirai_budget_set_start($str('email'), $str('indiensttreding'), $str('naam'))]];
            case 'add_purchase':
                $purchase = moirai_budget_add_purchase($str('email'), $payload);
                return [200, ['ok' => true, 'purchase' => moirai_budget_public_purchase($purchase), 'person' => moirai_budget_person($purchase['email'])]];
            case 'update_purchase':
                $purchase = moirai_budget_update_purchase($id(), $payload);
                return [200, ['ok' => true, 'person' => moirai_budget_person($purchase['email'])]];
            case 'confirm':
            case 'unconfirm':
                $purchase = moirai_budget_set_status($id(), $action === 'confirm' ? MOIRAI_BUDGET_STATUS_CONFIRMED : MOIRAI_BUDGET_STATUS_UNCONFIRMED);
                return [200, ['ok' => true, 'person' => moirai_budget_person($purchase['email'])]];
            case 'delete_purchase':
                $row = moirai_budget_purchase_row($id());
                moirai_budget_delete_purchase($id());
                return [200, ['ok' => true, 'person' => moirai_budget_person($row['email'])]];
            case 'phone_options':
                return [200, ['ok' => true] + moirai_budget_phone_options($str('imei'))];
            case 'link_phone':
                $purchaseId = (int) ($payload['purchase_id'] ?? 0);
                if ($purchaseId <= 0) {
                    moirai_budget_unlink_phone($str('imei'));
                    return [200, ['ok' => true] + moirai_budget_phone_options($str('imei'))];
                }
                return [200, ['ok' => true] + moirai_budget_link_phone($str('imei'), $purchaseId)];
            case 'unlink_phone':
                moirai_budget_unlink_phone($str('imei'));
                return [200, ['ok' => true] + moirai_budget_phone_options($str('imei'))];
            case 'import_preview':
                $file = $files['file'] ?? null;
                if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_file((string) ($file['tmp_name'] ?? ''))) {
                    throw new InvalidArgumentException(moirai_loc('budget.import.error.upload'));
                }
                $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['xls', 'xlsx'], true)) {
                    throw new InvalidArgumentException(moirai_loc('budget.import.error.upload'));
                }
                $rows = moirai_budget_import_parse(moirai_spreadsheet_read((string) $file['tmp_name'], (string) $file['name']));
                $token = bin2hex(random_bytes(16));
                // Rijen blijven server-side (sessie); de client stuurt alleen keuzes terug.
                $_SESSION['moirai_budget_import'] = ['token' => $token, 'rows' => $rows];
                return [200, ['ok' => true, 'token' => $token] + moirai_budget_import_preview($rows)];
            case 'import_commit':
                $stored = $_SESSION['moirai_budget_import'] ?? null;
                if (!is_array($stored) || !hash_equals((string) $stored['token'], $str('token'))) {
                    throw new InvalidArgumentException(moirai_loc('budget.import.error.expired'));
                }
                $result = moirai_budget_import_commit(
                    $stored['rows'],
                    is_array($payload['mapping'] ?? null) ? $payload['mapping'] : [],
                    is_array($payload['starts'] ?? null) ? $payload['starts'] : []
                );
                unset($_SESSION['moirai_budget_import']);
                return [200, ['ok' => true, 'result' => $result]];
            default:
                return [400, ['ok' => false, 'error' => moirai_loc('moirai.error.unknown_action')]];
        }
    } catch (InvalidArgumentException $error) {
        return [400, ['ok' => false, 'error' => $error->getMessage()]];
    }
}
