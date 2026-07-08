<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyFollowupsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\{Organization, User};
use App\Models\Privacy\{ComplianceFinding, Dpia, DpiaStep, PrivacyAttachment, PrivacyRequirement, ProcessingActivity, TechnicalMeasure};
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Privacy\{ComplianceAnalysisService, DataProtectionPermissions, DpiaWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Nachtrag 043: DSFA-Schritt-Workflow (a), TOM-Nachweise mit Gültig-bis (b),
 * konfigurierbarer Anforderungskatalog inkl. Branchenprofil-Vorlagen (c) und
 * Subprozessoren im Auskunfts-Export (d).
 */
class PrivacyFollowupsTest extends TestCase {
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

    private function activity(Organization $org): ProcessingActivity {
        return ProcessingActivity::create([
            'organization_id' => $org->id,
            'name' => 'Videoüberwachung',
            'controller_role' => 'controller',
            'status' => 'draft',
        ]);
    }

    public function test_dpia_workflow_enforces_order_and_approval_sets_outcome(): void {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $activity = $this->activity($org);

        $dpia = Dpia::query()->create(['organization_id' => $org->id, 'activity_id' => $activity->id]);
        $workflow = app(DpiaWorkflowService::class);
        $workflow->ensureSteps($dpia);
        $this->assertSame(count(DpiaStep::STEPS), $dpia->steps()->count());

        // Reihenfolge-Zwang: Freigabe vor den Inhaltsschritten ist gesperrt.
        $approval = $dpia->steps()->where('step', 'approval')->firstOrFail();
        try {
            $workflow->complete($approval, $user, null, 'proceed');
            $this->fail('Reihenfolge-Zwang griff nicht.');
        } catch (RuntimeException) {
            // erwartet
        }

        foreach (['description', 'necessity', 'risks', 'mitigations'] as $code) {
            $step = $dpia->steps()->where('step', $code)->firstOrFail();
            $workflow->complete($step, $user, 'Inhalt ' . $code);
        }

        // Inhaltsschritte spiegeln in die Dpia-Felder.
        $this->assertSame('Inhalt necessity', $dpia->refresh()->necessity);
        $this->assertSame('Inhalt risks', $dpia->risks);

        $workflow->complete($approval->fresh(), $user, 'Freigabe erteilt', 'proceed', 'low');
        $dpia->refresh();
        $this->assertSame('proceed', $dpia->outcome->value);
        $this->assertNotNull($dpia->assessed_at);
        $this->assertNull($workflow->nextStep($dpia));
    }

    public function test_dpia_step_route_and_pdf_report(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $activity = $this->activity($org);

        $this->actingAs($officer)
            ->post(route('dataprotection.activities.dpia.step', [$activity, 'description']), ['content' => 'Kamera im Lager'])
            ->assertRedirect(route('dataprotection.activities.show', $activity));

        $dpia = Dpia::query()->where('activity_id', $activity->id)->firstOrFail();
        $this->assertTrue($dpia->steps()->where('step', 'description')->firstOrFail()->isDone());

        // Bericht erst nach Anlage möglich; PDF-Header prüfen.
        $this->actingAs($officer)
            ->get(route('dataprotection.activities.dpia.report', $activity))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_expiring_tom_proof_creates_compliance_finding(): void {
        $org = Organization::factory()->create();
        $measure = TechnicalMeasure::create(['organization_id' => $org->id, 'name' => 'Verschlüsselung', 'category' => 'data_access']);
        PrivacyAttachment::create([
            'organization_id' => $org->id,
            'attachable_type' => TechnicalMeasure::class,
            'attachable_id' => $measure->id,
            'filename' => 'iso-zertifikat.pdf',
            'path' => 'privacy/attachments/test.pdf',
            'size' => 100,
            'mime' => 'application/pdf',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        app(ComplianceAnalysisService::class)->run($org);

        $finding = ComplianceFinding::query()
            ->where('organization_id', $org->id)
            ->where('requirement_key', 'tom_proof_current')
            ->firstOrFail();
        $this->assertSame('expiring', $finding->status);
        $this->assertStringContainsString('iso-zertifikat.pdf', (string) $finding->trigger);
    }

    public function test_deactivated_requirement_suppresses_check(): void {
        $org = Organization::factory()->create();
        $svc = app(ComplianceAnalysisService::class);
        $this->activity($org); // erzeugt tom_assigned-Lücke

        // Katalog materialisieren und tom_assigned deaktivieren.
        $svc->catalog($org);
        PrivacyRequirement::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('requirement_key', 'tom_assigned')
            ->update(['active' => false]);

        $svc->run($org);

        $this->assertSame(
            0,
            ComplianceFinding::query()->where('organization_id', $org->id)
                ->where('requirement_key', 'tom_assigned')
                ->whereIn('status', ['missing', 'expiring'])->count(),
        );
    }

    public function test_branch_profile_seeds_requirement_presets(): void {
        $org = Organization::factory()->create();
        $actor = User::factory()->admin()->create(['organization_id' => $org->id]);

        app(BranchProfileInstaller::class)->install($org, 'facility', $actor);

        $gvv = PrivacyRequirement::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('requirement_key', 'gvv_required')
            ->firstOrFail();
        $this->assertFalse($gvv->active);
        $this->assertSame('profile', $gvv->source);

        // Manuell angepasste Einträge überlebt ein erneuter Profil-Lauf.
        $gvv->update(['active' => true, 'source' => 'manual']);
        app(BranchProfileInstaller::class)->install($org, 'facility', $actor, force: true);
        $this->assertTrue($gvv->refresh()->active);
    }

    public function test_subprocessor_crud_with_safeguards_and_export(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $processor = \App\Models\Privacy\Processor::create(['organization_id' => $org->id, 'name' => 'Cloud GmbH', 'role' => 'processor']);
        $agreement = \App\Models\Privacy\ProcessingAgreement::create([
            'organization_id' => $org->id, 'processor_id' => $processor->id,
            'title' => 'AVV Cloud', 'version' => '1.0', 'status' => 'active',
        ]);

        $this->actingAs($officer)
            ->post(route('dataprotection.agreements.subprocessor.store', $agreement), [
                'name' => 'US-Hoster Inc.',
                'location' => 'USA',
                'third_country' => 1,
                'safeguards' => 'SCC (Modul 3)',
            ])->assertRedirect();

        $sub = \App\Models\Privacy\Subprocessor::query()->where('agreement_id', $agreement->id)->firstOrFail();
        $this->assertSame('SCC (Modul 3)', $sub->safeguards);

        // Export enthält die Kette AVV → Subprozessor.
        $snapshot = app(\App\Services\Privacy\PrivacyExportService::class)->ropaSnapshot($org);
        $this->assertSame('US-Hoster Inc.', $snapshot['agreements'][0]['subprocessors'][0]['name']);
        $this->assertSame('SCC (Modul 3)', $snapshot['agreements'][0]['subprocessors'][0]['safeguards']);

        // Entfernen (043d).
        $this->actingAs($officer)
            ->delete(route('dataprotection.agreements.subprocessor.destroy', [$agreement, $sub]))
            ->assertRedirect();
        $this->assertSame(0, \App\Models\Privacy\Subprocessor::query()->where('agreement_id', $agreement->id)->count());
    }
}
