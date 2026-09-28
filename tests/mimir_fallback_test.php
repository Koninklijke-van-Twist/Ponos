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
if (mimir_fallback_count() < 2) {
    mimir_fallback_fail('elke fallback moet gelogd worden, log=' . mimir_fallback_log());
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

unset($GLOBALS['PONOS_ODATA_BC_FETCH']);
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
