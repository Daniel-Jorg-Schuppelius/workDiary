<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EvidenceSnapshotTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Protocol\ProtocolType;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{DiaryEntry, Organization, Project, Timesheet, User};
use App\Services\DocumentDesign\{DocumentDesignRenderer, RenderProfileService};
use App\Services\Protocol\ProtocolService;
use App\Services\Timesheet\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-650 (Issue #83): Render-Snapshots für die Nachweis-Arten — signierte/
 * finalisierte Protokolle, Stundenzettel und Formular-Einreichungen frieren
 * ihren Designstand ein; ein späterer Profilwechsel verändert sie nicht mehr
 * (dasselbe Muster gilt für Entsorgungsakte, Lieferschein und
 * Fertigungsnachweis über identische Hooks).
 */
class EvidenceSnapshotTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private int $frozenVersionId;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        // Aktives CI-Basisdesign — der einzufrierende Stand.
        $profiles = app(RenderProfileService::class);
        $profile = $profiles->createProfile($this->org, 'CI-Basisdesign', [], true, $this->admin);
        $this->assertTrue($profiles->activate($profile->versions()->firstOrFail(), $this->admin)->ok());
        $this->frozenVersionId = (int) $profile->refresh()->active_version_id;
    }

    /** Aktiviert nach dem Einfrieren eine NEUE Profilversion (Profilwechsel). */
    private function supersedeActiveVersion(): void {
        $profiles = app(RenderProfileService::class);
        $profile = \App\Models\DocumentDesign\DocumentRenderProfile::query()->where('is_default', true)->firstOrFail();
        $draft = $profiles->newDraftFrom($profile->versions()->orderByDesc('version')->firstOrFail(), $this->admin);
        $this->assertTrue($profiles->activate($draft, $this->admin)->ok());
        $this->assertNotSame($this->frozenVersionId, (int) $profile->refresh()->active_version_id);
    }

    public function test_signed_timesheet_freezes_design_snapshot(): void {
        $project = Project::factory()->create(['organization_id' => $this->org->id]);
        $timesheet = Timesheet::create([
            'organization_id' => $this->org->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'work_date' => now()->toDateString(),
            'status' => TimesheetStatus::Draft->value,
        ]);

        $img = imagecreatetruecolor(80, 40);
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $png = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
        app(SignatureService::class)->sign($timesheet, $png, ['customer_name' => 'Erika Muster'], null, $this->admin);

        $this->assertDatabaseHas('document_render_snapshots', [
            'documentable_id' => $timesheet->id,
            'document_kind' => RenderDocumentKind::Timesheet->value,
            'profile_version_id' => $this->frozenVersionId,
        ]);

        $this->supersedeActiveVersion();
        $payload = app(DocumentDesignRenderer::class)->payloadFromSnapshot($timesheet, RenderDocumentKind::Timesheet);
        $this->assertNotNull($payload);
        $this->assertSame($this->frozenVersionId, $payload['profile_version_id'], 'Signierte Stundenzettel behalten ihren Designstand.');
    }

    public function test_protocol_signature_freezes_design_snapshot(): void {
        $entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->org->id]);
        $service = app(ProtocolService::class);
        $protocol = $service->create($entry, $this->admin, [
            'type' => ProtocolType::Service->value,
            'title' => 'Abnahme',
        ]);

        $service->addSignature($protocol->refresh(), $this->admin, ['signer_name' => 'Erika Muster']);

        $this->assertDatabaseHas('document_render_snapshots', [
            'documentable_id' => $protocol->id,
            'document_kind' => RenderDocumentKind::Protocol->value,
            'profile_version_id' => $this->frozenVersionId,
        ]);
    }

    public function test_form_submission_freezes_design_snapshot(): void {
        $template = \App\Models\FormTemplate::factory()->active()->create([
            'organization_id' => $this->org->id,
            'created_by_user_id' => $this->admin->id,
            'fields' => [
                ['key' => 'result', 'label' => 'Ergebnis', 'type' => 'text', 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
            ],
        ]);

        $submission = app(\App\Services\Form\FormService::class)->submit(
            $template->refresh(),
            null,
            ['result' => 'ohne Mängel'],
            $this->admin,
        );

        $this->assertDatabaseHas('document_render_snapshots', [
            'documentable_id' => $submission->id,
            'document_kind' => RenderDocumentKind::Form->value,
            'profile_version_id' => $this->frozenVersionId,
        ]);
    }
}
