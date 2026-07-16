<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : find-untranslated.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Scans Blade templates and JavaScript files for human-readable string
 * literals that are NOT wrapped in __() / trans() / @lang / window.__().
 * Produces a Markdown report at storage/reports/untranslated.md and exits
 * with a non-zero code when any non-whitelisted hits remain.
 *
 * Usage: php scripts/find-untranslated.php
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';

$targets = [
    'blade' => ROOT . '/resources/views',
    'js' => ROOT . '/resources/js',
];

$reportPath = ROOT . '/storage/reports/untranslated.md';
@mkdir(dirname($reportPath), 0775, true);

/**
 * Strings that are intentionally not translated (asset names, technical
 * tokens, debug strings, CSS classes that happen to look like text, …).
 * Anything matching one of these regular expressions is skipped.
 *
 * @var list<string>
 */
$whitelist = [
    '/^[\s\W\d]*$/u',                 // punctuation / numbers only
    '/^[A-Za-z0-9_\-\/.]+\.(?:js|css|png|jpg|jpeg|svg|gif|webp|pdf|html?)$/i',
    '/^https?:\/\//i',
    '/^[A-Z_]{2,}$/',                 // CONST_LIKE
    '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_.]*$/i', // dotted identifiers (route names, lang keys, …)
    '/^#[0-9a-f]{3,8}$/i',            // colour codes
    '/^@[a-z][\w-]*$/i',              // blade directives leaking into strings
    '/^\s*[<>{}();,.?:!=+\-*\/]+\s*$/', // operator-only
    '/^Google Drive$/',               // Markenname (wie Geschwister-Buttons Dropbox/Microsoft/Nextcloud)
];

/**
 * @return iterable<SplFileInfo>
 */
function walk(string $dir, string $ext): iterable {
    if (! is_dir($dir)) {
        return;
    }
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && str_ends_with($file->getFilename(), $ext)) {
            yield $file;
        }
    }
}

function isWhitelisted(string $value, array $patterns): bool {
    foreach ($patterns as $p) {
        if (preg_match($p, $value)) {
            return true;
        }
    }

    return false;
}

/**
 * Returns true if the literal at $offset inside $content already sits
 * within a translation call (looking back a short window).
 */
function isAlreadyTranslated(string $content, int $offset): bool {
    $window = substr($content, max(0, $offset - 30), 30);

    return (bool) preg_match('/(?:__|trans|@lang|window\.__|\\bt)\s*\(\s*$/', $window);
}

/** @var array<string, list<array{file:string, line:int, value:string}>> $hits */
$hits = ['blade' => [], 'js' => []];

// --- Blade scan: look for {{ "string" }} or attributes like title="text" outside __()/@lang
foreach (walk($targets['blade'], '.blade.php') as $file) {
    $content = (string) file_get_contents($file->getPathname());
    // Match raw German/English content between blade tags, but skip already-translated calls.
    if (preg_match_all('/[\'"]([\p{Lu}\p{Ll}][\p{L}\p{N}\s\.,!?\'\-äöüÄÖÜß]{3,})[\'"]/u', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $match) {
            $value = (string) $match[0];
            $offset = (int) $match[1];
            if (isWhitelisted($value, $whitelist)) {
                continue;
            }
            if (isAlreadyTranslated($content, $offset)) {
                continue;
            }
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            $hits['blade'][] = [
                'file' => str_replace(ROOT . '/', '', $file->getPathname()),
                'line' => $line,
                'value' => $value,
            ];
        }
    }
}

// --- JS scan
foreach (walk($targets['js'], '.js') as $file) {
    $content = (string) file_get_contents($file->getPathname());
    if (preg_match_all('/[\'"`]([A-Za-zäöüÄÖÜß][\p{L}\p{N}\s\.,!?\'\-äöüÄÖÜß]{4,})[\'"`]/u', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $match) {
            $value = (string) $match[0];
            $offset = (int) $match[1];
            if (isWhitelisted($value, $whitelist)) {
                continue;
            }
            if (isAlreadyTranslated($content, $offset)) {
                continue;
            }
            // Skip comments – cheap heuristic: line starts with //
            $lineStart = (int) strrpos(substr($content, 0, $offset), "\n") + 1;
            $lineText = substr($content, $lineStart, ($offset - $lineStart) + 1);
            if (preg_match('#^\s*//#', $lineText)) {
                continue;
            }
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            $hits['js'][] = [
                'file' => str_replace(ROOT . '/', '', $file->getPathname()),
                'line' => $line,
                'value' => $value,
            ];
        }
    }
}

// --- Render report
$total = count($hits['blade']) + count($hits['js']);
$lines = [];
$lines[] = '# Untranslated strings report';
$lines[] = '';
$lines[] = 'Generated: ' . date('c');
$lines[] = '';
$lines[] = '- Blade hits: ' . count($hits['blade']);
$lines[] = '- JS hits:    ' . count($hits['js']);
$lines[] = '';

foreach ($hits as $bucket => $entries) {
    if (! $entries) {
        continue;
    }
    $lines[] = '## ' . strtoupper($bucket);
    $lines[] = '';
    foreach ($entries as $h) {
        $lines[] = sprintf('- `%s:%d` — `%s`', $h['file'], $h['line'], str_replace('`', "'", $h['value']));
    }
    $lines[] = '';
}

file_put_contents($reportPath, implode("\n", $lines));

fwrite(STDOUT, sprintf("Report written to %s (%d hits)\n", $reportPath, $total));

exit($total > 0 ? 1 : 0);
