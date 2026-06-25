<?php

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $xamppPhp = 'C:/xampp/php/php.exe';
    if (is_file($xamppPhp)) {
        passthru(escapeshellarg($xamppPhp) . ' ' . escapeshellarg(__FILE__), $exitCode);
        exit((int) $exitCode);
    }

    fwrite(STDERR, "PDO sqlite driver is required to run Ponos tests.\n");
    exit(1);
}

if (!defined('PONOS_TEST_DB_PATH')) {
    define('PONOS_TEST_DB_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ponos_tests_' . getmypid() . '.sqlite');
}

if (is_file(PONOS_TEST_DB_PATH)) {
    @unlink(PONOS_TEST_DB_PATH);
}

require __DIR__ . '/assert.php';

echo "Ponos test suite\n";
echo str_repeat('-', 40) . "\n";

foreach (glob(__DIR__ . '/*_test.php') ?: [] as $testFile) {
    require $testFile;
}

if (is_file(PONOS_TEST_DB_PATH)) {
    @unlink(PONOS_TEST_DB_PATH);
}

$failures = (int) ($GLOBALS['ponos_test_failures'] ?? 0);
echo str_repeat('-', 40) . "\n";
echo $failures === 0 ? "All tests passed.\n" : "{$failures} test(s) failed.\n";

exit($failures > 0 ? 1 : 0);
