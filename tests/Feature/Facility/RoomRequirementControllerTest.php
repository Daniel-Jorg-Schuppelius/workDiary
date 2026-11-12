<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomRequirementControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Facility;

use App\Enums\Facility\RoomRequirementKind;
use App\Enums\User\UserRole;
use App\Models\Facility\{Room, RoomRequirement};
use App\Models\Platform\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class RoomRequirementControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $manager;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->manager = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->manager->assignRole(UserRole::TrainingManager->value);
    }

    public function test_manager_can_add_requirement_to_room(): void {
        $room = Room::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->manager)
            ->post(route('rooms.requirements.store', $room), [
                'kind' => RoomRequirementKind::SpecialCleaning->value,
                'level' => 'wöchentlich',
                'note' => 'Sonderreinigung nach OP',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('room_requirements', [
            'room_id' => $room->id,
            'organization_id' => $this->organization->id,
            'kind' => RoomRequirementKind::SpecialCleaning->value,
            'level' => 'wöchentlich',
        ]);
    }

    public function test_user_without_room_permission_cannot_add_requirement(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $room = Room::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('rooms.requirements.store', $room), [
                'kind' => RoomRequirementKind::OperatorDuty->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('room_requirements', 0);
    }

    public function test_manager_can_delete_requirement(): void {
        $room = Room::factory()->create(['organization_id' => $this->organization->id]);
        $requirement = RoomRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($this->manager)
            ->delete(route('rooms.requirements.destroy', [$room, $requirement]))
            ->assertRedirect();

        $this->assertDatabaseMissing('room_requirements', ['id' => $requirement->id]);
    }

    /** MVP-1619: Anforderung im eigenen Dialog ändern; danach öffnet der Raumdialog wieder. */
    public function test_manager_can_edit_requirement_in_dialog(): void {
        $room = Room::factory()->create(['organization_id' => $this->organization->id]);
        $requirement = RoomRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'room_id' => $room->id,
            'kind' => RoomRequirementKind::ItInventory->value,
        ]);

        $this->actingAs($this->manager)->get(route('rooms.requirements.edit', [$room, $requirement]))
            ->assertOk()
            ->assertSee(route('rooms.requirements.update', [$room, $requirement]), false);
        $this->actingAs($this->manager)->get(route('rooms.edit', $room))
            ->assertSee(route('rooms.requirements.edit', [$room, $requirement]), false);

        $this->actingAs($this->manager)
            ->put(route('rooms.requirements.update', [$room, $requirement]), [
                'kind' => RoomRequirementKind::ItInventory->value,
                'level' => 'Stufe 2',
                'note' => 'Zugang nur mit Begleitung',
                'is_active' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('open_dialog', route('rooms.edit', $room));

        $requirement->refresh();
        $this->assertSame('Stufe 2', $requirement->level);
        $this->assertFalse($requirement->is_active);
    }

    public function test_requirement_must_belong_to_room(): void {
        $roomA = Room::factory()->create(['organization_id' => $this->organization->id]);
        $roomB = Room::factory()->create(['organization_id' => $this->organization->id]);
        $requirement = RoomRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'room_id' => $roomB->id,
        ]);

        $this->actingAs($this->manager)
            ->delete(route('rooms.requirements.destroy', [$roomA, $requirement]))
            ->assertNotFound();

        $this->assertDatabaseHas('room_requirements', ['id' => $requirement->id]);
    }

    public function test_requirements_shown_in_room_index(): void {
        $room = Room::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Serverraum']);
        RoomRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'room_id' => $room->id,
            'kind' => RoomRequirementKind::ItInventory->value,
            'level' => 'Schutzbedarf hoch',
            'is_active' => true,
        ]);

        $this->actingAs($this->manager)
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee(RoomRequirementKind::ItInventory->label())
            ->assertSee('Schutzbedarf hoch');
    }

    public function test_cross_org_room_requirement_route_is_not_found(): void {
        $otherOrg = Organization::factory()->create();
        $foreignRoom = Room::factory()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->manager)
            ->post(route('rooms.requirements.store', $foreignRoom), [
                'kind' => RoomRequirementKind::Other->value,
            ])
            ->assertNotFound();
    }
}
