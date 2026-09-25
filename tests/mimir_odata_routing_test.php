<?php

declare(strict_types=1);

/**
 * OData-routing: Mímir als $mimirApi gezet is, BC als de key ontbreekt.
 */

require_once dirname(__DIR__) . '/web/odata.php';
require_once dirname(__DIR__) . '/web/auth_helper.php';

$mimirMockPort = 18947;
$mimirMockLog = sys_get_temp_dir() . '/ponos-mimir-mock.log';
$mimirMockScript = sys_get_temp_dir() . '/ponos-mimir-mock.php';
$mimirServerLog = sys_get_temp_dir() . '/ponos-mimir-server.log';
$mimirAuthPath = dirname(__DIR__) . '/web/auth.php';
$mimirAuthBackup = is_file($mimirAuthPath) ? file_get_contents($mimirAuthPath) : null;
$mimirSavedGlobals = [];
foreach (['mimirApi', 'mimirBase', 'baseUrl', 'environment', 'auth_list', 'auth'] as $mimirGlobalName) {
    $mimirSavedGlobals[$mimirGlobalName] = [
        'exists' => array_key_exists($mimirGlobalName, $GLOBALS),
        'value' => $GLOBALS[$mimirGlobalName] ?? null,
    ];
}

function mimir_test_reset_discovery(): void
{
    unset(
        $GLOBALS['demeter_company_environment_map'],
        $GLOBALS['demeter_companies_by_environment'],
        $GLOBALS['demeter_active_environments']
    );
}

function mimir_test_company_entity_url(string $baseUrl, string $environment, string $company, string $entitySet, array $query): string
{
    $safeCompany = str_replace("'", "''", trim($company));
    $companySegment = "Company('" . rawurlencode($safeCompany) . "')";
    $url = rtrim($baseUrl, '/') . '/' . rawurlencode($environment) . '/ODataV4/' . $companySegment . '/' . rawurlencode($entitySet);
    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $url;
}

