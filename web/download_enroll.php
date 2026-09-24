<?php

/**
 * Laptop-initpakket.
 *
 * Bron in git is de map web/init-laptop/ (geen binary zip).
 * Deze endpoint pakt die map in als init-laptop.zip op het moment van download.
 * Directe HTTP-toegang tot de map is geblokkeerd via web/init-laptop/.htaccess.
 */

function moirai_init_laptop_dir(): string
{
    return __DIR__ . '/init-laptop';
}

function moirai_init_laptop_has_shebang(string $path): bool
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }

    $head = fread($handle, 2);
    fclose($handle);

    return $head === '#!';
}

/**
 * Unix-modus voor een zip-entry (type + permissies), inclusief uitvoerbaar-bit.
 * Shebang-scripts blijven uitvoerbaar, ook als de webserver het bit niet bewaart.
 */
function moirai_init_laptop_zip_mode(string $path, bool $isDir): int
{
    $perms = @fileperms($path);
    $permBits = is_int($perms) ? ($perms & 0777) : ($isDir ? 0755 : 0644);
    if (!$isDir && moirai_init_laptop_has_shebang($path)) {
        $permBits |= 0755;
    }

    $type = $isDir ? 0040000 : 0100000;

    return $type | ($permBits & 0777);
}

function moirai_init_laptop_skip_entry(string $relative): bool
{
    $relative = str_replace('\\', '/', $relative);
    if ($relative === '' || str_contains($relative, "\0")) {
        return true;
    }

    foreach (explode('/', $relative) as $part) {
        if ($part === '' || $part === '.' || $part === '..' || $part === '.htaccess') {
            return true;
        }
    }

    return false;
}

/**
 * Schrijf init-laptop.zip vanuit de bronmap naar $destPath.
 *
 * @throws RuntimeException
 */
function moirai_write_init_laptop_zip(string $destPath, ?string $sourceDir = null): void
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('ZipArchive ontbreekt.');
    }

    $sourceDir ??= moirai_init_laptop_dir();
    $root = realpath($sourceDir);
    if ($root === false || !is_dir($root)) {
        throw new RuntimeException('Init-map niet gevonden.');
    }

    $rootPrefix = $root . DIRECTORY_SEPARATOR;
    $directories = [];
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $path = $item->getPathname();
        if (!str_starts_with($path, $rootPrefix)) {
            continue;
        }

        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($rootPrefix)));
        if (moirai_init_laptop_skip_entry($relative)) {
            continue;
        }

        $real = realpath($path);
        if ($real === false || ($real !== $root && !str_starts_with($real, $rootPrefix))) {
            continue;
        }

        if ($item->isDir()) {
            $directories[$relative] = $path;
            continue;
        }

        if ($item->isFile()) {
            $files[$relative] = $path;
        }
    }

    ksort($directories, SORT_STRING);
    ksort($files, SORT_STRING);

    if (is_file($destPath)) {
        unlink($destPath);
    }

    $zip = new ZipArchive();
    $opened = $zip->open($destPath, ZipArchive::CREATE);
    if ($opened !== true) {
        throw new RuntimeException('Zip kon niet worden gemaakt.');
    }

    $applyMode = static function (ZipArchive $zip, string $entryName, string $path, bool $isDir): void {
        $attr = moirai_init_laptop_zip_mode($path, $isDir) << 16;
        $index = $zip->locateName($entryName);
        if ($index === false && $isDir) {
            $index = $zip->locateName(rtrim($entryName, '/') . '/');
        }
        if ($index === false) {
            $index = $zip->locateName(rtrim($entryName, '/'));
        }
        if ($index === false) {
            return;
        }

        $zip->setExternalAttributesIndex($index, ZipArchive::OPSYS_UNIX, $attr);
    };

    try {
        foreach ($directories as $relative => $path) {
            if (!$zip->addEmptyDir($relative)) {
                throw new RuntimeException('Map kon niet aan de zip worden toegevoegd.');
            }
            $applyMode($zip, $relative, $path, true);
        }

        foreach ($files as $relative => $path) {
            if (!$zip->addFile($path, $relative)) {
                throw new RuntimeException('Bestand kon niet aan de zip worden toegevoegd.');
            }
            $applyMode($zip, $relative, $path, false);
        }

        if (!$zip->close()) {
            throw new RuntimeException('Zip kon niet worden afgesloten.');
        }
    } catch (Throwable $e) {
        $zip->close();
        if (is_file($destPath)) {
            unlink($destPath);
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException('Zip kon niet worden gemaakt.', 0, $e);
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== realpath(__FILE__)) {
    return;
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/moirai_data.php';

if (!moirai_is_admin()) {
    http_response_code(403);
    exit('Geen rechten.');
}

$initDir = moirai_init_laptop_dir();
if (!is_dir($initDir) || !is_readable($initDir)) {
    http_response_code(404);
    exit('Bestand niet gevonden.');
}

$tmp = tempnam(sys_get_temp_dir(), 'moirai-init-');
if ($tmp === false) {
    http_response_code(500);
    exit('Download kon niet worden gemaakt.');
}

ignore_user_abort(true);

try {
    moirai_write_init_laptop_zip($tmp);
    $size = filesize($tmp);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="init-laptop.zip"');
    if ($size !== false) {
        header('Content-Length: ' . (string) $size);
    }
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($tmp);
} catch (Throwable $e) {
    error_log('init-laptop zip: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        echo 'Download kon niet worden gemaakt.';
    }
} finally {
    if (is_file($tmp)) {
        unlink($tmp);
    }
}

exit;
