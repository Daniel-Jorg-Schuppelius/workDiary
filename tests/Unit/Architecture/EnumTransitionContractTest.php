<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnumTransitionContractTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Enums\Contracts\HasStatusTransitions;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Statusmaschinen-Vertrag" (Vollscan 2026-08-23, B5):
 * Seit M44 gibt es HasStatusTransitions + die Guards AssertsStatusTransition/
 * AssertsValidatedTransition — Neucode baute trotzdem wieder eigene Enums und
 * wortgleiche Guard-Kopien. Zwei Regeln:
 *
 * 1. Ein Enum mit `allowedTransitions()` implementiert das Interface (eine
 *    AKTIONS-Liste — list<string> — heißt stattdessen `allowedActions()`,
 *    Muster ProtocolStatus/OpenIssueStatus).
 * 2. Kein Service prüft `allowedTransitions()` inline — der Guard-Trait ist
 *    die eine Stelle für Meldung und Semantik.
 * 3. Übergangstabellen und -prüfer stehen nur im Enum (MVP-872): außerhalb
 *    `app/Enums` und der Guard-Traits keine Methode `canTransition`,
 *    `transitionTo`, `assertTransition`, `isTransitionAllowed`,
 *    `allowedTransitions` und keine Konstante `*TRANSITIONS`.
 */
class EnumTransitionContractTest extends TestCase {
    use ScansSourceTree;

    public function test_enums_with_transitions_implement_the_contract(): void {
        $violations = [];
        foreach ($this->phpFiles('app/Enums') as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/^enum\s+(\w+)/m', $source, $m) !== 1 || ! str_contains($source, 'function allowedTransitions(')) {
                continue;
            }
            $relative = $this->relativePath($file);
            /** @var class-string $class */
            $class = 'App\\' . str_replace('/', '\\', substr($relative, strlen('app/'), -strlen('.php')));
            if (! enum_exists($class) || is_subclass_of($class, HasStatusTransitions::class)) {
                continue;
            }
            $violations[] = $relative;
        }

        $this->assertSame([], $violations, "Enum mit allowedTransitions() ohne HasStatusTransitions-Interface.\n"
            . "Status→Status: Interface implementieren. Aktions-Liste (list<string>): Methode in allowedActions() umbenennen.\n\n" . implode("\n", $violations));
    }

    public function test_services_use_the_guard_traits_instead_of_inline_checks(): void {
        $violations = [];
        foreach ($this->phpFiles('app/Services') as $file) {
            $relative = $this->relativePath($file);
            if (str_contains($relative, '/Concerns/')) {
                continue; // die Guard-Traits selbst
            }
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match('/in_array\([^,]+,\s*[^,]+->allowedTransitions\(\)/', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, (int) $m[0][1]));
            }
        }

        $this->assertSame([], $violations, "Inline-Transition-Guard im Service — stattdessen AssertsStatusTransition (RuntimeException)\n"
            . "bzw. AssertsValidatedTransition (ValidationException, Message-Key-Parameter) nutzen.\n\n" . implode("\n", $violations));
    }

    public function test_transition_tables_live_in_the_enums(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if (str_starts_with($relative, 'app/Enums/') || str_starts_with($relative, 'app/Services/Concerns/')) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            $pattern = '/function\s+(canTransition|transitionTo|assertTransition|isTransitionAllowed|allowedTransitions)\s*\(|const\s+\w*TRANSITIONS\b/';
            if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[0] as [$hit, $offset]) {
                $violations[] = sprintf('%s:%d %s', $relative, $this->lineOf($source, $offset), trim($hit));
            }
        }

        $this->assertSame([], $violations, "Übergangstabelle gehört ins Status-Enum (HasStatusTransitions::allowedTransitions()),\n"
            . "die Prüfung in AssertsStatusTransition/AssertsValidatedTransition oder \$status->canTransitionTo().\n\n" . implode("\n", $violations));
    }
}
