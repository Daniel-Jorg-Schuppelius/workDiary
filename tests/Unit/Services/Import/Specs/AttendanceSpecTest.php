<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Import\ImportErrorCode;
use App\Models\{Attendance, DayClosure, User};
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\AttendanceSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AttendanceSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
    }

    private function spec(): AttendanceSpec {
        return app(AttendanceSpec::class);
    }

    public function test_normalize_parses_email_date_and_time(): void {
        $row = $this->spec()->normalize([
            'user_email' => '  Max@Example.COM ',
            'date' => '28.05.2026',
            'start_time' => '9:05',
            'end_time' => '17:30',
        ]);

        $this->assertSame('max@example.com', $row['user_email']);
        $this->assertSame('2026-05-28', $row['date']);
        $this->assertSame('09:05', $row['start_time']);
        $this->assertSame('17:30', $row['end_time']);
    }

    public function test_validate_row_flags_required_start_time(): void {
        $issues = $this->spec()->normalize(['user_email' => 'a@b.de', 'date' => '2026-05-28']);
        $result = $this->spec()->validateRow($issues, $this->organization);
        $codes = array_map(static fn ($i) => $i->code, $result);

        $this->assertContains(ImportErrorCode::Required, $codes);
    }

    public function test_upsert_creates_closed_attendance_with_import_source(): void {
        $user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);

        $row = $this->spec()->normalize([
            'user_email' => 'worker@example.com',
            'date' => '2026-07-01',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        [$outcome, $issue] = $this->spec()->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome);
        $this->assertNull($issue);

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(AttendanceSource::Import, $attendance->source);
        $this->assertSame('2026-07-01', $attendance->date->format('Y-m-d'));
        $this->assertNotNull($attendance->ended_at);
        // 8 h brutto abzüglich (0 oder 30 min ArbZG-Pause).
        $this->assertGreaterThanOrEqual(450, $attendance->duration_minutes);
        $this->assertLessThanOrEqual(480, $attendance->duration_minutes);
    }

    public function test_reimport_with_same_external_id_updates_not_duplicates(): void {
        User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);

        $base = [
            'user_email' => 'worker@example.com',
            'date' => '2026-07-01',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'external_id' => 'clock-42',
        ];

        [$first] = $this->spec()->upsert($this->spec()->normalize($base), $this->organization);
        [$second] = $this->spec()->upsert($this->spec()->normalize(['end_time' => '17:00'] + $base), $this->organization);

        $this->assertSame(ImportOutcome::Created, $first);
        $this->assertSame(ImportOutcome::Updated, $second);
        $this->assertSame(1, Attendance::query()->where('organization_id', $this->organization->id)->count());
    }

    public function test_locked_day_is_skipped_with_period_locked_error(): void {
        $user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);

        // Geschlossener Tagesabschluss → Sperre.
        DayClosure::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'day' => '2026-07-01',
            'status' => \App\Enums\TimeApproval\DayClosureStatus::Closed,
        ]);

        $row = $this->spec()->normalize([
            'user_email' => 'worker@example.com',
            'date' => '2026-07-01',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        [$outcome, $issue] = $this->spec()->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Failed, $outcome);
        $this->assertSame(ImportErrorCode::PeriodLocked, $issue->code);
        $this->assertSame(0, Attendance::query()->where('user_id', $user->id)->count());
    }
}
