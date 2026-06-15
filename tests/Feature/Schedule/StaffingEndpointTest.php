<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StaffingEndpointTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\DutyPlanStatus;
use App\Models\{CoverageRequirement, DutyPlan, ShiftType, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithGlobalDateRange;
use Tests\TestCase;

class StaffingEndpointTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;

    public function test_planner_gets_suggestions_via_endpoint(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;
        $type = ShiftType::factory()->create(['organization_id' => $orgId]);
        User::factory()->user()->create(['organization_id' => $orgId]);

        $this->actingAs($admin)
            ->getJson(route('schedule.suggest', [
                'date' => '2026-07-06',
                'shift_type_id' => $type->sqid,
            ]))
            ->assertOk()
            ->assertJsonStructure(['suggestions']);
    }

    public function test_user_without_staffing_permission_is_forbidden(): void {
        // Rolle aussendienst hat kein staffing.suggest.
        $user = User::factory()->aussendienst()->create();
        $type = ShiftType::factory()->create(['organization_id' => $user->organization_id]);

        $this->actingAs($user)
            ->getJson(route('schedule.suggest', [
                'date' => '2026-07-06',
                'shift_type_id' => $type->sqid,
            ]))
            ->assertForbidden();
    }

    public function test_schedule_index_exposes_open_slots_for_understaffing(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;
        $type = ShiftType::factory()->create(['organization_id' => $orgId]);

        $plan = DutyPlan::factory()->create([
            'organization_id' => $orgId,
            'from_date' => '2026-07-06',
            'to_date' => '2026-07-12',
            'status' => DutyPlanStatus::Published->value,
            'min_staff' => 0,
        ]);
        // Soll: 2 für Montag, Ist: 0 → Unterdeckung.
        CoverageRequirement::factory()->forWeekday(1)->create([
            'organization_id' => $orgId,
            'duty_plan_id' => $plan->id,
            'shift_type_id' => $type->id,
            'min_staff' => 2,
        ]);

        $response = $this->actingAs($admin)
            ->withSession($this->dateRangeMonth(2026, 7))
            ->get(route('schedule.index', ['view' => 'month']))
            ->assertOk();

        $openSlots = $response->viewData('openSlotsByDate');
        $this->assertArrayHasKey('2026-07-06', $openSlots);
        $this->assertSame(2, (int) collect($openSlots['2026-07-06'])->sum('missing'));
    }
}
