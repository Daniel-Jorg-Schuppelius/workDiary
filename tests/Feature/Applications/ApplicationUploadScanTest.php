<?php

/*
 * Filename     : ApplicationUploadScanTest.php
 * Description  : Quarantäne und Freigabe für Unterlagen aus dem öffentlichen
 *                Karrierebereich.
 */

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Enums\User\UserRole;
use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\Applications\{JobApplication, JobApplicationUpload};
use App\Services\Applications\ApplicationUploadScanService;
use App\Services\Whistleblowing\Scanning\ScanDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Herkunft: Vollscan 2026-09-15, Befund `P6-46`. Eingereichte Unterlagen
 * blieben dauerhaft in Quarantäne — nichts setzte je `clean`, und für die
 * Personalstelle gab es keinen Weg zur Datei.
 */
final class ApplicationUploadScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
    }

    /** Treiber mit festem Urteil, damit der Test nicht von ClamAV abhängt. */
    private function driver(?AttachmentScanStatus $verdict): void {
        app()->instance(ScanDriver::class, new class($verdict) implements ScanDriver {
            public function __construct(private readonly ?AttachmentScanStatus $verdict) {}

            public function scan(string $absolutePath, ?string $mime): ?AttachmentScanStatus {
                return $this->verdict;
            }
        });
    }

    private function application(): JobApplication {
        $hr = $this->userWithRole(UserRole::Personalverwaltung->value);
        $this->actingAs($hr)->post(route('recruiting.applications.store'), [
            'candidate_name' => 'Kim Beispiel',
            'email' => 'kim@example.test',
            'source' => 'website',
        ])->assertRedirect();

        return JobApplication::query()->firstOrFail();
    }

    private function upload(JobApplication $application, string $status = JobApplicationUpload::SCAN_PENDING): JobApplicationUpload {
        $key = 'careers/' . $application->organization_id . '/' . $application->id . '/lebenslauf.pdf';
        Storage::disk('local')->put($key, '%PDF-1.4 Lebenslauf');

        return JobApplicationUpload::query()->create([
            'organization_id' => $application->organization_id,
            'job_application_id' => $application->id,
            'storage_disk' => 'local',
            'storage_key' => $key,
            'original_name' => 'lebenslauf.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 19,
            'sha256' => str_repeat('a', 64),
            'scan_status' => $status,
        ]);
    }

    public function test_clean_verdict_releases_the_upload(): void {
        $upload = $this->upload($this->application());
        $this->driver(AttachmentScanStatus::Clean);

        $stats = app(ApplicationUploadScanService::class)->scanPending();

        $this->assertSame(1, $stats['clean']);
        $this->assertSame(JobApplicationUpload::SCAN_CLEAN, $upload->refresh()->scan_status);
    }

    public function test_rejected_verdict_blocks_the_upload(): void {
        $upload = $this->upload($this->application());
        $this->driver(AttachmentScanStatus::Rejected);

        app(ApplicationUploadScanService::class)->scanPending();

        $this->assertSame(JobApplicationUpload::SCAN_REJECTED, $upload->refresh()->scan_status);
    }

    public function test_without_a_verdict_the_upload_stays_in_quarantine(): void {
        $upload = $this->upload($this->application());
        $this->driver(null);

        $stats = app(ApplicationUploadScanService::class)->scanPending();

        $this->assertSame(1, $stats['skipped']);
        $this->assertSame(JobApplicationUpload::SCAN_PENDING, $upload->refresh()->scan_status);
    }

    public function test_missing_file_is_rejected_instead_of_hanging(): void {
        $application = $this->application();
        $upload = $this->upload($application);
        Storage::disk('local')->delete((string) $upload->storage_key);
        $this->driver(AttachmentScanStatus::Clean);

        app(ApplicationUploadScanService::class)->scanPending();

        $this->assertSame(JobApplicationUpload::SCAN_REJECTED, $upload->refresh()->scan_status);
    }

    public function test_download_is_available_only_after_release(): void {
        $application = $this->application();
        $upload = $this->upload($application);
        $hr = $this->userWithRole(UserRole::Personalverwaltung->value);

        // In Quarantäne: kein Zugriff.
        $this->actingAs($hr)
            ->get(route('recruiting.applications.uploads.download', [$application, $upload]))
            ->assertForbidden();

        $upload->forceFill(['scan_status' => JobApplicationUpload::SCAN_CLEAN])->save();

        $this->actingAs($hr)
            ->get(route('recruiting.applications.uploads.download', [$application, $upload]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_upload_of_a_foreign_application_is_not_reachable(): void {
        $application = $this->application();
        $upload = $this->upload($application, JobApplicationUpload::SCAN_CLEAN);

        $second = JobApplication::query()->create([
            'organization_id' => $this->organization->id,
            'candidate_name' => 'Andere Person',
            'source' => 'website',
            'status' => $application->status,
        ]);
        $hr = $this->userWithRole(UserRole::Personalverwaltung->value);

        $this->actingAs($hr)
            ->get(route('recruiting.applications.uploads.download', [$second, $upload]))
            ->assertNotFound();
    }

    public function test_scan_command_runs(): void {
        $this->driver(AttachmentScanStatus::Clean);

        $this->artisan('recruiting:scan-uploads')->assertExitCode(0);
    }
}
