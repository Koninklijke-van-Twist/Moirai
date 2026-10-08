<?php

/**
 * Lichte spreadsheet-lezer zonder externe library (alleen voor de eenmalige
 * telefoonbudget-import). Leest het EERSTE werkblad als rijen met cellen.
 *
 * - .xlsx: ZipArchive + SimpleXML (sharedStrings, inlineStr, getallen).
 * - .xls : OLE2/BIFF8 (Excel 97-2003): SST/LABELSST, LABEL, NUMBER, RK, MULRK,
 *          FORMULA (getal- of tekstresultaat), BOOLERR.
 *
 * Datums komen als Excel-serienummer (float) terug; de aanroeper weet welke
 * kolom een datum is (zie moirai_spreadsheet_serial_to_date()).
 */

declare(strict_types=1);

/**
 * @return list<list<string|int|float|null>>
 */
function moirai_spreadsheet_read(string $path, string $originalName = ''): array
{
    if (!is_file($path)) {
        throw new InvalidArgumentException('Spreadsheet niet gevonden.');
    }
    $head = (string) file_get_contents($path, false, null, 0, 8);
    $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));

    if (str_starts_with($head, "PK\x03\x04")) {
        return moirai_spreadsheet_read_xlsx($path);
    }
    if ($head === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
        return moirai_spreadsheet_read_xls($path);
    }

    throw new InvalidArgumentException('Onbekend bestandsformaat' . ($ext !== '' ? " (.{$ext})" : '') . '; gebruik .xls of .xlsx.');
}

function moirai_spreadsheet_serial_to_date(float $serial, bool $date1904 = false): string
{
    $days = (int) floor($serial);
    $base = $date1904 ? new DateTimeImmutable('1904-01-01') : new DateTimeImmutable('1899-12-30');

    return $base->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

/* ------------------------------------------------------------------ xlsx -- */

function moirai_spreadsheet_col_index(string $ref): int
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?? '';
    $n = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }

    return max(0, $n - 1);
}

function moirai_spreadsheet_read_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new InvalidArgumentException('PHP-zip ontbreekt; .xlsx kan niet gelezen worden.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new InvalidArgumentException('Kon .xlsx niet openen.');
    }

    $shared = [];
    $sst = $zip->getFromName('xl/sharedStrings.xml');
    if (is_string($sst) && $sst !== '') {
        $xml = simplexml_load_string($sst);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                    continue;
                }
                $text = '';
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $shared[] = $text;
            }
        }
    }

    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if (is_string($wb) && is_string($rels)) {
        $wbXml = simplexml_load_string($wb);
        $relXml = simplexml_load_string($rels);
        if ($wbXml !== false && $relXml !== false && isset($wbXml->sheets->sheet[0])) {
            $rid = (string) $wbXml->sheets->sheet[0]->attributes('r', true)->id;
            foreach ($relXml->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');
                    $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }
    }

    $sheet = $zip->getFromName($sheetPath);
    $zip->close();
    if (!is_string($sheet)) {
        throw new InvalidArgumentException('Geen werkblad gevonden in .xlsx.');
    }
    $xml = simplexml_load_string($sheet);
    if ($xml === false) {
        throw new InvalidArgumentException('Werkblad in .xlsx is ongeldig.');
    }

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $r = max(0, ((int) $row['r']) - 1);
        $cells = [];
        foreach ($row->c as $c) {
            $col = moirai_spreadsheet_col_index((string) $c['r']);
            $type = (string) $c['t'];
            $value = null;
            if ($type === 's') {
                $value = $shared[(int) $c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) $c->is->t;
            } elseif ($type === 'str' || $type === 'e') {
                $value = (string) $c->v;
            } elseif ($type === 'b') {
                $value = ((string) $c->v) === '1' ? 1 : 0;
            } elseif (isset($c->v)) {
                $raw = (string) $c->v;
                $value = is_numeric($raw) ? $raw + 0 : $raw;
            }
            $cells[$col] = $value;
        }
        $rows[$r] = moirai_spreadsheet_fill_row($cells);
    }

    return moirai_spreadsheet_fill_rows($rows);
}

