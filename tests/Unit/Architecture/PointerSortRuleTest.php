<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PointerSortRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Sortieren und Ziehen per Zeiger" (Konsolidierungs-Audit
 * 2026-10, k4-16): Listen werden über `resources/js/lib/pointer-sort.js`
 * sortiert, und auch der Zug auf ein Ziel (Kanban-Spalte, Projekt der
 * Schnellbuchung, Zelle im Dienstplan) läuft darüber. HTML5-Drag-and-drop
 * (`draggable="true"` + `dragstart`) kennt weder Touch noch Stift — mobile
 * Browser lösen `dragstart` nicht aus —, und jedes Modul brachte dafür eigene
 * Marker, eigenen Abbruch und eigene Einfügeregeln mit.
 *
 * Regel ohne Ausnahmen: kein `dragstart` und kein eingeschaltetes `draggable`
 * (Attribut, Bindung, Eigenschaft) in Views und Frontend-Modulen.
 * `draggable="false"` (natives Link-Ziehen abschalten) und reine Drop-Ziele
 * für Dateien (`dragover`/`drop`) bleiben erlaubt.
 */
class PointerSortRuleTest extends TestCase {
    use ScansSourceTree;

    /** `dragstart` sowie jedes `draggable`, das nicht ausdrücklich abschaltet; possessiv, damit `"false"` nicht über das Anführungszeichen durchrutscht. */
    private const VIEW_PATTERN = '/\bdragstart\b|(?<![\w-])draggable\s*+=\s*+["\']?+(?!false\b)/i';

    private const JS_PATTERN = '/\bdragstart\b|(?<![\w-])draggable\s*+=(?!=)\s*+["\']?+(?!false\b)|setAttribute\(\s*["\']draggable["\']\s*,\s*+(?!["\']false\b)/';

    public function test_lists_are_sorted_with_pointer_events_not_html5_drag_and_drop(): void {
        $violations = [];

        $sources = [];
        foreach ($this->filesUnder('resources/js', '/\.(?:js|mjs)$/') as $file) {
            $sources[$file] = [self::JS_PATTERN, fn (string $source): string => $this->stripComments($source)];
        }
        foreach ($this->bladeFiles() as $file) {
            $sources[$file] = [self::VIEW_PATTERN, fn (string $source): string => $this->stripBladeComments($source)];
        }

        foreach ($sources as $file => [$pattern, $strip]) {
            $source = $strip((string) file_get_contents($file));
            if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[0] as [$match, $offset]) {
                $violations[] = sprintf('%s:%d — %s', $this->relativePath($file), $this->lineOf($source, (int) $offset), trim($match));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "HTML5-Drag-and-drop — geht weder mit Finger noch mit Stift.\n"
            . "Sortieren und Ziehen über pointerSort() aus resources/js/lib/pointer-sort.js verdrahten\n"
            . "(Griff mit Klasse touch-none; Zug auf ein Ziel: mode \"mark\" mit target).\n\n"
            . implode("\n", $violations));
    }
}
