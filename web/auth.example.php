<?php

/**
 * Copy this file to auth.php (gitignored) and fill in local secrets.
 * Never commit real keys, Graph credentials, or passwords.
 *
 * Existing Moirai installs already have a local auth.php for Graph / ICT lists.
 * Add the $apiKeys block there (or start from this example).
 */

// Label/name => secret key string. Used by api.php (X-API-Key / Bearer / POST api_key).
// Placeholder only — replace locally. The example value is ignored by the API on purpose.
$apiKeys = [
    "voorbeeldKey" => "REPLACE_WITH_A_RANDOM_API_KEY",
];

// Existing app config (keep whatever your local auth.php already has):
// $graphCredentials = ['tenantId' => '', 'clientId' => '', 'clientSecret' => ''];
// $ictUsers = ['ict@kvt.nl'];
// $allowedUsers = ['ict@kvt.nl'];

// Mímir (optioneel). Laat het BC-blok hieronder staan naast $mimirApi:
// bij een Mímir-storing valt Moirai terug op die directe Business Central-credentials
// (web, nightly.php en download_enroll.php). Zonder $mimirApi wordt alleen het BC-blok gebruikt.
// $mimirApi  = 'mimir_…';
// $mimirBase = 'https://sleutels.kvt.nl/mimir/api';

// Business Central — verplicht als fallback zolang $mimirApi gezet is, en het enige pad zonder $mimirApi.
// $auth_list = [
//     'Production' => ['mode' => 'basic', 'user' => 'USERNAME', 'pass' => 'PASSWORD'],
// ];
// $environment = 'Production';
// $auth = $auth_list[$environment];
// $baseUrl = 'https://my-bc-domain.com:7148/';
