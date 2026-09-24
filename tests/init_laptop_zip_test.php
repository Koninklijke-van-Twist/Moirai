<?php

/**
 * CLI checks for the on-the-fly laptop init zip. Run: php tests/init_laptop_zip_test.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../web/download_enroll.php';

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

function zip_unix_mode(ZipArchive $zip, string $name): int
{
    $opsys = 0;
    $attr = 0;
    $found = $zip->getExternalAttributesName($name, $opsys, $attr);
    if ($found !== true) {
        $found = $zip->getExternalAttributesName(rtrim($name, '/') . '/', $opsys, $attr);
    }

    return $found === true ? (($attr >> 16) & 0xFFFF) : -1;
}

function folder_files(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if (basename($relative) === '.htaccess') {
            continue;
        }
        $files[$relative] = $item->getPathname();
    }
    ksort($files, SORT_STRING);

    return $files;
}

$package = moirai_init_laptop_dir();
expect(is_dir($package), 'package dir exists');
expect(is_file($package . '/.htaccess'), 'package dir blocks direct http');
expect(!is_file(dirname($package) . '/init-laptop.zip'), 'binary zip is not in web/');

$tmp = tempnam(sys_get_temp_dir(), 'init-laptop-test-');
expect($tmp !== false, 'temp path');
moirai_write_init_laptop_zip($tmp);

$zip = new ZipArchive();
expect($zip->open($tmp) === true, 'package zip opens');

$zipFiles = [];
$unsafe = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (!is_string($name) || str_ends_with($name, '/')) {
        continue;
    }
    if (str_contains($name, '..') || basename($name) === '.htaccess') {
        $unsafe[] = $name;
    }
    $zipFiles[$name] = true;
}
expect($unsafe === [], 'zip entries stay inside the package and omit .htaccess');

$diskFiles = folder_files($package);
expect($zipFiles === array_fill_keys(array_keys($diskFiles), true), 'zip file list matches the folder');

$top = [];
foreach (array_keys($diskFiles) as $name) {
    $top[explode('/', $name)[0]] = true;
}
foreach ([
    'bootanimation',
    'kvt-rdp',
    'kvt-rds-connect',
    'KVT-Energise',
    'enroll.sh',
    'hosts.sh',
    'init-device.sh',
    'readme.md',
    'kvtlogo.png',
    'backgr_1.png',
] as $expected) {
    expect(isset($top[$expected]), "top-level {$expected}");
}

$byteMismatches = 0;
$modeMismatches = 0;
foreach ($diskFiles as $relative => $path) {
    $fromZip = $zip->getFromName($relative);
    $fromDisk = file_get_contents($path);
    if ($fromZip !== $fromDisk) {
        $byteMismatches++;
    }

    $zipMode = zip_unix_mode($zip, $relative);
    $expectExec = moirai_init_laptop_has_shebang($path) || ((fileperms($path) & 0111) !== 0);
    $isExec = $zipMode >= 0 && ($zipMode & 0111) !== 0;
    if ($expectExec !== $isExec) {
        $modeMismatches++;
        echo "FAIL  mode {$relative} zip={$zipMode} expectExec=" . ($expectExec ? 'yes' : 'no') . "\n";
    }
}
expect($byteMismatches === 0, 'every package file matches the zip bytes');
expect($modeMismatches === 0, 'executable bits match the folder and shebangs');

$readmeMode = zip_unix_mode($zip, 'readme.md');
expect($readmeMode >= 0 && ($readmeMode & 0111) === 0, 'readme.md is not executable');
$rdsMode = zip_unix_mode($zip, 'kvt-rds-connect/install.sh');
expect($rdsMode >= 0 && ($rdsMode & 0111) !== 0, 'kvt-rds-connect/install.sh is executable');

$zip->close();
unlink($tmp);

$fixture = sys_get_temp_dir() . '/moirai-init-fixture-' . bin2hex(random_bytes(4));
mkdir($fixture . '/bin', 0755, true);
file_put_contents($fixture . '/run.sh', "#!/bin/sh\necho ok\n");
chmod($fixture . '/run.sh', 0644);
file_put_contents($fixture . '/notes.txt', "hello\n");
chmod($fixture . '/notes.txt', 0644);
file_put_contents($fixture . '/.htaccess', "Require all denied\n");
file_put_contents($fixture . '/bin/tool', "#!/bin/sh\necho tool\n");
chmod($fixture . '/bin/tool', 0755);

$fixtureZip = tempnam(sys_get_temp_dir(), 'init-laptop-fixture-');
expect($fixtureZip !== false, 'fixture zip path');
moirai_write_init_laptop_zip($fixtureZip, $fixture);

$built = new ZipArchive();
expect($built->open($fixtureZip) === true, 'fixture zip opens');
$fixtureNames = [];
for ($i = 0; $i < $built->numFiles; $i++) {
    $name = $built->getNameIndex($i);
    if (is_string($name)) {
        $fixtureNames[] = rtrim($name, '/');
    }
}
expect(in_array('run.sh', $fixtureNames, true), 'fixture keeps shebang script');
expect(in_array('notes.txt', $fixtureNames, true), 'fixture keeps plain file');
expect(in_array('bin/tool', $fixtureNames, true), 'fixture keeps nested tool');
expect(!in_array('.htaccess', $fixtureNames, true), 'fixture drops htaccess');
$runMode = zip_unix_mode($built, 'run.sh');
expect($runMode >= 0 && ($runMode & 0111) !== 0, 'shebang script becomes executable when the filesystem bit is missing');
$notesMode = zip_unix_mode($built, 'notes.txt');
expect($notesMode >= 0 && ($notesMode & 0111) === 0, 'plain file stays non-executable');
$toolMode = zip_unix_mode($built, 'bin/tool');
expect($toolMode >= 0 && ($toolMode & 0111) !== 0, 'nested executable bit is kept');
$built->close();
unlink($fixtureZip);

echo $failures === 0 ? "passed\n" : "{$failures} failed\n";
exit($failures === 0 ? 0 : 1);
