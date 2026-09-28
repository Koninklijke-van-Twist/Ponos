<?php
/**
 * Simuleert een onbereikbare Mímir en controleert de directe BC-fallback.
 * Run: php tests/mimir_fallback_test.php
 */

$logFile = sys_get_temp_dir() . '/ponos-mimir-fallback-test.log';
@unlink($logFile);
$mimirFallbackPreviousLog = ini_get('error_log');
ini_set('error_log', $logFile);
ini_set('log_errors', '1');

$mimirFallbackSavedGlobals = [];
foreach (['mimirApi', 'mimirBase', 'baseUrl', 'environment', 'auth_list', 'auth'] as $mimirFallbackGlobalName) {
    $mimirFallbackSavedGlobals[$mimirFallbackGlobalName] = [
        'exists' => array_key_exists($mimirFallbackGlobalName, $GLOBALS),
        'value' => $GLOBALS[$mimirFallbackGlobalName] ?? null,
    ];
}

$mimirApi = 'mimir_test_key_should_not_leak';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];

$calls = [];
$GLOBALS['PONOS_ODATA_BC_FETCH'] = static function (string $url, array $auth, int $ttl) use (&$calls): array {
    $calls[] = [
        'url' => $url,
        'user' => (string) ($auth['user'] ?? ''),
        'ttl' => $ttl,
    ];
    if (preg_match('#/ODataV4/Company(?:\\?|$)#', $url) === 1) {
        return [
            ['Name' => 'KVT Gas'],
            ['Name' => 'Hunter van Twist'],
            ['name' => 'Koninklijke van Twist'],
        ];
    }
    return [['No' => 'WO-1']];
};

require_once dirname(__DIR__) . '/web/odata.php';
require_once dirname(__DIR__) . '/web/auth_helper.php';
if (function_exists('odata_mimir_circuit_reset')) {
    odata_mimir_circuit_reset();
}

function mimir_fallback_fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function mimir_fallback_log(): string
{
    global $logFile;
    $raw = @file_get_contents($logFile);
    return is_string($raw) ? $raw : '';
}

function mimir_fallback_count(): int
{
    return substr_count(mimir_fallback_log(), '[Ponos] Mímir failed, falling back to direct OData:');
}

function mimir_fallback_seen_base_url(): string
{
    global $baseUrl;
    return (string) $baseUrl;
}

foreach (['web/index.php', 'web/nightly.php', 'web/ponos_data.php', 'web/ponos_api_key.php'] as $mimirFallbackEntry) {
    $mimirFallbackSource = file_get_contents(dirname(__DIR__) . '/' . $mimirFallbackEntry);
    if (!is_string($mimirFallbackSource) || strpos($mimirFallbackSource, 'auth.php') === false) {
        mimir_fallback_fail($mimirFallbackEntry . ' moet auth.php laden zodat de BC-fallback credentials heeft');
    }
}

if (odata_mimir_connect_timeout_seconds() !== 10) {
    mimir_fallback_fail('connect-timeout moet 10s zijn');
}
if (odata_mimir_timeout_seconds_for_sapi('cli') !== 600) {
    mimir_fallback_fail('CLI-timeout moet 600s blijven');
}
if (odata_mimir_timeout_seconds_for_sapi('fpm-fcgi') !== 90 || odata_mimir_timeout_seconds_for_sapi('apache2handler') !== 90) {
    mimir_fallback_fail('web-timeout moet ongeveer 90s zijn');
}
if (PHP_SAPI === 'cli' && odata_mimir_timeout_seconds() !== 600) {
    mimir_fallback_fail('huidige CLI-sapi moet de lange timeout gebruiken');
}

$names = odata_mimir_list_companies(null);
$expectedNames = ['Hunter van Twist', 'Koninklijke van Twist', 'KVT Gas'];
if ($names !== $expectedNames) {
    mimir_fallback_fail('company-fallback gaf ' . json_encode($names) . ' i.p.v. de gesorteerde BC-namen');
}
if (!odata_mimir_circuit_open()) {
    mimir_fallback_fail('circuit moet open na de eerste Mímir-fout');
}
if (count($calls) !== 1 || strpos($calls[0]['url'], 'https://bc.example:7148/Production/ODataV4/Company') !== 0) {
    mimir_fallback_fail('company-fallback riep de directe BC-fetch niet aan: ' . json_encode($calls));
}
if ($calls[0]['user'] !== 'bcuser') {
    mimir_fallback_fail('company-fallback gebruikte niet de BC-credentials');
}

