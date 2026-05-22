<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftTypeHtmlTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ShiftTypeHtmlTest extends TestCase {
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

    public function test_guest_redirected(): void {
        $this->get(route('shift-types.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_forbidden(): void {
        $this->actingAs($this->user())
            ->get(route('shift-types.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_index(): void {
        $this->actingAs($this->admin())
            ->get(route('shift-types.index'))
            ->assertOk()
            ->assertViewIs('shift-types.index');
    }

    public function test_admin_can_create_shift_type(): void {
        $this->actingAs($this->admin())
            ->post(route('shift-types.store'), [
                'name' => 'Frühschicht',
                'abbreviation' => 'F',
                'color' => '#3b82f6',
                'is_active' => 1,
            ])
            ->assertRedirect(route('shift-types.index'));

        $this->assertDatabaseHas('shift_types', ['name' => 'Frühschicht', 'abbreviation' => 'F']);
    }

    public function test_admin_can_update_shift_type(): void {
        $type = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('shift-types.update', $type), [
                'name' => 'Spätschicht',
                'abbreviation' => 'S',
                'color' => '#ef4444',
                'is_active' => 1,
            ])
            ->assertRedirect(route('shift-types.index'));

        $this->assertDatabaseHas('shift_types', ['id' => $type->id, 'name' => 'Spätschicht']);
    }

    public function test_admin_can_delete_unused_shift_type(): void {
        $type = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('shift-types.destroy', $type))
            ->assertRedirect(route('shift-types.index'));

        $this->assertDatabaseMissing('shift_types', ['id' => $type->id]);
    }
}
