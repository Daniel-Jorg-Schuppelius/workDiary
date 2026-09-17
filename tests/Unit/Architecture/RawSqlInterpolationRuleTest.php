<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawSqlInterpolationRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Rohes SQL ohne Interpolation" (Datenfluss-Audit
 * 2026-09-17): `whereRaw`/`orderByRaw`/`selectRaw`/`havingRaw`/`groupByRaw`
 * mit einer Variablen IM SQL-String sind der klassische Einstieg für
 * SQL-Injection. Werte gehören in die Bindungen (`?`), Ausdrücke in Konstanten
 * der Klasse.
 *
 * Der Audit fand keine ausnutzbare Stelle — die Liste hält diesen Stand:
 * Aufgeführt sind ausschließlich Dateien, in denen der eingesetzte Teil ein im
 * Code gebildeter Ausdruck ist (Spaltenname, Datumsfunktion je Treiber), nie
 * eine Eingabe.
 */
class RawSqlInterpolationRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Repo-relativer Pfad → Begründung */
    private const ALLOW_LIST = [
        'app/Http/Controllers/Reporting/MonthByUserTeamReportController.php' => 'Datums-Ausdruck je Treiber, im Code gebildet.',
        'app/Services/Customer/CustomerTrendBuilder.php' => 'Jahres-/Monats-Ausdruck je Treiber, im Code gebildet.',
        'app/Services/Billing/DocumentFeedQuery.php' => 'Bedingung aus einer Klassenkonstante.',
        'app/Services/Integration/Match/ExactField.php' => 'Spaltenname aus der Profilkonfiguration, Wert als Bindung.',
        'app/Services/Integration/Match/CompositeField.php' => 'Spaltenname aus der Profilkonfiguration, Wert als Bindung.',
        'app/Services/TimeAccount/TimeAccountPostingService.php' => 'Jahres-/Monats-Ausdruck je Treiber, im Code gebildet.',
        'app/Services/Privacy/SubjectData/AbstractSubjectSection.php' => 'Datumsspalte des Abschnitts, im Code gesetzt.',
        'app/Services/Reporting/CustomerAnalysisReportBuilder.php' => 'Aggregat-Ausdruck aus einer lokalen Konstante, Werte als Bindung.',
    ];

    public function test_raw_sql_does_not_interpolate_variables(): void {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripComments((string) file_get_contents($file));
            // `$` ohne Namen (etwa der JSON-Pfad '$.feld') interpoliert PHP nicht.
        $pattern = '/(?:whereRaw|orWhereRaw|orderByRaw|selectRaw|havingRaw|groupByRaw)\s*\(\s*(?:"[^"]*\$[A-Za-z_{]|\'[^\']*\'\s*\.\s*\$)/';
            if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[0] as [$match, $offset]) {
                $violations[] = sprintf('%s:%d — %s…', $relative, $this->lineOf($source, (int) $offset), trim($match));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Variable im rohen SQL-String.\n"
            . "Werte als Bindung (?) übergeben; unvermeidbare Ausdrücke aus Konstanten bilden und die Datei mit Begründung aufnehmen.\n\n"
            . implode("\n", $violations));
    }
}
