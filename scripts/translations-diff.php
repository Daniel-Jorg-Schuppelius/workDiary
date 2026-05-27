<?php

/**
 * Compare key parity across lang/de, lang/en, lang/fr, lang/it (PHP files).
 *
 * Exit code:
 *   0 — all locales contain the same keys (per file).
 *   1 — drift detected; report lists missing keys per locale.
 *
 * Stub files (lang/fr|it/*.php that just `return require __DIR__ . '/../en/...';`)
 * are treated as exact mirrors of EN, which is the intended baseline.
 *
 * Also compares JSON catalogs (lang/<locale>.json) against lang/en.json.
 * Note: no lang/de.json exists — DE is the source language for JSON catalogs,
 * so DE strings appear as JSON keys (e.g. __('Speichern')) and fall back to
 * the key when no translation is registered.
 *
 * Usage: php scripts/translations-diff.php
 */

declare(strict_types=1);

$base = dirname(__DIR__);
$locales = ['de', 'en', 'fr', 'it'];
$reference = 'de'; // German is the source language for the PHP catalogs.

function flatten(array $a, string $prefix = ''): array {
    $out = [];
    foreach ($a as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out = array_merge($out, flatten($v, $key));
        } else {
            $out[$key] = $v;
        }
    }
    return $out;
}

$exit = 0;

// --- PHP catalogs ---------------------------------------------------------
$allFiles = [];
foreach ($locales as $loc) {
    foreach (glob($base . "/lang/$loc/*.php") ?: [] as $f) {
        $allFiles[basename($f)] = true;
    }
}
ksort($allFiles);

foreach (array_keys($allFiles) as $file) {
    $keysPerLocale = [];
    foreach ($locales as $loc) {
        $path = $base . "/lang/$loc/$file";
        if (! is_file($path)) {
            echo "[MISSING FILE] lang/$loc/$file\n";
            $exit = 1;
            continue;
        }
        $data = (array) require $path;
        $keysPerLocale[$loc] = array_keys(flatten($data));
    }

    if (! isset($keysPerLocale[$reference])) {
        continue;
    }
    $ref = $keysPerLocale[$reference];

    $report = [];
    foreach ($locales as $loc) {
        if (! isset($keysPerLocale[$loc]) || $loc === $reference) {
            continue;
        }
        $missing = array_values(array_diff($ref, $keysPerLocale[$loc]));
        $extra = array_values(array_diff($keysPerLocale[$loc], $ref));
        if ($missing || $extra) {
            $report[$loc] = ['missing' => $missing, 'extra' => $extra];
        }
    }

    if ($report) {
        echo "=== $file ===\n";
        foreach ($report as $loc => $info) {
            if ($info['missing']) {
                echo "  missing in $loc (" . count($info['missing']) . "):\n";
                foreach ($info['missing'] as $k) { echo "    - $k\n"; }
                $exit = 1;
            }
            if ($info['extra']) {
                echo "  extra in $loc (" . count($info['extra']) . "):\n";
                foreach ($info['extra'] as $k) { echo "    + $k\n"; }
                $exit = 1;
            }
        }
    }
}

// --- JSON catalogs --------------------------------------------------------
// JSON catalogs use DE strings as keys (e.g. {{ __('Speichern') }}). DE itself
// therefore needs no JSON file (Laravel falls back to the key). EN/FR/IT must
// each provide a translation file with the same key set as lang/en.json.
$enJsonPath = $base . '/lang/en.json';
if (is_file($enJsonPath)) {
    $en = array_keys(json_decode((string) file_get_contents($enJsonPath), true) ?? []);
    foreach (['fr', 'it'] as $loc) {
        $path = $base . "/lang/$loc.json";
        if (! is_file($path)) {
            echo "[MISSING FILE] lang/$loc.json\n";
            $exit = 1;
            continue;
        }
        $other = array_keys(json_decode((string) file_get_contents($path), true) ?? []);
        $missing = array_values(array_diff($en, $other));
        if ($missing) {
            echo "=== lang/$loc.json ===\n";
            echo "  missing in $loc (" . count($missing) . ") vs en.json:\n";
            foreach ($missing as $k) { echo "    - $k\n"; }
            $exit = 1;
        }
    }
}

if ($exit === 0) {
    echo "Translations: all locales in sync.\n";
} else {
    echo "\nTranslations: drift detected (see above).\n";
}

exit($exit);
