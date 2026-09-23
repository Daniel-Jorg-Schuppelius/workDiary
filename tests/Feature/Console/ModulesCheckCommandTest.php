<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModulesCheckCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Console\Commands\Modules\{ModulesCacheCommand, ModulesCheckCommand, ModulesClearCommand};
use App\Modules\ModuleRegistry;
use Tests\TestCase;

/**
 * `modules:check`, `modules:cache`, `modules:clear` (MVP-861,
 * {@see ModulesCheckCommand}, {@see ModulesCacheCommand}, {@see ModulesClearCommand}).
 */
class ModulesCheckCommandTest extends TestCase {
    public function test_check_reports_the_committed_manifests_as_complete(): void {
        $this->artisan('modules:check')
            ->expectsOutputToContain('Manifeste vollständig')
            ->assertExitCode(0);
    }

    public function test_cache_and_clear_round_trip_without_touching_the_repo_cache(): void {
        $cache = sys_get_temp_dir() . '/workdiary-modules-' . uniqid() . '.php';
        $this->app->instance(ModuleRegistry::class, new ModuleRegistry(app_path('Modules/Manifests'), $cache));

        $this->artisan('modules:cache')->expectsOutputToContain('Manifest-Cache geschrieben')->assertExitCode(0);
        $this->assertFileExists($cache);

        $this->artisan('modules:clear')->expectsOutputToContain('entfernt')->assertExitCode(0);
        $this->assertFileDoesNotExist($cache);
    }
}