$mimirBase = 'http://192.0.2.1:9';
$started = microtime(true);
$rows = odata_get_all(
    "https://mimir.invalid/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    120
);
$elapsed = microtime(true) - $started;
if ($elapsed >= 2.0) {
    mimir_fallback_fail('circuit breaker sloeg Mímir niet over (' . round($elapsed, 3) . 's)');
}
if (($rows[0]['No'] ?? '') !== 'WO-1') {
    mimir_fallback_fail('entity-fallback gaf niet de gestubde BC-rijen terug');
}
$entityCall = $calls[1] ?? null;
$expectedEntityUrl = "https://bc.example:7148/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No";
if (!is_array($entityCall) || $entityCall['url'] !== $expectedEntityUrl || $entityCall['user'] !== 'bcuser' || $entityCall['ttl'] !== 120) {
    mimir_fallback_fail('entity-fallback URL/auth/ttl klopt niet: ' . json_encode($entityCall));
}
if (mimir_fallback_count() !== 1) {
    mimir_fallback_fail('alleen de eerste Mímir-fout mag een fallback loggen, log=' . mimir_fallback_log());
}
$log = mimir_fallback_log();
if (strpos($log, 'mimir_test_key_should_not_leak') !== false || strpos($log, 'bc-secret') !== false) {
    mimir_fallback_fail('log bevat een geheim');
}
if (strpos($log, '[Ponos] Mímir failed, falling back to direct OData:') === false) {
    mimir_fallback_fail('logregel mist het verwachte prefix');
}

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$beforeQuery = count($calls);
$queryRows = odata_mimir_query('KVT Gas', 'AppResource', ['$select' => 'No,Name'], 60);
if (($queryRows[0]['No'] ?? '') !== 'WO-1') {
    mimir_fallback_fail('odata_mimir_query viel niet terug op de stub');
}
$queryCall = $calls[$beforeQuery] ?? null;
if (!is_array($queryCall) || strpos($queryCall['url'], "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppResource?") !== 0) {
    mimir_fallback_fail('query-fallback bouwde niet de pre-Mímir BC-URL: ' . json_encode($queryCall));
}

odata_mimir_circuit_reset();
$beforeFetch = count($calls);
$fetchRows = odata_mimir_fetch_all(
    "https://mimir.invalid/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No",
    15
);
if (($fetchRows[0]['No'] ?? '') !== 'WO-1') {
    mimir_fallback_fail('odata_mimir_fetch_all viel niet terug');
}
$fetchCall = $calls[$beforeFetch] ?? null;
if (!is_array($fetchCall) || $fetchCall['url'] !== "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No") {
    mimir_fallback_fail('fetch_all-fallback herschreef de URL niet: ' . json_encode($fetchCall));
}

odata_mimir_circuit_reset();
$map = odata_mimir_company_environment_map(null);
if (($map['Hunter van Twist'] ?? '') !== 'Production' || ($map['KVT Gas'] ?? '') !== 'Production') {
    mimir_fallback_fail('environment-map viel niet terug op BC: ' . json_encode($map));
}

odata_mimir_circuit_reset();
$beforeAuth = count($calls);
$authRows = auth_odata_get_all(
    "https://mimir.invalid/Production/ODataV4/Company('KVT%20Gas')/AppProjecten?\$select=No",
    [],
    33
);
if (($authRows[0]['No'] ?? '') !== 'WO-1') {
    mimir_fallback_fail('auth_odata_get_all viel niet terug op de stub');
}
$authCall = $calls[$beforeAuth] ?? null;
if (!is_array($authCall) || $authCall['url'] !== "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppProjecten?\$select=No" || $authCall['user'] !== 'bcuser' || $authCall['ttl'] !== 33) {
    mimir_fallback_fail('auth-fallback URL/auth/ttl klopt niet: ' . json_encode($authCall));
}

