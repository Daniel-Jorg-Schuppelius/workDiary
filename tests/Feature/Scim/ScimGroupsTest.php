<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimGroupsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Scim;

use App\Models\{Organization, ScimGroup, ScimToken, Team, User};
use App\Services\Scim\ScimGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 057, MVP-121 → Rang 16: SCIM-2.0-Gruppen. Prüft Anlegen/GET mit
 * Mitgliedern (Okta-PUT-Falle), displayName-Filter + `excludedAttributes`, die
 * Entra-/Okta-PATCH-Formen (case-insensitiv, drei remove-Varianten, pfadloses
 * replace), die Team-Projektion nur bei gemappter Gruppe, tolerante Behandlung
 * unbekannter Member-IDs sowie die harte Zusage „SCIM vergibt keine Rollen".
 */
final class ScimGroupsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private string $plain;
    private User $alice;
    private User $bob;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        [, $this->plain] = ScimToken::issue($this->organization->id, 'Test-IdP');
        $this->alice = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'alice@example.com']);
        $this->bob = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'bob@example.com']);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function scim(string $method, string $uri, array $body = []): TestResponse {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_Authorization' => 'Bearer ' . $this->plain,
            'CONTENT_TYPE' => 'application/scim+json',
            'HTTP_ACCEPT' => 'application/scim+json',
        ], $body !== [] ? (string) json_encode($body) : null);
    }

    /**
     * @param  list<User>  $members
     * @return array<string, mixed>
     */
    private function groupPayload(string $displayName = 'Engineering', array $members = [], string $externalId = 'grp-1'): array {
        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
            'displayName' => $displayName,
            'externalId' => $externalId,
            'members' => array_map(fn (User $u): array => ['value' => $u->sqid], $members),
        ];
    }

    private function createGroup(string $displayName = 'Engineering', array $members = []): ScimGroup {
        $this->scim('POST', '/scim/v2/Groups', $this->groupPayload($displayName, $members))->assertStatus(201);

        return ScimGroup::query()->where('display_name', $displayName)->firstOrFail();
    }

    public function test_creates_group_with_members_and_returns_them(): void {
        $response = $this->scim('POST', '/scim/v2/Groups', $this->groupPayload('Engineering', [$this->alice, $this->bob]));

        $response->assertStatus(201)
            ->assertJsonPath('displayName', 'Engineering')
            ->assertJsonPath('externalId', 'grp-1')
            ->assertJsonPath('meta.resourceType', 'Group');
        $this->assertCount(2, $response->json('members'));

        $group = ScimGroup::query()->where('display_name', 'Engineering')->firstOrFail();
        $this->assertSame($this->organization->id, $group->organization_id);
        $this->assertNull($group->team_id); // Team-Mapping kommt NIE vom IdP
    }

    public function test_get_exposes_members_but_excluded_attributes_hides_them(): void {
        $group = $this->createGroup('Ops', [$this->alice]);

        // Volle Sicht (Okta-PUT-Falle: Mitglieder MÜSSEN in GET erscheinen).
        $this->scim('GET', '/scim/v2/Groups/' . $group->sqid)
            ->assertOk()
            ->assertJsonPath('members.0.value', $this->alice->sqid);

        // Entra fragt mit excludedAttributes=members ab → keine Mitglieder.
        $this->scim('GET', '/scim/v2/Groups/' . $group->sqid . '?excludedAttributes=members')
            ->assertOk()
            ->assertJsonMissingPath('members');
    }

    public function test_displayname_filter_returns_list(): void {
        $this->createGroup('Finance');
        $this->createGroup('Legal');

        $this->scim('GET', '/scim/v2/Groups?filter=' . rawurlencode('displayName eq "Finance"'))
            ->assertOk()
            ->assertJsonPath('totalResults', 1)
            ->assertJsonPath('Resources.0.displayName', 'Finance');
    }

    public function test_put_replaces_members_and_get_reflects(): void {
        $group = $this->createGroup('Team A', [$this->alice, $this->bob]);

        // PUT mit reduzierter Mitgliederliste (nur Alice bleibt).
        $this->scim('PUT', '/scim/v2/Groups/' . $group->sqid, $this->groupPayload('Team A', [$this->alice]))
            ->assertOk()
            ->assertJsonPath('displayName', 'Team A');

        $members = $this->scim('GET', '/scim/v2/Groups/' . $group->sqid)->json('members');
        $this->assertCount(1, $members);
        $this->assertSame($this->alice->sqid, $members[0]['value']);
    }

    public function test_patch_entra_add_and_okta_filter_remove(): void {
        $group = $this->createGroup('Patchers', []);

        // Entra-Stil: op "Add" (case-insensitiv), path "members", value-Array.
        $this->scim('PATCH', '/scim/v2/Groups/' . $group->sqid, [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
            'Operations' => [['op' => 'Add', 'path' => 'members', 'value' => [['value' => $this->alice->sqid], ['value' => $this->bob->sqid]]]],
        ])->assertStatus(204);
        $this->assertCount(2, $this->scim('GET', '/scim/v2/Groups/' . $group->sqid)->json('members'));

        // Okta-Stil: remove über Filterpfad members[value eq "…"].
        $this->scim('PATCH', '/scim/v2/Groups/' . $group->sqid, [
            'Operations' => [['op' => 'remove', 'path' => 'members[value eq "' . $this->alice->sqid . '"]']],
        ])->assertStatus(204);

        $members = $this->scim('GET', '/scim/v2/Groups/' . $group->sqid)->json('members');
        $this->assertCount(1, $members);
        $this->assertSame($this->bob->sqid, $members[0]['value']);
    }

    public function test_patch_remove_all_members_and_pathless_replace(): void {
        $group = $this->createGroup('Mixed', [$this->alice, $this->bob]);

        // remove path "members" ohne value = alle entfernen.
        $this->scim('PATCH', '/scim/v2/Groups/' . $group->sqid, [
            'Operations' => [['op' => 'remove', 'path' => 'members']],
        ])->assertStatus(204);
        $this->assertCount(0, $this->scim('GET', '/scim/v2/Groups/' . $group->sqid)->json('members'));

        // Pfadloses replace mit Objekt-Value (displayName + members).
        $this->scim('PATCH', '/scim/v2/Groups/' . $group->sqid, [
            'Operations' => [['op' => 'replace', 'value' => ['displayName' => 'Renamed', 'members' => [['value' => $this->alice->sqid]]]]],
        ])->assertStatus(204);

        $group->refresh();
        $this->assertSame('Renamed', $group->display_name);
        $this->assertCount(1, $this->scim('GET', '/scim/v2/Groups/' . $group->sqid)->json('members'));
    }

    public function test_members_project_to_team_only_when_mapped(): void {
        $group = $this->createGroup('Squad', [$this->alice]);
        $team = Team::query()->create(['organization_id' => $this->organization->id, 'name' => 'Squad-Team']);

        // Ohne Mapping: keine Projektion nach team_user.
        $this->assertSame(0, $team->members()->count());

        // Admin-Schritt: Gruppe dem Team zuordnen → aktuelle Mitglieder projizieren.
        app(ScimGroupService::class)->mapToTeam($group->refresh(), $team);
        $this->assertTrue($team->members()->where('users.id', $this->alice->id)->exists());

        // PATCH add bob → Team bekommt bob dazu.
        $this->scim('PATCH', '/scim/v2/Groups/' . $group->sqid, [
            'Operations' => [['op' => 'add', 'path' => 'members', 'value' => [['value' => $this->bob->sqid]]]],
        ])->assertStatus(204);
        $this->assertTrue($team->members()->where('users.id', $this->bob->id)->exists());

        // PATCH remove alice → aus dem Team gelöst.
        $this->scim('PATCH', '/scim/v2/Groups/' . $group->sqid, [
            'Operations' => [['op' => 'remove', 'path' => 'members[value eq "' . $this->alice->sqid . '"]']],
        ])->assertStatus(204);
        $this->assertFalse($team->members()->where('users.id', $this->alice->id)->exists());
        $this->assertTrue($team->members()->where('users.id', $this->bob->id)->exists());
    }

    public function test_unknown_member_value_is_tolerated(): void {
        $this->scim('POST', '/scim/v2/Groups', [
            'displayName' => 'Tolerant',
            'members' => [['value' => 'nonexistent-sqid'], ['value' => $this->alice->sqid]],
        ])->assertStatus(201);

        $group = ScimGroup::query()->where('display_name', 'Tolerant')->firstOrFail();
        // Unbekannte value bleibt erhalten (user_id null), damit Entra nicht endlos PATCHt.
        $members = collect($group->members);
        $this->assertCount(2, $members);
        $this->assertNull($members->firstWhere('value', 'nonexistent-sqid')['user_id']);
        $this->assertSame($this->alice->id, $members->firstWhere('value', $this->alice->sqid)['user_id']);
    }

    public function test_duplicate_displayname_conflicts(): void {
        $this->createGroup('Dupes');
        $this->scim('POST', '/scim/v2/Groups', $this->groupPayload('Dupes'))
            ->assertStatus(409)
            ->assertJsonPath('scimType', 'uniqueness');
    }

    public function test_scim_group_never_assigns_role(): void {
        $group = $this->createGroup('NoRoles', [$this->alice]);
        $team = Team::query()->create(['organization_id' => $this->organization->id, 'name' => 'NoRoles-Team']);
        app(ScimGroupService::class)->mapToTeam($group->refresh(), $team);

        // Team-Mitgliedschaft ja — aber keinerlei Rollenvergabe (keine Admin-Eskalation).
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $this->alice->id)->count());
    }

    public function test_delete_removes_group_and_detaches_from_team(): void {
        $group = $this->createGroup('Temp', [$this->alice]);
        $team = Team::query()->create(['organization_id' => $this->organization->id, 'name' => 'Temp-Team']);
        app(ScimGroupService::class)->mapToTeam($group->refresh(), $team);
        $this->assertTrue($team->members()->where('users.id', $this->alice->id)->exists());

        $this->scim('DELETE', '/scim/v2/Groups/' . $group->sqid)->assertStatus(204);

        $this->assertNull(ScimGroup::query()->find($group->id));
        $this->assertFalse($team->members()->where('users.id', $this->alice->id)->exists());
    }

    public function test_admin_index_lists_scim_groups(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        ScimGroup::query()->create(['organization_id' => $this->organization->id, 'display_name' => 'Vertrieb', 'members' => []]);

        $this->actingAs($admin)->get(route('admin.sso.index'))
            ->assertOk()
            ->assertSee('Vertrieb')
            ->assertSee(__('sso.groups_heading'));
    }

    public function test_admin_maps_group_to_team_and_projects_members(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $group = ScimGroup::query()->create([
            'organization_id' => $this->organization->id,
            'display_name' => 'Devs',
            'members' => [['value' => $this->alice->sqid, 'user_id' => $this->alice->id]],
        ]);
        $team = Team::query()->create(['organization_id' => $this->organization->id, 'name' => 'Dev-Team']);

        $this->actingAs($admin)
            ->post(route('admin.sso.groups.map', $group->sqid), ['team' => $team->sqid])
            ->assertRedirect();

        $group->refresh();
        $this->assertSame($team->id, $group->team_id);
        $this->assertTrue($team->members()->where('users.id', $this->alice->id)->exists());
    }

    public function test_admin_unmaps_group_and_detaches_members(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $team = Team::query()->create(['organization_id' => $this->organization->id, 'name' => 'X-Team']);
        $group = ScimGroup::query()->create([
            'organization_id' => $this->organization->id,
            'display_name' => 'X',
            'members' => [['value' => $this->alice->sqid, 'user_id' => $this->alice->id]],
            'team_id' => $team->id,
        ]);
        $team->members()->syncWithoutDetaching([$this->alice->id]);

        $this->actingAs($admin)
            ->post(route('admin.sso.groups.map', $group->sqid), ['team' => ''])
            ->assertRedirect();

        $group->refresh();
        $this->assertNull($group->team_id);
        $this->assertFalse($team->members()->where('users.id', $this->alice->id)->exists());
    }

    public function test_admin_cannot_map_a_foreign_team(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $group = ScimGroup::query()->create(['organization_id' => $this->organization->id, 'display_name' => 'Y', 'members' => []]);
        $otherOrg = Organization::factory()->create(['plan' => Organization::PLAN_ENTERPRISE]);
        $foreignTeam = Team::query()->create(['organization_id' => $otherOrg->id, 'name' => 'Foreign-Team']);

        $this->actingAs($admin)
            ->post(route('admin.sso.groups.map', $group->sqid), ['team' => $foreignTeam->sqid])
            ->assertNotFound();

        $this->assertNull($group->refresh()->team_id);
    }

    public function test_tenant_isolation_hides_foreign_groups(): void {
        $otherOrg = Organization::factory()->create(['plan' => Organization::PLAN_ENTERPRISE]);
        $foreign = ScimGroup::query()->create(['organization_id' => $otherOrg->id, 'display_name' => 'Foreign']);

        $this->scim('GET', '/scim/v2/Groups/' . $foreign->sqid)->assertStatus(404);
    }
}
