<?php

/**
 * Copy this file to auth.php (gitignored) and fill in local secrets.
 * Never commit real keys, Graph credentials, or passwords.
 *
 * Existing Moirai installs already have a local auth.php for Graph / ICT lists.
 * Add the $apiKeys block there (or start from this example).
 */

// Label/name => secret key string. Used by api.php (X-API-Key / Bearer / POST api_key).
// Placeholder only — replace locally. Do not commit real secrets in auth.php.
$apiKeys = [
    "voorbeeldKey" => "1234-5678-1234",
];

// Existing app config (keep whatever your local auth.php already has):
// $graphCredentials = ['tenantId' => '', 'clientId' => '', 'clientSecret' => ''];
// $ictUsers = ['ict@kvt.nl'];
// $allowedUsers = ['ict@kvt.nl'];
