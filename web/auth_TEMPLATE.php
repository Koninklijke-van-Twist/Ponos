<?php
/**
 * Auth-template voor Ponos. Kopieer naar web/auth.php en vul in.
 * auth.php staat niet in git.
 *
 * Mímir is de voorkeursroute. De Business Central-gegevens hieronder
 * blijven naast $mimirApi staan: dat is de automatische fallback
 * (web, nightly.php en CLI) wanneer Mímir faalt.
 *
 *   $mimirApi  = 'mimir_…';  // verplicht om Mímir te activeren
 *   $mimirBase = 'https://sleutels.kvt.nl/mimir/api'; // optioneel
 *
 * Zonder $mimirApi wordt alleen het BC-blok gebruikt.
 */

// --- Mímir (aanbevolen) ---
// $mimirApi  = 'mimir_…';
// $mimirBase = 'https://sleutels.kvt.nl/mimir/api';

// --- Business Central (direct pad, én fallback als Mímir uitvalt) ---
$auth_list =
    [
        'env1' => ['mode' => 'basic', 'user' => 'USERNAME', 'pass' => 'PASSWORD'],
    ];
$environment = 'env1';
$auth = $auth_list[$environment];
$baseUrl = 'https://my-bc-domain.com:7148/';

$ictUsers = [
    'user@domain.nl',
];

$allowedUsers = [
    'user@domain.nl',
];

// Optioneel, alleen als een HTTP-runner odata.php?action=… moet aanroepen.
// Header X-Ponos-Secret moet exact dit geheim zijn. Weglaten: geen anonieme toegang.
// CLI (php web/nightly.php) heeft dit niet nodig.
// $ponosRunnerSecret = '';

$graphCredentials = [
    'tenantId' => '',
    'clientId' => '',
    'clientSecret' => '',
];
