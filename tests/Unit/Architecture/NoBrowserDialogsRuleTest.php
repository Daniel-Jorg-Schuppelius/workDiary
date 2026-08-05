<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NoBrowserDialogsRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Erzwingt AGENTS.md §4.7: keine nativen Browser-Dialoge (alert()/confirm()/
 * prompt(), auch window.*) im Frontend — stattdessen window.notifyAction() /
 * window.confirmAction().
 *
 * Native Dialoge blockieren den Main-Thread, ignorieren das Corporate Design und
 * lassen sich nicht lokalisieren. Bewusste Ausnahmen (Fallbacks, wenn die
 * Notify-Infrastruktur noch nicht im DOM ist) werden pro Zeile mit dem Marker
 * `browser-dialog-ok` freigegeben — analog zur trustedServerHtml-Begründung.
 */
class NoBrowserDialogsRuleTest extends TestCase {
    private const MARKER = 'browser-dialog-ok';

    /** Native Dialog-Aufrufe: bare `alert(` sowie `window.alert(` (nicht `foo.prompt(`). */
    private const PATTERN = '/(?:\bwindow\s*\.\s*(?:alert|confirm|prompt)\s*\()|(?<![\w.])(?:alert|confirm|prompt)\s*\(/';

    public function test_no_native_browser_dialogs_in_frontend(): void {
        $root = dirname(__DIR__, 3);
        $violations = [];

        foreach (['resources/js', 'resources/views'] as $relDir) {
            $dir = $root . '/' . $relDir;
            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->sourceFiles($dir) as $file) {
                $violations = array_merge($violations, $this->scan($root, $file));
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Native Browser-Dialoge gefunden (AGENTS.md §4.7 — window.notifyAction()/window.confirmAction() nutzen).\n"
                . "Bewusste Fallbacks pro Zeile mit '" . self::MARKER . "' markieren:\n  "
                . implode("\n  ", $violations)
        );
    }

    /**
     * @return list<string>
     */
    private function scan(string $root, SplFileInfo $file): array {
        $lines = preg_split('/\r?\n/', (string) file_get_contents($file->getPathname())) ?: [];
        $rel = str_replace($root . '/', '', $file->getPathname());
        $hits = [];

        foreach ($lines as $i => $line) {
            if (! preg_match(self::PATTERN, $line)) {
                continue;
            }
            // Freigabe auf der Trefferzeile selbst oder der Kommentarzeile davor.
            if (str_contains($line, self::MARKER) || str_contains($lines[$i - 1] ?? '', self::MARKER)) {
                continue;
            }
            $hits[] = $rel . ':' . ($i + 1) . '  ' . trim($line);
        }

        return $hits;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function sourceFiles(string $dir): iterable {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (str_ends_with($name, '.js') || str_ends_with($name, '.blade.php')) {
                yield $file;
            }
        }
    }
}
