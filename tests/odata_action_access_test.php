<?php

/**
 * odata.php-acties zijn niet anoniem, en cache_status lekt geen voorbeeldrijen.
 * De FTP-deploy neemt de afscherming mee en laat SQLite/cache-JSON staan.
 */

require_once dirname(__DIR__) . '/web/odata.php';

function ponos_odata_contains(string $haystack, string $needle): bool
{
    return strpos($haystack, $needle) !== false;
}

/**
 * @param list<array{0:string,1:string}> $rules
 */
function ponos_lftp_last_include(array $rules, string $path, bool $starMatchesSlash): bool
{
    $included = true;
    foreach ($rules as $rule) {
        $pattern = $rule[1];
        if ($starMatchesSlash) {
            $pattern = str_replace('**', '*', $pattern);
            $matched = fnmatch($pattern, $path);
        } else {
            $pattern = str_replace('**', '*', $pattern);
            $matched = fnmatch($pattern, $path, FNM_PATHNAME);
        }
        if ($matched) {
            $included = $rule[0] === 'include';
        }
    }

    return $included;
}

/**
 * @return list<array{0:string,1:string}>
 */
function ponos_lftp_rules_from_workflow(string $workflow): array
{
    $rules = [];
    if (preg_match_all('/--(exclude-glob|include-glob) ([^\s\\\\]+)/', $workflow, $matches, PREG_SET_ORDER) === false) {
        return $rules;
    }
    foreach ($matches as $match) {
        $rules[] = [$match[1] === 'include-glob' ? 'include' : 'exclude', $match[2]];
    }

    return $rules;
}

ponos_test('odata acties eisen login vóór cache_status, delete en clear', function (): void {
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

    assert_true(ponos_odata_contains($odataSource, 'php_sapi_name()'), 'cli-check');
    assert_true(ponos_odata_contains($odataSource, "=== 'cli'"), 'cli-vergelijking');
    assert_true(!ponos_odata_contains($odataSource, 'is_trusted_requester'), 'geen localhost-bypass');
    assert_true(!ponos_odata_contains($odataSource, 'odata_cache_extract_attributes_from_payload'), 'geen voorbeeldrij-extractor');
    assert_true(!ponos_odata_contains($odataSource, 'entry.attributes'), 'widget toont geen voorbeeldrij');
    assert_true(ponos_odata_contains($odataSource, 'session_write_close()'), 'sessie wordt gesloten');
    assert_true(ponos_odata_contains($dispatch, 'Onbekende actie'), 'onbekende actie blijft achter de gate');
});

ponos_test('sessielock gaat dicht na de logincheck', function (): void {
    $script = sys_get_temp_dir() . '/ponos-session-lock-' . bin2hex(random_bytes(4)) . '.php';
    $odata = dirname(__DIR__) . '/web/odata.php';
    file_put_contents($script, "<?php\n"
        . 'require_once ' . var_export($odata, true) . ";\n"
        . "session_start();\n"
        . "\$_SESSION['ponos_access_probe'] = 1;\n"
        . "odata_release_session_lock();\n"
        . "if (session_status() === PHP_SESSION_ACTIVE) {\n"
        . "    fwrite(STDERR, \"locked\\n\");\n"
        . "    exit(1);\n"
        . "}\n"
        . "echo \"closed\\n\";\n");
    $php = trim((string) (PHP_BINARY !== '' ? PHP_BINARY : shell_exec('command -v php')));
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([$php, $script], $descriptor, $pipes);
    assert_true(is_resource($process), 'sessie-test startte niet');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    @unlink($script);
    $stdout = is_string($stdout) ? $stdout : '';
    $stderr = is_string($stderr) ? $stderr : '';
    assert_eq(0, $exitCode, 'actieve sessie blijft gelockt ' . $stderr . ' ' . $stdout);
    assert_true(ponos_odata_contains($stdout, 'closed'), 'sessie is niet gesloten');
});

