<?php

/**
 * CLI checks for leeftijd_min / leeftijd_max list filters. Run: php tests/age_filter_test.php
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

$tmp = sys_get_temp_dir() . '/moirai_age_filter_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$GLOBALS['moirai_db_file'] = $tmp;
$GLOBALS['moirai_today'] = new DateTimeImmutable('2026-10-15');

require_once __DIR__ . '/../web/moirai_data.php';

expect(moirai_device_age_months(['aanschafdatum' => '2026-10-15']) === 0, 'same day is 0 months');
expect(moirai_device_age_months(['aanschafdatum' => '2026-09-15']) === 1, 'one month later is 1');
expect(moirai_device_age_months(['aanschafdatum' => '2025-10-15']) === 12, 'one year is 12 months');
expect(moirai_device_age_months(['aanschafdatum' => '2024-04-15']) === 30, '1y6m is 30 months');
expect(moirai_device_age_months(['aanschafdatum' => '']) === null, 'empty purchase date is null');
expect(moirai_device_age_months(['aanschafdatum' => '2027-01-01']) === 0, 'future purchase clamps to 0');

$young = ['aanschafdatum' => '2026-04-15']; // 6 months
$mid = ['aanschafdatum' => '2024-10-15']; // 24 months
$old = ['aanschafdatum' => '2021-10-15']; // 60 months
$missing = ['aanschafdatum' => ''];

expect(moirai_device_matches_filter($young, '', 'all', ['leeftijd_min' => '6', 'leeftijd_max' => '24']), '6m in 6–24');
expect(moirai_device_matches_filter($mid, '', 'all', ['leeftijd_min' => '6', 'leeftijd_max' => '24']), '24m in 6–24');
expect(!moirai_device_matches_filter($old, '', 'all', ['leeftijd_min' => '6', 'leeftijd_max' => '24']), '60m outside 6–24');
expect(!moirai_device_matches_filter($missing, '', 'all', ['leeftijd_min' => '0']), 'missing date excluded when age filter set');
expect(moirai_device_matches_filter($old, '', 'all', ['leeftijd_min' => '48']), 'min-only keeps older device');
expect(!moirai_device_matches_filter($young, '', 'all', ['leeftijd_min' => '48']), 'min-only drops young device');
expect(moirai_device_matches_filter($young, '', 'all', ['leeftijd_max' => '12']), 'max-only keeps young device');
expect(!moirai_device_matches_filter($old, '', 'all', ['leeftijd_max' => '12']), 'max-only drops old device');

$parsed = moirai_parse_list_filters('laptop', ['os' => 'Windows', 'leeftijd_min' => '12', 'leeftijd_max' => '36']);
expect(($parsed['os'] ?? null) === 'Windows', 'parse keeps attribute filter');
expect(($parsed['leeftijd_min'] ?? null) === '12', 'parse leeftijd_min');
expect(($parsed['leeftijd_max'] ?? null) === '36', 'parse leeftijd_max');

$ignored = moirai_parse_list_filters('accessory', ['leeftijd_min' => '12']);
expect(!isset($ignored['leeftijd_min']), 'accessories ignore age filter params');

$bad = moirai_parse_list_filters('phone', ['leeftijd_min' => '-3', 'leeftijd_max' => 'abc']);
expect($bad === [], 'invalid age values ignored');

@unlink($tmp);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failure(s)\n");
    exit(1);
}

echo "\nAll age filter checks passed.\n";
