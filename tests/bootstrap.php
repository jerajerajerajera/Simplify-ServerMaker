<?php

// Loads the plugin code without a panel: the plugin's own classes, stand-ins for the Simplify classes it extends and
// English lookups for __(). Laravel and Pelican classes are not available here; tests only use pure code.

declare(strict_types=1);
use Illuminate\Validation\ValidationException;

spl_autoload_register(function (string $class): void {
    $prefix = 'Simplify\\ServerMaker\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/../src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require __DIR__.'/stubs/simplify.php';
require __DIR__.'/stubs/laravel.php';

if (! function_exists('__')) {
    function __($key = null, $replace = [], $locale = null)
    {
        $prefix = 'simplify-server-maker::';
        if (! is_string($key) || ! str_starts_with($key, $prefix)) {
            return $key;
        }
        [$group, $path] = explode('.', substr($key, strlen($prefix)), 2);
        static $groups = [];
        $groups[$group] ??= require __DIR__.'/../lang/en/'.$group.'.php';
        $value = $groups[$group];
        foreach (explode('.', $path) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return $key;
            }
            $value = $value[$part];
        }
        if (! is_string($value)) {
            return $key;
        }
        foreach ($replace as $name => $text) {
            $value = str_replace(':'.$name, (string) $text, $value);
        }

        return $value;
    }
}

function check(string $what, bool $passed, string $detail = ''): void
{
    if (! $passed) {
        echo "FAIL $what".($detail !== '' ? ": $detail" : '')."\n";
        exit(1);
    }
}

/** The field a validation error is for, or null when the call did not fail. */
function failsOn(callable $run): ?string
{
    try {
        $run();
    } catch (ValidationException $exception) {
        return (string) array_key_first($exception->errors());
    }

    return null;
}