ponos_test('allowedUsers en runner-geheim volgen de Hermes-regels', function (): void {
    $allowed = [
        'analyst@kvt.nl',
        'planner@kvt.nl' => [15, 40],
    ];
    assert_true(odata_email_is_allowed('Analyst@kvt.nl', $allowed), 'lijst-gebruiker');
    assert_true(odata_email_is_allowed('planner@kvt.nl', $allowed), 'map-gebruiker');
    assert_true(!odata_email_is_allowed('andere@kvt.nl', $allowed), 'niet op de lijst');
    assert_true(!odata_email_is_allowed('', $allowed), 'lege sessie');
    assert_true(odata_email_is_allowed('analyst@kvt.nl', null), 'geen whitelist');

    assert_true(odata_direct_action_skips_login('cli', '', ''), 'cli zonder geheim');
    assert_true(!odata_direct_action_skips_login('fpm-fcgi', '', ''), 'web anoniem');
    assert_true(!odata_direct_action_skips_login('fpm-fcgi', '', 'server-secret'), 'web zonder header');
    assert_true(!odata_direct_action_skips_login('fpm-fcgi', 'server-secret', ''), 'leeg servergeheim');
    assert_true(!odata_direct_action_skips_login('fpm-fcgi', 'nope', 'server-secret'), 'fout geheim');
    assert_true(odata_direct_action_skips_login('cgi-fcgi', 'server-secret', 'server-secret'), 'bestaand servergeheim');
    assert_true(!odata_direct_action_skips_login('apache2handler', 'server-secret', 'ander-geheim'), 'geheim komt niet overeen');

    $savedHeader = $_SERVER['HTTP_X_PONOS_SECRET'] ?? null;
    $savedGetSecret = $_GET['secret'] ?? null;
    $_SERVER['HTTP_X_PONOS_SECRET'] = '  uit-de-header  ';
    $_GET['secret'] = 'uit-de-query';
    assert_eq('uit-de-header', odata_presented_runner_secret(), 'header, geen query');
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
        assert_eq('auth-php-geheim', odata_configured_runner_secret($fromFile), 'geheim uit auth.php');
        assert_true(odata_email_is_allowed('nacht@kvt.nl', $fromFile['allowedUsers'] ?? null), 'whitelist uit auth.php');
        putenv('PONOS_RUNNER_SECRET=env-geheim');
        assert_eq('auth-php-geheim', odata_configured_runner_secret($fromFile), 'auth.php wint van een lege global');
        $GLOBALS['ponosRunnerSecret'] = 'global-geheim';
        assert_eq('global-geheim', odata_configured_runner_secret($fromFile), 'global wint');
    } finally {
        unset($GLOBALS['ponosRunnerSecret']);
        putenv('PONOS_RUNNER_SECRET');
        if ($previousAuthPath === null) {
            unset($GLOBALS['PONOS_AUTH_PHP_PATH']);
        } else {
            $GLOBALS['PONOS_AUTH_PHP_PATH'] = $previousAuthPath;
        }
        if ($previousRunner !== null) {
            $GLOBALS['ponosRunnerSecret'] = $previousRunner;
        }
        if ($previousEnv !== false && $previousEnv !== '') {
            putenv('PONOS_RUNNER_SECRET=' . $previousEnv);
        }
        @unlink($authPath);
    }
});

