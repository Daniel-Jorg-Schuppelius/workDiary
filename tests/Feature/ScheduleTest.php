<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\ScheduledShift;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    // ── Index view ──────────────────────────────────────────────────────────

    public function test_guest_cannot_access_schedule(): void {
        $this->get(route('schedule.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_schedule_week(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('schedule.index', ['view' => 'week']))
            ->assertOk()
            ->assertViewIs('schedule.index')
            ->assertViewHas('view', 'week');
    }

    public function test_user_can_view_schedule_month(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('schedule.index', ['view' => 'month']))
            ->assertOk()
            ->assertViewHas('view', 'month');
    }

    // ── CRUD (admin only) ────────────────────────────────────────────────────

    public function test_regular_user_cannot_create_shift(): void {
        $user = User::factory()->user()->create();
        $target = User::factory()->user()->create();

        $this->actingAs($user)
            ->postJson(route('schedule.shifts.store'), [
                'user_id' => $target->id,
                'date' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_shift(): void {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->user()->create();

        $this->actingAs($admin)
            ->postJson(route('schedule.shifts.store'), [
                'user_id' => $target->id,
                'date' => now()->toDateString(),
                'status' => 'draft',
            ])
            ->assertCreated()
            ->assertJsonPath('user_id', $target->id);
    }

    public function test_admin_can_update_shift(): void {
        $admin = User::factory()->admin()->create();
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->putJson(route('schedule.shifts.update', $shift), [
                'date' => now()->addDay()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('date', now()->addDay()->toDateString());
    }

    public function test_admin_can_delete_shift(): void {
        $admin = User::factory()->admin()->create();
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('schedule.shifts.destroy', $shift))
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('scheduled_shifts', ['id' => $shift->id]);
    }

    // ── Shift types ──────────────────────────────────────────────────────────

    public function test_admin_can_create_shift_type(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('schedule.types.store'), [
                'name' => 'Frühschicht',
                'abbreviation' => 'F',
                'color' => '#22c55e',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Frühschicht');

        $this->assertDatabaseHas('shift_types', ['abbreviation' => 'F']);
    }

    public function test_regular_user_cannot_create_shift_type(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->postJson(route('schedule.types.store'), [
                'name' => 'Test',
                'abbreviation' => 'T',
                'color' => '#000000',
            ])
            ->assertForbidden();
    }

    // ── Confirm (own user) ───────────────────────────────────────────────────

    public function test_user_can_confirm_own_shift(): void {
        $user = User::factory()->user()->create();
        $shift = ScheduledShift::factory()
            ->published()
            ->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson(route('schedule.shifts.confirm', $shift))
            ->assertOk()
            ->assertJsonPath('status', ScheduledShiftStatus::Confirmed->value);
    }

    public function test_user_cannot_confirm_other_users_shift(): void {
        $user = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $user->organization_id]);
        $shift = ScheduledShift::factory()
            ->published()
            ->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->patchJson(route('schedule.shifts.confirm', $shift))
            ->assertForbidden();
    }

    // ── Import ──────────────────────────────────────────────────────────────

    public function test_admin_can_access_import_page(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('schedule.import'))
            ->assertOk()
            ->assertViewIs('schedule.import.index');
    }

    public function test_non_admin_cannot_access_import_page(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('schedule.import'))
            ->assertForbidden();
    }
}
