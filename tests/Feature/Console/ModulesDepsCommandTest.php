<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModulesDepsCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\Modules\ModulesDepsCommand;
use Tests\TestCase;

/** `modules:deps` (MVP-863): Kantenanalyse läuft und meldet die Baseline-Verstöße. */
class ModulesDepsCommandTest extends TestCase {
    public function test_deps_lists_edges_and_violations(): void {
        $this->artisan(ModulesDepsCommand::class)
            ->expectsOutputToContain('Kanten zwischen')
            ->assertExitCode(0);
    }

    public function test_deps_all_lists_allowed_edges_too(): void {
        $this->artisan(ModulesDepsCommand::class, ['--all' => true])
            ->expectsOutputToContain('Kanten zwischen')
            ->assertExitCode(0);
    }
}
