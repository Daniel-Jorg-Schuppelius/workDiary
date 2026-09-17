<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardComponentRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Karten laufen über <x-card> (MVP-807, Entscheid P11b-08 aus dem Vollscan
 * 2026-09-15).
 *
 * Bis 2026-09-17 standen 98 Karten in 40 Dateien als rohe daisyUI-Karte
 * (`card bg-base-100 shadow-sm` + `card-body`) neben der Komponente — mit
 * eigenem Schatten, eigenem Innenabstand und ohne Rahmen. Der Umbau ist
 * erledigt; dieses Gate hält den Stand:
 *
 *  - Ein Element, dessen Klassen `card` zusammen mit `bg-base-100` oder einem
 *    Schatten (`shadow`, `shadow-sm`) tragen, ist eine handgebaute Karte.
 *
 * Nicht betroffen: getönte Einschübe in Dialogen (`card bg-base-200/40`), die
 * Themen-Vorschau (`card wd-surface`) und die öffentlichen Seiten mit eigenem
 * `.card`-Stil — sie tragen weder `bg-base-100` noch einen Schatten. Druck,
 * PDF und Mails sind ausgenommen (keine Komponenten). Eine bewusste Ausnahme
 * wird mit `raw-card-ok` auf derselben oder der Zeile davor markiert.
 */
final class CardComponentRuleTest extends TestCase {
    private const MARKER = 'raw-card-ok';

    /** Pfade (Regex auf den views-relativen Pfad), die nicht geprüft werden. */
    private const PATH_EXEMPT = [
        '#^components/#',
        '#^vendor/#',
        '#(^|/)pdf/#i',
        '#(^|/)print/#i',
        '#(^|/)[^/]*pdf[^/]*\.blade\.php$#i',
        '#(^|/)[^/]*print[^/]*\.blade\.php$#i',
        '#(^|/)mails?/#',
    ];

    public function test_cards_use_the_card_component(): void {
        $viewsDir = dirname(__DIR__, 3) . '/resources/views';
        $violations = [];

        foreach ($this->bladeFiles($viewsDir) as $file) {
            $rel = str_replace($viewsDir . '/', '', $file->getPathname());
            if ($this->exempt($rel)) {
                continue;
            }

            $src = (string) file_get_contents($file->getPathname());
            $lines = preg_split('/\r?\n/', $src) ?: [];

            // class="…" darf mehrzeilig sein — deshalb auf dem ganzen Quelltext.
            if (! preg_match_all('/\bclass="([^"]*)"/s', $src, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as [$classes, $offset]) {
                $tokens = preg_split('/\s+/', trim($classes)) ?: [];
                if (! in_array('card', $tokens, true)) {
                    continue;
                }
                if (array_intersect($tokens, ['bg-base-100', 'shadow', 'shadow-sm']) === []) {
                    continue;
                }

                $line = substr_count(substr($src, 0, $offset), "\n");
                if (str_contains($lines[$line] ?? '', self::MARKER) || str_contains($lines[$line - 1] ?? '', self::MARKER)) {
                    continue;
                }

                $violations[] = $rel . ':' . ($line + 1) . '  handgebaute Karte — <x-card> nutzen (padding=…, as=…)';
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Karten laufen über <x-card> (siehe Test-Kopf). Bewusste Ausnahmen mit '" . self::MARKER . "' markieren:\n  "
                . implode("\n  ", $violations)
        );
    }

    public function test_rule_recognises_a_raw_card(): void {
        $src = '<div class="card bg-base-100 shadow-sm"><div class="card-body">x</div></div>';

        preg_match_all('/\bclass="([^"]*)"/s', $src, $matches);
        $raw = array_filter($matches[1], static function (string $classes): bool {
            $tokens = preg_split('/\s+/', trim($classes)) ?: [];

            return in_array('card', $tokens, true) && array_intersect($tokens, ['bg-base-100', 'shadow', 'shadow-sm']) !== [];
        });

        $this->assertCount(1, $raw);
    }

    private function exempt(string $rel): bool {
        foreach (self::PATH_EXEMPT as $pattern) {
            if (preg_match($pattern, $rel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function bladeFiles(string $dir): iterable {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file;
            }
        }
    }
}
