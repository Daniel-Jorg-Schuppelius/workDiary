<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\DataSubjectRequestType;
use App\Models\{Organization, User};
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * HTTP-Schicht: Plan-Gating (423), Rechte-Gating (403), Zugriff der Rolle
 * `datenschutz`, Export-Audit und strikte Mandantentrennung.
 */
class PrivacyHttpTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function officer(Organization $org): User {
        DataProtectionPermissions::seedOrganization($org);
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        return $user;
    }

    public function test_officer_can_open_module(): void {
        $org = Organization::factory()->create(); // Default enterprise
        $officer = $this->officer($org);

        $this->actingAs($officer)->get(route('dataprotection.activities.index'))->assertOk();
        $this->actingAs($officer)->get(route('dataprotection.requests.index'))->assertOk();
    }

    public function test_regular_user_is_forbidden(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($plain)->get(route('dataprotection.activities.index'))->assertForbidden();
    }

    public function test_free_plan_is_gated_with_423(): void {
        $org = Organization::factory()->free()->create();
        $officer = $this->officer($org);

        $this->actingAs($officer)->get(route('dataprotection.activities.index'))->assertStatus(423);
    }

    public function test_ropa_export_writes_audit(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);

        $this->actingAs($officer)->get(route('dataprotection.activities.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'event' => 'privacy.ropa.exported',
        ]);
    }

    public function test_dashboard_widget_visible_for_officer(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);

        $this->actingAs($officer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('dataprotection.requests.index'), false);
    }

    public function test_dashboard_widget_hidden_for_regular_user(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($plain)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('dataprotection.requests.index'), false);
    }

    public function test_tenant_isolation_blocks_foreign_request(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $officerA = $this->officer($orgA);

        $foreign = app(DataSubjectRequestService::class)->open(
            $orgB,
            DataSubjectRequestType::Access,
            'Fremd',
            'Fremdes Anliegen',
        );

        // Org-Scope blendet fremde Faelle aus → Route-Model-Binding findet nichts.
        $this->actingAs($officerA)->get(route('dataprotection.requests.show', $foreign))->assertNotFound();
    }
}
