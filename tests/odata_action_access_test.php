<?php
/**
 * odata.php-acties zijn niet anoniem, cache_status lekt geen voorbeeldrijen,
 * en data/cache zijn niet via de webserver te lezen.
 * Run: php tests/run.php
 */

if (!function_exists('ponos_test')) {
    require_once __DIR__ . '/assert.php';
}

require_once dirname(__DIR__) . '/web/odata.php';

ponos_test('directe odata-acties eisen login vóór cache_status, cache_delete en cache_clear', function (): void {
    $root = dirname(__DIR__);
    $odataSource = (string) file_get_contents($root . '/web/odata.php');
    $dispatch = substr($odataSource, (int) strrpos($odataSource, '$odataAction = '));
    $requireAt = strpos($dispatch, 'odata_require_direct_action_access()');
    $statusAt = strpos($dispatch, 'odata_send_cache_status_json()');
    $deleteAt = strpos($dispatch, 'odata_send_cache_delete_json()');
    $clearAt = strpos($dispatch, 'odata_send_cache_clear_json()');
    assert_true($requireAt !== false && $statusAt !== false && $deleteAt !== false && $clearAt !== false, 'actie-dispatch mist login of een cache-actie');
    assert_true($requireAt < $statusAt && $requireAt < $deleteAt && $requireAt < $clearAt, 'logincheck moet vóór cache_status, cache_delete en cache_clear');

    $gateStart = strpos($odataSource, 'function odata_require_direct_action_access');
    $gateEnd = strpos($odataSource, 'function odata_release_session_lock');
    assert_true($gateStart !== false && $gateEnd !== false && $gateEnd > $gateStart, 'login-gate of sessie-vrijgave ontbreekt');
    $gate = substr($odataSource, $gateStart, $gateEnd - $gateStart);
    $emailAt = strpos($gate, 'odata_email_is_allowed');
    $releaseAt = strpos($gate, 'odata_release_session_lock()');
    assert_true($emailAt !== false && $releaseAt !== false && $releaseAt > $emailAt, 'sessielock moet vrij na de logincheck, vóór het lezen van de cache');
    $releaseStart = strpos($odataSource, 'function odata_release_session_lock');
    $releaseBody = substr($odataSource, (int) $releaseStart, 500);
    assert_true(strpos($releaseBody, 'session_write_close()') !== false, 'session_write_close ontbreekt');
    assert_true(strpos($odataSource, 'php_sapi_name()') !== false, 'cli-check ontbreekt');
    assert_true(strpos($odataSource, "=== 'cli'") !== false, 'cli-vergelijking ontbreekt');
    assert_true(strpos($odataSource, 'is_trusted_requester') === false, 'localhost is geen bypass');
    assert_true(strpos($odataSource, 'odata_cache_extract_attributes_from_payload') === false, 'voorbeeldrij-extractor moet weg');
    assert_true(strpos($odataSource, 'entry.attributes') === false, 'widget toont geen voorbeeldrij');
    assert_true(strpos($odataSource, 'login/lib.php') !== false, 'login/lib.php ontbreekt');
    assert_true(strpos($odataSource, 'HTTP_X_PONOS_SECRET') !== false, 'servergeheim-header ontbreekt');
});

ponos_test('sessielock gaat dicht na de logincheck', function (): void {
    $script = sys_get_temp_dir() . '/ponos-session-lock-' . bin2hex(random_bytes(4)) . '.php';
    $odata = dirname(__DIR__) . '/web/odata.php';
    file_put_contents($script, "<?php\n"
        . 'require ' . var_export($odata, true) . ";\n"
        . "session_start();\n"
        . "if (session_status() !== PHP_SESSION_ACTIVE) { fwrite(STDERR, \"no-session\\n\"); exit(2); }\n"
        . "\$_SESSION['ponos_access_probe'] = 1;\n"
        . "odata_release_session_lock();\n"
        . "echo session_status() === PHP_SESSION_ACTIVE ? 'active' : 'closed';\n");
    $output = [];
    $code = 0;
    exec('php ' . escapeshellarg($script) . ' 2>&1', $output, $code);
    @unlink($script);
    assert_eq(0, $code, 'sessie-script faalde: ' . implode("\n", $output));
    assert_eq('closed', implode('', $output));
});

