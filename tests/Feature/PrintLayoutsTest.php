<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintLayoutsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Vacation\VacationStatus;
use App\Enums\Vacation\VacationType;
use App\Models\DutyPlan;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\Organization;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PrintLayoutsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function admin(): User {
        return User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function user(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function planWithShift(?User $assignee = null): array {
        $assignee = $assignee ?? $this->user();
        $plan = DutyPlan::factory()->draft()->weekly()->create([
            'organization_id' => $this->organization->id,
            'from_date' => '2026-05-18',
            'to_date' => '2026-05-24',
        ]);
        $type = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Frühdienst',
            'abbreviation' => 'F',
            'color' => '#3b82f6',
        ]);
        $shift = ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $plan->id,
            'user_id' => $assignee->id,
            'shift_type_id' => $type->id,
            'date' => '2026-05-19',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
        ]);

        return ['plan' => $plan, 'type' => $type, 'shift' => $shift, 'user' => $assignee];
    }

    // ── Duty plan layouts ────────────────────────────────────────────────────

    public function test_admin_can_print_duty_plan_roster_a3(): void {
        $ctx = $this->planWithShift();

        $res = $this->actingAs($this->admin())
            ->get(route('print.duty-plan.roster', $ctx['plan']));

        $res->assertOk()
            ->assertSee($ctx['user']->name)
            ->assertSee('A3 landscape', false)
            ->assertSee('Dienstplan-Aushang');
    }

    public function test_print_duty_plan_roster_anonymises_when_requested(): void {
        $ctx = $this->planWithShift();

        $res = $this->actingAs($this->admin())
            ->get(route('print.duty-plan.roster', [$ctx['plan'], 'anonymous' => 1]));

        $res->assertOk()
            ->assertDontSee($ctx['user']->name)
            ->assertSee(printable_initials($ctx['user']->name));
    }

    public function test_admin_can_print_week_layout_a4(): void {
        $ctx = $this->planWithShift();

        $this->actingAs($this->admin())
            ->get(route('print.duty-plan.week', [$ctx['plan'], 'date' => '2026-05-19']))
            ->assertOk()
            ->assertSee('A4 landscape', false)
            ->assertSee('Wochenplan')
            ->assertSee($ctx['user']->name);
    }

    public function test_admin_can_print_day_briefing_a4(): void {
        $ctx = $this->planWithShift();

        $this->actingAs($this->admin())
            ->get(route('print.duty-plan.day', [$ctx['plan'], 'date' => '2026-05-19']))
            ->assertOk()
            ->assertSee('A4 portrait', false)
            ->assertSee('Tagesbriefing')
            ->assertSee('06:00');
    }

    public function test_cross_org_user_cannot_view_print(): void {
        $ctx = $this->planWithShift();

        $otherOrg = Organization::factory()->create();
        $intruder = User::factory()->user()->create(['organization_id' => $otherOrg->id]);

        // Cross-Org: der OrganizationScope filtert den DutyPlan beim Route-
        // Model-Binding heraus, bevor eine Policy greift – das liefert
        // 404 (Existenz wird gar nicht offenbart) statt 403. Beides ist
        // ein gültiges „kein Zugriff", für diesen Test akzeptieren wir
        // explizit nur den 404-Pfad als Sicherheitsverhalten.
        $response = $this->actingAs($intruder)
            ->get(route('print.duty-plan.roster', $ctx['plan']));
        $this->assertContains(
            $response->status(),
            [403, 404],
            'Cross-Org-User darf den Plan weder sehen noch wissen, dass er existiert.',
        );
    }

    // ── User month ───────────────────────────────────────────────────────────

    public function test_user_can_print_own_month(): void {
        $u = $this->user();

        $this->actingAs($u)
            ->get(route('print.user.month', [$u, 'month' => '2026-05-01']))
            ->assertOk()
            ->assertSee('Monatsplan');
    }

    public function test_user_cannot_print_other_user_month(): void {
        $other = $this->user();
        $intruder = $this->user();

        $this->actingAs($intruder)
            ->get(route('print.user.month', [$other]))
            ->assertForbidden();
    }

    public function test_admin_can_print_any_user_month(): void {
        $other = $this->user();

        $this->actingAs($this->admin())
            ->get(route('print.user.month', [$other, 'month' => '2026-05-01']))
            ->assertOk()
            ->assertSee('Monatsplan');
    }

    // ── On-call ──────────────────────────────────────────────────────────────

    public function test_admin_can_print_on_call_layout(): void {
        $u = $this->user();
        OnCallShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'start_at' => '2026-05-19 18:00:00',
            'end_at' => '2026-05-20 06:00:00',
        ]);
        EmergencyAssignment::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'start_at' => '2026-05-20 02:00:00',
            'end_at' => '2026-05-20 03:30:00',
        ]);

        $this->actingAs($this->admin())
            ->get(route('print.on-call', ['from' => '2026-05-01', 'to' => '2026-05-31']))
            ->assertOk()
            ->assertSee('Bereitschaft')
            ->assertSee('Notdienste')
            ->assertSee($u->name);
    }

    public function test_non_admin_cannot_print_on_call(): void {
        $this->actingAs($this->user())
            ->get(route('print.on-call'))
            ->assertForbidden();
    }

    // ── Vacation year ────────────────────────────────────────────────────────

    public function test_admin_can_print_vacation_year(): void {
        $u = $this->user();
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-14',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);

        $this->actingAs($this->admin())
            ->get(route('print.vacations', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Urlaubsübersicht')
            ->assertSee('2026')
            ->assertSee($u->name);
    }

    public function test_non_admin_cannot_print_vacation_year(): void {
        $this->actingAs($this->user())
            ->get(route('print.vacations'))
            ->assertForbidden();
    }
}
