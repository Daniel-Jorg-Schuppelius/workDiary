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
$locales = ['de', 'en', 'fr', 'it', 'es'];
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
    foreach (['fr', 'it', 'es'] as $loc) {
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

// --- Source scan ----------------------------------------------------------
// JSON-style keys used in resources/views + app that are absent from en.json
// fall back to the German source text in EVERY locale — catalog parity alone
// cannot see this. Framework-free port of Translations::sourceJsonKeys();
// keep the heuristics in sync with that method.
$namespaces = [];
foreach (glob($base . '/lang/de/*.php') ?: [] as $f) {
    $namespaces[substr(basename($f), 0, -4)] = true;
}

$sourceKeys = [];
$dirs = [$base . '/resources/views', $base . '/app'];
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (! $file instanceof SplFileInfo || ! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }
        $src = (string) file_get_contents($file->getPathname());
        if (! preg_match_all('~(?<![A-Za-z0-9_])(?:__|trans)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*[,)]~s', $src, $m)) {
            continue;
        }
        foreach ($m[2] as $raw) {
            $k = stripcslashes($raw);
            if ($k === '' || str_contains($k, '$') || str_contains($k, '{')) {
                continue; // dynamic/interpolated
            }
            if (! str_contains($k, ' ')) {
                if (str_contains($k, '.') && isset($namespaces[explode('.', $k, 2)[0]])) {
                    continue; // namespace key (lang/de/<file>.php)
                }
                if (preg_match('/^[a-z0-9_]+(\.[a-z0-9_:-]+)+$/', $k) === 1) {
                    continue; // dotted-lowercase = namespace convention
                }
                if (str_ends_with($k, '.') || str_ends_with($k, ':') || str_ends_with($k, '_')) {
                    continue; // concatenation prefix
                }
            }
            $sourceKeys[$k] = true;
        }
    }
}

if (is_file($enJsonPath)) {
    $enSet = array_fill_keys(array_keys(json_decode((string) file_get_contents($enJsonPath), true) ?? []), true);
    $sourceMissing = array_keys(array_diff_key($sourceKeys, $enSet));
    sort($sourceMissing);
    if ($sourceMissing) {
        echo "=== source scan (views + app vs en.json) ===\n";
        echo '  used in source but missing in en.json (' . count($sourceMissing) . ") — shown as German everywhere:\n";
        foreach ($sourceMissing as $k) { echo "    - $k\n"; }
        $exit = 1;
    }
}

if ($exit === 0) {
    echo "Translations: all locales in sync.\n";
} else {
    echo "\nTranslations: drift detected (see above).\n";
}

exit($exit);
