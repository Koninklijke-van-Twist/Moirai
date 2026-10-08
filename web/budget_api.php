<?php

/**
 * JSON-endpoint voor het tabblad Telefoonbudget. Alleen ICT-admins;
 * schrijfacties vereisen POST + X-CSRF-Token (zie moirai_budget_api_dispatch()).
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/moirai_data.php';

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$payload = $_GET;
if (strtoupper($method) === 'POST') {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        $payload = is_array($json) ? $json + $_GET : $_GET;
    } else {
        $payload = $_POST + $_GET;
    }
}
// localization.php heeft de sessie gesloten; de import bewaart rijen in de sessie.
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload['_csrf'] ?? '');

try {
    [$status, $body] = moirai_budget_api_dispatch($action, $method, $payload, $csrf, $_FILES);
    moirai_json_response($body, $status);
} catch (Throwable $error) {
    error_log('budget_api: ' . $error->getMessage());
    moirai_json_response(['ok' => false, 'error' => moirai_loc('moirai.error.generic')], 500);
}
