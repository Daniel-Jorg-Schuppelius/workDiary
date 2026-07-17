<?php

/*
 * scripts/translations-coverage.php
 *
 * 2-Pass-Check:
 *  A) Extrahiert alle in resources/ und app/ verwendeten Translation-Keys
 *     (__/trans/@lang/Lang::get) und meldet jeden Key, der weder in
 *     lang/en.json (DE-Strings als JSON-Keys) noch unter einem
 *     lang/de/*.php-Modul existiert.
 *  B) Sucht in resources/views/**.blade.php hartcodierte deutsche Strings
 *     außerhalb von __() (Heuristik: Umlaut ODER ≥ 2 Wörter mit Großbuchstaben).
 *
 * Schreibt einen Markdown-Report nach storage/reports/translations-coverage.md
 * und liefert Exit-Code 0 (sauber) / 1 (Probleme).
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
const REPORT = ROOT . '/storage/reports/translations-coverage.md';

@mkdir(dirname(REPORT), 0775, true);

// ---------- Helpers ----------

/** @return iterable<SplFileInfo> */
function walk(string $dir, array $extensions): iterable {
    if (! is_dir($dir)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile()) { continue; }
        foreach ($extensions as $ext) {
            if (str_ends_with($file->getFilename(), $ext)) {
                yield $file;
                break;
            }
        }
    }
}

/** Flattened dot-paths from a nested PHP array. */
function flatten(array $arr, string $prefix = ''): array {
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out = array_merge($out, flatten($v, $key));
        } else {
            $out[] = $key;
        }
    }
    return $out;
}

// ---------- Build "defined keys" set ----------

// JSON-Catalog: DE-Strings sind die Keys (Source-Convention). Wir lesen
// lang/en.json — der Keyspace ist über alle Locales identisch.
$definedJson = [];
$path = ROOT . '/lang/en.json';
if (is_file($path)) {
    $data = json_decode((string) file_get_contents($path), true);
    if (is_array($data)) {
        foreach (array_keys($data) as $k) { $definedJson[$k] = true; }
    }
}

$definedPhp = [];
$definedPhpPrefix = []; // parent paths returning arrays (allow `trans('access.permission')` which returns array)
foreach (['de', 'en'] as $locale) {
    $dir = ROOT . '/lang/' . $locale;
    if (! is_dir($dir)) { continue; }
    foreach (glob($dir . '/*.php') as $file) {
        $data = require $file;
        if (! is_array($data)) { continue; }
        $module = basename($file, '.php');
        $definedPhpPrefix[$module] = true;
        foreach (flatten($data) as $path) {
            $full = $module . '.' . $path;
            $definedPhp[$full] = true;
            // record every parent prefix
            $parts = explode('.', $full);
            for ($i = 1; $i < count($parts); $i++) {
                $definedPhpPrefix[implode('.', array_slice($parts, 0, $i))] = true;
            }
        }
    }
}

// ---------- PASS A: extract used translation keys ----------

$usedKeys = []; // key => [ [file,line], ... ]

$pattern = '/(?<![\w$])(?:__|trans|@lang)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/u';

$lookDirs = [
    ROOT . '/resources/views',
    ROOT . '/resources/js',
    ROOT . '/app',
];

foreach ($lookDirs as $dir) {
    foreach (walk($dir, ['.blade.php', '.php', '.js']) as $file) {
        $content = (string) file_get_contents($file->getPathname());
        if (! preg_match_all($pattern, $content, $m, PREG_OFFSET_CAPTURE)) { continue; }
        foreach ($m[2] as $i => $cap) {
            $key = (string) $cap[0];
            // unescape \\' \\" \\\\ -> ' " \
            $key = str_replace(['\\\'', '\\"', '\\\\'], ['\'', '"', '\\'], $key);
            if ($key === '' || str_contains($key, "\n")) { continue; }
            // Skip dynamic keys with PHP/Blade interpolation ("values.$x", "values.{$obj->prop}").
            // Statisch nicht auflösbar — Laufzeit-Validierung via Lang::has() bzw. Catalog liegt im Code.
            if (preg_match('/[$\{]/', $key)) { continue; }
            $offset = (int) $m[0][$i][1];
            // Concatenated prefix ("'foo.bar_' . $x") — the literal is a key
            // prefix, not the full key; validated as prefix in the missing pass.
            $matchEnd = $offset + strlen((string) $m[0][$i][0]);
            $isConcat = (bool) preg_match('/^\s*\./', substr($content, $matchEnd, 24));
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            $rel = str_replace(ROOT . '/', '', $file->getPathname());
            $usedKeys[$key][] = ['file' => $rel, 'line' => $line, 'concat' => $isConcat];
        }
    }
}

// ---------- Missing keys ----------

