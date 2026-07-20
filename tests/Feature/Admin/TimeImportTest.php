<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Import\{ImportErrorCode, ImportRunState};
use App\Models\{Attendance, DayClosure, ImportRun, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-438 — Zeiterfassungs-Import (Stempelungen) über den MVP-049-Wizard,
 * End-to-End inkl. iCal-Quelle und GoBD-Sperr-Schutz.
 */
class TimeImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        Storage::fake('local');
    }

    private function runImport(User $admin, UploadedFile $file): ImportRun {
        $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), ['entity' => 'attendances', 'file' => $file])
            ->assertRedirect();

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);

        $this->actingAs($admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        return $run->refresh();
    }

    public function test_ical_attendance_import_creates_records_with_import_source(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'worker@example.com']);

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n" .
            "BEGIN:VEVENT\r\nUID:evt-1\r\nDTSTART:20260701T060000Z\r\nDTEND:20260701T140000Z\r\n" .
            "SUMMARY:Baustelle\r\nORGANIZER:mailto:worker@example.com\r\nEND:VEVENT\r\n" .
            "END:VCALENDAR\r\n";

        // Echter Upload mit text/calendar (wie der Browser) — der Fake-Guesser
        // erkennt .ics nicht zuverlässig.
        $path = tempnam(sys_get_temp_dir(), 'ics_') . '.ics';
        file_put_contents($path, $ics);
        $run = $this->runImport($admin, new UploadedFile($path, 'att.ics', 'text/calendar', null, true));

        $this->assertContains($run->state, [ImportRunState::Succeeded, ImportRunState::Running]);
        $attendance = Attendance::query()->firstOrFail();
        $this->assertSame(AttendanceSource::Import, $attendance->source);
        $this->assertSame('2026-07-01', $attendance->date->format('Y-m-d'));
    }

    public function test_csv_import_skips_locked_day_and_imports_the_rest(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'worker@example.com']);

        DayClosure::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'day' => '2026-07-01',
            'status' => \App\Enums\TimeApproval\DayClosureStatus::Closed,
        ]);

        $csv = "user_email;date;start_time;end_time\n" .
            "worker@example.com;2026-07-01;08:00;16:00\n" .   // gesperrt → skip
            "worker@example.com;2026-07-02;08:00;16:00\n";     // frei → import

        $run = $this->runImport($admin, UploadedFile::fake()->createWithContent('att.csv', $csv));

        $this->assertSame(1, $run->rows_created);
        $this->assertGreaterThanOrEqual(1, $run->rows_failed);
        $this->assertSame(ImportRunState::Partial, $run->state);

        $this->assertSame(1, Attendance::query()->where('user_id', $user->id)->count());
        $this->assertTrue(
            $run->errors()->get()->contains(fn ($e) => $e->code === ImportErrorCode::PeriodLocked),
            'Erwartete PeriodLocked-Fehlerzeile fehlt.',
        );
    }

    public function test_non_admin_without_permission_cannot_open_attendance_import(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.imports.create', ['entity' => 'attendances']))
            ->assertForbidden();
    }
}
