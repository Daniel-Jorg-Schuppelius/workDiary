<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Phase6ToolingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\Organization;
use App\Models\Whistleblowing\{Portal, WhistleblowingCase};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-6-Werkzeuge: Produktions-Readiness-Check (Preflight) und Pilot-Seeder.
 */
class Phase6ToolingTest extends TestCase {
    use RefreshDatabase;

    private function goodKey(): void {
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    public function test_preflight_fails_without_key(): void {
        config()->set('whistleblowing.key', '');

        $this->artisan('whistleblowing:preflight')->assertExitCode(1);
    }

    public function test_preflight_fails_when_module_key_equals_app_key(): void {
        config()->set('whistleblowing.key', (string) config('app.key'));

        $this->artisan('whistleblowing:preflight')->assertExitCode(1);
    }

    public function test_preflight_passes_with_sound_config(): void {
        $this->goodKey();
        config()->set('whistleblowing.retention_months', 36);

        $this->artisan('whistleblowing:preflight')->assertExitCode(0);
    }

    public function test_demo_seed_creates_synthetic_cases(): void {
        $this->goodKey();
        $org = Organization::factory()->create();

        $this->artisan('whistleblowing:demo-seed', ['organization' => $org->id, '--count' => 3])
            ->assertExitCode(0);

        $this->assertSame(3, WhistleblowingCase::withoutGlobalScopes()->where('organization_id', $org->id)->count());
        $this->assertSame(1, Portal::withoutGlobalScopes()->where('organization_id', $org->id)->count());
    }
}