$missing = []; // key => occurrences
foreach ($usedKeys as $key => $occ) {
    // Dotted key with leading module-like segment "alpha[._]" -> check PHP catalog
    $isDotted = (bool) preg_match('/^[a-z][a-z0-9_-]*(?:\.[a-zA-Z0-9_-]+)+$/', $key);
    // Concat prefix with trailing dot ("'scheduler.cadence.' . $x") — fällt
    // sonst durch die isDotted-Regex in den JSON-Zweig; als Prefix prüfen.
    if (! $isDotted
        && preg_match('/^[a-z][a-z0-9_-]*(?:\.[a-zA-Z0-9_-]+)*\.$/', $key)
        && array_filter($occ, static fn ($o) => ! empty($o['concat']))) {
        $resolves = false;
        foreach ($definedPhp as $defined => $_) {
            if (str_starts_with($defined, $key)) {
                $resolves = true;
                break;
            }
        }
        if (! $resolves) { $missing[$key] = $occ; }
        continue;
    }
    if ($isDotted) {
        $exactOcc = array_values(array_filter($occ, fn ($o) => empty($o['concat'])));
        $concatOcc = array_values(array_filter($occ, fn ($o) => ! empty($o['concat'])));
        $bad = [];
        if ($exactOcc && ! isset($definedPhp[$key]) && ! isset($definedPhpPrefix[$key])) {
            // Accept array-parent usage (e.g. trans('access.permission') returns nested array).
            $bad = $exactOcc;
        }
        if ($concatOcc) {
            // Concatenated prefix key: defined if at least one catalog key starts with it.
            $resolves = false;
            foreach ($definedPhp as $defined => $_) {
                if (str_starts_with($defined, $key)) {
                    $resolves = true;
                    break;
                }
            }
            if (! $resolves) { $bad = array_merge($bad, $concatOcc); }
        }
        if ($bad) { $missing[$key] = $bad; }
        continue;
    }
    // Plain text key → JSON
    if (isset($definedJson[$key])) { continue; }
    // If the key itself is identical in DE & EN (e.g. "OK", "PDF", "CSV") and short, ignore.
    if (preg_match('/^[A-Z0-9]{1,4}$/', $key)) { continue; }
    $missing[$key] = $occ;
}

// ---------- PASS B: hardcoded German strings in Blade outside __() ----------

$hardcoded = []; // file => [ [line, snippet], ... ]

$bladeKeywords = ['extends', 'section', 'endsection', 'include', 'if', 'else', 'elseif', 'endif', 'foreach', 'endforeach', 'forelse', 'endforelse', 'for', 'endfor', 'isset', 'endisset', 'empty', 'endempty', 'php', 'endphp', 'yield', 'stack', 'endstack', 'push', 'endpush', 'slot', 'endslot', 'props', 'use', 'endsection', 'can', 'endcan', 'cannot', 'endcannot', 'endwhile', 'while', 'switch', 'case', 'endswitch', 'env', 'json', 'dd', 'dump', 'class', 'endcomponent', 'component', 'livewire'];

