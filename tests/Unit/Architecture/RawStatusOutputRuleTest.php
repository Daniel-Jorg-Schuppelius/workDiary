<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawStatusOutputRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „kein roher Status-Code in der Oberfläche" (Anlass
 * 2026-09-19: Scheduler-Badge „running" in der deutschen Oberfläche).
 * Die Übersetzungsskripte sehen das nicht — find-untranslated.php überspringt
 * jede Ausgabe mit Variable, lang:coverage prüft nur Literale und Keys. Und
 * Blade gibt ein Backed Enum per `{{ }}` als ->value aus: auch ein sauber
 * gecasteter Status erscheint roh, wenn niemand label() aufruft.
 *
 * Gemeldet werden Status-Spalten (`status`, `state`, `*_status`) als
 * - Text-Echo:        {{ $x->status }}, {{ $x->status->value }}, {{ $row['status'] ?? '—' }}
 * - Übersetzer-Aufruf: __($x->status) — ohne Namensraum findet sich kein Katalogeintrag
 * - Anzeige-Prop:      :label="$x->status", :value="$x->status ?? '—'"
 *
 * Nicht gemeldet: Attributwerte (`data-status="{{ … }}"` ist Maschinenwert)
 * und numerische Spalten wie http_status.
 *
 * Fix: Enum → `->label()`; String-Code → `__('values.' . $code)` (Code in
 * lang/<loc>/values.php ergänzen); Fremdsystem-Code → `Trans::or($key, $code)`.
 */
class RawStatusOutputRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Repo-relativer Pfad → Begründung */
    private const ALLOW_LIST = [
        'resources/views/legacy/' => 'Altoberfläche, eigener Rückbauplan.',
        'resources/views/vendor/' => 'Ansichten eines Fremdpakets (l5-swagger).',
    ];

    /** Spalten mit Zahl statt Code. */
    private const NUMERIC_COLUMNS = ['http_status'];

    public function test_status_codes_are_not_rendered_raw(): void {
        $column = '([a-z_]*_status|status|state)';
        $chain = '\$\w+(?:\??->\w+)*\??->' . $column . '(?:\??->value)?';
        $fallback = '(?:\s*\?\?\s*(?:\'[^\']*\'|"[^"]*"))?';
        $patterns = [
            'echo' => '/\{\{\s*' . $chain . $fallback . '\s*\}\}/',
            'array' => '/\{\{\s*\$\w+\[[\'"]' . $column . '[\'"]\]' . $fallback . '\s*\}\}/',
            'translator' => '/__\(\s*' . $chain . '\s*\)/',
            'prop' => '/\s:(?:label|value|badge|title|text)="' . $chain . $fallback . '"/',
        ];

        $violations = [];
        $files = array_merge($this->bladeFiles(), $this->filesUnder('app/Plugins', '/\.blade\.php$/'));
        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }
            $source = $this->stripBladeComments((string) file_get_contents($file));
            foreach ($patterns as $kind => $pattern) {
                if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
                    continue;
                }
                foreach ($matches as $match) {
                    [$text, $offset] = $match[0];
                    if (in_array($match[1][0], self::NUMERIC_COLUMNS, true)) {
                        continue;
                    }
                    if (in_array($kind, ['echo', 'array'], true) && $this->insideAttribute($source, (int) $offset)) {
                        continue;
                    }
                    $violations[] = sprintf('%s:%d  %s', $relative, $this->lineOf($source, (int) $offset), trim($text));
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Roher Status-Code in der Oberfläche.\n"
            . "Enum → ->label(); String-Code → __('values.' . \$code) (Code in lang/<loc>/values.php ergänzen);\n"
            . "Fremdsystem-Code → \\App\\Support\\Trans::or(\$key, \$code).\n\n"
            . implode("\n", $violations));
    }

    /** `data-status="{{ … }}"`: das Echo steht in einem HTML-Attributwert derselben Zeile. */
    private function insideAttribute(string $source, int $offset): bool {
        $lineStart = strrpos(substr($source, 0, $offset), "\n");
        $before = substr($source, $lineStart === false ? 0 : $lineStart + 1, $offset - ($lineStart === false ? 0 : $lineStart + 1));

        return preg_match('/=\s*"[^"]*$/', $before) === 1;
    }
}
