<?php
/**
 * Build a posprint:// URL from a .pos document (array or JSON string).
 *
 * Encoding: UTF-8 JSON → raw deflate (gzdeflate) → base64url (no padding).
 *
 * @param array|string $pos PosFile as associative array or JSON string
 * @param array{noconfirm?: bool, referrer?: string} $options Optional URL flags
 * @return string posprint://print?v=1&d=…
 * @throws InvalidArgumentException
 */
function posprint_url($pos, array $options = []): string
{
    if (is_array($pos)) {
        $json = json_encode($pos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException('json_encode failed: ' . json_last_error_msg());
        }
    } elseif (is_string($pos)) {
        $json = $pos;
    } else {
        throw new InvalidArgumentException('pos must be array or JSON string');
    }

    $compressed = gzdeflate($json, 9);
    if ($compressed === false) {
        throw new InvalidArgumentException('gzdeflate failed');
    }

    $b64 = rtrim(strtr(base64_encode($compressed), '+/', '-_'), '=');
    $url = 'posprint://print?v=1&d=' . $b64;

    if (!empty($options['noconfirm'])) {
        $url .= '&noconfirm=1';
    }
    if (!empty($options['referrer'])) {
        $ref = trim((string) $options['referrer']);
        if ($ref !== '') {
            $url .= '&referrer=' . rawurlencode($ref);
        }
    }

    return $url;
}
