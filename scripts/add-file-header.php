<?php
/*
 * Adds (or skips if present) a standard file header to all project PHP files.
 * - License: AGPL-3.0-or-later
 * - Created on: first git commit date of the file (fallback: today)
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

$dirs = ['app', 'database', 'tests', 'config', 'routes', 'bootstrap'];

$marker = 'Author       : Daniel Jörg Schuppelius';

$todayDate = date('D M d Y');

function gitCreatedDate(string $relPath, string $fallback): string {
    $cmd = sprintf(
        "git log --diff-filter=A --follow --format='%%ad' --date=format:'%%a %%b %%d %%Y' -- %s 2>/dev/null | tail -1",
        escapeshellarg($relPath)
    );
    $out = trim((string) shell_exec($cmd));
    return $out !== '' ? $out : $fallback;
}

function buildHeader(string $filename, string $date): string {
    return "/*\n"
        . " * Created on   : {$date}\n"
        . " * Author       : Daniel Jörg Schuppelius\n"
        . " * Author Uri   : https://schuppelius.org\n"
        . " * Filename     : {$filename}\n"
        . " * License      : AGPL-3.0-or-later\n"
        . " * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html\n"
        . " */\n";
}

$processed = 0;
$inserted = 0;
$skipped = 0;
$updated = 0;

foreach ($dirs as $dir) {
    $absDir = $root . '/' . $dir;
    if (!is_dir($absDir)) {
        continue;
    }
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $relPath = ltrim(str_replace($root, '', $path), '/');
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $processed++;

        $basename = $file->getBasename();

        if (str_contains($content, $marker)) {
            // Already has our header — ensure license is AGPL.
            $new = preg_replace(
                '/(\* Author Uri   : https:\/\/schuppelius\.org\n \* Filename     : [^\n]+\n \* License      : )[^\n]+(\n \* License Uri  : )[^\n]+/u',
                '$1AGPL-3.0-or-later$2https://www.gnu.org/licenses/agpl-3.0.html',
                $content,
                1
            );
            if ($new !== null && $new !== $content) {
                file_put_contents($path, $new);
                $updated++;
            } else {
                $skipped++;
            }
            continue;
        }

        // Must begin with <?php opener.
        if (!preg_match('/^<\?php\s*\R/', $content, $m)) {
            $skipped++;
            continue;
        }

        $date = gitCreatedDate($relPath, $todayDate);
        $header = buildHeader($basename, $date);

        $opener = $m[0]; // "<?php\n"
        $rest = substr($content, strlen($opener));
        // Ensure exactly one blank line between header and following code.
        $rest = ltrim($rest, "\r\n");
        $new = $opener . $header . "\n" . $rest;
        file_put_contents($path, $new);
        $inserted++;
    }
}

echo "Processed: {$processed}\nInserted: {$inserted}\nUpdated license: {$updated}\nSkipped: {$skipped}\n";
