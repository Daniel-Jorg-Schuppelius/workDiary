<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanDriverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\Organization;
use App\Models\Whistleblowing\{Attachment, WhistleblowingCase};
use App\Services\Whistleblowing\{ReporterCredentialService, WhistleblowingAttachmentService};
use App\Services\Whistleblowing\Scanning\ScanDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Orchestrierung des Anhang-Scans ueber den pluggbaren ScanDriver: clean gibt
 * frei, rejected sperrt, kein Urteil bleibt in Quarantaene.
 */
class ScanDriverTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
        Storage::fake('whistleblowing');
    }

    private function fakeDriver(?AttachmentScanStatus $verdict): void {
        $this->app->instance(ScanDriver::class, new class($verdict) implements ScanDriver {
            public function __construct(private readonly ?AttachmentScanStatus $verdict) {}

            public function scan(string $absolutePath, ?string $mime): ?AttachmentScanStatus {
                return $this->verdict;
            }
        });
    }

    private function attachment(): Attachment {
        $org = Organization::factory()->create();
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

        return app(WhistleblowingAttachmentService::class)
            ->storeReporterUpload($case, UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'));
    }

    public function test_clean_verdict_releases(): void {
        $this->fakeDriver(AttachmentScanStatus::Clean);
        $attachment = $this->attachment();

        $this->artisan('whistleblowing:scan')->assertExitCode(0);

        $this->assertSame('clean', $attachment->fresh()->scan_status->value);
    }

    public function test_rejected_verdict_quarantines_and_logs_event(): void {
        $this->fakeDriver(AttachmentScanStatus::Rejected);
        $attachment = $this->attachment();

        $this->artisan('whistleblowing:scan')->assertExitCode(0);

        $this->assertSame('rejected', $attachment->fresh()->scan_status->value);
        $this->assertDatabaseHas('whistleblowing_case_events', [
            'case_id' => $attachment->case_id, 'event' => 'attachment.rejected',
        ]);
    }

    public function test_no_verdict_stays_pending(): void {
        $this->fakeDriver(null);
        $attachment = $this->attachment();

        $this->artisan('whistleblowing:scan')->assertExitCode(0);

        $this->assertSame('pending', $attachment->fresh()->scan_status->value);
    }
}
