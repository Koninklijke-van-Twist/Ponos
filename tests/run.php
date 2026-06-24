<?php

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

require __DIR__ . '/assert.php';

echo "Ponos test suite\n";
echo str_repeat('-', 40) . "\n";

foreach (glob(__DIR__ . '/*_test.php') ?: [] as $testFile) {
    require $testFile;
}

$failures = (int) ($GLOBALS['ponos_test_failures'] ?? 0);
echo str_repeat('-', 40) . "\n";
echo $failures === 0 ? "All tests passed.\n" : "{$failures} test(s) failed.\n";

exit($failures > 0 ? 1 : 0);
