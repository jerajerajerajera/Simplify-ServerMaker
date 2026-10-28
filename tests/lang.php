<?php

// Every language has exactly the English keys, and keeps every :placeholder of the English text.

declare(strict_types=1);

function flatten(array $lines, string $prefix = ''): array
{
    $flat = [];
    foreach ($lines as $key => $value) {
        if (is_array($value)) {
            $flat += flatten($value, $prefix.$key.'.');
        } else {
            $flat[$prefix.$key] = $value;
        }
    }

    return $flat;
}

$failures = [];
$english = [];
foreach (glob(__DIR__.'/../lang/en/*.php') as $file) {
    $english[basename($file)] = flatten(require $file);
}
$locales = glob(__DIR__.'/../lang/*', GLOB_ONLYDIR);
foreach ($locales as $directory) {
    $locale = basename($directory);
    foreach ($english as $group => $lines) {
        $file = $directory.'/'.$group;
        if (! is_file($file)) {
            $failures[] = "$locale: $group is missing";

            continue;
        }
        $translated = flatten(require $file);
        foreach (array_keys(array_diff_key($lines, $translated)) as $key) {
            $failures[] = "$locale: missing $group $key";
        }
        foreach (array_keys(array_diff_key($translated, $lines)) as $key) {
            $failures[] = "$locale: unknown key $group $key";
        }
        foreach (array_intersect_key($translated, $lines) as $key => $text) {
            preg_match_all('/:[a-z_]+/', $lines[$key], $want);
            preg_match_all('/:[a-z_]+/', (string) $text, $have);
            $lost = array_diff($want[0], $have[0]);
            if ($lost) {
                $failures[] = "$locale: $group $key lost ".implode(', ', $lost);
            }
        }
    }
}
if (count($locales) !== 32) {
    $failures[] = count($locales).' languages instead of 32';
}
if ($failures) {
    echo "FAIL translations:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo 'PASS '.count($locales)." languages have every key and placeholder\n";