function moirai_spreadsheet_fill_row(array $cells): array
{
    if ($cells === []) {
        return [];
    }
    $max = max(array_keys($cells));
    $out = [];
    for ($i = 0; $i <= $max; $i++) {
        $out[] = $cells[$i] ?? null;
    }

    return $out;
}

function moirai_spreadsheet_fill_rows(array $rows): array
{
    if ($rows === []) {
        return [];
    }
    $max = max(array_keys($rows));
    $out = [];
    for ($i = 0; $i <= $max; $i++) {
        $out[] = $rows[$i] ?? [];
    }

    return $out;
}

/* ------------------------------------------------------------------- xls -- */

function moirai_spreadsheet_ole_stream(string $data, array $names): string
{
    if (strlen($data) < 512) {
        throw new InvalidArgumentException('Ongeldig .xls-bestand.');
    }
    $u16 = static fn(int $o): int => unpack('v', substr($data, $o, 2))[1];
    $u32 = static fn(int $o): int => unpack('V', substr($data, $o, 4))[1];

    $sectorSize = 1 << $u16(0x1E);
    $miniSectorSize = 1 << $u16(0x20);
    $numFatSectors = $u32(0x2C);
    $dirStart = $u32(0x30);
    $miniCutoff = $u32(0x38);
    $miniFatStart = $u32(0x3C);
    $difatStart = $u32(0x44);
    $numDifat = $u32(0x48);

    $sectorOffset = static fn(int $sid): int => 512 + $sid * $sectorSize;

    $fatSectors = [];
    for ($i = 0; $i < 109 && count($fatSectors) < $numFatSectors; $i++) {
        $fatSectors[] = $u32(0x4C + $i * 4);
    }
    $sid = $difatStart;
    for ($n = 0; $n < $numDifat && $sid < 0xFFFFFFFA; $n++) {
        $off = $sectorOffset($sid);
        $per = intdiv($sectorSize, 4) - 1;
        for ($i = 0; $i < $per && count($fatSectors) < $numFatSectors; $i++) {
            $fatSectors[] = $u32($off + $i * 4);
        }
        $sid = $u32($off + $per * 4);
    }

    $fat = [];
    foreach ($fatSectors as $fs) {
        $chunk = substr($data, $sectorOffset($fs), $sectorSize);
        foreach (unpack('V*', $chunk) ?: [] as $v) {
            $fat[] = $v;
        }
    }

    $readChain = static function (int $start) use ($fat, $data, $sectorOffset, $sectorSize): string {
        $out = '';
        $sid = $start;
        $guard = 0;
        while ($sid < 0xFFFFFFFA && isset($fat[$sid]) && $guard++ < 1000000) {
            $out .= substr($data, $sectorOffset($sid), $sectorSize);
            $sid = $fat[$sid];
        }

        return $out;
    };

    $dir = $readChain($dirStart);
    $entries = [];
    for ($o = 0; $o + 128 <= strlen($dir); $o += 128) {
        $nameLen = unpack('v', substr($dir, $o + 64, 2))[1];
        $name = $nameLen >= 2 ? (string) iconv('UTF-16LE', 'UTF-8', substr($dir, $o, $nameLen - 2)) : '';
        $entries[] = [
            'name' => $name,
            'type' => ord($dir[$o + 66]),
            'start' => unpack('V', substr($dir, $o + 116, 4))[1],
            'size' => unpack('V', substr($dir, $o + 120, 4))[1],
        ];
    }

    $root = $entries[0] ?? null;
    foreach ($entries as $entry) {
        if ($entry['type'] !== 2 || !in_array($entry['name'], $names, true)) {
            continue;
        }
        if ($entry['size'] >= $miniCutoff || $root === null) {
            return substr($readChain($entry['start']), 0, $entry['size']);
        }
        // Kleine stream: staat in de mini-stream van de root entry.
        $miniStream = $readChain($root['start']);
        $miniFat = [];
        foreach (unpack('V*', $readChain($miniFatStart)) ?: [] as $v) {
            $miniFat[] = $v;
        }
        $out = '';
        $sid = $entry['start'];
        $guard = 0;
        while ($sid < 0xFFFFFFFA && $guard++ < 1000000) {
            $out .= substr($miniStream, $sid * $miniSectorSize, $miniSectorSize);
            $sid = $miniFat[$sid] ?? 0xFFFFFFFE;
        }

        return substr($out, 0, $entry['size']);
    }

    throw new InvalidArgumentException('Geen Workbook-stream gevonden in .xls (alleen Excel 97-2003 BIFF8 wordt ondersteund).');
}