odata_mimir_circuit_reset();
$mimirApi = 'mimir_test_key_should_not_leak';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'only-auth', 'pass' => 'only-secret'];
$GLOBALS['PONOS_AUTH_PHP_LOAD_TRIED'] = true;
unset($auth_list, $GLOBALS['auth_list']);
unset($GLOBALS['demeter_company_environment_map']);
$beforeOnlyAuth = count($calls);
$onlyNames = odata_mimir_list_companies(null);
if ($onlyNames !== $expectedNames) {
    mimir_fallback_fail('companylijst zonder $auth_list gaf ' . json_encode($onlyNames));
}
$onlyQuery = odata_mimir_query('Solo BV', 'AppResource', ['$select' => 'No'], 10);
if (($onlyQuery[0]['No'] ?? '') !== 'WO-1') {
    mimir_fallback_fail('query zonder $auth_list viel niet terug');
}
$onlyFetch = odata_mimir_fetch_all(
    "https://mimir.invalid/Production/ODataV4/Company('Solo%20BV')/AppWerkorders?\$select=No",
    10
);
if (($onlyFetch[0]['No'] ?? '') !== 'WO-1') {
    mimir_fallback_fail('URL-fetch zonder $auth_list viel niet terug');
}
$onlyCalls = array_slice($calls, $beforeOnlyAuth);
$onlyCompany = $onlyCalls[0] ?? null;
$onlyQueryCall = $onlyCalls[1] ?? null;
$onlyFetchCall = $onlyCalls[2] ?? null;
if (!is_array($onlyCompany) || strpos($onlyCompany['url'], 'https://bc.example:7148/Production/ODataV4/Company') !== 0 || $onlyCompany['user'] !== 'only-auth') {
    mimir_fallback_fail('companylijst zonder $auth_list gebruikte niet $auth: ' . json_encode($onlyCompany));
}
if (!is_array($onlyQueryCall) || strpos($onlyQueryCall['url'], "https://bc.example:7148/Production/ODataV4/Company('Solo%20BV')/AppResource?") !== 0 || $onlyQueryCall['user'] !== 'only-auth') {
    mimir_fallback_fail('query zonder $auth_list gebruikte niet $auth: ' . json_encode($onlyQueryCall));
}
if (!is_array($onlyFetchCall) || $onlyFetchCall['url'] !== "https://bc.example:7148/Production/ODataV4/Company('Solo%20BV')/AppWerkorders?\$select=No" || $onlyFetchCall['user'] !== 'only-auth') {
    mimir_fallback_fail('URL-fetch zonder $auth_list gebruikte niet $auth: ' . json_encode($onlyFetchCall));
}
if (count($onlyCalls) !== 3) {
    mimir_fallback_fail('alleen-$auth fallback deed ' . count($onlyCalls) . ' BC-calls: ' . json_encode($onlyCalls));
}

$GLOBALS['demeter_company_environment_map'] = ['Hunter van Twist' => 'Sandbox'];
$keptAuth = $auth;
$context = auth_set_current_company_context('Hunter van Twist', 30);
if (($context['auth'] ?? null) !== [] || ($context['environment'] ?? '') !== 'Sandbox') {
    mimir_fallback_fail('company-context zonder auth_list-entry moet een lege sentinel teruggeven: ' . json_encode($context));
}
if (($auth['user'] ?? '') !== 'only-auth' || ($auth['pass'] ?? '') !== 'only-secret') {
    mimir_fallback_fail('company-context veegde de globale $auth weg: ' . json_encode($auth));
}
$environment = 'Production';
$auth = $keptAuth;
unset($GLOBALS['demeter_company_environment_map']);