ponos_test('allowedUsers geldt voor lijst en map, niet voor een lege sessie', function (): void {
    $allowed = [
        'analyst@kvt.nl',
        'planner@kvt.nl' => [15, 40],
    ];
    assert_true(odata_email_is_allowed('Analyst@kvt.nl', $allowed));
    assert_true(odata_email_is_allowed('planner@kvt.nl', $allowed));
    assert_false(odata_email_is_allowed('andere@kvt.nl', $allowed));
    assert_false(odata_email_is_allowed('', $allowed));
    assert_true(odata_email_is_allowed('analyst@kvt.nl', null));
    assert_false(odata_email_is_allowed('', null));
});

ponos_test('cli of een bestaand servergeheim slaat de login over, een leeg geheim niet', function (): void {
    assert_true(odata_direct_action_skips_login('cli', '', ''));
    assert_false(odata_direct_action_skips_login('fpm-fcgi', '', ''));
    assert_false(odata_direct_action_skips_login('fpm-fcgi', '', 'server-secret'));
    assert_false(odata_direct_action_skips_login('fpm-fcgi', 'server-secret', ''));
    assert_false(odata_direct_action_skips_login('fpm-fcgi', 'nope', 'server-secret'));
    assert_true(odata_direct_action_skips_login('cgi-fcgi', 'server-secret', 'server-secret'));
    assert_false(odata_direct_action_skips_login('apache2handler', 'server-secret', 'ander-geheim'));

    $savedHeader = $_SERVER['HTTP_X_PONOS_SECRET'] ?? null;
    $savedGetSecret = $_GET['secret'] ?? null;
    $_SERVER['HTTP_X_PONOS_SECRET'] = '  uit-de-header  ';
    $_GET['secret'] = 'uit-de-query';
    assert_eq('uit-de-header', odata_presented_runner_secret());
    if ($savedHeader === null) {
        unset($_SERVER['HTTP_X_PONOS_SECRET']);
    } else {
        $_SERVER['HTTP_X_PONOS_SECRET'] = $savedHeader;
    }
    if ($savedGetSecret === null) {
        unset($_GET['secret']);
    } else {
        $_GET['secret'] = $savedGetSecret;
    }
});

ponos_test('runner-geheim komt uit auth.php, global of env, en een leeg geheim telt niet', function (): void {
    $authPath = sys_get_temp_dir() . '/ponos-auth-runner-secret.php';
    file_put_contents($authPath, "<?php\n\$ponosRunnerSecret = \"auth-php-geheim\";\n\$allowedUsers = ['nacht@kvt.nl'];\n");
    $previousAuthPath = $GLOBALS['PONOS_AUTH_PHP_PATH'] ?? null;
    $previousRunner = $GLOBALS['ponosRunnerSecret'] ?? null;
    unset($GLOBALS['ponosRunnerSecret']);
    $previousEnv = getenv('PONOS_RUNNER_SECRET');
    putenv('PONOS_RUNNER_SECRET');
    $GLOBALS['PONOS_AUTH_PHP_PATH'] = $authPath;
    try {
        $fromFile = odata_read_auth_file_vars();
        assert_eq('auth-php-geheim', odata_configured_runner_secret($fromFile));
        assert_true(odata_email_is_allowed('nacht@kvt.nl', $fromFile['allowedUsers'] ?? null));
        putenv('PONOS_RUNNER_SECRET=env-geheim');
        assert_eq('auth-php-geheim', odata_configured_runner_secret($fromFile));
        $GLOBALS['ponosRunnerSecret'] = 'global-geheim';
        assert_eq('global-geheim', odata_configured_runner_secret($fromFile));
        unset($GLOBALS['ponosRunnerSecret']);
        putenv('PONOS_RUNNER_SECRET');
        assert_eq('auth-php-geheim', odata_configured_runner_secret($fromFile));
    } finally {
        if ($previousAuthPath === null) {
            unset($GLOBALS['PONOS_AUTH_PHP_PATH']);
        } else {
            $GLOBALS['PONOS_AUTH_PHP_PATH'] = $previousAuthPath;
        }
        if ($previousRunner !== null) {
            $GLOBALS['ponosRunnerSecret'] = $previousRunner;
        } else {
            unset($GLOBALS['ponosRunnerSecret']);
        }
        if ($previousEnv !== false && $previousEnv !== '') {
            putenv('PONOS_RUNNER_SECRET=' . $previousEnv);
        } else {
            putenv('PONOS_RUNNER_SECRET');
        }
        @unlink($authPath);
    }
});

