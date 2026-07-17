<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyGvvTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\ProcessorRole;
use App\Models\{Organization, User};
use App\Models\Privacy\{JointControllerAgreement, Processor};
use App\Services\Privacy\DataProtectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** GVV (Art. 26): Anlage mit Zuständigkeitsmatrix, Gating, Mandantentrennung. */
class PrivacyGvvTest extends TestCase {
    use RefreshDatabase;

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

    private function partner(Organization $org): Processor {
        return Processor::create([
            'organization_id' => $org->id, 'name' => 'Partner AG', 'role' => ProcessorRole::JointController->value,
        ]);
    }

    public function test_officer_can_create_gvv_with_matrix(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $partner = $this->partner($org);

        $this->actingAs($officer)->post(route('dataprotection.gvv.store'), [
            'partner_id' => $partner->sqid,
            'title' => 'Gemeinsames Portal',
            'contact_point' => 'datenschutz@example.com',
            'essence_provided' => '1',
            'responsibilities' => [
                'information_duties' => 'us',
                'data_subject_rights' => 'joint',
                'incidents' => 'partner',
                'authority_contact' => 'us',
            ],
        ])->assertRedirect();

        $gvv = JointControllerAgreement::where('organization_id', $org->id)->firstOrFail();
        $this->assertSame('us', data_get($gvv->responsibilities, 'information_duties'));
        $this->assertSame('partner', data_get($gvv->responsibilities, 'incidents'));
        $this->assertTrue($gvv->essence_provided);
    }

    public function test_free_plan_gated(): void {
        $org = Organization::factory()->free()->create();
        $this->actingAs($this->officer($org))->get(route('dataprotection.gvv.index'))->assertStatus(423);
    }

    public function test_regular_user_forbidden(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($plain)->get(route('dataprotection.gvv.index'))->assertForbidden();
    }

    public function test_tenant_isolation(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $officerA = $this->officer($orgA);
        $foreign = JointControllerAgreement::create([
            'organization_id' => $orgB->id, 'partner_id' => $this->partner($orgB)->id, 'title' => 'Fremd',
        ]);

        $this->actingAs($officerA)->get(route('dataprotection.gvv.show', $foreign))->assertNotFound();
    }
}
