<?php
declare(strict_types=1);

// Tiny test runner (no dependencies). Usage: php tests/run.php
require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/Fakes.php';

final class T
{
    public static int $pass = 0;
    public static array $fail = [];
    public static string $current = '';

    public static function eq(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected === $actual) {
            self::$pass++;
            return;
        }
        self::$fail[] = sprintf("%s: %s\n    expected %s\n    got      %s", self::$current, $msg, var_export($expected, true), var_export($actual, true));
    }

    public static function true(bool $cond, string $msg = ''): void
    {
        self::eq(true, $cond, $msg);
    }

    public static function throws(string $class, callable $fn, string $msg = ''): ?\Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            self::eq($class, $e instanceof $class ? $class : $e::class, $msg ?: 'exception type');
            return $e;
        }
        self::$fail[] = self::$current . ": expected $class, nothing thrown. $msg";
        return null;
    }
}

function test(string $name, callable $fn): void
{
    T::$current = $name;
    try {
        $fn();
    } catch (\Throwable $e) {
        T::$fail[] = "$name: unexpected " . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
}

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

foreach (T::$fail as $f) {
    echo "FAIL $f\n";
}
printf("\n%d assertions passed, %d failed\n", T::$pass, count(T::$fail));
exit(T::$fail ? 1 : 0);