odata_mimir_circuit_reset();
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'prod-user', 'pass' => 'prod-secret'];
$auth_list = [
    'Sandbox' => ['mode' => 'basic', 'user' => 'sand-user', 'pass' => 'sand-secret'],
];
unset($GLOBALS['demeter_company_environment_map']);
$beforeUnmapped = count($calls);
$unmappedRows = odata_mimir_query('Unmapped BV', 'AppResource', ['$select' => 'No'], 12);
if (($unmappedRows[0]['No'] ?? '') !== 'WO-1') {
    mimir_fallback_fail('unmapped query viel niet terug');
}
$unmappedCall = $calls[$beforeUnmapped] ?? null;
if (!is_array($unmappedCall) || strpos($unmappedCall['url'], "https://bc.example:7148/Production/ODataV4/Company('Unmapped%20BV')/AppResource?") !== 0 || $unmappedCall['user'] !== 'prod-user') {
    mimir_fallback_fail('unmapped bedrijf ging niet via $auth: ' . json_encode($unmappedCall));
}
if (count($calls) !== $beforeUnmapped + 1) {
    mimir_fallback_fail('unmapped query deed extra BC-calls: ' . json_encode(array_slice($calls, $beforeUnmapped)));
}

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'prod-user', 'pass' => 'prod-secret'];
$auth_list = ['Production' => $auth];
$beforeSandbox = count($calls);
$sandboxRefused = false;
try {
    odata_get_all("https://mimir.invalid/Sandbox/ODataV4/Company('X')/AppWerkorders", $auth, 30);
    mimir_fallback_fail('Sandbox-URL moet weigeren als alleen Production in $auth_list staat');
} catch (Throwable $sandboxError) {
    $sandboxRefused = strpos($sandboxError->getMessage(), 'Mímir') !== false;
    if (!$sandboxRefused) {
        mimir_fallback_fail('Sandbox-URL gooide niet de Mímir-fout: ' . $sandboxError->getMessage());
    }
}
if (count($calls) !== $beforeSandbox) {
    mimir_fallback_fail('Sandbox-URL mag geen BC-call doen: ' . json_encode(array_slice($calls, $beforeSandbox)));
}
$beforeSandboxCompanies = count($calls);
try {
    odata_mimir_list_companies('Sandbox');
    mimir_fallback_fail('Sandbox-companylijst moet weigeren zonder eigen auth_list-entry');
} catch (Throwable $sandboxCompaniesError) {
    if (strpos($sandboxCompaniesError->getMessage(), 'Mímir') === false) {
        mimir_fallback_fail('Sandbox-companylijst gooide niet de Mímir-fout: ' . $sandboxCompaniesError->getMessage());
    }
}
if (count($calls) !== $beforeSandboxCompanies) {
    mimir_fallback_fail('Sandbox-companylijst mag geen BC-call doen: ' . json_encode(array_slice($calls, $beforeSandboxCompanies)));
}
$beforeSandboxAuth = count($calls);
try {
    auth_odata_get_all("https://mimir.invalid/Sandbox/ODataV4/Company('X')/AppProjecten", $auth, 30);
    mimir_fallback_fail('auth_odata_get_all moet een Sandbox-URL weigeren');
} catch (Throwable $sandboxAuthError) {
    if (strpos($sandboxAuthError->getMessage(), 'Mímir') === false) {
        mimir_fallback_fail('auth-Sandbox-URL gooide niet de Mímir-fout: ' . $sandboxAuthError->getMessage());
    }
}
if (count($calls) !== $beforeSandboxAuth) {
    mimir_fallback_fail('auth-Sandbox-URL mag geen BC-call doen: ' . json_encode(array_slice($calls, $beforeSandboxAuth)));
}
unset($GLOBALS['demeter_company_environment_map']);
$spacedUrl = odata_bc_url_from_odata_url("https://mimir.invalid/My%20Env/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No");
$expectedSpacedUrl = "https://bc.example:7148/My%20Env/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No";
if ($spacedUrl !== $expectedSpacedUrl) {
    mimir_fallback_fail('environment-segment mag maar één keer geëncodeerd worden: ' . $spacedUrl);
}

$auth_list['Sandbox'] = ['mode' => 'basic', 'user' => 'sandbox-user', 'pass' => 'sandbox-secret'];
$GLOBALS['demeter_company_environment_map'] = [
    'Hunter van Twist' => 'Sandbox',
    'KVT Gas' => 'Production',
];
odata_mimir_circuit_reset();
$mimirApi = 'mimir_test_key_should_not_leak';
$mimirBase = 'http://127.0.0.1:9';
$beforeSandbox = count($calls);
$loggedBeforeSandbox = mimir_fallback_count();
$sandboxRows = odata_get_all(
    "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    10
);
$sandboxCall = $calls[$beforeSandbox] ?? null;
if (($sandboxRows[0]['No'] ?? '') !== 'WO-1' || !is_array($sandboxCall)) {
    mimir_fallback_fail('sandbox-fallback gaf geen stub-rijen terug');
}
if ($sandboxCall['user'] !== 'sandbox-user' || strpos($sandboxCall['url'], 'https://bc.example:7148/Sandbox/ODataV4/Company(') !== 0) {
    mimir_fallback_fail('sandbox-URL moet de credentials van dat environment gebruiken: ' . json_encode($sandboxCall));
}
if (mimir_fallback_count() !== $loggedBeforeSandbox + 1) {
    mimir_fallback_fail('sandbox-fallback moet precies één keer loggen');
}

