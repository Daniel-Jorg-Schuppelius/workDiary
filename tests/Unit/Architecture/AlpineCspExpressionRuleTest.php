<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AlpineCspExpressionRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für den Alpine-CSP-Build (Vollscan 2026-08-23, I1): Der
 * Sandbox-Evaluator von @alpinejs/csp kennt keine Arrow-Funktionen, kein
 * Nullish-Coalescing, kein Optional-Chaining und keine Template-Literale —
 * solche Ausdrücke scheitern still (abhängige Selects in disposal/_form_dialog
 * und domain/show blieben leer). Logik gehört in Alpine.data()-Komponenten
 * (resources/js/alpine/components.js), die Direktive ruft nur Methoden auf.
 *
 * Geprüft werden die Werte von x-*-, @*- und :*-Attributen.
 */
class AlpineCspExpressionRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> */
    private const ALLOW_LIST = [
        'resources/views/legacy/' => 'Legacy-Modul, wird abgelöst.',
        'resources/views/vendor/' => 'Fremd-Views.',
    ];

    /** @var list<array{0: string, 1: string}> Muster → Beschreibung */
    private const BANNED = [
        ['/=>/', 'Arrow-Funktion (=>)'],
        ['/\?\?/', 'Nullish-Coalescing (??)'],
        ['/\?\./', 'Optional-Chaining (?.)'],
        ['/`/', 'Template-Literal (`)'],
    ];

    public function test_alpine_directives_contain_only_sandbox_safe_expressions(): void {
        $violations = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));

            // Attributwerte von x-data/x-init/x-show/x-for/…, @click/… (double-
            // oder single-quoted). Blade-Ausdrücke {{ … }}/@js(…)/@json(…) darin
            // werden vorher neutralisiert — sie sind PHP, nicht Alpine.
            $neutralized = $this->neutralizeBladePhp($source);

            if (preg_match_all('/\s(x-[a-z:.-]+|@[a-z:.-]+|:[a-z][a-z0-9:.-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $neutralized, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                $attribute = $match[1][0];
                $value = $match[2][0] !== '' ? $match[2][0] : ($match[3][0] ?? '');
                // Blade-Komponenten-Props (:label="__('…')") sind PHP — nur
                // echte Alpine-Bindings prüfen: x-*, @*, und :* in x-data-Nähe
                // lässt sich nicht zuverlässig trennen, daher nur x-*/@*.
                if ($attribute[0] === ':') {
                    continue;
                }
                foreach (self::BANNED as [$pattern, $label]) {
                    if (preg_match($pattern, $value) === 1) {
                        $violations[] = sprintf('%s:%d — %s enthält %s', $relative, $this->lineOf($neutralized, (int) $match[0][1]), $attribute, $label);
                    }
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Alpine-Ausdruck, den der CSP-Sandbox-Evaluator nicht kennt.\n"
            . "Logik in eine Alpine.data()-Komponente verschieben und in der Direktive nur Methoden aufrufen.\n\n"
            . implode("\n", $violations));
    }

    /**
     * Ersetzt {{ … }} sowie @js(…)/@json(…) (balancierte Klammern) durch
     * Leerzeichen — Zeilenumbrüche bleiben, damit Zeilennummern stimmen.
     */
    private function neutralizeBladePhp(string $source): string {
        $out = '';
        $length = strlen($source);
        $i = 0;
        while ($i < $length) {
            if (substr($source, $i, 2) === '{{') {
                $end = strpos($source, '}}', $i + 2);
                $end = $end === false ? $length : $end + 2;
                $out .= preg_replace('/[^\n]/', ' ', substr($source, $i, $end - $i));
                $i = $end;
                continue;
            }
            if (preg_match('/\G@(?:js|json)\s*\(/', $source, $m, 0, $i) === 1) {
                $depth = 0;
                $j = $i + strlen($m[0]) - 1;
                for (; $j < $length; $j++) {
                    if ($source[$j] === '(') {
                        $depth++;
                    } elseif ($source[$j] === ')') {
                        $depth--;
                        if ($depth === 0) {
                            $j++;
                            break;
                        }
                    }
                }
                $out .= preg_replace('/[^\n]/', ' ', substr($source, $i, $j - $i));
                $i = $j;
                continue;
            }
            $out .= $source[$i];
            $i++;
        }

        return $out;
    }
}
