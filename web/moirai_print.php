<?php

require_once __DIR__ . '/posprint_url.php';

const MOIRAI_PRINT_VERIFY_FILE = __DIR__ . '/data/print_verify_codes.json';
const MOIRAI_PRINT_VERIFY_TTL_SECONDS = 600;

function moirai_request_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }

    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    $scriptDir = rtrim($scriptDir, '/');

    return $scheme . '://' . $host . $scriptDir;
}

function moirai_absolute_web_url(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    return moirai_request_base_url() . '/' . $relativePath;
}

function moirai_print_verify_load(): array
{
    if (!is_file(MOIRAI_PRINT_VERIFY_FILE)) {
        return [];
    }

    $raw = file_get_contents(MOIRAI_PRINT_VERIFY_FILE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function moirai_print_verify_save(array $codes): void
{
    $dir = dirname(MOIRAI_PRINT_VERIFY_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    file_put_contents(
        MOIRAI_PRINT_VERIFY_FILE,
        json_encode($codes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function moirai_print_verify_prune(array $codes): array
{
    $now = time();
    $kept = [];
    foreach ($codes as $code => $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $expires = (int) ($meta['expires'] ?? 0);
        if ($expires > $now) {
            $kept[(string) $code] = $meta;
        }
    }

    return $kept;
}

function moirai_create_print_verify_code(): string
{
    $codes = moirai_print_verify_prune(moirai_print_verify_load());
    $code = bin2hex(random_bytes(12));
    $codes[$code] = [
        'expires' => time() + MOIRAI_PRINT_VERIFY_TTL_SECONDS,
        'created' => time(),
    ];
    moirai_print_verify_save($codes);

    return $code;
}

function moirai_check_print_verify_code(string $code): bool
{
    $code = trim($code);
    if ($code === '' || !preg_match('/^[a-f0-9]{16,64}$/', $code)) {
        return false;
    }

    $codes = moirai_print_verify_prune(moirai_print_verify_load());
    $ok = isset($codes[$code]);
    moirai_print_verify_save($codes);

    return $ok;
}

function moirai_format_print_date_value(string $value): string
{
    $raw = trim($value);
    if ($raw === '') {
        return '-';
    }

    $parts = explode('-', $raw);
    if (count($parts) !== 3) {
        return $raw;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('j M Y', $timestamp);
}

function moirai_print_value(?string $value): string
{
    $text = trim((string) $value);
    return $text !== '' ? $text : '-';
}

/**
 * @return array{version: int, metadata: array, body: string}
 */
function moirai_build_device_pos_document(array $device, string $type): array
{
    $typeKey = moirai_type_key($type);
    if ($typeKey === null) {
        throw new InvalidArgumentException(moirai_loc('moirai.error.unknown_type'));
    }

    $keyField = moirai_device_key_field($typeKey);
    $id = trim((string) ($device[$keyField] ?? $device['id'] ?? ''));
    $title = trim((string) ($device['model'] ?? $device['naam'] ?? ''));
    if ($title === '') {
        $title = moirai_loc('moirai.unnamed');
    }

    $shortType = $typeKey === 'laptops' ? 'l' : 'p';
    $qrUrl = moirai_absolute_web_url('index.php') . '?' . http_build_query([
        't' => $shortType,
        'd' => $id,
    ]);
    $logoUrl = moirai_absolute_web_url('icons/kvt-logo.png');

    $lines = [];
    if ($typeKey === 'laptops') {
        $lines[] = [moirai_loc('moirai.print.ram'), moirai_print_value($device['ram'] ?? null)];
        $lines[] = [moirai_loc('moirai.print.storage'), moirai_print_value($device['opslag'] ?? null)];
        $lines[] = [moirai_loc('moirai.print.cpu'), moirai_print_value($device['cpu'] ?? null)];
    } else {
        $lines[] = [moirai_loc('moirai.print.screen'), moirai_print_value($device['schermformaat'] ?? null)];
        $lines[] = [moirai_loc('moirai.print.storage'), moirai_print_value($device['opslag'] ?? null)];
    }

    $osCombined = trim(implode(' ', array_filter([
        trim((string) ($device['os'] ?? '')),
        trim((string) ($device['os_versie'] ?? '')),
    ])));
    $lines[] = [moirai_loc('moirai.print.purchased'), moirai_format_print_date_value((string) ($device['aanschafdatum'] ?? ''))];
    $lines[] = [moirai_loc('moirai.print.os'), $osCombined !== '' ? $osCombined : '-'];

    if ($typeKey === 'laptops') {
        $lines[] = [moirai_loc('moirai.print.keyboard'), moirai_print_value($device['toetsenbord'] ?? null)];
    }

    $body = [];
    $body[] = ':center:';
    $body[] = '@image ' . $logoUrl . ' width=50% align=center';
    $body[] = '# ' . $title;
    $body[] = '@qr ' . $qrUrl . ' size=4 ecc=M align=center';
    if ($id !== '') {
        $body[] = '## ' . $id;
    }
    $body[] = '---';

    foreach ($lines as [$label, $value]) {
        $body[] = ':left: ' . $label . ': :right: ' . $value;
    }

    $body[] = '@cut';

    return [
        'version' => 1,
        'metadata' => [
            'title' => 'Moirai: ' . $title,
            'page_width_mm' => 53.0,
            'chars_per_line' => 32,
            'codepage' => 'CP437',
        ],
        'body' => implode("\n", $body) . "\n",
    ];
}

function moirai_build_device_posprint_url(array $device, string $type): string
{
    $pos = moirai_build_device_pos_document($device, $type);
    $code = moirai_create_print_verify_code();
    $referrer = moirai_absolute_web_url('print_verify.php') . '?' . http_build_query([
        'code' => $code,
    ]);

    return posprint_url($pos, [
        'noconfirm' => true,
        'referrer' => $referrer,
    ]);
}
