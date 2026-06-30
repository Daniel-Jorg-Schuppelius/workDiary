<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkScheduleAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Das Arbeitszeit-Modell darf nur von Personalverwaltung + Geschäftsführung
 * (Admin via Bypass) bearbeitet werden; alle anderen sehen es read-only.
 */
class WorkScheduleAccessTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function member(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array<string, mixed> */
    private function payload(): array {
        return [
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2026-01-01',
        ];
    }

    public function test_personnel_admin_can_update_work_schedule(): void {
        $hr = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $member = $this->member();

        $this->actingAs($hr)
            ->put(route('users.work-schedule.update', $member), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('work_schedules', [
            'user_id' => $member->id,
            'weekly_minutes' => 2400,
        ]);
    }

    public function test_management_can_update_work_schedule(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id]);
        $member = $this->member();

        $this->actingAs($gf)
            ->put(route('users.work-schedule.update', $member), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('work_schedules', ['user_id' => $member->id]);
    }

    public function test_regular_user_cannot_edit_or_update_work_schedule(): void {
        $member = $this->member();

        $this->actingAs($member)
            ->get(route('users.work-schedule.edit', $member))
            ->assertForbidden();

        $this->actingAs($member)
            ->put(route('users.work-schedule.update', $member), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('work_schedules', 0);
    }

    public function test_team_lead_can_no_longer_edit_work_schedule(): void {
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $member = $this->member();

        $this->actingAs($lead)
            ->put(route('users.work-schedule.update', $member), $this->payload())
            ->assertForbidden();
    }

    public function test_self_view_is_read_only_for_regular_user(): void {
        $member = $this->member();

        $this->actingAs($member)
            ->get(route('account.work-schedule'))
            ->assertOk()
            ->assertSee(__('Änderungen am Arbeitszeit-Modell können nur die Personalverwaltung oder Geschäftsführung vornehmen.'));
    }
}
