<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SystemHealthCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_system_health_passes_in_test_environment(): void {
        $this->artisan('system:health')
            ->expectsOutputToContain('Alle Checks bestanden.')
            ->assertExitCode(0);
    }

    public function test_system_health_fails_without_app_key(): void {
        config(['app.key' => '']);

        $this->artisan('system:health')->assertExitCode(1);
    }
}
