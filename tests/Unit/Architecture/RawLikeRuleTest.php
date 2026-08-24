<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawLikeRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate gegen rohe LIKE-Muster mit Variablenanteil (Vollscan
 * 2026-08-23, B14/D6/E9): Die whereLikeEscaped-Pflicht (Whitebox 2026-07,
 * Runde 3) hatte kein Gate — MVP-601 und W4c brachten fünf bzw. vier neue
 * `->where('…', 'like', "%{$search}%")` mit unescaptem Nutzer-Input.
 *
 * Regel: Das dritte Argument eines 'like'/'LIKE'-Where darf keine Variable
 * (`$…`) enthalten. Konstante Muster ('support.%', 'demo+%@…') bleiben
 * erlaubt; Suchbegriffe laufen über das Macro whereLikeEscaped()/
 * orWhereLikeEscaped() (AppServiceProvider).
 */
class RawLikeRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Bewusst belassene Variablen-Muster: Pfad → Begründung.
     *
     * @var array<string, string>
     */
    private const ALLOW_LIST = [
        // $marker = 'RET#' . $original->id — interne Kennung, kein Nutzer-Input.
        'app/Services/Finance/ReconciliationService.php' => 'Retouren-Marker aus der Beleg-ID, kein Nutzer-Input.',
        // $datePatterns entstehen aus geparsten Datumsangaben (Y-m-d-Fragmente).
        'app/Services/Billing/DocumentFeedQuery.php' => 'Datumsmuster aus Carbon-Parsing, kein roher Suchbegriff.',
    ];

    public function test_like_patterns_with_variables_use_the_escaped_macro(): void {
        $violations = [];

        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripComments((string) file_get_contents($file));

            // ->where('col', 'like', <arg>) / ->orWhere(...) — <arg> bis zum
            // schließenden Klammerpaar der Argumentliste (eine Zeile reicht,
            // mehrzeilige Aufrufe werden über den Rest der Zeile erfasst).
            if (preg_match_all('/->(?:or)?[wW]here\s*\(\s*[^,]+,\s*[\'"](?:like|LIKE|ilike|ILIKE)[\'"]\s*,\s*([^\n]*)/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[1] as [$argument, $offset]) {
                if (str_contains($argument, '$')) {
                    $violations[] = sprintf('%s:%d — %s', $relative, $this->lineOf($source, (int) $offset), trim($argument));
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Rohes LIKE mit Variablenanteil gefunden — whereLikeEscaped()/orWhereLikeEscaped() nutzen\n"
            . "(Macro in AppServiceProvider; escaped %, _ und ! im Suchbegriff).\n"
            . "Konstante Muster bleiben erlaubt; bewusste Ausnahmen mit Begründung in ALLOW_LIST.\n\n"
            . implode("\n", $violations));
    }
}
