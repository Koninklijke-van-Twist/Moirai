<?php

require_once __DIR__ . '/moirai_data.php';
require_once __DIR__ . '/login_path.php';
moirai_require_login_file('session_config.php');

configure_app_session();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$sessionEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
$isLoggedIn = $sessionEmail !== '' && filter_var($sessionEmail, FILTER_VALIDATE_EMAIL);

$link = moirai_parse_deep_link_from_request();
$device = null;
if ($link !== null) {
    $device = moirai_get_device($link['type'], $link['deviceId']);
}

$deviceName = moirai_device_display_name($device);
$deviceNameHtml = htmlspecialchars($deviceName, ENT_QUOTES, 'UTF-8');

session_write_close();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title><?= $isLoggedIn ? 'Gevonden apparaat' : 'Bedankt!' ?> – Moirai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="brand.css">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px 40px;
        }

        .card {
            width: min(560px, 100%);
            background: var(--kvt-panel-bg);
            border: 1px solid var(--kvt-line);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 28px 24px 32px;
            text-align: center;
        }

        .logo {
            max-width: 280px;
            width: 100%;
            height: auto;
            margin: 0 auto 28px;
            display: block;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 1.9rem;
            color: var(--kvt-perkins-blue);
        }

        .lead {
            margin: 0 0 20px;
            color: var(--kvt-muted);
            font-size: 1.05rem;
            line-height: 1.55;
        }

        .device-name {
            margin: 0 0 20px;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--kvt-text);
        }

        .address {
            margin: 0 auto 20px;
            padding: 16px 18px;
            border-radius: 10px;
            background: var(--kvt-page-bg);
            border: 1px solid var(--kvt-line);
            text-align: left;
            line-height: 1.6;
            font-style: normal;
            color: var(--kvt-text);
        }

        .reward {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--kvt-perkins-blue);
        }

        footer {
            margin-top: 28px;
            font-size: 0.9rem;
            color: var(--kvt-muted);
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="card">
            <img class="logo" src="https://sleutels.kvt.nl/kvt_logo.png" alt="KVT logo">

            <?php if ($isLoggedIn): ?>
                <h1>Gevonden apparaat</h1>
                <p class="lead">Breng het apparaat naar ICT, dan nemen wij het van daar over.</p>
            <?php else: ?>
                <h1>Bedankt!</h1>
                <p class="lead">Je hebt onze verloren <?= $deviceNameHtml ?> gevonden!</p>
                <p class="lead">Breng hem naar:</p>
                <address class="address">
                    Keerweer 1<br>
                    3316KA Dordrecht<br>
                    Zuid-Holland
                </address>
                <p class="reward">en ontvang een beloning!</p>
            <?php endif; ?>

            <footer>Koninklijke van Twist</footer>
        </div>
    </main>
</body>
</html>