ponos_test('cache_status bevat geen voorbeeldrij of attributes', function (): void {
    $cacheDir = cache_base_dir();
    $fixtureName = 'ponos-access-test-' . bin2hex(random_bytes(8)) . '.json';
    $fixturePath = $cacheDir . '/' . $fixtureName;
    $jobNo = 'JOB-SECRET-44021';
    $customerName = 'Voorbeeld Klant Jansen';
    $description = 'Werkorder omschrijving VF-998877';
    $sourceUrl = "https://bc.example:7148/Production/ODataV4/Company('KVT')/AppProjecten";
    $fixture = [
        '_meta' => [
            'cached_at' => time() - 60,
            'expires_at' => time() + 3600,
            'source_url' => $sourceUrl,
            'fetched' => true,
        ],
        'data' => [[
            'Job_No' => $jobNo,
            'Customer_Name' => $customerName,
            'Description' => $description,
            'Amount' => 12345.67,
            '@odata.etag' => 'W/"example"',
        ]],
    ];
    file_put_contents($fixturePath, json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        $payload = odata_cache_status_payload();
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        assert_true(is_string($encoded), 'cache_status payload is geen JSON');
        assert_true(!ponos_odata_contains($encoded, $jobNo), 'cache_status job');
        assert_true(!ponos_odata_contains($encoded, $customerName), 'cache_status naam');
        assert_true(!ponos_odata_contains($encoded, $description), 'cache_status omschrijving');
        assert_true(!ponos_odata_contains($encoded, '12345.67'), 'cache_status bedrag');
        assert_true(!ponos_odata_contains($encoded, 'attributes'), 'cache_status');
        assert_true(!ponos_odata_contains($encoded, 'Customer_Name'), 'cache_status veldnaam');

        $match = null;
        foreach ($payload['entries'] as $entry) {
            if (($entry['id'] ?? '') === $fixtureName) {
                $match = $entry;
                break;
            }
        }
        assert_true(is_array($match), 'fixture ontbreekt in cache_status');
        assert_true(!array_key_exists('attributes', $match) && !array_key_exists('data', $match), 'cache_status-entry bevat rijdata');
        assert_eq($sourceUrl, $match['url'] ?? '', 'bron-url voor ingelogde widget');
        assert_true((int) ($match['size_bytes'] ?? 0) > 0, 'cache_status mist bestandsgrootte');
    } finally {
        @unlink($fixturePath);
    }

    $widget = injectTimerHtml([
        'statusUrl' => 'odata.php?action=cache_status',
    ]);
    assert_true(!ponos_odata_contains($widget, 'attributes'), 'widget-html');
    assert_true(ponos_odata_contains($widget, 'odata.php?action=cache_status'), 'widget houdt status-url');
});

ponos_test('data en cache zijn dicht, inclusief submappen', function (): void {
    $root = dirname(__DIR__);
    $dirs = [
        $root . '/web/data',
        $root . '/web/data/ponos',
        $root . '/web/data/ponos/attachments',
        $root . '/web/data/user_prefs',
        $root . '/web/data/user_avatars',
        $root . '/web/cache',
        $root . '/web/cache/odata',
    ];
    foreach ($dirs as $dir) {
        $htaccess = $dir . '/.htaccess';
        $rules = (string) file_get_contents($htaccess);
        assert_true(ponos_odata_contains($rules, 'Options -Indexes'), $htaccess);
        assert_true(ponos_odata_contains($rules, 'Require all denied'), $htaccess);
        assert_true(ponos_odata_contains($rules, 'Deny from all'), $htaccess);
        assert_true(ponos_odata_contains($rules, '<IfModule mod_authz_core.c>'), $htaccess);
        assert_true(ponos_odata_contains($rules, '<IfModule !mod_authz_core.c>'), $htaccess);
        foreach (['index.html', 'index.php'] as $indexName) {
            $indexFile = $dir . '/' . $indexName;
            assert_true(is_file($indexFile), 'ontbrekend indexbestand ' . $indexFile);
            $indexBody = (string) file_get_contents($indexFile);
            assert_true(strlen($indexBody) <= 16, 'indexbestand is niet leeg: ' . $indexFile);
            assert_true(!ponos_odata_contains($indexBody, 'Customer'), $indexFile);
        }
    }
});

// FTP-deploy en afscherming: zie tests/protect_dirs_test.php.

ponos_test('anonieme odata-actie geeft geen cachedata', function (): void {
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
    assert_true($exitCode === 0 || ponos_odata_contains($stdout, 'Geen toegang'), 'anonieme cgi-actie exit ' . $exitCode . ' ' . $stderr . ' ' . $stdout);
    assert_true(ponos_odata_contains($stdout, 'Geen toegang'), 'anonieme cgi-actie');
    assert_true(!ponos_odata_contains($stdout, 'AppProjecten'), 'anonieme cgi-actie');
    assert_true(!ponos_odata_contains($stdout, 'Voorbeeld Klant'), 'anonieme cgi-actie');
});
