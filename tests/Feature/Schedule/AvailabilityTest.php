<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AvailabilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\{AvailabilityKind, ShiftPreference};
use App\Models\{AvailabilityWindow, DesiredShift, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_view_own_availability_page(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('schedule.availability.index'))
            ->assertOk()
            ->assertViewIs('availability.index');
    }

    public function test_user_can_create_recurring_availability_window(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('schedule.availability.windows.store'), [
                'weekday' => 1,
                'kind' => AvailabilityKind::Preferred->value,
                'start_time' => '08:00',
                'end_time' => '16:00',
            ])
            ->assertRedirect(route('schedule.availability.index'));

        $this->assertDatabaseHas('availability_windows', [
            'user_id' => $user->id,
            'weekday' => 1,
            'kind' => AvailabilityKind::Preferred->value,
        ]);
    }

    public function test_user_can_create_date_based_window(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('schedule.availability.windows.store'), [
                'specific_date' => '2026-07-01',
                'kind' => AvailabilityKind::Unavailable->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('availability_windows', [
            'user_id' => $user->id,
            'specific_date' => '2026-07-01',
            'kind' => AvailabilityKind::Unavailable->value,
        ]);
    }

    public function test_window_requires_weekday_or_date(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('schedule.availability.windows.store'), [
                'kind' => AvailabilityKind::Available->value,
            ])
            ->assertSessionHasErrors(['weekday', 'specific_date']);
    }

    public function test_user_can_delete_own_window(): void {
        $user = User::factory()->user()->create();
        $window = AvailabilityWindow::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('schedule.availability.windows.destroy', $window))
            ->assertRedirect();

        $this->assertSoftDeleted('availability_windows', ['id' => $window->id]);
    }

    public function test_user_cannot_delete_foreign_window(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $window = AvailabilityWindow::factory()->create([
            'organization_id' => $owner->organization_id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->delete(route('schedule.availability.windows.destroy', $window))
            ->assertForbidden();

        $this->assertDatabaseHas('availability_windows', ['id' => $window->id, 'deleted_at' => null]);
    }

    public function test_user_can_create_desired_shift(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('schedule.availability.desired.store'), [
                'date' => '2026-07-10',
                'preference' => ShiftPreference::Want->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('desired_shifts', [
            'user_id' => $user->id,
            'date' => '2026-07-10',
            'preference' => ShiftPreference::Want->value,
        ]);
    }

    public function test_user_can_delete_own_desired_shift(): void {
        $user = User::factory()->user()->create();
        $wish = DesiredShift::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('schedule.availability.desired.destroy', $wish))
            ->assertRedirect();

        $this->assertDatabaseMissing('desired_shifts', ['id' => $wish->id]);
    }
}