function moirai_spreadsheet_rk(int $rk): float|int
{
    $mult100 = ($rk & 1) === 1;
    if (($rk & 2) === 2) {
        $v = $rk >> 2;
        if ($v & 0x20000000) {
            $v -= 0x40000000;
        }
        return $mult100 ? $v / 100 : $v;
    }
    $bits = pack('V', 0) . pack('V', $rk & 0xFFFFFFFC);
    $v = unpack('e', $bits)[1];

    return $mult100 ? $v / 100 : $v;
}

/**
 * Leest een BIFF8 unicode-string. $segments bevat de eindoffsets van de
 * record-delen (CONTINUE), want bij een overgang wordt de optie-byte herhaald.
 *
 * @param list<int> $segments
 */
function moirai_spreadsheet_biff_string(string $buf, int &$pos, array $segments, bool $lenIs16 = true): string
{
    $len = $lenIs16 ? unpack('v', substr($buf, $pos, 2))[1] : ord($buf[$pos]);
    $pos += $lenIs16 ? 2 : 1;
    $flags = ord($buf[$pos]);
    $pos++;
    $rich = ($flags & 0x08) ? unpack('v', substr($buf, $pos, 2))[1] : 0;
    if ($flags & 0x08) {
        $pos += 2;
    }
    $ext = ($flags & 0x04) ? unpack('V', substr($buf, $pos, 4))[1] : 0;
    if ($flags & 0x04) {
        $pos += 4;
    }
    $wide = ($flags & 0x01) === 1;
    $out = '';
    $remaining = $len;
    while ($remaining > 0) {
        $segEnd = strlen($buf);
        foreach ($segments as $end) {
            if ($end > $pos) {
                $segEnd = $end;
                break;
            }
        }
        $bytesAvail = $segEnd - $pos;
        $charsAvail = $wide ? intdiv($bytesAvail, 2) : $bytesAvail;
        $take = min($remaining, $charsAvail);
        if ($take <= 0) {
            if ($pos >= strlen($buf)) {
                break;
            }
            // Nieuwe CONTINUE: eerste byte is opnieuw de optie-byte.
            $wide = (ord($buf[$pos]) & 0x01) === 1;
            $pos++;
            continue;
        }
        $bytes = substr($buf, $pos, $wide ? $take * 2 : $take);
        $out .= $wide ? (string) iconv('UTF-16LE', 'UTF-8', $bytes) : (string) iconv('ISO-8859-1', 'UTF-8', $bytes);
        $pos += $wide ? $take * 2 : $take;
        $remaining -= $take;
        if ($remaining > 0 && in_array($pos, $segments, true)) {
            $wide = (ord($buf[$pos]) & 0x01) === 1;
            $pos++;
        }
    }
    $pos += $rich * 4 + $ext;

    return $out;
}

