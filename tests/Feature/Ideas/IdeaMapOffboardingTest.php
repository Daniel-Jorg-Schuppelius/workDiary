<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapOffboardingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ideas;

use App\Models\Ideas\IdeaMap;
use App\Models\Platform\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ideenkarten beim Austritt übertragen (Entscheidung 2026-10-21): nur Anzahl, kein Titel, auditiert. */
final class IdeaMapOffboardingTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    public function test_dialog_shows_only_the_count_and_exit_transfers_the_maps(): void {
        $leaving = User::factory()->user()->create(['organization_id' => $this->org->id]);
        $successor = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Dora Nachfolge']);
        $maps = IdeaMap::factory()->count(2)->create(['organization_id' => $this->org->id, 'owner_user_id' => $leaving->id, 'created_by' => $leaving->id, 'title' => 'Geheimes Vorhaben']);
        $archived = IdeaMap::factory()->create(['organization_id' => $this->org->id, 'owner_user_id' => $leaving->id, 'created_by' => $leaving->id]);
        $archived->delete();

        $this->actingAs($this->admin)->get(route('org.members.offboard.dialog', $leaving))->assertOk()
            ->assertSee('3 Ideenkarten')
            ->assertSee('name="handovers[idea_maps]"', false)
            ->assertDontSee('Geheimes Vorhaben');

        $this->actingAs($this->admin)->post(route('org.members.offboard', $leaving), [
            'left_at' => now()->addMonth()->toDateString(),
            'handovers' => ['idea_maps' => $successor->sqid],
        ])->assertRedirect();

        foreach ([...$maps, $archived] as $map) {
            $this->assertSame($successor->id, (int) IdeaMap::query()->withTrashed()->findOrFail($map->id)->owner_user_id);
        }
        $this->assertDatabaseHas('audit_logs', ['event' => 'idea_map.ownership_transferred', 'auditable_id' => $maps[0]->id]);
    }

    public function test_without_a_choice_the_maps_stay_and_foreign_successors_are_ignored(): void {
        $leaving = User::factory()->user()->create(['organization_id' => $this->org->id]);
        $foreign = User::factory()->create();
        $map = IdeaMap::factory()->create(['organization_id' => $this->org->id, 'owner_user_id' => $leaving->id, 'created_by' => $leaving->id]);

        $this->actingAs($this->admin)->post(route('org.members.offboard', $leaving), [
            'left_at' => now()->addMonth()->toDateString(),
            'handovers' => ['idea_maps' => $foreign->sqid],
        ])->assertRedirect();
        $this->assertSame($leaving->id, (int) $map->fresh()?->owner_user_id);

        $this->actingAs($this->admin)->get(route('org.members.offboard.dialog', User::factory()->user()->create(['organization_id' => $this->org->id])))
            ->assertOk()->assertDontSee('handovers[idea_maps]', false);
    }
}
