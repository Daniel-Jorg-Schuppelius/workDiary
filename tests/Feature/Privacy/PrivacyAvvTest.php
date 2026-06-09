<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyAvvTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{AgreementStatus, ProcessorRole};
use App\Models\{Organization, User};
use App\Models\Privacy\{ProcessingAgreement, Processor};
use App\Services\Privacy\{AgreementService, DataProtectionPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** MVP 2: Dienstleister-/AVV-Register — Zugriff, Gating, Lebenszyklus, Mandanten. */
class PrivacyAvvTest extends TestCase {
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

    private function agreement(Organization $org): ProcessingAgreement {
        $processor = Processor::create([
            'organization_id' => $org->id, 'name' => 'Cloud GmbH', 'role' => ProcessorRole::Processor->value,
        ]);

        return ProcessingAgreement::create([
            'organization_id' => $org->id, 'processor_id' => $processor->id,
            'title' => 'Hosting-AVV', 'version' => '1.0', 'status' => AgreementStatus::Active->value,
        ]);
    }

    public function test_officer_can_open_registers(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);

        $this->actingAs($officer)->get(route('dataprotection.processors.index'))->assertOk();
        $this->actingAs($officer)->get(route('dataprotection.agreements.index'))->assertOk();
    }

    public function test_regular_user_forbidden(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($plain)->get(route('dataprotection.processors.index'))->assertForbidden();
    }

    public function test_free_plan_gated(): void {
        $org = Organization::factory()->free()->create();
        $officer = $this->officer($org);

        $this->actingAs($officer)->get(route('dataprotection.agreements.index'))->assertStatus(423);
    }

    public function test_termination_and_data_return_proof(): void {
        $org = Organization::factory()->create();
        $svc = app(AgreementService::class);
        $agreement = $this->agreement($org);

        $svc->terminate($agreement);
        $agreement->refresh();
        $this->assertSame(AgreementStatus::Terminated, $agreement->status);
        $this->assertNotNull($agreement->terminated_at);
        $this->assertSame('pending', $agreement->data_return);

        $svc->confirmDataReturn($agreement, 'deleted');
        $agreement->refresh();
        $this->assertSame('deleted', $agreement->data_return);
        $this->assertNotNull($agreement->data_return_confirmed_at);
    }

    public function test_link_activities_only_within_org(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $agreement = $this->agreement($org);
        $activity = \App\Models\Privacy\ProcessingActivity::create([
            'organization_id' => $org->id, 'name' => 'Hosting', 'controller_role' => 'controller', 'status' => 'draft',
        ]);
        $foreignActivity = \App\Models\Privacy\ProcessingActivity::create([
            'organization_id' => Organization::factory()->create()->id, 'name' => 'Fremd', 'controller_role' => 'controller', 'status' => 'draft',
        ]);

        $this->actingAs($officer)->post(route('dataprotection.agreements.activities', $agreement), [
            'activity_ids' => [$activity->id, $foreignActivity->id],
        ])->assertRedirect();

        $linked = $agreement->activities()->pluck('privacy_processing_activities.id')->all();
        $this->assertContains($activity->id, $linked);
        $this->assertNotContains($foreignActivity->id, $linked, 'Fremde Org-Taetigkeit wird nicht verknüpft.');
    }

    public function test_tenant_isolation_blocks_foreign_agreement(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $officerA = $this->officer($orgA);
        $foreign = $this->agreement($orgB);

        $this->actingAs($officerA)->get(route('dataprotection.agreements.show', $foreign))->assertNotFound();
    }
}
