<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftHtmlTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\ScheduledShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ScheduledShiftHtmlTest extends TestCase {
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

    public function test_admin_can_view_show(): void {
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('scheduled-shifts.show', $shift))
            ->assertOk()
            ->assertViewIs('scheduled-shifts._form_dialog');
    }

    public function test_admin_can_edit(): void {
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('scheduled-shifts.edit', $shift))
            ->assertOk()
            ->assertViewIs('scheduled-shifts._form_dialog');
    }

    public function test_admin_can_update(): void {
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'note' => 'old',
        ]);

        $this->actingAs($this->admin())
            ->put(route('scheduled-shifts.update', $shift), [
                'note' => 'updated note',
            ])
            ->assertRedirect(route('schedule.index'));

        $this->assertDatabaseHas('scheduled_shifts', ['id' => $shift->id, 'note' => 'updated note']);
    }

    public function test_admin_can_delete(): void {
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('scheduled-shifts.destroy', $shift))
            ->assertRedirect(route('schedule.index'));

        $this->assertDatabaseMissing('scheduled_shifts', ['id' => $shift->id]);
    }

    public function test_non_admin_cannot_edit(): void {
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->user())
            ->get(route('scheduled-shifts.edit', $shift))
            ->assertForbidden();
    }
}
