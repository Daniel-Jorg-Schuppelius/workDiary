<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanIstSummaryReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\User\Permission as P;
use App\Models\{Attendance, Team, User, WorkSchedule};
use App\Services\Reporting\PlanIstReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 38: Plan/Ist Team-/Org-Sicht — Rechte-Matrix, Summen == Einzelwerte,
 * Drilldown-Scoping.
 */
class PlanIstSummaryReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function seedUserWithPlanAndAttendance(User $user): void {
        WorkSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'core_start' => '08:00:00',
            'core_end' => '16:30:00',
            'frame_start' => '06:00:00',
            'frame_end' => '20:00:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2024-01-01',
            'valid_to' => null,
        ]);
        Attendance::withoutEvents(function () use ($user): void {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => '2024-01-15',
                'started_at' => '2024-01-15 08:00:00',
                'ended_at' => '2024-01-15 16:30:00',
                'duration_minutes' => 480,
            ]);
        });
    }

    public function test_summary_totals_equal_individual_rows(): void {
        $a = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $b = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->seedUserWithPlanAndAttendance($a);
        $this->seedUserWithPlanAndAttendance($b);

        $builder = app(PlanIstReportBuilder::class);
        $from = CarbonImmutable::create(2024, 1, 15) ?? CarbonImmutable::now();
        $to = $from;

        $summary = $builder->presenceSummaryFor([$a, $b], $from, $to);

        $expectedPlan = 0;
        $expectedActual = 0;
        foreach ([$a, $b] as $user) {
            $days = $builder->presenceFor($user, $from, $to);
            $expectedPlan += array_sum(array_column($days, 'plan_minutes'));
            $expectedActual += array_sum(array_column($days, 'actual_minutes'));
        }

        $this->assertSame($expectedPlan, $summary['totals']['plan_minutes']);
        $this->assertSame($expectedActual, $summary['totals']['actual_minutes']);
        $this->assertCount(2, $summary['rows']);
    }

    public function test_team_and_org_views_enforce_permissions(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)->get(route('reports.plan-ist.team'))->assertForbidden();
        $this->actingAs($plain)->get(route('reports.plan-ist.organization'))->assertForbidden();

        $lead = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $lead->givePermissionTo(P::ReportPresenceTeam->value);
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach([$lead->id, $plain->id]);

        $this->actingAs($lead)->get(route('reports.plan-ist.team'))->assertOk();
        // Team-Recht deckt keine Org-Sicht.
        $this->actingAs($lead)->get(route('reports.plan-ist.organization'))->assertForbidden();

        $hr = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $hr->givePermissionTo(P::ReportPresenceOrganization->value);
        $this->actingAs($hr)->get(route('reports.plan-ist.organization'))->assertOk();
    }

    public function test_presence_drilldown_is_scoped_to_own_teams(): void {
        $lead = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $lead->givePermissionTo(P::ReportPresenceTeam->value);

        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $outsider = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach([$lead->id, $member->id]);

        // Mitglied des eigenen Teams: erlaubt.
        $this->actingAs($lead)
            ->get(route('reports.plan-ist.presence', ['user' => $member->sqid]))
            ->assertOk()
            ->assertSee($member->name);

        // Kein gemeinsames Team: verboten.
        $this->actingAs($lead)
            ->get(route('reports.plan-ist.presence', ['user' => $outsider->sqid]))
            ->assertForbidden();

        // Ohne jedes Recht: fremde Sicht verboten.
        $this->actingAs($member)
            ->get(route('reports.plan-ist.presence', ['user' => $lead->sqid]))
            ->assertForbidden();
    }
}
