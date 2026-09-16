<?php

function moirai_mail_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function moirai_app_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return 'https://sleutels.kvt.nl/moirai';
    }

    $path = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

    return $scheme . '://' . $host . ($path !== '' && $path !== '/' ? $path : '');
}

function moirai_device_app_url(string $type, string $id): string
{
    return moirai_app_base_url() . '/index.php?' . http_build_query([
        'type' => $type,
        'device' => $id,
    ]);
}

function moirai_mail_smtp_settings(): ?array
{
    global $reportMail;
    if (!is_array($reportMail ?? null)) {
        return null;
    }

    $smtp = $reportMail['smtp'] ?? null;
    if (!is_array($smtp)) {
        return null;
    }

    $host = trim((string) ($smtp['host'] ?? ''));
    if ($host === '') {
        return null;
    }

    return [
        'host' => $host,
        'port' => (int) ($smtp['port'] ?? 587),
        'encryption' => strtolower(trim((string) ($smtp['encryption'] ?? 'tls'))),
        'username' => trim((string) ($smtp['username'] ?? '')),
        'password' => (string) ($smtp['password'] ?? ''),
        'timeout' => max(5, (int) ($smtp['timeout'] ?? 20)),
    ];
}

function moirai_mail_smtp_read($socket): string
{
    $data = '';
    while (is_resource($socket) && !feof($socket)) {
        $line = fgets($socket, 8192);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function moirai_mail_smtp_expect(string $response, array $codes): bool
{
    return $response !== '' && in_array((int) substr($response, 0, 3), $codes, true);
}

function moirai_mail_smtp_dot_stuff(string $body): string
{
    $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
    $stuffed = [];
    foreach ($lines as $line) {
        $stuffed[] = ($line !== '' && $line[0] === '.') ? '.' . $line : $line;
    }

    return implode("\r\n", $stuffed);
}

function moirai_mail_send_smtp(
    string $to,
    string $fromEmail,
    string $fromName,
    string $encodedSubject,
    string $mimeHeaders,
    string $body
): bool {
    $smtp = moirai_mail_smtp_settings();
    if ($smtp === null) {
        return false;
    }

    $encryption = $smtp['encryption'];
    if ($encryption !== 'ssl' && $encryption !== 'tls') {
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://')
        . $smtp['host'] . ':' . $smtp['port'];
    $socket = @stream_socket_client($remote, $errno, $errstr, $smtp['timeout'], STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        return false;
    }

    stream_set_timeout($socket, $smtp['timeout']);
    if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [220])) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "EHLO moirai.local\r\n");
    if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [250])) {
        fclose($socket);
        return false;
    }

    $tlsActive = $encryption === 'ssl';
    if ($encryption === 'tls') {
        fwrite($socket, "STARTTLS\r\n");
        if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [220])) {
            fclose($socket);
            return false;
        }
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
            fclose($socket);
            return false;
        }
        fwrite($socket, "EHLO moirai.local\r\n");
        if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [250])) {
            fclose($socket);
            return false;
        }
        $tlsActive = true;
    }

    if ($smtp['username'] !== '' && $smtp['password'] !== '') {
        if (!$tlsActive) {
            fclose($socket);
            return false;
        }
        fwrite($socket, "AUTH LOGIN\r\n");
        if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [334])) {
            fclose($socket);
            return false;
        }
        fwrite($socket, base64_encode($smtp['username']) . "\r\n");
        if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [334])) {
            fclose($socket);
            return false;
        }
        fwrite($socket, base64_encode($smtp['password']) . "\r\n");
        if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [235])) {
            fclose($socket);
            return false;
        }
    }

    fwrite($socket, 'MAIL FROM:<' . $fromEmail . ">\r\n");
    if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [250])) {
        fclose($socket);
        return false;
    }
    fwrite($socket, 'RCPT TO:<' . $to . ">\r\n");
    if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [250, 251])) {
        fclose($socket);
        return false;
    }
    fwrite($socket, "DATA\r\n");
    if (!moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [354])) {
        fclose($socket);
        return false;
    }

    $payload = 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail) . "\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . $encodedSubject . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . $mimeHeaders . "\r\n\r\n"
        . moirai_mail_smtp_dot_stuff($body);
    fwrite($socket, $payload . "\r\n.\r\n");
    $ok = moirai_mail_smtp_expect(moirai_mail_smtp_read($socket), [250]);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return $ok;
}

