<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExceptionMessageMatchRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Exception-Klassen statt Meldungstexte" (Sweep 2026-09-13):
 * Fehlerfälle werden an der Exception-Klasse oder an typisierten Feldern
 * (`errorInfo`, `status()`) unterschieden, nie an Teilstrings der Meldung.
 * Meldungstexte sind Prosa — sie ändern sich mit Übersetzung, Toolkit-Release
 * oder Treiber und brechen dann still: „unique" stand in der SQLite-Meldung,
 * unter MariaDB heißt der Index `…_uq`; „file" traf auch „file fetch failed:
 * 401". Duplikate → UniqueConstraintViolationException, Toolkit-Limits →
 * DocumentLimitExceededException, Fachzustände → eigene Klasse.
 */
class ExceptionMessageMatchRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung */
    private const ALLOW_LIST = [];

    /** Textfunktion, in deren Argumenten (bis zwei Klammerebenen tief) eine getMessage()-Rückgabe steckt. */
    private const PATTERN_FUNCTION = '/\b(?:str_contains|str_starts_with|str_ends_with|stripos|strpos|mb_stripos|mb_strpos|preg_match|in_array|Str::(?:contains|containsAll|startsWith|endsWith|is|isMatch))\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*?getMessage\(\)/';

    /** Direkter Vergleich einer Meldung mit einem String-Literal, auch in Yoda-Schreibweise. */
    private const PATTERN_COMPARE = '/getMessage\(\)\s*(?:===|!==|==|!=)\s*[\'"]|[\'"]\s*(?:===|!==|==|!=)\s*\$\w+(?:->\w+(?:\(\))?)*->getMessage\(\)/';

    public function test_error_handling_reacts_to_exception_classes_not_message_texts(): void {
        $files = [
            ...array_map(static fn (string $file): array => [$file, false], $this->phpFiles('app')),
            ...array_map(static fn (string $file): array => [$file, true], $this->bladeFiles()),
        ];

        $violations = [];
        foreach ($files as [$file, $isBlade]) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }
            $source = (string) file_get_contents($file);
            $source = $isBlade ? $this->stripBladeComments($source) : $this->stripComments($source);
            foreach ([self::PATTERN_FUNCTION, self::PATTERN_COMPARE] as $pattern) {
                if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) < 1) {
                    continue;
                }
                foreach ($matches[0] as [$snippet, $offset]) {
                    $violations[] = sprintf('%s:%d — %s', $relative, $this->lineOf($source, (int) $offset), trim((string) preg_replace('/\s+/', ' ', $snippet)));
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Fehlerfall am Meldungstext erkannt — auf die Exception-Klasse reagieren (UniqueConstraintViolationException,\n"
            . "DocumentLimitExceededException, eigene Fachexception) oder auf typisierte Felder (errorInfo, status());\n"
            . "begründete Ausnahmen in die ALLOW_LIST.\n\n" . implode("\n", $violations));
    }
}