odata_mimir_circuit_reset();
$beforeSandboxQuery = count($calls);
$sandboxQuery = odata_mimir_query('Hunter van Twist', 'AppResource', ['$select' => 'No'], 10);
$sandboxQueryCall = $calls[$beforeSandboxQuery] ?? null;
if (($sandboxQuery[0]['No'] ?? '') !== 'WO-1' || !is_array($sandboxQueryCall) || $sandboxQueryCall['user'] !== 'sandbox-user') {
    mimir_fallback_fail('query voor een bedrijf in Sandbox gebruikte niet die environment: ' . json_encode($sandboxQueryCall));
}
if (strpos($sandboxQueryCall['url'], "https://bc.example:7148/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppResource?") !== 0) {
    mimir_fallback_fail('query-fallback bouwde niet de Sandbox-URL: ' . json_encode($sandboxQueryCall));
}

odata_mimir_circuit_reset();
$loggedBeforeTranslate = mimir_fallback_count();
$callsBeforeTranslate = count($calls);
$translateThrew = false;
try {
    odata_get_all('https://mimir.invalid/not-an-odata-path', $auth, 10);
} catch (Throwable $translateError) {
    $translateThrew = strpos($translateError->getMessage(), 'kon niet worden vertaald') !== false;
}
if (!$translateThrew) {
    mimir_fallback_fail('een onvertaalbare OData-URL moet de oorspronkelijke fout geven');
}
if (odata_mimir_circuit_open() || mimir_fallback_count() !== $loggedBeforeTranslate || count($calls) !== $callsBeforeTranslate) {
    mimir_fallback_fail('een fout buiten Mímir mag het circuit niet openen en geen fallback loggen');
}

$savedEnvironmentForCache = $environment;
$environment = 'mimir';
$cacheKey = build_cache_key("https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders", $auth);
$cacheParts = explode('|', $cacheKey);
$cacheEnv = $cacheParts[count($cacheParts) - 1] ?? '';
if ($cacheEnv !== 'Sandbox') {
    mimir_fallback_fail('cache-key moet het echte BC-environment gebruiken: ' . $cacheKey);
}
$mappedCacheKey = build_cache_key("https://mimir.invalid/mimir/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders", $auth);
$mappedParts = explode('|', $mappedCacheKey);
$mappedEnv = $mappedParts[count($mappedParts) - 1] ?? '';
if ($mappedEnv !== 'Sandbox') {
    mimir_fallback_fail('cache-key mag het placeholder-environment mimir niet gebruiken: ' . $mappedCacheKey);
}
$environment = $savedEnvironmentForCache;

unset($GLOBALS['demeter_company_environment_map']);
$auth_list = ['Production' => $auth];

$loggedBeforeRethrow = mimir_fallback_count();
$callsBeforeRethrow = count($calls);
odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://mimir.invalid/';
$environment = 'mimir';
$auth = [];
$auth_list = [];
$rethrown = null;
try {
    odata_get_all('https://mimir.invalid/mimir/ODataV4/Company(\'X\')/AppWerkorders', ['mode' => 'basic', 'user' => '', 'pass' => ''], 30);
    mimir_fallback_fail('zonder BC-credentials moet de oorspronkelijke Mímir-fout terugkomen');
} catch (Throwable $exception) {
    $rethrown = $exception;
}
if (!$rethrown instanceof Throwable) {
    mimir_fallback_fail('zonder BC-credentials moet een exception terugkomen');
}
if (strpos($rethrown->getMessage(), 'Mímir') !== 0 && strpos($rethrown->getMessage(), 'Mímir') === false) {
    mimir_fallback_fail('hergooide fout is niet de Mímir-fout: ' . $rethrown->getMessage());
}
if (stripos($rethrown->getMessage(), 'credential') !== false) {
    mimir_fallback_fail('hergooide fout maskeert Mímir met een credentials-melding: ' . $rethrown->getMessage());
}
if (count($calls) !== $callsBeforeRethrow) {
    mimir_fallback_fail('zonder BC-credentials mag de directe fetch niet starten');
}
if (mimir_fallback_count() !== $loggedBeforeRethrow) {
    mimir_fallback_fail('zonder BC-credentials mag er geen fallback gelogd worden');
}

odata_mimir_circuit_reset();
$mimirApi = '';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];
$loggedBeforeDirect = mimir_fallback_count();
$directOnlyUrl = 'https://mimir.invalid/Production/ODataV4/Company(\'KVT%20Gas\')/AppWerkorders?$select=No';
$directRows = odata_get_all($directOnlyUrl, $auth, 45);
if (odata_mimir_circuit_open()) {
    mimir_fallback_fail('lege $mimirApi mag Mímir niet proberen');
}
if (mimir_fallback_count() !== $loggedBeforeDirect) {
    mimir_fallback_fail('lege $mimirApi mag geen Mímir-fallback loggen');
}
$directCall = $calls[count($calls) - 1] ?? null;
if (($directRows[0]['No'] ?? '') !== 'WO-1' || !is_array($directCall) || $directCall['url'] !== $directOnlyUrl) {
    mimir_fallback_fail('lege $mimirApi moet de oude directe route ongewijzigd gebruiken: ' . json_encode($directCall));
}

