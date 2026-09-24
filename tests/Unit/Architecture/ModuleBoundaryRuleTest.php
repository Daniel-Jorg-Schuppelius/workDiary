<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleBoundaryRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Console\Commands\Modules\ModulesDepsCommand;
use App\Modules\{BoundaryRules, DependencyGraph, ModuleRegistry};
use Tests\TestCase;

/**
 * Gate MVP-863: Abhängigkeiten zwischen Modulen folgen der Regel
 * ({@see BoundaryRules}); was noch dagegen verstößt, steht begründet in der
 * Baseline `tests/Unit/Architecture/baselines/module-boundaries.php` — und
 * die darf nur schrumpfen (`php artisan modules:deps --baseline` nach Abbau).
 */
class ModuleBoundaryRuleTest extends TestCase {
    public function test_module_edges_follow_the_boundary_rule_or_are_baselined(): void {
        $registry = $this->app->make(ModuleRegistry::class);
        $graph = new DependencyGraph($registry, new BoundaryRules($registry), base_path());
        $violations = $graph->violationKeys();
        /** @var list<string> $baseline */
        $baseline = require base_path(ModulesDepsCommand::BASELINE);

        $new = array_values(array_diff($violations, $baseline));
        $stale = array_values(array_diff($baseline, $violations));

        $this->assertSame([], $new, "Neue Modulverstöße — Contract, Event, Erweiterungspunkt oder Verschiebung (CLAUDE.md „Modulgrenzen“, `php artisan modules:deps`):\n" . implode("\n", $new));
        $this->assertSame([], $stale, "Baseline-Einträge ohne Verstoß — aus tests/Unit/Architecture/baselines/module-boundaries.php streichen (`php artisan modules:deps --baseline`):\n" . implode("\n", $stale));
    }

    public function test_baseline_is_sorted_and_unique(): void {
        /** @var list<string> $baseline */
        $baseline = require base_path(ModulesDepsCommand::BASELINE);
        $sorted = array_values(array_unique($baseline));
        sort($sorted);

        $this->assertSame($sorted, $baseline, 'Baseline unsortiert oder mit Dubletten — mit `php artisan modules:deps --baseline` neu schreiben.');
    }
}
