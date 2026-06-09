<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyWorkflowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{ControllerRole, DataSubjectRequestStatus, DataSubjectRequestType, ProcessingActivityStatus};
use App\Models\{Organization, User};
use App\Models\Privacy\DataSubjectRequest;
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService, ProcessingActivityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Workflow der Betroffenenanfragen + VVT-Versionierung + Zugriff. */
class PrivacyWorkflowTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_data_subject_request_lifecycle(): void {
        $org = Organization::factory()->create();
        $handler = User::factory()->create(['organization_id' => $org->id]);
        $svc = app(DataSubjectRequestService::class);

        $dsr = $svc->open($org, DataSubjectRequestType::Access, 'Erika Muster, erika@example.com', 'Bitte Auskunft.', 'email', $handler);
        $this->assertSame(DataSubjectRequestStatus::Intake, $dsr->status);
        $this->assertNotNull($dsr->deadline_at);
        $this->assertStringStartsWith('DSR-', $dsr->request_number);

        $svc->verifyIdentity($dsr, $handler);
        $svc->assign($dsr, $handler, $handler);
        $svc->decide($dsr, 'granted', 'Auskunft erteilt am ...', $handler);

        $fresh = DataSubjectRequest::findOrFail($dsr->id);
        $this->assertSame(DataSubjectRequestStatus::Completed, $fresh->status);
        $this->assertSame('granted', $fresh->decision);
        $this->assertSame('Auskunft erteilt am ...', $fresh->decision_note_ciphertext); // entschluesselt
        $this->assertNotNull($fresh->closed_at);

        // Vollstaendige, integere Ereigniskette.
        $this->assertSame(4, $fresh->events()->count()); // opened, identity_verified, assigned, decided
        $this->artisan('audit:verify')->assertExitCode(0);
    }

    public function test_processing_activity_versioning_and_approval(): void {
        $org = Organization::factory()->create();
        $author = User::factory()->create(['organization_id' => $org->id]);
        $approver = User::factory()->create(['organization_id' => $org->id]);
        $svc = app(ProcessingActivityService::class);

        $activity = $svc->createDraft($org, 'Lohnabrechnung', 'Entgeltabrechnung', ControllerRole::Controller, ['data_categories' => ['Stammdaten']], $author);
        $this->assertSame(ProcessingActivityStatus::Draft, $activity->status);
        $this->assertSame(1, $activity->versions()->count());

        $v2 = $svc->addVersion($activity, ['data_categories' => ['Stammdaten', 'Bankdaten']], $author, 'Bankdaten ergaenzt');
        $svc->submitForReview($activity);
        $svc->approve($activity, $v2, $approver);

        $fresh = $activity->fresh();
        $this->assertSame(ProcessingActivityStatus::Approved, $fresh->status);
        $this->assertSame($v2->id, $fresh->current_version_id);
        $this->assertNotNull($fresh->review_due_at);
    }

    public function test_access_requires_dataprotection_permission(): void {
        $org = Organization::factory()->create();
        DataProtectionPermissions::seedOrganization($org);

        $plain = User::factory()->create(['organization_id' => $org->id]);
        $this->assertFalse($plain->can('viewAny', DataSubjectRequest::class));

        $officer = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $officer->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);
        $this->assertTrue($officer->fresh()->can('viewAny', DataSubjectRequest::class));
    }
}
