<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyExtrasTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\DataSubjectRequestType;
use App\Enums\Privacy\{IncidentType, MeasureCategory, ProcessorRole};
use App\Models\{Organization, User};
use App\Models\Privacy\{MeasureAssignment, PrivacyAttachment, ProcessingActivity, ProcessingAgreement, Processor};
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService, IncidentService, TechnicalMeasureService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Resterweiterungen: Anhänge, Meldungsentwürfe, CSV/Druck-Export, TOM↔AVV. */
class PrivacyExtrasTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
        Storage::fake('local');
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

    public function test_attachment_upload_download_and_tenant_isolation(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = app(DataSubjectRequestService::class)->open($org, DataSubjectRequestType::Access, 'Subj', 'Inhalt');

        $this->actingAs($officer)->post(route('dataprotection.requests.attach', $dsr), [
            'file' => UploadedFile::fake()->create('auskunft.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $att = PrivacyAttachment::firstOrFail();
        Storage::disk('local')->assertExists($att->path);
        $this->actingAs($officer)->get(route('dataprotection.attachment.download', $att))->assertOk();

        // Fremde Org darf nicht herunterladen.
        $foreign = $this->officer(Organization::factory()->create());
        $this->actingAs($foreign)->get(route('dataprotection.attachment.download', $att))->assertNotFound();
    }

    public function test_incident_report_drafts_download(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $incident = app(IncidentService::class)->open($org, IncidentType::Disclosure, 'Offenlegung', 'Daten');

        $this->actingAs($officer)->get(route('dataprotection.incidents.draft', [$incident, 'authority']))
            ->assertOk()->assertSee($incident->incident_number);
        $this->actingAs($officer)->get(route('dataprotection.incidents.draft', [$incident, 'subjects']))->assertOk();
        $this->actingAs($officer)->get(route('dataprotection.incidents.draft', [$incident, 'authority', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($officer)->get(route('dataprotection.incidents.draft', [$incident, 'nonsense']))->assertNotFound();
    }

    public function test_vvt_export_csv_and_print(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        ProcessingActivity::create(['organization_id' => $org->id, 'name' => 'Lohn', 'controller_role' => 'controller', 'status' => 'draft']);

        $this->actingAs($officer)->get(route('dataprotection.activities.export', ['format' => 'csv']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($officer)->get(route('dataprotection.activities.export', ['format' => 'print']))->assertOk()->assertSee('Verzeichnis');
    }

    public function test_assign_tom_to_avv(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $processor = Processor::create(['organization_id' => $org->id, 'name' => 'P', 'role' => ProcessorRole::Processor->value]);
        $agreement = ProcessingAgreement::create(['organization_id' => $org->id, 'processor_id' => $processor->id, 'title' => 'AVV', 'version' => '1.0', 'status' => 'active']);
        $measure = app(TechnicalMeasureService::class)->createDraft($org, 'Backup', MeasureCategory::Recovery, []);

        $this->actingAs($officer)->post(route('dataprotection.agreements.tom', $agreement), ['measure_id' => $measure->id])->assertRedirect();

        $this->assertSame(1, MeasureAssignment::where('agreement_id', $agreement->id)->where('measure_id', $measure->id)->count());
    }
}
