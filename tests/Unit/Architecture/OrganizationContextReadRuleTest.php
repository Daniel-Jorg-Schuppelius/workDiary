<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationContextReadRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Org-Kontext nie blind lesen" (Mandanten-Review 2026-09-13):
 * `app('currentOrganization')` ist eine Laufzeit-Bindung, keine garantierte
 * Konstante. Fehlt sie, wirft der Container „Target class
 * [currentOrganization] does not exist" — in Konsole, Queue und Tests der
 * Normalfall. Im HTTP-Stack ist die Bindung seit dem Fail-closed-Umbau von
 * {@see \App\Http\Middleware\SetOrganizationContext} zwar garantiert, aber
 * dieselben Dienste laufen auch aus Befehlen und Jobs heraus.
 *
 * Regel: Jede Lesung steht zusammen mit einer Absicherung — `bound()`-Prüfung,
 * `instanceof Organization` oder ein Helfer, der selbst abbricht
 * ({@see \App\Support\OrganizationContext::current()},
 * {@see \App\Http\Controllers\Concerns\ResolvesCurrentOrganization}).
 */
class OrganizationContextReadRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung */
    private const ALLOW_LIST = [
        // Die kanonischen Leser selbst — sie SIND die Absicherung.
        'app/Support/OrganizationContext.php' => 'Kanonischer Leser (current/currentId).',
        'app/Http/Controllers/Concerns/ResolvesCurrentOrganization.php' => 'Kanonischer Controller-Helfer (abort 403).',
    ];

    /** Zeilen um die Lesung herum, in denen die Absicherung stehen darf. */
    private const WINDOW = 6;

    public function test_the_organization_context_is_never_read_unguarded(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }
            $lines = explode("\n", $this->stripComments((string) file_get_contents($file)));
            foreach ($lines as $index => $line) {
                if (! str_contains($line, "app('currentOrganization')")) {
                    continue;
                }
                $window = implode("\n", array_slice($lines, max(0, $index - self::WINDOW), self::WINDOW * 2 + 1));
                if (preg_match("/bound\('currentOrganization'\)|instanceof Organization/", $window) === 1) {
                    continue;
                }
                $violations[] = sprintf('%s:%d — %s', $relative, $index + 1, trim($line));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Org-Kontext ohne Absicherung gelesen — OrganizationContext::current()/currentId(),\n"
            . "ResolvesCurrentOrganization::currentOrganization() (bricht mit 403 ab) oder eine eigene bound()-/instanceof-Prüfung nutzen.\n\n"
            . implode("\n", $violations));
    }
}