foreach (walk(ROOT . '/resources/views', ['.blade.php']) as $file) {
    $rel = str_replace(ROOT . '/', '', $file->getPathname());
    $content = (string) file_get_contents($file->getPathname());

    // Mask translation calls so their contents are ignored.
    $masked = preg_replace_callback(
        '/(?<![\w$])(?:__|trans|@lang)\s*\(\s*([\'"])(?:\\\\.|(?!\1).)*\1[^)]*\)/u',
        fn($m) => str_repeat(' ', strlen($m[0])),
        $content
    ) ?? $content;

    // Mask PHP blocks @php ... @endphp and {{-- comments --}}
    $masked = preg_replace('/\{\{--.*?--\}\}/us', '', $masked) ?? $masked;
    $masked = preg_replace_callback(
        '/@php\b.*?@endphp/us',
        fn($m) => str_repeat(' ', strlen($m[0])),
        $masked
    ) ?? $masked;

    // Mask @props([...]) Blade directive (component prop defaults often contain PHP comments).
    $masked = preg_replace_callback(
        '/@props\s*\(\s*\[.*?\]\s*\)/us',
        fn($m) => str_repeat(' ', strlen($m[0])),
        $masked
    ) ?? $masked;

    // Mask inline PHP open tags (Blade allows raw PHP blocks delimited by open/close tags).
    $masked = preg_replace_callback(
        '/<\?php\b.*?\?>/us',
        fn($m) => str_repeat(' ', strlen($m[0])),
        $masked
    ) ?? $masked;

    // Mask PHP comment lines that leaked through (// ... or /** ... */ blocks at file top).
    $masked = preg_replace('#/\*.*?\*/#us', '', $masked) ?? $masked;

    // Mask HTML attributes that are technical (class, id, name, type, role, aria-*, x-*, wire:*, src, href, route, action, method, target)
    // Strategy: kill the whole attribute value for known attrs.
    $masked = preg_replace(
        '/\b(?:class|id|name|type|role|src|href|action|method|target|for|value|x-[\w-]+|aria-[\w-]+|wire:[\w.:-]+|data-[\w-]+|style|tabindex|alt|placeholder|autocomplete|inputmode|pattern|min|max|step|sizes|loading|decoding|referrerpolicy|rel|crossorigin)\s*=\s*"[^"]*"/u',
        '',
        $masked
    ) ?? $masked;
    $masked = preg_replace(
        "/\\b(?:class|id|name|type|role|src|href|action|method|target|for|value|x-[\\w-]+|aria-[\\w-]+|wire:[\\w.:-]+|data-[\\w-]+|style|tabindex|alt|placeholder|autocomplete|inputmode|pattern|min|max|step|sizes|loading|decoding|referrerpolicy|rel|crossorigin)\\s*=\\s*'[^']*'/u",
        '',
        $masked
    ) ?? $masked;

    // Now scan for German-ish text nodes between '>' and '<' OR in untrusted attribute values like placeholder/title that the user wants translated.
    // Approach: collect text between > and < that contains umlauts OR ≥2 capitalized words OR matches a strong DE word.
    if (preg_match_all('/>([^<]{3,})</u', $masked, $tnodes, PREG_OFFSET_CAPTURE)) {
        foreach ($tnodes[1] as $node) {
            $raw = trim((string) $node[0]);
            if ($raw === '') { continue; }
            // skip pure code-ish content
            if (preg_match('/^[\s\W\d]+$/u', $raw)) { continue; }
            // skip blade output we already removed (becomes spaces) and stray braces
            if (preg_match('/[{}@]/', $raw)) { continue; }
            // skip PHP-code-ish fragments that leaked through (arrow ops, scope ops, $vars, function calls).
            if (preg_match('/->|::|\$[a-zA-Z_]|\([^)]*\)\s*$|=>/u', $raw)) { continue; }
            // skip mehrwortige Markennamen (Eigennamen, wie die Geschwister-Buttons
            // Dropbox/Microsoft/Nextcloud einzeilig — nie zu übersetzen).
            if (in_array($raw, ['Google Drive'], true)) { continue; }
            // keyword filter
            $low = mb_strtolower($raw);
            $isGerman = (bool) preg_match('/[äöüÄÖÜß]/u', $raw)
                || (preg_match_all('/\b[A-ZÄÖÜ][a-zäöüß]+\b/u', $raw, $w) >= 2)
                || (bool) preg_match('/\b(und|oder|nicht|mit|für|von|der|die|das|ein|eine|kein|keine|wird|werden|sind|ist|sein|noch|bereits|aktuell|verfügbar|gespeichert|gelöscht|gespeichert)\b/u', $low);
            if (! $isGerman) { continue; }
            // skip if only ascii alphanumeric short (e.g. "Lorem ipsum dolor")
            $offset = (int) $node[1];
            $line = substr_count(substr($masked, 0, $offset), "\n") + 1;
            $hardcoded[$rel][] = ['line' => $line, 'text' => mb_substr($raw, 0, 140)];
        }
    }
}

// ---------- Render report ----------

$lines = [];
$lines[] = '# Translations Coverage Report';
$lines[] = '';
$lines[] = 'Generated: ' . date('c');
$lines[] = '';
$lines[] = '## A — Used translation keys NOT defined in catalogs';
$lines[] = '';
$lines[] = 'Missing: **' . count($missing) . '**';
$lines[] = '';
if ($missing) {
    ksort($missing);
    foreach ($missing as $key => $occ) {
        $lines[] = '### `' . str_replace('`', "'", $key) . '`';
        foreach ($occ as $o) {
            $lines[] = '- ' . $o['file'] . ':' . $o['line'];
        }
        $lines[] = '';
    }
}

$lines[] = '## B — Hardcoded German text in Blade views (outside __())';
$lines[] = '';
$totalHardcoded = array_sum(array_map('count', $hardcoded));
$lines[] = 'Files with hits: **' . count($hardcoded) . '** — total hits: **' . $totalHardcoded . '**';
$lines[] = '';
if ($hardcoded) {
    ksort($hardcoded);
    foreach ($hardcoded as $file => $entries) {
        $lines[] = '### ' . $file;
        foreach ($entries as $e) {
            $lines[] = '- L' . $e['line'] . ': ' . $e['text'];
        }
        $lines[] = '';
    }
}

file_put_contents(REPORT, implode("\n", $lines));

$problems = count($missing) + $totalHardcoded;
fwrite(STDOUT, sprintf(
    "Coverage report: %s\n  Missing keys: %d\n  Hardcoded DE strings: %d (in %d files)\n",
    REPORT,
    count($missing),
    $totalHardcoded,
    count($hardcoded),
));

exit($problems > 0 ? 1 : 0);