$authFile = tempnam(sys_get_temp_dir(), 'ponos-auth');
if (!is_string($authFile) || $authFile === '') {
    mimir_fallback_fail('tijdelijk auth-bestand kon niet worden aangemaakt');
}
file_put_contents(
    $authFile,
    "<?php\n\$baseUrl = 'https://bc-from-file.example:7148/';\n\$environment = 'Sandbox';\n\$auth = ['mode' => 'basic', 'user' => 'file-user', 'pass' => 'file-secret'];\n\$auth_list = ['Sandbox' => \$auth];\n\$base = 'https://bc-from-file.example:7148/';\n"
);
unset($GLOBALS['baseUrl'], $GLOBALS['environment'], $GLOBALS['auth'], $GLOBALS['auth_list'], $GLOBALS['base'], $GLOBALS['PONOS_AUTH_PHP_LOAD_TRIED']);
$GLOBALS['PONOS_AUTH_PHP_PATH'] = $authFile;
odata_load_auth_for_fallback();
if (($GLOBALS['baseUrl'] ?? '') !== 'https://bc-from-file.example:7148/') {
    mimir_fallback_fail('lazy auth.php zette baseUrl niet in $GLOBALS');
}
if (($GLOBALS['environment'] ?? '') !== 'Sandbox') {
    mimir_fallback_fail('lazy auth.php zette environment niet in $GLOBALS');
}
if (($GLOBALS['auth']['user'] ?? '') !== 'file-user' || ($GLOBALS['auth_list']['Sandbox']['user'] ?? '') !== 'file-user') {
    mimir_fallback_fail('lazy auth.php zette auth/auth_list niet in $GLOBALS');
}
if (($GLOBALS['base'] ?? '') !== 'https://bc-from-file.example:7148/') {
    mimir_fallback_fail('lazy auth.php zette base niet in $GLOBALS');
}
if (mimir_fallback_seen_base_url() !== 'https://bc-from-file.example:7148/') {
    mimir_fallback_fail('een functie met global $baseUrl ziet de gekopieerde waarde niet');
}

$GLOBALS['baseUrl'] = 'https://keep.example/';
unset($GLOBALS['environment'], $GLOBALS['auth'], $GLOBALS['auth_list'], $GLOBALS['base'], $GLOBALS['PONOS_AUTH_PHP_LOAD_TRIED']);
odata_load_auth_for_fallback();
if (($GLOBALS['baseUrl'] ?? '') !== 'https://keep.example/') {
    mimir_fallback_fail('een gezette baseUrl mag niet worden overschreven');
}
if (($GLOBALS['environment'] ?? '') !== 'Sandbox' || ($GLOBALS['auth']['user'] ?? '') !== 'file-user') {
    mimir_fallback_fail('ontbrekende auth-globals moeten alsnog uit het bestand komen');
}
@unlink($authFile);

if (strpos(mimir_fallback_log(), 'sandbox-secret') !== false || strpos(mimir_fallback_log(), 'file-secret') !== false) {
    mimir_fallback_fail('log bevat een geheim uit auth_list of auth.php');
}

unset($GLOBALS['PONOS_ODATA_BC_FETCH'], $GLOBALS['PONOS_AUTH_PHP_PATH'], $GLOBALS['PONOS_AUTH_PHP_LOAD_TRIED'], $GLOBALS['demeter_company_environment_map']);
odata_mimir_circuit_reset();
$mimirApi = '';
$mimirBase = '';
foreach ($mimirFallbackSavedGlobals as $mimirFallbackGlobalName => $mimirFallbackSaved) {
    if ($mimirFallbackSaved['exists']) {
        $GLOBALS[$mimirFallbackGlobalName] = $mimirFallbackSaved['value'];
    } else {
        unset($GLOBALS[$mimirFallbackGlobalName]);
    }
}
if (is_string($mimirFallbackPreviousLog)) {
    ini_set('error_log', $mimirFallbackPreviousLog);
}

echo "OK\n";
