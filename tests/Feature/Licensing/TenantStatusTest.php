<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantStatusTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Enums\Organization\TenantStatus;
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SaaS-Mandantenstatus: Ableitung, Anzeige, Umschaltung (Plattform-Admin) und
 * Schreibsperre bei gesperrtem Mandanten (Feature 021).
 */
class TenantStatusTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_derives_active_for_plain_org(): void {
        $org = Organization::factory()->create(['is_active' => true, 'tenant_status' => null, 'trial_ends_at' => null]);

        $this->assertSame(TenantStatus::Active, $org->tenantStatus());
        $this->assertFalse($org->tenantWritesBlocked());
    }

    public function test_derives_trial_from_trial_ends_at(): void {
        $org = Organization::factory()->create([
            'is_active' => true,
            'tenant_status' => null,
            'trial_ends_at' => CarbonImmutable::now()->addDays(10),
        ]);

        $this->assertSame(TenantStatus::Trial, $org->tenantStatus());
    }

    public function test_derives_suspended_when_inactive(): void {
        $org = Organization::factory()->create(['is_active' => false, 'tenant_status' => null]);

        $this->assertSame(TenantStatus::Suspended, $org->tenantStatus());
        $this->assertTrue($org->tenantWritesBlocked());
    }

    public function test_explicit_status_overrides_derivation(): void {
        $org = Organization::factory()->create([
            'is_active' => true,
            'tenant_status' => TenantStatus::Suspended,
        ]);

        $this->assertSame(TenantStatus::Suspended, $org->tenantStatus());
        $this->assertTrue($org->tenantWritesBlocked());
    }

    public function test_admin_can_toggle_tenant_status(): void {
        $admin = User::factory()->platformAdmin()->create();
        $org = $admin->organization;

        $this->actingAs($admin)
            ->post(route('admin.license.tenantStatus'), ['tenant_status' => 'suspended'])
            ->assertRedirect();

        $this->assertSame(TenantStatus::Suspended, $org->refresh()->tenant_status);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'event' => 'tenant.statusChanged',
        ]);

        // inherit setzt den expliziten Wert zurück.
        $this->actingAs($admin)
            ->post(route('admin.license.tenantStatus'), ['tenant_status' => 'inherit'])
            ->assertRedirect();
        $this->assertNull($org->refresh()->tenant_status);
    }

    public function test_non_admin_cannot_toggle_tenant_status(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('admin.license.tenantStatus'), ['tenant_status' => 'suspended'])
            ->assertForbidden();
    }

    public function test_license_admin_page_shows_tenant_status(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.license.index'))
            ->assertOk()
            ->assertSee(__('Mandantenstatus'))
            ->assertSee(TenantStatus::Active->label());
    }

    public function test_suspended_tenant_blocks_write_actions(): void {
        $admin = User::factory()->admin()->create();
        $org = $admin->organization;
        $org->update(['tenant_status' => TenantStatus::Suspended]);

        // Schreibende Aktion (POST) auf eine reguläre Org-Route → 423.
        $this->actingAs($admin)
            ->post(route('org.members.store'), [
                'name' => 'X',
                'email' => 'x@example.test',
                'role' => 'user',
                'password' => 'Sup3r-Secret!2026',
                'password_confirmation' => 'Sup3r-Secret!2026',
            ])
            ->assertStatus(423);
    }

    public function test_suspended_tenant_allows_read_and_status_change(): void {
        $admin = User::factory()->platformAdmin()->create();
        $org = $admin->organization;
        $org->update(['tenant_status' => TenantStatus::Suspended]);

        // Lesezugriff bleibt erhalten.
        $this->actingAs($admin)->get(route('admin.license.index'))->assertOk();

        // Aufhebung der Sperre über die ausgenommene Lizenz-Route bleibt möglich.
        $this->actingAs($admin)
            ->post(route('admin.license.tenantStatus'), ['tenant_status' => 'active'])
            ->assertRedirect();
        $this->assertSame(TenantStatus::Active, $org->refresh()->tenant_status);
    }
}
