<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalManagementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\{Organization, User};
use App\Models\Whistleblowing\Portal;
use App\Services\Whistleblowing\WhistleblowingPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Portal-Verwaltung (Permission settings.manage) inkl. Anlegen, Aktualisieren,
 * Slug-Rotation; rendert das App-Layout (Smoke fuer den neuen Menue-Eintrag).
 */
class PortalManagementTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function manager(Organization $org): User {
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(WhistleblowingPermissions::ROLE_MELDESTELLE);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    public function test_manager_can_open_and_create_portal(): void {
        $org = Organization::factory()->create();
        $user = $this->manager($org);

        $this->actingAs($user)->get('/compliance/portal')->assertOk()->assertSee('Meldeportal');

        $this->actingAs($user)->put('/compliance/portal', [
            'is_enabled' => '1',
            'allow_anonymous' => '1',
            'retention_months' => 36,
        ])->assertRedirect(route('whistleblowing.portal.edit'));

        $portal = Portal::withoutGlobalScopes()->where('organization_id', $org->id)->firstOrFail();
        $this->assertTrue((bool) $portal->is_enabled);
        $this->assertNotEmpty($portal->public_slug);
        $this->assertFalse((bool) $portal->allow_confidential, 'Nicht angehakte Checkbox = false.');
    }

    public function test_slug_can_be_rotated(): void {
        $org = Organization::factory()->create();
        $user = $this->manager($org);
        $portal = Portal::create(['organization_id' => $org->id, 'public_slug' => 'wb-original', 'is_enabled' => true]);

        $this->actingAs($user)->post('/compliance/portal/slug')->assertRedirect();

        $this->assertNotSame('wb-original', $portal->fresh()->public_slug);
    }

    public function test_user_without_settings_permission_is_forbidden(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($plain)->get('/compliance/portal')->assertForbidden();
    }

    public function test_edit_page_shows_qr_and_poster_streams_pdf(): void {
        $org = Organization::factory()->create();
        $user = $this->manager($org);
        Portal::create(['organization_id' => $org->id, 'public_slug' => 'wb-aushang', 'is_enabled' => true]);

        $this->actingAs($user)->get('/compliance/portal')
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64', false)
            ->assertSee(route('whistleblowing.portal.poster'), false);

        $this->actingAs($user)->get('/compliance/portal/aushang')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_poster_requires_settings_permission(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($plain)->get('/compliance/portal/aushang')->assertForbidden();
    }
}