ponos_test('cache_status bevat geen voorbeeldrij of attributes', function (): void {
    $cacheDir = cache_base_dir();
    $fixtureName = 'ponos-access-test-' . bin2hex(random_bytes(8)) . '.json';
    $fixturePath = $cacheDir . '/' . $fixtureName;
    $customerNo = 'C-SECRET-44021';
    $customerName = 'Voorbeeld Klant Jansen';
    $documentNo = 'VF-998877';
    $sourceUrl = "https://bc.example:7148/Production/ODataV4/Company('KVT')/Customer_Ledger_Entries";
    $fixture = [
        '_meta' => [
            'cached_at' => time() - 60,
            'expires_at' => time() + 3600,
            'source_url' => $sourceUrl,
            'fetched' => true,
        ],
        'data' => [[
            'Customer_No' => $customerNo,
            'Customer_Name' => $customerName,
            'Document_No' => $documentNo,
            'Amount' => 12345.67,
            '@odata.etag' => 'W/"example"',
        ]],
    ];
    file_put_contents($fixturePath, json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        $payload = odata_cache_status_payload();
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        assert_true(is_string($encoded), 'cache_status payload is geen JSON');
        assert_true(strpos($encoded, $customerNo) === false, 'cache_status lekt klantnummer');
        assert_true(strpos($encoded, $customerName) === false, 'cache_status lekt klantnaam');
        assert_true(strpos($encoded, $documentNo) === false, 'cache_status lekt documentnummer');
        assert_true(strpos($encoded, '12345.67') === false, 'cache_status lekt bedrag');
        assert_true(strpos($encoded, 'attributes') === false, 'cache_status bevat attributes');
        assert_true(strpos($encoded, 'Customer_Name') === false, 'cache_status lekt veldnaam uit de rij');

        $match = null;
        foreach ($payload['entries'] as $entry) {
            if (($entry['id'] ?? '') === $fixtureName) {
                $match = $entry;
                break;
            }
        }
        assert_true(is_array($match), 'fixture ontbreekt in cache_status');
        assert_false(array_key_exists('attributes', $match) || array_key_exists('data', $match), 'cache_status-entry bevat rijdata');
        assert_eq($sourceUrl, $match['url'] ?? '');
        assert_true((int) ($match['size_bytes'] ?? 0) > 0, 'cache_status mist bestandsgrootte');
    } finally {
        @unlink($fixturePath);
    }

    $widget = injectTimerHtml([
        'statusUrl' => 'odata.php?action=cache_status',
    ]);
    assert_true(strpos($widget, 'attributes') === false, 'widget-html toont attributes');
    assert_true(strpos($widget, 'odata.php?action=cache_status') !== false, 'widget houdt status-url');
});

ponos_test('elke data- en cachemap weigert directory listing en directe toegang', function (): void {
    $root = dirname(__DIR__);
    $dirs = [
        'web/cache',
        'web/cache/odata',
        'web/data',
        'web/data/ponos',
        'web/data/ponos/attachments',
        'web/data/user_prefs',
        'web/data/user_avatars',
    ];
    foreach ($dirs as $dir) {
        $htaccess = $root . '/' . $dir . '/.htaccess';
        $rules = (string) file_get_contents($htaccess);
        assert_true(strpos($rules, 'Options -Indexes') !== false, $htaccess);
        assert_true(strpos($rules, 'Require all denied') !== false, $htaccess);
        assert_true(strpos($rules, 'Deny from all') !== false, $htaccess);
        assert_true(strpos($rules, '<IfModule mod_authz_core.c>') !== false, $htaccess);
        assert_true(strpos($rules, '<IfModule !mod_authz_core.c>') !== false, $htaccess);
        foreach (['index.html', 'index.php'] as $indexName) {
            $indexFile = $root . '/' . $dir . '/' . $indexName;
            assert_true(is_file($indexFile), 'ontbrekend indexbestand ' . $indexFile);
            $indexBody = (string) file_get_contents($indexFile);
            assert_true(strlen($indexBody) <= 16, 'indexbestand is niet leeg: ' . $indexFile);
            assert_true(strpos($indexBody, 'Customer') === false, $indexFile);
        }
    }
});

