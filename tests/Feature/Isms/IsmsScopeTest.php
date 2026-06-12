<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsScopeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Models\Isms\IsmsScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Geltungsbereiche (Feature 046): Minimal-CRUD nur mit isms.manage;
 * Default-Scope nicht löschbar (Policy + Serviceregel).
 */
class IsmsScopeTest extends TestCase {
    use RefreshDatabase;

    public function test_admin_can_create_and_update_scope(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.scopes.index'))
            ->post(route('isms.scopes.store'), [
                'name' => 'Rechenzentrum Nord',
                'description' => 'Colocation-Standort inkl. Netzbetrieb.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_scopes', [
            'name' => 'Rechenzentrum Nord',
            'organization_id' => $admin->organization_id,
            'is_default' => false,
        ]);

        app()->instance('currentOrganization', $admin->organization);
        /** @var IsmsScope $scope */
        $scope = IsmsScope::query()->where('name', 'Rechenzentrum Nord')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('isms.scopes.update', $scope), ['name' => 'Rechenzentrum Nord (RZ-N)'])
            ->assertRedirect();

        $this->assertSame('Rechenzentrum Nord (RZ-N)', $scope->refresh()->name);
    }

    public function test_default_scope_cannot_be_deleted(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $default = IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)
            ->from(route('isms.scopes.index'))
            ->delete(route('isms.scopes.destroy', $default))
            ->assertRedirect(route('isms.scopes.index'))
            ->assertSessionHasErrors('scope');

        $this->assertNotSoftDeleted('isms_scopes', ['id' => $default->id]);

        // Nicht-Default-Scope ist löschbar.
        $extra = IsmsScope::factory()->create(['organization_id' => $admin->organization_id]);
        $this->actingAs($admin)->delete(route('isms.scopes.destroy', $extra))->assertRedirect();
        $this->assertSoftDeleted('isms_scopes', ['id' => $extra->id]);
    }

    public function test_scopes_require_manage_permission(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();
        $user = User::factory()->user()->create();

        // Geltungsbereiche sind Verwaltungsfläche: auch lesend nur isms.manage.
        $this->actingAs($gf)->get(route('isms.scopes.index'))->assertForbidden();
        $this->actingAs($user)->get(route('isms.scopes.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('isms.scopes.store'), ['name' => 'Verboten'])
            ->assertForbidden();
    }
}
