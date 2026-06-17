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



function moirai_email_in_list(string $email, array $list): bool

{

    $email = strtolower(trim($email));

    if ($email === '') {

        return false;

    }



    foreach ($list as $entry) {

        if (strtolower(trim((string) $entry)) === $email) {

            return true;

        }

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



    $sessionEmail = (string) ($_SESSION['user']['email'] ?? '');



    if (isset($allowedUsers) &&

        !moirai_email_in_list($sessionEmail, $allowedUsers)

    ) {

        require __DIR__ . '/../login/403.php';

        die();

    }



    $isAdmin = isset($ictUsers) && is_array($ictUsers)

        && moirai_email_in_list($sessionEmail, $ictUsers);



    if (session_status() !== PHP_SESSION_ACTIVE) {

        session_start();

    }



    $_SESSION['user']['admin'] = $isAdmin;

}