ponos_test('ftp-deploy uploadt afscherming en laat data en cache-json staan', function (): void {
    $workflow = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy-ftp.yml');
    $includeAt = strpos($workflow, '--include-glob cache/.htaccess');
    $includeDataAt = strpos($workflow, '--include-glob data/ponos/.htaccess');
    $includeAttachAt = strpos($workflow, '--include-glob data/ponos/attachments/.htaccess');
    $excludeCacheAt = strpos($workflow, '--exclude-glob cache/**');
    $excludeDataAt = strpos($workflow, '--exclude-glob data/**');
    $excludeHtaccessAt = strpos($workflow, '--exclude-glob .htaccess');
    assert_true($includeAt !== false && $includeDataAt !== false && $includeAttachAt !== false, 'ftp-deploy mist htaccess-includes');
    assert_true($excludeCacheAt !== false && $excludeDataAt !== false && $excludeHtaccessAt !== false, 'ftp-deploy mist excludes');
    assert_true($includeAt > $excludeCacheAt && $includeAt > $excludeHtaccessAt, 'cache/.htaccess moet ná de excludes staan');
    assert_true($includeDataAt > $excludeDataAt && $includeDataAt > $excludeHtaccessAt, 'data/ponos/.htaccess moet ná de excludes staan');
    assert_true($includeAttachAt > $excludeDataAt, 'attachments/.htaccess moet ná de data-exclude staan');
    foreach ([
        '--include-glob cache/index.html',
        '--include-glob cache/index.php',
        '--include-glob cache/odata/.htaccess',
        '--include-glob cache/odata/index.html',
        '--include-glob cache/odata/index.php',
        '--include-glob data/.htaccess',
        '--include-glob data/index.html',
        '--include-glob data/index.php',
        '--include-glob data/ponos/index.html',
        '--include-glob data/ponos/index.php',
        '--include-glob data/ponos/attachments/index.html',
        '--include-glob data/ponos/attachments/index.php',
        '--include-glob data/user_prefs/.htaccess',
        '--include-glob data/user_prefs/index.html',
        '--include-glob data/user_prefs/index.php',
        '--include-glob data/user_avatars/.htaccess',
        '--include-glob data/user_avatars/index.html',
        '--include-glob data/user_avatars/index.php',
        '--exclude-glob auth.php',
        '--exclude-glob data/ponos/**',
        '--exclude-glob data/user_prefs/**',
        '--exclude-glob data/user_avatars/**',
        '--exclude-glob analytics/*.sqlite',
    ] as $needle) {
        assert_true(strpos($workflow, $needle) !== false, 'ftp-deploy mist ' . $needle);
    }
    assert_true(strpos($workflow, 'ponos.sqlite') === false, 'ftp-deploy noemt de sqlite niet als upload');
    assert_true(strpos($workflow, '--include-glob data/**') === false, 'ftp-deploy mag de datamap niet in zijn geheel uploaden');
});

