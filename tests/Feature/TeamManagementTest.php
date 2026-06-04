<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamManagementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Organization, Team, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TeamManagementTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function lead(): User {
        return User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function member(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_team_lead_can_create_team_with_members_and_lead_is_member(): void {
        $lead = $this->lead();
        $m1 = $this->member();

        $this->actingAs($lead)
            ->post(route('teams.store'), [
                'name' => 'Montage Nord',
                'color' => '#3366ff',
                'lead_user_id' => $lead->id,
                'member_ids' => [$m1->id],
            ])
            ->assertRedirect();

        $team = Team::where('name', 'Montage Nord')->first();
        $this->assertNotNull($team);
        $this->assertSame($this->organization->id, $team->organization_id);
        $this->assertSame($lead->id, $team->lead_user_id);
        // Teamleiter + ausgewähltes Mitglied sind Mitglieder; Leiter ist als is_lead markiert.
        $this->assertEqualsCanonicalizing([$lead->id, $m1->id], $team->members()->pluck('users.id')->all());
        $this->assertSame(1, (int) $team->members()->where('users.id', $lead->id)->first()->pivot->is_lead);
    }

    public function test_regular_member_can_view_but_not_create(): void {
        $viewer = $this->member();

        $this->actingAs($viewer)->get(route('teams.index'))->assertOk();
        $this->actingAs($viewer)->post(route('teams.store'), [
            'name' => 'Heimlich',
        ])->assertForbidden();
    }

    public function test_lead_can_attach_and_detach_members(): void {
        $lead = $this->lead();
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $u = $this->member();

        $this->actingAs($lead)
            ->post(route('teams.members.attach', $team), ['user_id' => \App\Support\Sqid::encode(\App\Models\User::class, $u->id)])
            ->assertRedirect();
        $this->assertTrue($team->members()->whereKey($u->id)->exists());

        $this->actingAs($lead)
            ->delete(route('teams.members.detach', [$team, $u]))
            ->assertRedirect();
        $this->assertFalse($team->fresh()->members()->whereKey($u->id)->exists());
    }

    public function test_detaching_lead_clears_team_lead(): void {
        $lead = $this->lead();
        $team = Team::factory()->create([
            'organization_id' => $this->organization->id,
            'lead_user_id' => $lead->id,
        ]);
        $team->members()->attach($lead->id, ['is_lead' => true]);

        $this->actingAs($lead)
            ->delete(route('teams.members.detach', [$team, $lead]))
            ->assertRedirect();

        $this->assertNull($team->fresh()->lead_user_id);
    }

    public function test_cannot_manage_team_from_other_org(): void {
        $otherOrg = Organization::factory()->create();
        $otherTeam = Team::factory()->create(['organization_id' => $otherOrg->id]);

        // Org-Scope blendet fremde Teams komplett aus → Route-Binding 404 (statt 403).
        $this->actingAs($this->lead())
            ->get(route('teams.edit', $otherTeam))
            ->assertNotFound();
    }
}
