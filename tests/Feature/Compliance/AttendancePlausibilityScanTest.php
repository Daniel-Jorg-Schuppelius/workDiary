<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendancePlausibilityScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Compliance;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\Vacation\VacationStatus;
use App\Models\{Attendance, ComplianceFinding, Organization, User, Vacation, WorkSchedule};
use App\Services\Compliance\AttendancePlausibilityScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Plausibilitäts-Scan der Stempelzeiten (MVP-519, „Ungeklärte Fälle"):
 * vergessene Geht-Stempelung, Stempelung an freiem Tag / trotz Abwesenheit,
 * Rahmenzeit-Überschreitung mit Bagatellgrenze — persistiert als eigene
 * Befund-Kategorie über den gemeinsamen Recorder (Dedup, kein Cross-Close
 * mit dem ArbZG-Lauf).
 */
class AttendancePlausibilityScanTest extends TestCase {
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        // Fixe „Jetzt" NACH den Testtagen, damit offene Anwesenheiten als
        // „über den Tag hinaus offen" gelten und das Scan-Fenster passt.
        $this->travelTo(Carbon::parse('2026-06-10 12:00:00'));
        config()->set('app.display_timezone', 'UTC');
        config(['timesheet.breaks.auto_apply' => false]);
        $this->organization = Organization::factory()->create(['timezone' => 'UTC']);
        app()->instance('currentOrganization', $this->organization);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        // Mo–Fr, Rahmenzeit 06:00–20:00.
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'frame_start' => '06:00',
            'frame_end' => '20:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2020-01-01',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function attendance(array $attributes = []): Attendance {
        return Attendance::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-05', // Freitag
            'started_at' => '2026-06-05 08:00:00',
            'ended_at' => '2026-06-05 16:00:00',
            'status' => AttendanceStatus::Closed->value,
            'source' => AttendanceSource::Manual->value,
        ], $attributes));
    }

    private function scan(): void {
        $this->artisan('compliance:scan-findings', ['--days' => 30])->assertExitCode(0);
    }

    private function findings(string $kind): int {
        return ComplianceFinding::query()
            ->where('category', AttendancePlausibilityScanService::CATEGORY)
            ->where('rule_code', $kind)
            ->count();
    }

    public function test_missing_checkout_is_detected(): void {
        $this->attendance([
            'ended_at' => null,
            'status' => AttendanceStatus::Open->value,
        ]);

        $this->scan();

        $this->assertSame(1, $this->findings(AttendancePlausibilityScanService::KIND_MISSING_CHECKOUT));
    }

    public function test_open_attendance_today_is_not_flagged(): void {
        $this->attendance([
            'date' => '2026-06-10',
            'started_at' => '2026-06-10 08:00:00',
            'ended_at' => null,
            'status' => AttendanceStatus::Open->value,
        ]);

        $this->scan();

        $this->assertSame(0, $this->findings(AttendancePlausibilityScanService::KIND_MISSING_CHECKOUT));
    }

    public function test_stamp_on_free_day_is_detected(): void {
        // Samstag — kein Arbeitstag laut Modell, kein geplanter Dienst.
        $this->attendance([
            'date' => '2026-06-06',
            'started_at' => '2026-06-06 08:00:00',
            'ended_at' => '2026-06-06 12:00:00',
        ]);

        $this->scan();

        $this->assertSame(1, $this->findings(AttendancePlausibilityScanService::KIND_FREE_DAY_STAMP));
    }

    public function test_stamp_during_approved_vacation_is_detected(): void {
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'start_date' => '2026-06-04',
            'end_date' => '2026-06-05',
            'type' => \App\Enums\Vacation\VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);
        $this->attendance();

        $this->scan();

        $this->assertSame(1, $this->findings(AttendancePlausibilityScanService::KIND_ABSENCE_STAMP));
        // Abwesenheit hat Vorrang — kein zusätzlicher Frei-Tag-Befund.
        $this->assertSame(0, $this->findings(AttendancePlausibilityScanService::KIND_FREE_DAY_STAMP));
    }

    public function test_frame_time_exceeded_beyond_tolerance_is_detected(): void {
        // 05:00–16:00: 60 Minuten vor Rahmenbeginn 06:00 > 15 Min. Bagatelle.
        $this->attendance([
            'started_at' => '2026-06-05 05:00:00',
        ]);

        $this->scan();

        $this->assertSame(1, $this->findings(AttendancePlausibilityScanService::KIND_FRAME_TIME));
        $finding = ComplianceFinding::query()
            ->where('rule_code', AttendancePlausibilityScanService::KIND_FRAME_TIME)
            ->firstOrFail();
        $this->assertSame(60, $finding->detected_value);
        $this->assertSame(15, $finding->threshold_value);
    }

    public function test_frame_time_within_tolerance_is_not_flagged(): void {
        // 05:50–16:00: 10 Minuten vor Rahmenbeginn ≤ 15 Min. Bagatelle.
        $this->attendance([
            'started_at' => '2026-06-05 05:50:00',
        ]);

        $this->scan();

        $this->assertSame(0, $this->findings(AttendancePlausibilityScanService::KIND_FRAME_TIME));
    }

    public function test_second_scan_does_not_duplicate_and_does_not_cross_close(): void {
        // Plausibilität + ArbZG gleichzeitig: 05:00–17:00 ohne Pause verletzt
        // auch Tageshöchstarbeitszeit — beide Kategorien müssen nebeneinander
        // bestehen bleiben (kein Auto-„behoben" über Kategoriegrenzen).
        $this->attendance([
            'started_at' => '2026-06-05 05:00:00',
            'ended_at' => '2026-06-05 17:00:00',
        ]);

        $this->scan();
        $this->scan();

        $this->assertSame(1, $this->findings(AttendancePlausibilityScanService::KIND_FRAME_TIME));
        $this->assertGreaterThanOrEqual(1, ComplianceFinding::query()->where('category', 'arbzg')->count());
        $this->assertSame(0, ComplianceFinding::query()->where('status', 'resolved')->count());
    }

    public function test_disabled_rule_suppresses_finding(): void {
        $this->organization->update([
            'settings' => array_merge($this->organization->settings ?? [], [
                'compliance' => ['rules' => ['plausibility_frame_time' => false]],
            ]),
        ]);
        app()->instance('currentOrganization', $this->organization->fresh());

        $this->attendance([
            'started_at' => '2026-06-05 05:00:00',
        ]);

        $this->scan();

        $this->assertSame(0, $this->findings(AttendancePlausibilityScanService::KIND_FRAME_TIME));
    }
}
