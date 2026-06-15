<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArbZgComplianceReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Models\{Attendance, TimeCorrectionRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class ArbZgComplianceReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        // Deterministische Zeitzone, damit Stempel-Tage stabil sind.
        $this->setUpOrganization(['timezone' => 'UTC']);
        // Pflichtpausen NICHT automatisch auffüllen, damit ein Pausenverstoß
        // erfasst werden kann (sonst topt Attendance::saving() auf).
        config(['timesheet.breaks.auto_apply' => false]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function attendance(int $userId, string $date, string $start, string $end, int $break = 0): Attendance {
        return Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $userId,
            'date' => $date,
            'started_at' => "$date $start",
            'ended_at' => "$date $end",
            'break_minutes_auto' => 0,
            'break_minutes_manual' => $break,
            'status' => AttendanceStatus::Closed->value,
            'source' => AttendanceSource::Manual->value,
        ]);
    }

    private function render(array $parameters = []): TestResponse {
        return $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.arbzg-compliance', $parameters));
    }

    public function test_admin_can_render(): void {
        $this->render()->assertOk();
    }

    public function test_plain_user_forbidden(): void {
        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.arbzg-compliance'));
        $response->assertForbidden();
    }

    public function test_buchhaltung_may_view(): void {
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($accountant)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.arbzg-compliance'))
            ->assertOk();
    }

    public function test_more_than_ten_hours_per_day_is_reported(): void {
        // 06:00–17:00 = 11 h brutto − 30 min = 10:30 netto > 10 h.
        $this->attendance($this->user->id, '2030-03-04', '06:00:00', '17:00:00', 30);

        $this->render()->assertOk()
            ->assertSee((string) __('compliance.report.kind.maxDailyHours'))
            ->assertSee('10:30 h');
    }

    public function test_missing_break_is_reported(): void {
        // 7 h brutto, nur 10 min Pause ⇒ Pflichtpause 30 min unterschritten.
        $this->attendance($this->user->id, '2030-03-05', '08:00:00', '15:00:00', 10);

        $this->render()->assertOk()
            ->assertSee((string) __('compliance.report.kind.breakMissing'));
    }

    public function test_short_rest_period_is_reported(): void {
        $this->attendance($this->user->id, '2030-03-06', '08:00:00', '20:00:00', 60);
        $this->attendance($this->user->id, '2030-03-07', '06:00:00', '12:00:00', 0);

        $this->render()->assertOk()
            ->assertSee((string) __('compliance.report.kind.restPeriod'));
    }

    public function test_kind_filter_limits_results(): void {
        $this->attendance($this->user->id, '2030-03-04', '06:00:00', '17:00:00', 30); // daily
        $this->attendance($this->user->id, '2030-03-05', '08:00:00', '15:00:00', 10); // break

        $response = $this->render(['kind' => 'breakMissing'])->assertOk();
        $response->assertSee((string) __('compliance.report.kind.breakMissing'));
        $response->assertDontSee('10:30 h');
    }

    public function test_approved_correction_is_flagged(): void {
        $this->attendance($this->user->id, '2030-03-04', '06:00:00', '17:00:00', 30);
        TimeCorrectionRequest::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'requested_by_user_id' => $this->user->id,
            'scope_date' => '2030-03-04',
            'status' => TimeCorrectionStatus::Approved->value,
            'reason' => 'Stempelung vergessen',
        ]);

        $this->render()->assertOk()->assertSee((string) __('compliance.report.corrected'));
    }

    public function test_csv_export_returns_download(): void {
        $this->attendance($this->user->id, '2030-03-04', '06:00:00', '17:00:00', 30);

        $response = $this->render(['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('arbzg_compliance_', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_export_returns_pdf(): void {
        $this->attendance($this->user->id, '2030-03-04', '06:00:00', '17:00:00', 30);

        $response = $this->render(['export' => 'pdf']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_requires_authentication(): void {
        // Guest-Zugriff (ohne actingAs) wird auf Login umgeleitet.
        $this->withSession($this->dateRangeYear(2030))
            ->get(route('reports.arbzg-compliance'))
            ->assertRedirect(route('login'));
    }
}
