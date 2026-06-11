<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MetricsPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Metrics;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsPageTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.metrics.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.metrics.index'))->assertForbidden();
    }

    public function test_index_renders_for_admin_with_privacy_notice_and_version(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.metrics.index'))
            ->assertOk()
            ->assertSee(__('metrics.title.index'))
            // Telemetrie-Hinweis: Daten bleiben lokal, kein externes Senden.
            ->assertSee(__('metrics.privacy_notice'))
            // Versions-Anzeige (Feature 022) auf der Metrik-Seite.
            ->assertSee((string) config('app.version'));
    }
}
