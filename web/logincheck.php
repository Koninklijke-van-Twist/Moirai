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

    require_once __DIR__ . '/login_path.php';
    require moirai_resolve_login_file('lib.php');



    $sessionEmail = (string) ($_SESSION['user']['email'] ?? '');



    if (isset($allowedUsers) &&

        !moirai_email_in_list($sessionEmail, $allowedUsers)

    ) {

        require __DIR__ . '/403.php';

        die();

    }



    $isAdmin = isset($ictUsers) && is_array($ictUsers)

        && moirai_email_in_list($sessionEmail, $ictUsers);



    if (session_status() !== PHP_SESSION_ACTIVE) {

        session_start();

    }



    $_SESSION['user']['admin'] = $isAdmin;

    $analyticsEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    $analyticsApiKey = trim((string) ($_SESSION['user']['api_key'] ?? ''));
    $analyticsOid = strtolower(trim((string) ($_SESSION['user']['oid'] ?? '')));
    if ($analyticsEmail !== '' && $analyticsApiKey !== '' && $analyticsOid !== '') {
        $analyticsScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $analyticsHost = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $analyticsBase = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        $analyticsUrl = $analyticsScheme . '://' . $analyticsHost . $analyticsBase . '/analytics/analytics.php?' . http_build_query([
            'user_email' => $analyticsEmail,
            'api_key' => $analyticsApiKey,
            'oid' => $analyticsOid,
        ], '', '&', PHP_QUERY_RFC3986);

        if (function_exists('curl_init')) {
            $analyticsCurl = curl_init($analyticsUrl);
            curl_setopt_array($analyticsCurl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 1,
                CURLOPT_HTTPHEADER => ['X-API-Key: ' . $analyticsApiKey],
            ]);
            curl_exec($analyticsCurl);
            curl_close($analyticsCurl);
        }
    }

}