ponos_test('anonieme web-actie krijgt geen cache_status', function (): void {
    $phpCgi = trim((string) shell_exec('command -v php-cgi 2>/dev/null'));
    if ($phpCgi === '') {
        return;
    }

    $root = dirname(__DIR__);
    $script = sys_get_temp_dir() . '/ponos-odata-cgi-' . bin2hex(random_bytes(4)) . '.php';
    $odataFile = $root . '/web/odata.php';
    file_put_contents($script, "<?php\n"
        . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($odataFile, true) . ";\n"
        . '$_SERVER["SCRIPT_NAME"] = "/ponos/odata.php";' . "\n"
        . '$_SERVER["PHP_SELF"] = "/ponos/odata.php";' . "\n"
        . '$_SERVER["REMOTE_ADDR"] = "203.0.113.50";' . "\n"
        . '$_SERVER["SERVER_ADDR"] = "198.51.100.10";' . "\n"
        . '$_GET["action"] = "cache_status";' . "\n"
        . 'require ' . var_export($odataFile, true) . ";\n");
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([$phpCgi, '-q', '-d', 'display_errors=0', $script], $descriptor, $pipes, $root);
    assert_true(is_resource($process), 'php-cgi startte niet');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    @unlink($script);
    $stdout = is_string($stdout) ? $stdout : '';
    $stderr = is_string($stderr) ? $stderr : '';
    assert_true($exitCode === 0 || strpos($stdout, 'Geen toegang') !== false, 'anonieme cgi-actie exit ' . $exitCode . ' ' . $stderr . ' ' . $stdout);
    assert_true(strpos($stdout, 'Geen toegang') !== false, 'anonieme cgi-actie');
    assert_true(strpos($stdout, 'Customer_Ledger_Entries') === false, 'anonieme cgi-actie lekt een bron');
    assert_true(strpos($stdout, '"entries"') === false, 'anonieme cgi-actie lekt cache_status');

    file_put_contents($script, "<?php\n"
        . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($odataFile, true) . ";\n"
        . '$_SERVER["SCRIPT_NAME"] = "/ponos/odata.php";' . "\n"
        . '$_SERVER["PHP_SELF"] = "/ponos/odata.php";' . "\n"
        . '$_SERVER["REMOTE_ADDR"] = "203.0.113.50";' . "\n"
        . '$_GET["action"] = "nope";' . "\n"
        . 'require ' . var_export($odataFile, true) . ";\n");
    $process = proc_open([$phpCgi, '-q', '-d', 'display_errors=0', $script], $descriptor, $pipes, $root);
    assert_true(is_resource($process), 'php-cgi startte niet voor onbekende actie');
    fclose($pipes[0]);
    $unknown = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    $unknown = is_string($unknown) ? $unknown : '';
    assert_true(strpos($unknown, 'Geen toegang') !== false, 'onbekende actie blijft achter de login');
    assert_true(strpos($unknown, 'Onbekende actie') === false, 'anonieme onbekende actie mag de dispatcher niet tonen');

    $authPath = sys_get_temp_dir() . '/ponos-cgi-auth-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($authPath, "<?php\n\$ponosRunnerSecret = 'nacht-geheim';\n\$allowedUsers = ['nacht@kvt.nl'];\n");
    file_put_contents($script, "<?php\n"
        . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($odataFile, true) . ";\n"
        . '$_SERVER["SCRIPT_NAME"] = "/ponos/odata.php";' . "\n"
        . '$_SERVER["PHP_SELF"] = "/ponos/odata.php";' . "\n"
        . '$_SERVER["REMOTE_ADDR"] = "203.0.113.50";' . "\n"
        . '$_SERVER["HTTP_X_PONOS_SECRET"] = "nacht-geheim";' . "\n"
        . '$_GET["action"] = "cache_status";' . "\n"
        . '$GLOBALS["PONOS_AUTH_PHP_PATH"] = ' . var_export($authPath, true) . ";\n"
        . 'require ' . var_export($odataFile, true) . ";\n");
    $process = proc_open([$phpCgi, '-q', '-d', 'display_errors=0', $script], $descriptor, $pipes, $root);
    assert_true(is_resource($process), 'php-cgi startte niet voor het servergeheim');
    fclose($pipes[0]);
    $withSecret = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    @unlink($script);
    @unlink($authPath);
    $withSecret = is_string($withSecret) ? $withSecret : '';
    assert_true(strpos($withSecret, 'Geen toegang') === false, 'servergeheim hoort de nightly door te laten');
    assert_true(strpos($withSecret, '"entries"') !== false, 'servergeheim krijgt cache_status');
    assert_true(strpos($withSecret, 'attributes') === false, 'servergeheim-cache_status zonder attributes');
});
