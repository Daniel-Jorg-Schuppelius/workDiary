<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UtilizationReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Project, ReportTarget, TimeEntry, User, WorkSchedule};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Auslastung & Realisierung (MVP-467): Auslastung = erfasst/Soll (WorkBalance),
 * abrechenbare Quote = billable/erfasst, Realisierung ehrlich null ohne lokale
 * Fakturierung; Bullet-Serie gegen den org-weiten Zielwert.
 *
 * Fixture: Arbeitszeitmodell 480 min × Mo–Fr, Woche 2030-01-07 (Mo) bis
 * 2030-01-13 (So) → Soll 2400. Erfasst: Mo+Di abrechenbar (960), Mi intern
 * (480) → Auslastung 60 %, abrechenbare Quote 66,7 %.
 */
class UtilizationReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->worker = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Willi Worker',
        ]);

        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'frame_start' => '06:00',
            'frame_end' => '20:00',
            'core_start' => '09:00',
            'core_end' => '15:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2030-01-01',
        ]);

        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Projekt Auslastung',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        foreach ([['2030-01-07', true], ['2030-01-08', true], ['2030-01-09', false]] as [$day, $billable]) {
            TimeEntry::create([
                'organization_id' => $this->organization->id,
                'project_id' => $project->id,
                'user_id' => $this->worker->id,
                'date' => $day,
                'started_at' => $day . ' 08:00:00',
                'ended_at' => $day . ' 16:00:00',
                'kind' => TimeEntryKind::Work->value,
                'billable' => $billable,
            ]);
        }

        ReportTarget::factory()->create([
            'organization_id' => $this->organization->id,
            'metric' => ReportTargetMetric::Utilization,
            'scope' => ReportTargetScope::Org,
            'target_value' => 80.0,
        ]);
    }

    public function test_rates_follow_work_balance_and_billable_definitions(): void {
        // setUpOrganization bringt weitere Nutzer mit Default-Soll mit —
        // der User-Filter isoliert die Fixbeispiel-Rechnung auf Willi.
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-07', '2030-01-13'))
            ->get(route('reports.utilization', ['user' => \App\Support\Sqid::encode(User::class, $this->worker->id)]));

        $response->assertOk();
        $totals = $response->viewData('totals');
        $this->assertSame(2400, $totals['targetMinutes']);
        $this->assertSame(1440, $totals['trackedMinutes']);
        $this->assertSame(960, $totals['billableMinutes']);
        $this->assertSame(60.0, $totals['utilization']);
        $this->assertSame(66.7, $totals['billableRate']);
        // Ohne lokale Fakturierung keine Realisierungs-Datenbasis.
        $this->assertFalse($response->viewData('hasInvoiceData'));
        $this->assertNull($totals['realization']);

        $rows = $response->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertSame('Willi Worker', $rows[0]['userName']);
    }

    public function test_bullet_series_resolves_org_target(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-07', '2030-01-13'))
            ->get(route('reports.utilization', ['user' => \App\Support\Sqid::encode(User::class, $this->worker->id)]));

        $series = $response->viewData('bulletSeries');
        $this->assertSame([['x' => 'Willi Worker', 'y' => 60.0, 'target' => 80.0]], $series);

        $orgEval = $response->viewData('orgEval');
        $this->assertNotNull($orgEval);
        $this->assertFalse($orgEval['met']);
        $this->assertSame(80.0, $orgEval['target']);
    }

    public function test_monthly_boxplot_covers_per_user_distribution(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-07', '2030-01-13'))
            ->get(route('reports.utilization', ['user' => \App\Support\Sqid::encode(User::class, $this->worker->id)]));

        $boxes = $response->viewData('boxSeries');
        $this->assertCount(1, $boxes);
        $this->assertSame('2030-01', $boxes[0]['x']);
        $this->assertSame(1, $boxes[0]['n']);
        $this->assertSame(60.0, $boxes[0]['median']);
    }

    public function test_plain_user_without_view_any_is_forbidden(): void {
        $this->actingAs($this->worker)
            ->withSession($this->dateRangeSession('2030-01-07', '2030-01-13'))
            ->get(route('reports.utilization'))
            ->assertForbidden();
    }
}
