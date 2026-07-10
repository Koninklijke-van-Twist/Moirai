<?php

function moirai_resolve_login_file(string $file): string
{
    $file = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $file), DIRECTORY_SEPARATOR);
    $candidates = [
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . $file,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . $file,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Login-bestand niet gevonden: ' . $file);
}

function moirai_require_login_file(string $file): void
{
    require_once moirai_resolve_login_file($file);
}
