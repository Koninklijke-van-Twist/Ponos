<?php

declare(strict_types=1);

$GLOBALS['ponos_test_failures'] = 0;
$GLOBALS['ponos_test_current'] = '';

function ponos_test(string $name, callable $callback): void
{
    $GLOBALS['ponos_test_current'] = $name;

    try {
        $callback();
        echo "OK   {$name}\n";
    } catch (Throwable $error) {
        $GLOBALS['ponos_test_failures']++;
        echo "FAIL {$name}: {$error->getMessage()}\n";
    }
}

function assert_true(mixed $condition, string $message = 'Expected true'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_false(mixed $condition, string $message = 'Expected false'): void
{
    if ($condition) {
        throw new RuntimeException($message);
    }
}

function assert_eq(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $label = $message !== '' ? $message : 'Values are not equal';
        throw new RuntimeException($label . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
    }
}

function assert_array_has_key(string $key, array $array, string $message = ''): void
{
    if (!array_key_exists($key, $array)) {
        throw new RuntimeException($message !== '' ? $message : "Missing key: {$key}");
    }
}
