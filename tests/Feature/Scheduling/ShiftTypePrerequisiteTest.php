<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftTypePrerequisiteTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Scheduling;

use App\Models\{ShiftType, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prerequisite-Referenzfall (Feature 067, MVP-181): Der Schichtplan
 * erklärt ohne angelegte Schichttypen den Setup-Schritt, statt still
 * einen leeren Dialog zu zeigen.
 */
class ShiftTypePrerequisiteTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_schedule_without_shift_types_shows_setup_hint_with_admin_cta(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('schedule.index'))
            ->assertOk()
            ->assertSee(__('prerequisites.shift_types.missing'))
            ->assertSee(__('prerequisites.shift_types.cta'));
    }

    public function test_schedule_without_shift_types_shows_role_hint_for_regular_user(): void {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($user)->get(route('schedule.index'))
            ->assertOk()
            ->assertSee(__('prerequisites.shift_types.missing'))
            ->assertDontSee(__('prerequisites.shift_types.cta'));
    }

    public function test_schedule_with_shift_types_shows_no_hint(): void {
        $admin = User::factory()->admin()->create();
        ShiftType::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Frühschicht',
            'abbreviation' => 'F',
            'color' => '#22c55e',
            'default_start_time' => '06:00',
            'default_end_time' => '14:00',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('schedule.index'))
            ->assertOk()
            ->assertDontSee(__('prerequisites.shift_types.missing'));
    }
}
