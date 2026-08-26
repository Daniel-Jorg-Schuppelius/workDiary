<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserWorkspaceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Workspace;

use App\Models\{User, UserWorkspace};
use App\Services\Navigation\{NavFocusService, NavigationRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 082 Phase 2 (MVP-731, Vollscan G17): eigene Arbeitsbereiche.
 *
 * Der Kern ist nicht das CRUD, sondern die Grenze: gespeichert werden darf
 * nur, was die Person laut NavGate ohnehin sieht — und ein Arbeitsbereich
 * gehört genau einer Person.
 */
final class UserWorkspaceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgAdmin();
        $this->actingAs($this->user);
    }

    /** @return list<string> Nav-Schlüssel, die dieser Nutzer sehen darf. */
    private function allowedKeys(): array {
        return app(NavigationRegistry::class)->selectableKeys();
    }

    private function workspaceFor(User $user, string $name = 'Mein Fokus'): UserWorkspace {
        return UserWorkspace::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->getKey(),
            'name' => $name,
            'icon' => 'dashboard_customize',
            'sort' => 0,
            'items' => array_slice($this->allowedKeys(), 0, 2),
        ]);
    }

    public function test_index_lists_only_the_own_workspaces(): void {
        $this->workspaceFor($this->user, 'Meiner');
        $other = $this->orgAdmin();
        $this->workspaceFor($other, 'Fremder');

        $response = $this->get(route('me.workspaces.index'));

        $response->assertOk();
        $response->assertSee('Meiner');
        $response->assertDontSee('Fremder');
    }

    public function test_create_and_update_persist_the_selection_in_order(): void {
        $keys = array_slice($this->allowedKeys(), 0, 3);
        $this->assertGreaterThanOrEqual(3, count($keys));

        $this->post(route('me.workspaces.store'), [
            'name' => 'Tagesgeschäft',
            'icon' => 'schedule',
            'sort' => 5,
            'items' => $keys,
        ])->assertRedirect(route('me.workspaces.index'));

        $workspace = UserWorkspace::query()->where('user_id', $this->user->getKey())->firstOrFail();
        $this->assertSame('Tagesgeschäft', $workspace->name);
        $this->assertSame(5, $workspace->sort);
        // Reihenfolge ist die Aussage des Editors — nicht sortieren.
        $this->assertSame($keys, $workspace->keys());

        $reordered = array_reverse($keys);
        $this->put(route('me.workspaces.update', $workspace), [
            'name' => 'Tagesgeschäft',
            'items' => $reordered,
        ])->assertRedirect(route('me.workspaces.index'));

        $this->assertSame($reordered, $workspace->fresh()?->keys());
    }

    public function test_unknown_or_forbidden_nav_keys_are_rejected(): void {
        $this->post(route('me.workspaces.store'), [
            'name' => 'Schmuggel',
            'items' => ['item:admin.organizations.index', 'section:erfunden'],
        ])->assertSessionHasErrors('items.0');

        $this->assertSame(0, UserWorkspace::query()->count());
    }

    public function test_a_workspace_without_items_is_rejected(): void {
        $this->post(route('me.workspaces.store'), ['name' => 'Leer', 'items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_names_are_unique_per_user_but_not_across_users(): void {
        $this->workspaceFor($this->user, 'Doppelt');

        $this->post(route('me.workspaces.store'), [
            'name' => 'Doppelt',
            'items' => array_slice($this->allowedKeys(), 0, 1),
        ])->assertSessionHasErrors('name');

        $other = $this->orgAdmin();
        $this->workspaceFor($other, 'Doppelt');
        $this->assertSame(2, UserWorkspace::query()->where('name', 'Doppelt')->count());
    }

    public function test_a_foreign_workspace_is_not_reachable(): void {
        $foreign = $this->workspaceFor($this->orgAdmin(), 'Fremd');

        $this->get(route('me.workspaces.edit', $foreign))->assertNotFound();
        $this->put(route('me.workspaces.update', $foreign), [
            'name' => 'Gekapert',
            'items' => array_slice($this->allowedKeys(), 0, 1),
        ])->assertNotFound();
        $this->delete(route('me.workspaces.destroy', $foreign))->assertNotFound();

        $this->assertSame('Fremd', $foreign->fresh()?->name);
    }

    public function test_the_own_workspace_can_be_switched_to_and_filters_the_sidebar(): void {
        $workspace = $this->workspaceFor($this->user, 'Nur Zwei');
        $key = NavFocusService::personalKey($workspace);

        $this->post(route('me.focus.switch', $key))->assertRedirect();

        $this->assertSame($key, session(NavFocusService::SESSION_KEY));
        $this->assertSame($workspace->keys(), app(NavFocusService::class)->keepKeys($key));
        // Eigene Bereiche filtern nur die Sidebar — das Verwaltungsmenü bleibt.
        $this->assertNull(app(NavFocusService::class)->manageKeep($key));
    }

    public function test_a_foreign_workspace_key_cannot_be_activated(): void {
        $foreign = $this->workspaceFor($this->orgAdmin(), 'Fremd');
        $key = NavFocusService::personalKey($foreign);

        $this->post(route('me.focus.switch', $key))->assertRedirect();

        $this->assertNull(session(NavFocusService::SESSION_KEY));
        $this->assertNull(app(NavFocusService::class)->keepKeys($key));
    }

    public function test_deleting_the_active_workspace_falls_back_to_show_everything(): void {
        $workspace = $this->workspaceFor($this->user);
        $key = NavFocusService::personalKey($workspace);
        $this->post(route('me.focus.switch', $key));

        $this->delete(route('me.workspaces.destroy', $workspace))->assertRedirect(route('me.workspaces.index'));

        $this->assertSame(0, UserWorkspace::query()->count());
        $this->assertNull(session(NavFocusService::SESSION_KEY));
        $this->assertSame('all', $this->user->fresh()?->getPreference(NavFocusService::PREFERENCE_KEY));
    }

    public function test_the_switcher_offers_personal_workspaces_after_the_product_ones(): void {
        $this->workspaceFor($this->user, 'Zuletzt');

        $available = app(NavFocusService::class)->availableFor($this->organization, $this->user);
        $personal = array_values(array_filter($available, static fn (array $f): bool => (bool) $f['personal']));

        $this->assertCount(1, $personal);
        $this->assertSame('Zuletzt', $personal[0]['label']);
        $this->assertTrue(app(NavFocusService::class)->isAvailableFor($this->organization, $personal[0]['key']));
        $this->assertFalse((bool) $available[0]['personal']);
    }

    public function test_workspaces_are_listed_in_the_configured_order(): void {
        $keys = array_slice($this->allowedKeys(), 0, 1);
        foreach ([['B', 2], ['A', 1]] as [$name, $sort]) {
            UserWorkspace::create([
                'organization_id' => $this->organization->id,
                'user_id' => $this->user->getKey(),
                'name' => $name,
                'sort' => $sort,
                'items' => $keys,
            ]);
        }

        $names = UserWorkspace::query()->forUser($this->user)->pluck('name')->all();

        $this->assertSame(['A', 'B'], $names);
    }
}
