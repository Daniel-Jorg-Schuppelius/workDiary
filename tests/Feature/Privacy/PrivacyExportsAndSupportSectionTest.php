<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyExportsAndSupportSectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\{AuditLog, Organization, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyExportsAndSupportSectionTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_exports_section_shows_recent_tenant_exports(): void {
        $admin = User::factory()->admin()->create();
        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'tenant.export.requested',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['format' => 'zip', 'scope' => 'all', 'bytes' => 204800],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.privacy.index'));

        $response->assertOk()
            ->assertSee(__('Mandantenexporte'))
            ->assertSee('tenant.export.requested')
            ->assertSee('zip');
    }

    public function test_exports_section_shows_empty_state_when_no_exports(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.privacy.index'));

        $response->assertOk()
            ->assertSee(__('Mandantenexporte'))
            ->assertSee(__('Keine Exporte verzeichnet.'));
    }

    public function test_support_section_shows_recent_support_events(): void {
        $admin = User::factory()->admin()->create();
        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.reportGenerated',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['sha256' => str_repeat('a', 64), 'bytes' => 1024],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.privacy.index'));

        $response->assertOk()
            ->assertSee(__('Letzte Supportzugriffe'))
            ->assertSee('support.reportGenerated');
    }

    public function test_support_section_shows_empty_state_when_no_events(): void {
        $admin = User::factory()->admin()->create();
        // Remove any audit-log entries that the admin/seed pipeline may have
        // written so we definitely see the empty state.
        AuditLog::query()->where('organization_id', $admin->organization_id)
            ->where('event', 'like', 'support.%')
            ->delete();

        $response = $this->actingAs($admin)->get(route('admin.privacy.index'));

        $response->assertOk()->assertSee(__('Keine Supportzugriffe verzeichnet.'));
    }

    public function test_exports_section_does_not_leak_cross_organization_events(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create();
        AuditLog::query()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => null,
            'event' => 'tenant.export.requested',
            'auditable_type' => Organization::class,
            'auditable_id' => $otherOrg->id,
            'changes' => ['format' => 'tar', 'scope' => 'fremd-org'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.privacy.index'));

        $response->assertOk()
            // Eigener Org-Eintrag fehlt → leerer Zustand wird gezeigt.
            ->assertSee(__('Keine Exporte verzeichnet.'))
            ->assertDontSee('fremd-org');
    }
}
