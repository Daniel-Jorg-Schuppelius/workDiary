#!/usr/bin/env php
<?php
/*
 * find-legacy-usage-in-new.php
 *
 * Audit-Script (Read-only). Sucht jede Referenz auf App\Legacy\* außerhalb der
 * legitimen Bridge-Stellen und meldet sie als Markdown-Tabelle. Wird in der
 * Phase-4-Bereinigung verwendet, um zu prüfen, dass keine Legacy-Daten mehr in
 * den neuen Bereich lecken.
 *
 * Aufruf: php scripts/find-legacy-usage-in-new.php
 *         php scripts/find-legacy-usage-in-new.php --json   (JSON statt MD)
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$scanDirs = [
    'app/Http/Controllers',
    'app/Http/Middleware',
    'app/Models',
    'app/Services',
    'app/Listeners',
    'app/Observers',
    'app/Policies',
    'resources/views',
];

// Pfade/Glob-Muster, die explizit erlaubte Bridge-Stellen darstellen.
$allowList = [
    '#^app/Legacy/#',
    '#^resources/views/legacy/#',
    '#^resources/views/components/legacy-week-grid\.blade\.php$#',
    // Layout zeigt Legacy-Nav für isLegacyMode-User.
    '#^resources/views/layouts/app\.blade\.php$#',
    // Bewusste Migrations-/Bridge-Controller.
    '#^app/Http/Controllers/Admin/LegacyMigration#',
];

// Symbole, die überall erlaubt sind: die LegacyBridge ist der einzige
// vorgesehene Legacy-Einstiegspunkt für neuen Code.
$allowedSymbols = [
    '#^App\\\\Legacy\\\\LegacyBridge$#',
];

// Datei-spezifisch zusätzlich erlaubte Symbole (Rückgabetypen von
// Bridge-Delegationen, die den konkreten Legacy-Typ deklarieren müssen).
$allowedSymbolsPerFile = [
    // User::legacyUser() deklariert den Rückgabetyp der Bridge-Delegation.
    '#^app/Models/User\.php$#' => ['#^App\\\\Legacy\\\\Models\\\\LegacyUser$#'],
];

$pattern = '/App\\\\Legacy\\\\[A-Za-z0-9_\\\\]+/';
$hits = [];

$iter = function (string $dir) use ($root, $pattern, $allowList, $allowedSymbols, $allowedSymbolsPerFile, &$hits): void {
    $abs = $root . '/' . $dir;
    if (! is_dir($abs)) {
        return;
    }
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (! in_array($ext, ['php'], true) && ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $rel = ltrim(str_replace($root, '', $file->getPathname()), '/');

        foreach ($allowList as $rx) {
            if (preg_match($rx, $rel)) {
                continue 2;
            }
        }

        $content = @file_get_contents($file->getPathname());
        if ($content === false) {
            continue;
        }

        if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        $fileSymbolRules = $allowedSymbols;
        foreach ($allowedSymbolsPerFile as $fileRx => $symbolRules) {
            if (preg_match($fileRx, $rel)) {
                $fileSymbolRules = array_merge($fileSymbolRules, $symbolRules);
            }
        }

        foreach ($matches[0] as [$symbol, $offset]) {
            foreach ($fileSymbolRules as $rx) {
                if (preg_match($rx, $symbol)) {
                    continue 2;
                }
            }

            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            $hits[] = ['file' => $rel, 'line' => $line, 'symbol' => $symbol];
        }
    }
};

foreach ($scanDirs as $d) {
    $iter($d);
}

$asJson = in_array('--json', $argv, true);

if ($asJson) {
    fwrite(STDOUT, json_encode($hits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($hits === [] ? 0 : 1);
}

echo '# Legacy-Nutzung im neuen Bereich' . PHP_EOL . PHP_EOL;

if ($hits === []) {
    echo 'Keine Treffer — sauber.' . PHP_EOL;
    exit(0);
}

echo '| Datei | Zeile | Symbol |' . PHP_EOL;
echo '|-------|------:|--------|' . PHP_EOL;
foreach ($hits as $hit) {
    printf('| %s | %d | `%s` |%s', $hit['file'], $hit['line'], $hit['symbol'], PHP_EOL);
}

echo PHP_EOL . sprintf('Treffer: %d', count($hits)) . PHP_EOL;
exit(1);
