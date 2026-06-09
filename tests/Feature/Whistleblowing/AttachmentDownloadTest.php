<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentDownloadTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\{Organization, User};
use App\Models\Whistleblowing\{Attachment, CaseAssignment, WhistleblowingCase};
use App\Services\Whistleblowing\{
    ReporterCredentialService,
    WhistleblowingAttachmentScanService,
    WhistleblowingAttachmentService,
    WhistleblowingPermissions,
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Anhang-Auslieferung nur an berechtigte Bearbeiter und NUR nach Freigabe
 * (scan_status = clean). Pending bleibt in Quarantaene (Abschnitt 11).
 */
class AttachmentDownloadTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
        Storage::fake('whistleblowing');
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function handler(Organization $org): User {
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(WhistleblowingPermissions::ROLE_MELDESTELLE);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function makeCase(Organization $org): WhistleblowingCase {
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = 'S';
        $case->description_ciphertext = 'D';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
        ]);
        $case->save();

        return $case;
    }

    private function attachment(WhistleblowingCase $case): Attachment {
        return app(WhistleblowingAttachmentService::class)
            ->storeReporterUpload($case, UploadedFile::fake()->create('beweis.pdf', 20, 'application/pdf'));
    }

    private function url(WhistleblowingCase $case, Attachment $a): string {
        return route('whistleblowing.internal.attachment', ['case' => $case, 'attachment' => $a->id]);
    }

    public function test_pending_attachment_is_withheld(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        CaseAssignment::create(['organization_id' => $org->id, 'case_id' => $case->id, 'user_id' => $handler->id, 'role' => 'processor', 'assigned_at' => now()]);
        $attachment = $this->attachment($case);

        $this->actingAs($handler)->get($this->url($case, $attachment))->assertForbidden();
    }

    public function test_clean_attachment_is_delivered(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        CaseAssignment::create(['organization_id' => $org->id, 'case_id' => $case->id, 'user_id' => $handler->id, 'role' => 'processor', 'assigned_at' => now()]);
        $attachment = $this->attachment($case);

        app(WhistleblowingAttachmentScanService::class)->markClean($attachment);

        $this->actingAs($handler)->get($this->url($case, $attachment))->assertOk()->assertDownload();
    }

    public function test_unassigned_handler_cannot_download(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $other = $this->handler($org);
        $case = $this->makeCase($org);
        CaseAssignment::create(['organization_id' => $org->id, 'case_id' => $case->id, 'user_id' => $handler->id, 'role' => 'processor', 'assigned_at' => now()]);
        $attachment = $this->attachment($case);
        app(WhistleblowingAttachmentScanService::class)->markClean($attachment);

        $this->actingAs($other)->get($this->url($case, $attachment))->assertForbidden();
    }
}
