<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UrlSinkRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „URL-Senken im Frontend" (Datenfluss-Audit 2026-09-17,
 * `url-1`/`openredirect-1`, CodeQL `js/xss-through-dom`): Jede Navigation und
 * jedes Formularziel, dessen Wert aus dem DOM stammt, läuft über
 * `sameOriginPath()` aus `resources/js/lib/html.js`. Die Funktion liefert
 * einen Pfad der eigenen Herkunft oder `null` — damit sind `javascript:`,
 * `data:` und fremde Hosts ausgeschlossen, und das CSRF-Token verlässt die
 * eigene Anwendung nicht.
 *
 * Geprüft werden `window.location[.href] =`, `location.assign()/replace()`
 * und `form.action =`. Ein Treffer gilt als sauber, wenn in denselben zehn
 * Zeilen davor `sameOriginPath(` steht.
 */
class UrlSinkRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad-Präfix → Begründung */
    private const ALLOW_LIST = [
        'resources/js/lib/html.js' => 'Definiert sameOriginPath() selbst.',
        'resources/js/layout.js' => 'Folgt der href-Eigenschaft eines Ankers der Seite nach dem Bestätigungsdialog — dieselbe Navigation, die der Klick ohnehin ausgelöst hätte.',
        'resources/js/sw.js' => 'Service-Worker ohne DOM-Kontext.',
    ];

    private const WINDOW = 10;

    public function test_navigation_targets_pass_through_same_origin_path(): void {
        $violations = [];

        foreach ($this->filesUnder('resources/js', '/\.(?:js|mjs)$/') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripComments((string) file_get_contents($file));
            $lines = explode("\n", $source);

            foreach ($lines as $index => $line) {
                if (preg_match('/window\.location(?:\.href)?\s*=|(?<![.\w])location\.(?:assign|replace)\s*\(|\.action\s*=/', $line) !== 1) {
                    continue;
                }

                $from = max(0, $index - self::WINDOW);
                $context = implode("\n", array_slice($lines, $from, $index - $from + 1));
                if (str_contains($context, 'sameOriginPath(')) {
                    continue;
                }

                $violations[] = sprintf('%s:%d — %s', $relative, $index + 1, trim($line));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "URL-Senke ohne Herkunftsprüfung.\n"
            . "Ziel über sameOriginPath() aus resources/js/lib/html.js führen und bei null nicht navigieren.\n\n"
            . implode("\n", $violations));
    }
}
