<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageRequirementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\CoverageRequirement;
use App\Models\DutyPlan;
use App\Models\Organization;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\CoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CoverageRequirementTest extends TestCase {
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

    /**
     * @return array{plan: DutyPlan, fruh: ShiftType, spaet: ShiftType}
     */
    private function planWithTypes(): array {
        $plan = DutyPlan::factory()->draft()->weekly()->create([
            'organization_id' => $this->organization->id,
            'from_date' => '2026-05-18', // Monday
            'to_date' => '2026-05-24', // Sunday
            'min_staff' => 0,
        ]);
        $fruh = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Frühdienst',
            'abbreviation' => 'F',
            'color' => '#3b82f6',
        ]);
        $spaet = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Spätdienst',
            'abbreviation' => 'S',
            'color' => '#f59e0b',
        ]);

        return ['plan' => $plan, 'fruh' => $fruh, 'spaet' => $spaet];
    }

    // ── HTTP CRUD ────────────────────────────────────────────────────────────

    public function test_admin_sees_index_page(): void {
        $ctx = $this->planWithTypes();

        $res = $this->actingAs($this->admin())
            ->get(route('duty-plans.coverage.index', $ctx['plan']));

        $res->assertOk()->assertSee('Soll-Besetzung');
    }

    public function test_non_admin_cannot_create(): void {
        $ctx = $this->planWithTypes();

        $res = $this->actingAs($this->user())
            ->post(route('duty-plans.coverage.store', $ctx['plan']), [
                'shift_type_id' => $ctx['fruh']->id,
                'weekday' => 1,
                'min_staff' => 1,
            ]);

        $res->assertForbidden();
        $this->assertDatabaseCount('coverage_requirements', 0);
    }

    public function test_admin_can_create_requirement(): void {
        $ctx = $this->planWithTypes();

        $res = $this->actingAs($this->admin())
            ->post(route('duty-plans.coverage.store', $ctx['plan']), [
                'shift_type_id' => $ctx['fruh']->id,
                'weekday' => 1,
                'min_staff' => 2,
                'max_staff' => 4,
                'notes' => 'Mo Frühdienst',
            ]);

        $res->assertRedirect(route('duty-plans.coverage.index', $ctx['plan']));
        $this->assertDatabaseHas('coverage_requirements', [
            'duty_plan_id' => $ctx['plan']->id,
            'shift_type_id' => $ctx['fruh']->id,
            'weekday' => 1,
            'min_staff' => 2,
            'max_staff' => 4,
        ]);
    }

    public function test_admin_can_update_and_delete_requirement(): void {
        $ctx = $this->planWithTypes();
        $req = CoverageRequirement::factory()
            ->forWeekday(1)
            ->create([
                'organization_id' => $this->organization->id,
                'duty_plan_id' => $ctx['plan']->id,
                'shift_type_id' => $ctx['fruh']->id,
                'min_staff' => 1,
            ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('duty-plans.coverage.update', [$ctx['plan'], $req]), [
                'shift_type_id' => $ctx['fruh']->id,
                'weekday' => 1,
                'min_staff' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('coverage_requirements', ['id' => $req->id, 'min_staff' => 3]);

        $this->actingAs($admin)
            ->delete(route('duty-plans.coverage.destroy', [$ctx['plan'], $req]))
            ->assertRedirect();

        $this->assertDatabaseMissing('coverage_requirements', ['id' => $req->id]);
    }

    // ── Cross-org isolation ──────────────────────────────────────────────────

    public function test_requirements_from_other_org_are_not_returned_by_service(): void {
        $ctx = $this->planWithTypes();

        // Eigene Anforderung im Plan (Mo, min=2).
        CoverageRequirement::factory()->forWeekday(1)->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'shift_type_id' => $ctx['fruh']->id,
            'min_staff' => 2,
        ]);

        // Fremde Org legt eigene Anforderung mit höherer Priorität an (specific_date).
        $otherOrg = Organization::factory()->create();
        CoverageRequirement::withoutGlobalScopes()
            ->getQuery()
            ->insert([
                'organization_id' => $otherOrg->id,
                'duty_plan_id' => null,
                'shift_type_id' => $ctx['fruh']->id,
                'specific_date' => '2026-05-18',
                'min_staff' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        // Fremde Anforderung darf das Soll der eigenen Org nicht beeinflussen.
        $this->actingAs($this->admin());
        $svc = app(CoverageService::class);
        $req = $svc->requirementsFor($ctx['plan']);

        $this->assertSame(2, $req['2026-05-18'][$ctx['fruh']->id]['min']);
    }

    // ── Service: Soll-/Ist-/Gap-Logik ────────────────────────────────────────

    public function test_service_uses_plan_min_staff_when_no_requirement(): void {
        $ctx = $this->planWithTypes();
        $ctx['plan']->update(['min_staff' => 1]);

        // mind. eine Schicht muss existieren, sonst sind keine Schichttypen "im Plan"
        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'user_id' => $this->user()->id,
            'shift_type_id' => $ctx['fruh']->id,
            'date' => '2026-05-18',
        ]);

        $svc = app(CoverageService::class);
        $req = $svc->requirementsFor($ctx['plan']);

        // Jeder Tag im Plan muss min=1 für Frühdienst fordern.
        $this->assertSame(1, $req['2026-05-18'][$ctx['fruh']->id]['min']);
        $this->assertSame(1, $req['2026-05-22'][$ctx['fruh']->id]['min']);
    }

    public function test_service_weekday_overrides_plan_default(): void {
        $ctx = $this->planWithTypes();
        $ctx['plan']->update(['min_staff' => 1]);

        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'user_id' => $this->user()->id,
            'shift_type_id' => $ctx['fruh']->id,
            'date' => '2026-05-18',
        ]);

        // Mo (weekday=1) braucht 3 Frühdienst.
        CoverageRequirement::factory()->forWeekday(1)->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'shift_type_id' => $ctx['fruh']->id,
            'min_staff' => 3,
        ]);

        $svc = app(CoverageService::class);
        $req = $svc->requirementsFor($ctx['plan']);

        $this->assertSame(3, $req['2026-05-18'][$ctx['fruh']->id]['min']);  // Mo
        $this->assertSame(1, $req['2026-05-19'][$ctx['fruh']->id]['min']);  // Di → Plan-Default
    }

    public function test_specific_date_overrides_weekday(): void {
        $ctx = $this->planWithTypes();

        CoverageRequirement::factory()->forWeekday(1)->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'shift_type_id' => $ctx['fruh']->id,
            'min_staff' => 2,
        ]);
        CoverageRequirement::factory()->forDate('2026-05-18')->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'shift_type_id' => $ctx['fruh']->id,
            'min_staff' => 5,
        ]);

        $svc = app(CoverageService::class);
        $req = $svc->requirementsFor($ctx['plan']);

        $this->assertSame(5, $req['2026-05-18'][$ctx['fruh']->id]['min']);
    }

    public function test_actual_staffing_counts_only_published_or_confirmed(): void {
        $ctx = $this->planWithTypes();
        $u = $this->user();

        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'user_id' => $u->id,
            'shift_type_id' => $ctx['fruh']->id,
            'date' => '2026-05-18',
        ]);
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'user_id' => $u->id,
            'shift_type_id' => $ctx['fruh']->id,
            'date' => '2026-05-18',
            'status' => ScheduledShiftStatus::Draft->value,
        ]);
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'user_id' => $u->id,
            'shift_type_id' => $ctx['fruh']->id,
            'date' => '2026-05-18',
            'status' => ScheduledShiftStatus::Cancelled->value,
        ]);

        $svc = app(CoverageService::class);
        $actual = $svc->actualStaffing($ctx['plan']);

        $this->assertSame(1, $actual['2026-05-18'][$ctx['fruh']->id]);
    }

    public function test_gaps_lists_underbesetzte_tage(): void {
        $ctx = $this->planWithTypes();

        // Mo-Fr je 1 Frühdienst gefordert (weekday 1..5).
        foreach ([1, 2, 3, 4, 5] as $wd) {
            CoverageRequirement::factory()->forWeekday($wd)->create([
                'organization_id' => $this->organization->id,
                'duty_plan_id' => $ctx['plan']->id,
                'shift_type_id' => $ctx['fruh']->id,
                'min_staff' => 1,
            ]);
        }

        // Mo (2026-05-18) ist besetzt, andere Werktage nicht.
        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'user_id' => $this->user()->id,
            'shift_type_id' => $ctx['fruh']->id,
            'date' => '2026-05-18',
        ]);

        $svc = app(CoverageService::class);
        $gaps = $svc->gaps($ctx['plan']);

        // 4 Tage offen: Di, Mi, Do, Fr.
        $dates = array_column($gaps, 'date');
        sort($dates);
        $this->assertSame(['2026-05-19', '2026-05-20', '2026-05-21', '2026-05-22'], $dates);
        foreach ($gaps as $g) {
            $this->assertSame('under', $g['severity']);
            $this->assertSame(1, $g['min']);
            $this->assertSame(0, $g['actual']);
        }
    }

    public function test_gaps_lists_überbesetzte_tage_when_max_set(): void {
        $ctx = $this->planWithTypes();
        $u = $this->user();
        $u2 = $this->user();

        CoverageRequirement::factory()->forDate('2026-05-18')->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'shift_type_id' => $ctx['fruh']->id,
            'min_staff' => 1,
            'max_staff' => 1,
        ]);

        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'user_id' => $u->id,
            'shift_type_id' => $ctx['fruh']->id,
            'date' => '2026-05-18',
        ]);
        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $ctx['plan']->id,
            'user_id' => $u2->id,
            'shift_type_id' => $ctx['fruh']->id,
            'date' => '2026-05-18',
        ]);

        $svc = app(CoverageService::class);
        $gaps = $svc->gaps($ctx['plan']);

        $this->assertCount(1, $gaps);
        $this->assertSame('over', $gaps[0]['severity']);
        $this->assertSame(2, $gaps[0]['actual']);
    }
}
