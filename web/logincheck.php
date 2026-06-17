<?php

function is_trusted_requester(): bool
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $server = $_SERVER['SERVER_ADDR'] ?? '';
    $trusted = ['127.0.0.1', '::1'];
    if ($remote === $server && $remote !== '') {
        return true;
    }
    if (in_array($remote, $trusted, true)) {
        return true;
    }
    return false;
}

if (is_trusted_requester()) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $_SESSION['user'] = [
            'email' => 'localtester@kvt.nl',
            'name' => 'Local Tester',
            'admin' => true,
        ];
} else {
    require __DIR__ . '/../login/lib.php';

    if ( isset($allowedUsers) &&
        !array_any($allowedUsers, function ($email) {
            return strtolower($email) == strtolower($_SESSION['user']['email']);
        })
    ) {
        require __DIR__ . "/../login/403.php";
        die();
    }

    $_SESSION['user']['admin'] = false;

    if ( isset($ictUsers) &&
        array_any($ictUsers, function ($email) {
            return strtolower($email) == strtolower($_SESSION['user']['email']);
        })
    ) {
        $_SESSION['user']['admin'] = true;
    }
}