function moirai_send_mail(string $to, string $subject, string $plainBody, string $htmlBody): bool
{
    $to = strtolower(trim($to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    global $reportMail;
    $fromEmail = 'kvtbot@kvt.nl';
    $fromName = 'Moirai';
    if (is_array($reportMail ?? null)) {
        $fromEmail = (string) ($reportMail['from_email'] ?? $fromEmail);
        $fromName = (string) ($reportMail['from_name'] ?? $fromName);
    }

    $boundary = 'moirai_' . bin2hex(random_bytes(8));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $mimeHeaders = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $plainBody . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $htmlBody . "\r\n"
        . '--' . $boundary . '--';

    if (moirai_mail_smtp_settings() !== null && moirai_mail_send_smtp($to, $fromEmail, $fromName, $encodedSubject, $mimeHeaders, $body)) {
        return true;
    }

    $mailHeaders = "MIME-Version: 1.0\r\n"
        . $mimeHeaders . "\r\n"
        . 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail);

    return @mail($to, $encodedSubject, $body, $mailHeaders);
}

function moirai_send_aging_alert(array $device, string $type): bool
{
    $typeKey = moirai_type_key($type) ?? 'laptops';
    $keyField = moirai_device_key_field($typeKey);
    $id = trim((string) ($device[$keyField] ?? $device['id'] ?? ''));
    $model = trim((string) ($device['model'] ?? $device['naam'] ?? ''));
    $typeLabel = $typeKey === 'phones' ? 'Telefoon' : 'Laptop';
    $idLabel = $typeKey === 'phones' ? 'IMEI' : 'Serienummer';
    $purchase = trim((string) ($device['aanschafdatum'] ?? ''));
    $status = moirai_device_status($device);
    if ($status === 'assigned') {
        $assignee = trim((string) ($device['uitgegeven_aan']['naam'] ?? ''))
            . ' (' . trim((string) ($device['uitgegeven_aan']['email'] ?? '')) . ')';
    } elseif ($status === 'unavailable') {
        $assignee = 'Onbeschikbaar';
    } else {
        $assignee = 'Reserve';
    }

    $url = $id !== '' ? moirai_device_app_url(moirai_public_type($typeKey), $id) : moirai_app_base_url();
    $subject = 'Moirai: vervanging nodig — ' . ($model !== '' ? $model : $typeLabel)
        . ($id !== '' ? ' (' . $id . ')' : '');
    $plain = "Er is een {$typeLabel} die 4 jaar en 10 maanden of ouder is en vervangen moet worden.\n\n"
        . "Type: {$typeLabel}\n"
        . "Model: {$model}\n"
        . "{$idLabel}: {$id}\n"
        . "Aanschafdatum: {$purchase}\n"
        . "Uitgegeven aan: {$assignee}\n\n"
        . "Open in Moirai: {$url}\n";
    $html = '<p>Er is een ' . moirai_mail_h($typeLabel)
        . ' die 4 jaar en 10 maanden of ouder is en vervangen moet worden.</p>'
        . '<ul>'
        . '<li>Type: ' . moirai_mail_h($typeLabel) . '</li>'
        . '<li>Model: ' . moirai_mail_h($model) . '</li>'
        . '<li>' . moirai_mail_h($idLabel) . ': ' . moirai_mail_h($id) . '</li>'
        . '<li>Aanschafdatum: ' . moirai_mail_h($purchase) . '</li>'
        . '<li>Uitgegeven aan: ' . moirai_mail_h($assignee) . '</li>'
        . '</ul>'
        . '<p><a href="' . moirai_mail_h($url) . '">Open in Moirai</a></p>';

    return moirai_send_mail(MOIRAI_AGING_ALERT_EMAIL, $subject, $plain, $html);
}