function moirai_spreadsheet_read_xls(string $path): array
{
    $data = (string) file_get_contents($path);
    $wb = moirai_spreadsheet_ole_stream($data, ['Workbook', 'Book']);
    $len = strlen($wb);

    $records = [];
    for ($p = 0; $p + 4 <= $len;) {
        $h = unpack('vtype/vlen', substr($wb, $p, 4));
        $records[] = [$h['type'], $p + 4, substr($wb, $p + 4, $h['len'])];
        $p += 4 + $h['len'];
    }

    $sst = [];
    $sheetOffset = null;
    $count = count($records);
    for ($i = 0; $i < $count; $i++) {
        [$type, $offset, $body] = $records[$i];
        if ($type === 0x0085 && $sheetOffset === null && strlen($body) >= 6 && ord($body[5]) === 0) {
            $sheetOffset = unpack('V', substr($body, 0, 4))[1];
        }
        if ($type === 0x00FC) {
            $buf = $body;
            $segments = [strlen($buf)];
            while ($i + 1 < $count && $records[$i + 1][0] === 0x003C) {
                $i++;
                $buf .= $records[$i][2];
                $segments[] = strlen($buf);
            }
            $total = unpack('V', substr($buf, 4, 4))[1];
            $pos = 8;
            for ($n = 0; $n < $total && $pos < strlen($buf); $n++) {
                $sst[] = moirai_spreadsheet_biff_string($buf, $pos, $segments);
            }
        }
    }
    if ($sheetOffset === null) {
        throw new InvalidArgumentException('Geen werkblad gevonden in .xls.');
    }

    $rows = [];
    $inSheet = false;
    $pendingFormula = null;
    foreach ($records as [$type, $offset, $body]) {
        if (!$inSheet) {
            if ($offset - 4 === $sheetOffset) {
                $inSheet = true;
            }
            continue;
        }
        if ($type === 0x000A) {
            break;
        }
        if (strlen($body) < 6 && $type !== 0x0207) {
            continue;
        }
        $row = $type !== 0x0207 ? unpack('v', substr($body, 0, 2))[1] : 0;
        $col = $type !== 0x0207 ? unpack('v', substr($body, 2, 2))[1] : 0;
        switch ($type) {
            case 0x00FD: // LABELSST
                $rows[$row][$col] = $sst[unpack('V', substr($body, 6, 4))[1]] ?? '';
                break;
            case 0x0204: // LABEL
                $pos = 6;
                $rows[$row][$col] = moirai_spreadsheet_biff_string($body, $pos, [strlen($body)]);
                break;
            case 0x0203: // NUMBER
                $rows[$row][$col] = unpack('e', substr($body, 6, 8))[1];
                break;
            case 0x027E: // RK
                $rows[$row][$col] = moirai_spreadsheet_rk(unpack('V', substr($body, 6, 4))[1]);
                break;
            case 0x00BD: // MULRK
                $last = unpack('v', substr($body, -2))[1];
                for ($c = $col, $p = 4; $c <= $last; $c++, $p += 6) {
                    $rows[$row][$c] = moirai_spreadsheet_rk(unpack('V', substr($body, $p + 2, 4))[1]);
                }
                break;
            case 0x0205: // BOOLERR
                $rows[$row][$col] = ord($body[7]) === 0 ? ord($body[6]) : null;
                break;
            case 0x0006: // FORMULA
                $result = substr($body, 6, 8);
                if (substr($result, 6, 2) === "\xFF\xFF") {
                    $kind = ord($result[0]);
                    if ($kind === 0) {
                        $pendingFormula = [$row, $col];
                    } elseif ($kind === 1) {
                        $rows[$row][$col] = ord($result[2]);
                    } else {
                        $rows[$row][$col] = $kind === 3 ? '' : null;
                    }
                } else {
                    $rows[$row][$col] = unpack('e', $result)[1];
                }
                break;
            case 0x0207: // STRING (resultaat van een formule)
                if ($pendingFormula !== null) {
                    $pos = 0;
                    $rows[$pendingFormula[0]][$pendingFormula[1]] = moirai_spreadsheet_biff_string($body, $pos, [strlen($body)]);
                    $pendingFormula = null;
                }
                break;
        }
    }

    $filled = [];
    foreach ($rows as $r => $cells) {
        $filled[$r] = moirai_spreadsheet_fill_row($cells);
    }

    return moirai_spreadsheet_fill_rows($filled);
}
