<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : GitHub Copilot
 * Filename     : FlexControllerTest.php
 * License      : AGPL-3.0-or-later
 */

namespace Tests\Feature;

use App\Models\{User, WorkSchedule};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class FlexControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_admin_can_select_other_user_via_sqid(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)->get(route('flex.index', [
            'user' => Sqid::encode(User::class, $other->id),
        ]));

        $response->assertOk();
        $response->assertViewHas('user', static fn(User $user): bool => (int) $user->id === (int) $other->id);
    }

    public function test_admin_can_select_other_user_via_numeric_fallback(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)->get(route('flex.index', [
            'user' => (string) $other->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('user', static fn(User $user): bool => (int) $user->id === (int) $other->id);
    }

    public function test_flex_page_shows_model_badge_and_balance_for_flextime(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('flex.index'))
            ->assertOk()
            ->assertViewHas('tracksTarget', true)
            ->assertSee(__('work_schedule.type.flextime'))
            ->assertSee(__('Saldo'));
    }

    public function test_trust_user_sees_attendance_instead_of_balance(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'schedule_type' => 'trust',
            'working_days' => [1, 2, 3, 4, 5],
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2020-01-01',
        ]);

        $this->actingAs($user)->get(route('flex.index'))
            ->assertOk()
            ->assertViewHas('tracksTarget', false)
            ->assertSee(__('work_schedule.type.trust'))
            ->assertSee(__('Anwesenheitstage'));
    }

    public function test_non_admin_cannot_switch_user_via_numeric_fallback(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->get(route('flex.index', [
            'user' => (string) $other->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('user', static fn(User $resolved): bool => (int) $resolved->id === (int) $user->id);
    }
}
