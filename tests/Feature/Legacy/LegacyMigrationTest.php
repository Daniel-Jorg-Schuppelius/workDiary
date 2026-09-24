<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Legacy;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LegacyMigrationTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_legacy_write_blocked_by_default(): void {
        Config::set('app.legacy_write_enabled', false);
        $user = User::factory()->admin()->create(['legacy_user_id' => 1]);

        $response = $this->actingAs($user)->post(route('legacy.diary.store'), [
            'inhalt' => 'Test',
            'gelesen' => 2,
        ]);

        // Middleware fängt vor Validierung ab und redirected zurück.
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_legacy_read_routes_still_accessible_when_blocked(): void {
        Config::set('app.legacy_write_enabled', false);
        $user = User::factory()->admin()->create();

        // Index sollte zumindest nicht durch das Write-Block-Middleware gestoppt werden.
        // (Kann 500 werfen wenn Legacy-DB fehlt — aber NICHT 423/Redirect mit error.)
        $response = $this->actingAs($user)->get(route('legacy.diary.index'));
        $this->assertNotEquals(423, $response->getStatusCode());
        if ($response->isRedirect()) {
            $this->assertNull(session('error'));
        }
    }

    public function test_migration_dashboard_requires_admin(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user)
            ->get(route('admin.legacy-migration.index'))
            ->assertForbidden();
    }

    /** Sicherheitsaudit 2026-09-17 (tenant-legacy-2): die Org-Admin-Rolle allein öffnet den Legacy-Bereich nicht. */
    public function test_org_admin_without_legacy_account_has_no_legacy_access(): void {
        $admin = User::factory()->admin()->create();

        // Wer den neuen Bereich nutzen darf, wird dorthin umgeleitet statt ins Legacy-Archiv.
        $this->actingAs($admin)->get(route('legacy.archive.index'))->assertRedirect(route('dashboard'));
    }

    /** Sicherheitsaudit 2026-09-17 (tenant-legacy-1): installationsweit, also nur der Plattform-Betreiber. */
    public function test_org_admin_can_neither_view_nor_run_the_migration(): void {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.legacy-migration.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.legacy-migration.run'), ['type' => 'users'])->assertForbidden();
    }

    public function test_admin_can_view_migration_dashboard(): void {
        $admin = User::factory()->admin()->platformAdmin()->create();
        $this->actingAs($admin)
            ->get(route('admin.legacy-migration.index'))
            ->assertOk()
            ->assertSee('Legacy-Migration');
    }
}
