<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SystemAccessControlTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAccessControlTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_legacy_only_user_cannot_access_new_dashboard(): void {
        $user = User::factory()->legacyOnly()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_new_only_user_cannot_access_legacy_archive(): void {
        $user = User::factory()->user()->create([
            'legacy_user_id' => null,
            'is_new_system' => true,
        ]);

        $this->actingAs($user)
            ->get(route('legacy.archive.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_both_areas(): void {
        $user = User::factory()->admin()->create([
            'legacy_user_id' => null,
            'is_new_system' => true,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        // Legacy-Routen f\u00fchren ohne echte Legacy-DB ggf. zu 503, dies ist
        // f\u00fcr die Access-Pr\u00fcfung jedoch irrelevant: wichtig ist, dass
        // das Access-Middleware NICHT mit 403 abweist.
        $response = $this->actingAs($user)->get(route('legacy.archive.index'));
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_switch_mode_rejects_legacy_for_user_without_legacy_access(): void {
        config(['database.connections.legacy.database' => 'legacy_test']);

        $user = User::factory()->user()->create([
            'legacy_user_id' => null,
            'is_new_system' => true,
        ]);

        $this->actingAs($user)
            ->post(route('mode.switch', 'legacy'), ['origin' => 'home'])
            ->assertSessionMissing('work_mode')
            ->assertSessionHas('error');
    }

    public function test_switch_mode_rejects_new_for_legacy_only_user(): void {
        $user = User::factory()->legacyOnly()->create();

        $this->actingAs($user)
            ->post(route('mode.switch', 'new'), ['origin' => 'home'])
            ->assertSessionHas('error');
    }

    public function test_layout_hides_mode_switch_for_user_without_legacy_access(): void {
        config(['database.connections.legacy.database' => 'legacy_test']);

        $newOnly = User::factory()->user()->create([
            'legacy_user_id' => null,
            'is_new_system' => true,
        ]);

        $response = $this->actingAs($newOnly)
            ->withSession(['work_mode' => 'new'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('id="mode-switch-toggle"', false);
    }
}
