<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyComplianceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\ProcessorRole;
use App\Models\{Organization, User};
use App\Models\Privacy\{ComplianceFinding, ProcessingActivity, ProcessingAgreement, Processor};
use App\Services\Privacy\{ComplianceAnalysisService, DataProtectionPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Compliance-/Lückenanalyse: Regel-Engine, Auto-Resolve, manueller Override, Gating. */
class PrivacyComplianceTest extends TestCase {
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

    public function test_analysis_detects_and_resolves_avv_gap(): void {
        $org = Organization::factory()->create();
        $svc = app(ComplianceAnalysisService::class);
        $processor = Processor::create(['organization_id' => $org->id, 'name' => 'Cloud GmbH', 'role' => ProcessorRole::Processor->value]);

        $svc->run($org);
        $finding = ComplianceFinding::where('organization_id', $org->id)
            ->where('requirement_key', 'avv_required')->where('processor_id', $processor->id)->firstOrFail();
        $this->assertSame('missing', $finding->status);

        // AVV anlegen → erneute Analyse markiert die Lücke als behoben.
        ProcessingAgreement::create(['organization_id' => $org->id, 'processor_id' => $processor->id, 'title' => 'AVV', 'version' => '1.0', 'status' => 'active']);
        $svc->run($org);
        $this->assertSame('present', $finding->fresh()->status);
    }

    public function test_manual_override_is_respected_by_reanalysis(): void {
        $org = Organization::factory()->create();
        $svc = app(ComplianceAnalysisService::class);
        ProcessingActivity::create(['organization_id' => $org->id, 'name' => 'Newsletter', 'controller_role' => 'controller', 'status' => 'draft']);

        $svc->run($org);
        $finding = ComplianceFinding::where('organization_id', $org->id)->where('requirement_key', 'tom_assigned')->firstOrFail();
        $this->assertSame('missing', $finding->status);

        $svc->override($finding, 'not_applicable', 'Kein Personenbezug');
        $svc->run($org);
        $this->assertSame('not_applicable', $finding->fresh()->status, 'Manuelle Entscheidung bleibt erhalten.');
    }

    public function test_not_applicable_requires_justification(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        ProcessingActivity::create(['organization_id' => $org->id, 'name' => 'X', 'controller_role' => 'controller', 'status' => 'draft']);
        app(ComplianceAnalysisService::class)->run($org);
        $finding = ComplianceFinding::where('organization_id', $org->id)->firstOrFail();

        $this->actingAs($officer)->put(route('dataprotection.compliance.update', $finding), ['status' => 'not_applicable'])
            ->assertSessionHasErrors('justification');
    }

    public function test_run_via_http(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $this->actingAs($officer)->get(route('dataprotection.compliance.index'))->assertOk();
        $this->actingAs($officer)->post(route('dataprotection.compliance.run'))->assertRedirect();
    }

    public function test_free_plan_gated(): void {
        $freeOrg = Organization::factory()->free()->create();
        $this->actingAs($this->officer($freeOrg))->get(route('dataprotection.compliance.index'))->assertStatus(423);
    }
}