function mimir_test_write_mock(): void
{
    global $mimirMockScript, $mimirMockLog;
    $log = var_export($mimirMockLog, true);
    $php = <<<'PHP'
<?php
$log = LOG_PATH;
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
file_put_contents($log, json_encode([
    'uri' => $uri,
    'method' => $method,
    'ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    'authorization' => $authorization,
    'api_key' => (string) ($_SERVER['HTTP_X_API_KEY'] ?? ''),
], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
header('Content-Type: application/json');

if (str_contains($uri, '/mimir-dup/api/companies.php')) {
    echo json_encode(['value' => [
        ['name' => 'Overlap BV', 'environment' => 'Production'],
        ['name' => 'Overlap BV', 'environment' => 'Sandbox'],
    ]]);
    exit;
}

if (str_contains($uri, '/mimir/api/companies.php')) {
    echo json_encode(['value' => [
        ['name' => 'Koninklijke van Twist', 'environment' => 'Production'],
        ['name' => "Van Twist's", 'environment' => 'Production'],
        ['name' => 'Hunter van Twist', 'environment' => 'Sandbox'],
    ]]);
    exit;
}

if (str_contains($uri, '/mimir/api/query.php')) {
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = [];
    }
    echo json_encode(['value' => [[
        'No' => 'PRJ1',
        'company' => (string) ($body['company'] ?? ''),
        'table' => (string) ($body['table'] ?? ''),
        'select' => $body['select'] ?? [],
        'filter' => (string) ($body['filter'] ?? ''),
        'max_age' => $body['max_age'] ?? null,
    ]]]);
    exit;
}

$user = '';
if (str_starts_with($authorization, 'Basic ')) {
    $decoded = base64_decode(substr($authorization, 6), true);
    if (is_string($decoded) && str_contains($decoded, ':')) {
        $user = explode(':', $decoded, 2)[0];
    }
}
echo json_encode(['value' => [[
    'Name' => 'BC Company',
    'via' => 'bc',
    'user' => $user,
]]]);
PHP;
    file_put_contents($mimirMockScript, str_replace('LOG_PATH', $log, $php));
}

function mimir_test_mock_requests(): array
{
    global $mimirMockLog;
    if (!is_file($mimirMockLog)) {
        return [];
    }
    $rows = [];
    foreach (file($mimirMockLog, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $rows[] = $decoded;
        }
    }
    return $rows;
}

function mimir_test_clear_bc_config(): void
{
    $GLOBALS['mimirApi'] = '';
    $GLOBALS['mimirBase'] = '';
    $GLOBALS['baseUrl'] = '';
    unset($GLOBALS['auth_list'], $GLOBALS['environment'], $GLOBALS['auth']);
    mimir_test_reset_discovery();
}

$GLOBALS['mimirApi'] = '';
$GLOBALS['mimirBase'] = '';
mimir_test_clear_bc_config();

ponos_test('mimir uit zonder key', function (): void {
    $GLOBALS['mimirApi'] = '   ';
    assert_false(odata_mimir_enabled());
    $GLOBALS['mimirApi'] = '';
    assert_false(odata_mimir_enabled());
    unset($GLOBALS['mimirBase']);
    assert_eq('https://sleutels.kvt.nl/mimir/api', odata_mimir_base_url());
});

ponos_test('entity-URL met spatie in bedrijfsnaam', function (): void {
    $spaceUrl = mimir_test_company_entity_url(
        'https://bc.example',
        'Production',
        'Koninklijke van Twist',
        'AppProjecten',
        [
            '$select' => 'No,Description',
            '$filter' => "No eq 'PRJ1'",
        ]
    );
    $parsedSpace = odata_mimir_parse_entity_url($spaceUrl);
    assert_true(is_array($parsedSpace));
    assert_eq('Koninklijke van Twist', $parsedSpace['company'] ?? '');
    assert_eq('AppProjecten', $parsedSpace['entity'] ?? '');
    assert_eq('No,Description', $parsedSpace['query']['$select'] ?? '');
    assert_eq("No eq 'PRJ1'", $parsedSpace['query']['$filter'] ?? '');
    assert_eq(null, odata_mimir_parse_companies_url($spaceUrl));
});

ponos_test('lege baseUrl en apostrof in bedrijfsnaam', function (): void {
    $apostropheUrl = mimir_test_company_entity_url(
        '',
        'Production',
        "Van Twist's",
        'AppProjecten',
        ['$select' => 'No,Description']
    );
    $parsed = odata_mimir_parse_entity_url($apostropheUrl);
    assert_true(is_array($parsed));
    assert_eq("Van Twist's", $parsed['company'] ?? '');
    assert_eq('AppProjecten', $parsed['entity'] ?? '');
    assert_eq('No,Description', $parsed['query']['$select'] ?? '');
});

ponos_test('companies-URL levert environment', function (): void {
    $companiesUrl = 'https://bc.example/Production/ODataV4/Companies?$select=Name';
    $parsed = odata_mimir_parse_companies_url($companiesUrl);
    assert_true(is_array($parsed));
    assert_eq('Production', $parsed['environment'] ?? '');
    assert_eq(null, odata_mimir_parse_entity_url($companiesUrl));
});

ponos_test('BC-auth ontbreekt blijft exception zonder Mímir', function (): void {
    mimir_test_clear_bc_config();
    $threw = false;
    try {
        auth_get_auth_for_environment('Production');
    } catch (RuntimeException $error) {
        $threw = str_contains($error->getMessage(), 'Geen auth-configuratie');
    }
    assert_true($threw);
});

ponos_test('company-discovery faalt snel zonder BC-config en zonder Mímir', function (): void {
    mimir_test_clear_bc_config();
    $threw = false;
    try {
        auth_discover_companies_across_active_environments(30);
    } catch (RuntimeException $error) {
        $threw = str_contains($error->getMessage(), 'Geen actieve environments');
    }
    assert_true($threw);
});

$mimirServer = null;
try {
    mimir_test_write_mock();
    @unlink($mimirMockLog);
    @unlink($mimirServerLog);
    $mimirServer = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $mimirMockPort, $mimirMockScript],
        [
            1 => ['file', $mimirServerLog, 'w'],
            2 => ['file', $mimirServerLog, 'a'],
        ],
        $mimirPipes,
        sys_get_temp_dir()
    );
    $mimirReady = false;
    if (is_resource($mimirServer)) {
        for ($mimirAttempt = 0; $mimirAttempt < 50; $mimirAttempt++) {
            $mimirSocket = @fsockopen('127.0.0.1', $mimirMockPort, $mimirErrno, $mimirErrstr, 0.2);
            if (is_resource($mimirSocket)) {
                fclose($mimirSocket);
                $mimirReady = true;
                break;
            }
            usleep(100000);
        }
    }

    ponos_test('mock-server start', function () use ($mimirReady): void {
        assert_true($mimirReady);
    });

    if ($mimirReady) {
        $GLOBALS['mimirApi'] = 'mimir_test_key';
        $GLOBALS['mimirBase'] = 'http://127.0.0.1:' . $mimirMockPort . '/mimir/api';
        $GLOBALS['baseUrl'] = '';
        unset($GLOBALS['auth_list'], $GLOBALS['environment'], $GLOBALS['auth']);
        mimir_test_reset_discovery();

        ponos_test('Mímir company-discovery zonder BC-creds', function (): void {
            $discovered = auth_discover_companies_across_active_environments(30);
            assert_eq(
                ['Hunter van Twist', 'Koninklijke van Twist', "Van Twist's"],
                $discovered['companies'] ?? null
            );
            assert_eq('Production', $discovered['map']['Koninklijke van Twist'] ?? '');
            assert_eq('Sandbox', $discovered['map']['Hunter van Twist'] ?? '');
            assert_eq('Production', $discovered['primary_environment'] ?? '');
        });

        ponos_test('lege auth-sentinel in Mímir-modus', function (): void {
            assert_eq([], auth_get_auth_for_environment('Production'));
        });

        ponos_test('company-context zonder BC-auth', function (): void {
            $context = auth_set_current_company_context('Hunter van Twist', 30);
            assert_eq('Sandbox', $context['environment'] ?? '');
            assert_eq([], $context['auth'] ?? null);
        });

        ponos_test('environments uit Mímir als auth_list ontbreekt', function (): void {
            mimir_test_reset_discovery();
            unset($GLOBALS['auth_list'], $GLOBALS['environment']);
            $active = auth_get_active_environments();
            assert_eq(['Production', 'Sandbox'], $active);
        });

        ponos_test('odata_get_all en auth_odata_get_all via Mímir zonder filecache', function (): void {
            $entityUrl = mimir_test_company_entity_url(
                '',
                'Production',
                'Koninklijke van Twist',
                'AppProjecten',
                [
                    '$select' => 'No,Description',
                    '$filter' => "No eq 'PRJ1'",
                ]
            );
            $beforeCache = glob(dirname(__DIR__) . '/web/cache/odata/*.json') ?: [];
            $rows = odata_get_all($entityUrl, [], 60);
            $project = $rows[0] ?? null;
            assert_true(is_array($project));
            assert_eq('Koninklijke van Twist', $project['company'] ?? '');
            assert_eq('AppProjecten', $project['table'] ?? '');
            assert_eq("No eq 'PRJ1'", $project['filter'] ?? '');
            assert_eq(60, $project['max_age'] ?? null);
            assert_true(in_array('No', $project['select'] ?? [], true));

            $viaAuth = auth_odata_get_all($entityUrl, [], 60);
            assert_eq('AppProjecten', $viaAuth[0]['table'] ?? '');

            $page = auth_odata_get_json($entityUrl, []);
            assert_eq('AppProjecten', $page['value'][0]['table'] ?? '');

            $afterCache = glob(dirname(__DIR__) . '/web/cache/odata/*.json') ?: [];
            assert_eq(count($beforeCache), count($afterCache));
        });

        ponos_test('company-discovery-URL gaat naar Mímir, niet naar BC-host', function (): void {
            $companyRows = odata_get_all('https://bc.example/Sandbox/ODataV4/Company?$select=Name', [], 30);
            $companyNames = array_map(static function (array $row): string {
                return (string) ($row['Name'] ?? '');
            }, $companyRows);
            assert_eq(['Hunter van Twist'], $companyNames);
        });

        ponos_test('geen request naar de BC-host en Mímir-client user-agent', function (): void {
            $hitBcHost = false;
            $sawMimirUa = false;
            foreach (mimir_test_mock_requests() as $request) {
                if (str_contains((string) ($request['uri'] ?? ''), 'bc.example')) {
                    $hitBcHost = true;
                }
                if (($request['ua'] ?? '') === 'Ponos-MimirClient/1.0' && str_contains((string) ($request['uri'] ?? ''), '/mimir/api/')) {
                    $sawMimirUa = true;
                }
            }
            assert_false($hitBcHost);
            assert_true($sawMimirUa);
        });

        ponos_test('overlap tussen Mímir-environments blijft een fout', function () use ($mimirMockPort): void {
            mimir_test_reset_discovery();
            $GLOBALS['mimirBase'] = 'http://127.0.0.1:' . $mimirMockPort . '/mimir-dup/api';
            $overlapThrew = false;
            try {
                auth_discover_companies_across_active_environments(30);
            } catch (RuntimeException $error) {
                $overlapThrew = str_contains($error->getMessage(), 'Bedrijfsnaam-overlap');
            }
            assert_true($overlapThrew);
        });

        ponos_test('zonder Mímir blijft BC-fetch werken', function () use ($mimirMockPort, $mimirAuthPath): void {
            global $mimirMockLog;
            $GLOBALS['mimirApi'] = '';
            $GLOBALS['baseUrl'] = 'http://127.0.0.1:' . $mimirMockPort;
            $GLOBALS['environment'] = 'Production';
            $GLOBALS['auth_list'] = [
                'Production' => [
                    'mode' => 'basic',
                    'user' => 'bcuser',
                    'pass' => 'bcpass',
                ],
            ];
            $GLOBALS['auth'] = $GLOBALS['auth_list']['Production'];
            file_put_contents(
                $mimirAuthPath,
                "<?php\n\$baseUrl = " . var_export($GLOBALS['baseUrl'], true) . ";\n\$environment = 'Production';\n\$auth_list = " . var_export($GLOBALS['auth_list'], true) . ";\n\$mimirApi = '';\n"
            );
            mimir_test_reset_discovery();
            @unlink($mimirMockLog);

            assert_false(odata_mimir_enabled());
            $bcUrl = $GLOBALS['baseUrl'] . '/Production/ODataV4/Companies?$select=Name';
            $bcRows = odata_get_all($bcUrl, $GLOBALS['auth'], 30);
            assert_eq('bc', $bcRows[0]['via'] ?? '');
            assert_eq('bcuser', $bcRows[0]['user'] ?? '');

            @unlink($mimirMockLog);
            $authUrl = $GLOBALS['baseUrl'] . '/Production/ODataV4/Company?$select=Name';
            $authRows = auth_odata_get_all($authUrl, $GLOBALS['auth'], 30);
            assert_eq('bc', $authRows[0]['via'] ?? '');
            assert_eq('bcuser', $authRows[0]['user'] ?? '');

            $bcHitMimir = false;
            foreach (mimir_test_mock_requests() as $request) {
                if (str_contains((string) ($request['uri'] ?? ''), '/mimir/')) {
                    $bcHitMimir = true;
                }
            }
            assert_false($bcHitMimir);
        });
    }
} finally {
    if (is_resource($mimirServer)) {
        proc_terminate($mimirServer);
        proc_close($mimirServer);
    }
    if ($mimirAuthBackup === null) {
        @unlink($mimirAuthPath);
    } else {
        file_put_contents($mimirAuthPath, $mimirAuthBackup);
    }
    @unlink($mimirMockScript);
    @unlink($mimirMockLog);
    @unlink($mimirServerLog);
    foreach (glob(dirname(__DIR__) . '/web/cache/odata/*.json') ?: [] as $mimirCacheFile) {
        $mimirRaw = @file_get_contents($mimirCacheFile);
        if (is_string($mimirRaw) && str_contains($mimirRaw, '127.0.0.1:' . $mimirMockPort)) {
            @unlink($mimirCacheFile);
        }
    }

    foreach ($mimirSavedGlobals as $mimirGlobalName => $mimirSaved) {
        if ($mimirSaved['exists']) {
            $GLOBALS[$mimirGlobalName] = $mimirSaved['value'];
        } else {
            unset($GLOBALS[$mimirGlobalName]);
        }
    }
    mimir_test_reset_discovery();
}
