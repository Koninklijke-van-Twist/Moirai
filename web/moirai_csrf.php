<?php

/**
 * CSRF-bescherming voor alle state-changing beheeracties die via de sessie gaan
 * (devices_api.php, print_label.php, kvt-chat notities, budget_api.php).
 * api.php (API-key: X-API-Key / Bearer) gebruikt dit NIET; integraties zoals Metis
 * blijven zonder token werken.
 *
 * Client: index.php zet het token in <meta name="moirai-csrf"> en voegt het als header
 * X-CSRF-Token toe aan elke same-origin fetch/XHR die geen GET/HEAD is.
 */

declare(strict_types=1);

const MOIRAI_CSRF_HEADER = 'HTTP_X_CSRF_TOKEN';
const MOIRAI_CSRF_FIELD = '_csrf';

/**
 * Token per sessie. localization.php sluit de sessie al vroeg (session_write_close),
 * dus heropenen we hem kort om het token te bewaren. Roep dit aan vóór er output is.
 */
function moirai_csrf_token(): string
{
    if (!empty($_SESSION['moirai_csrf']) && is_string($_SESSION['moirai_csrf'])) {
        return $_SESSION['moirai_csrf'];
    }
    $reopened = false;
    if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        $reopened = @session_start();
    }
    if (empty($_SESSION['moirai_csrf']) || !is_string($_SESSION['moirai_csrf'])) {
        $_SESSION['moirai_csrf'] = bin2hex(random_bytes(32));
    }
    $token = $_SESSION['moirai_csrf'];
    if ($reopened) {
        session_write_close();
    }

    return $token;
}

function moirai_csrf_valid(string $presented): bool
{
    $expected = (string) ($_SESSION['moirai_csrf'] ?? '');

    return $expected !== '' && $presented !== '' && hash_equals($expected, $presented);
}

/** Token uit header X-CSRF-Token, of uit het formulier-/JSON-veld _csrf. */
function moirai_csrf_presented(?array $jsonPayload = null): string
{
    $header = trim((string) ($_SERVER[MOIRAI_CSRF_HEADER] ?? ''));
    if ($header !== '') {
        return $header;
    }
    if (isset($_POST[MOIRAI_CSRF_FIELD]) && is_string($_POST[MOIRAI_CSRF_FIELD])) {
        return trim($_POST[MOIRAI_CSRF_FIELD]);
    }
    if (is_array($jsonPayload) && isset($jsonPayload[MOIRAI_CSRF_FIELD]) && is_string($jsonPayload[MOIRAI_CSRF_FIELD])) {
        return trim($jsonPayload[MOIRAI_CSRF_FIELD]);
    }

    return '';
}

/** Weigert het verzoek met 403 JSON (error_code csrf) als het token ontbreekt of niet klopt. */
function moirai_csrf_require(?array $jsonPayload = null): void
{
    if (moirai_csrf_valid(moirai_csrf_presented($jsonPayload))) {
        return;
    }
    $message = function_exists('moirai_loc') ? moirai_loc('budget.error.csrf') : 'CSRF token invalid.';
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message, 'error_code' => 'csrf'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
