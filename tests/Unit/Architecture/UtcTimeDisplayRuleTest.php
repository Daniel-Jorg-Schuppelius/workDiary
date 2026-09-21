<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UtcTimeDisplayRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „UTC-Uhrzeit in der Anzeige" (UI-Fuzz 2026-09-21): Zeitpunkte
 * liegen in UTC; `$x->…_at->format('… H:i')` (ebenso `translatedFormat`, `isoFormat('L LT')`)
 * ohne `orgTz()` zeigte sie um den Zeitzonenversatz verschoben. Anzeige über
 * `->orgTz()`, `->fdatetime()` oder `->ftime()`.
 */
class UtcTimeDisplayRuleTest extends TestCase {
    use ScansSourceTree;

    private const PATTERN = '~(?:->\w+_at\??|optional\(\$[^)]*_at\))->(?:(?:format|translatedFormat)\(\s*[\'"][^\'"]*H:i|isoFormat\(\s*[\'"][^\'"]*(?:LT|LLL|H:mm|HH:mm))~';

    /** @var array<string, string> Pfad-Präfix → Begründung */
    private const ALLOW_LIST = [
        // Fahrtenbuch bleibt Ortszeit (GoBD-Aufzeichnung, Entscheidung MVP-823).
        'resources/views/travel-logs/_form_body.blade.php' => 'Fahrtenbuch führt Ortszeit',
    ];

    public function test_views_show_utc_times_in_local_time(): void {
        $violations = [];

        foreach ([...$this->bladeFiles(), ...$this->bladeFiles('app/Plugins')] as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match_all(self::PATTERN, $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [$match, $offset]) {
                    $violations[] = sprintf('%s:%d %s', $relative, $this->lineOf($source, (int) $offset), $match);
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "UTC-Zeitpunkt ohne Umrechnung angezeigt — `->orgTz()->format(…)`, `->fdatetime()` oder `->ftime()` verwenden.\n"
            . "Spalten, die bewusst Ortszeit speichern, begründet in die ALLOW_LIST.\n\n"
            . implode("\n", $violations));
    }
}
