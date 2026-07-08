<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiTokenTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTokenTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    private function apiUser(): User {
        $org = Organization::factory()->create();

        return User::factory()->user()->create(['organization_id' => $org->id]);
    }

    public function test_index_lists_tokens(): void {
        $user = User::factory()->user()->create();
        $user->createToken('Mein Token');

        $this->actingAs($user)->get(route('profile.api-tokens.index'))
            ->assertOk()
            ->assertSee('Mein Token');
    }

    public function test_create_token_shows_plain_text_once(): void {
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->post(route('profile.api-tokens.store'), [
            'name' => 'CLI',
        ])->assertRedirect(route('profile.api-tokens.index'));

        $response->assertSessionHas('newToken');
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'CLI']);
    }

    public function test_destroy_revokes_token(): void {
        $user = User::factory()->user()->create();
        $token = $user->createToken('temp')->accessToken;

        $this->actingAs($user)
            ->delete(route('profile.api-tokens.destroy', $token->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_store_persists_selected_abilities(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('profile.api-tokens.store'), [
            'name' => 'Scoped',
            'abilities' => ['diary:read', 'assets:read'],
        ])->assertRedirect();

        $token = $user->tokens()->firstOrFail();
        $this->assertEqualsCanonicalizing(['diary:read', 'assets:read'], $token->abilities);
    }

    public function test_store_without_abilities_defaults_to_wildcard(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('profile.api-tokens.store'), ['name' => 'Full'])->assertRedirect();

        $this->assertSame(['*'], $user->tokens()->firstOrFail()->abilities);
    }

    public function test_store_rejects_unknown_ability(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('profile.api-tokens.store'), [
            'name' => 'Bogus',
            'abilities' => ['diary:read', 'made-up:thing'],
        ])->assertSessionHasErrors('abilities.1');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_index_shows_ability_checkboxes(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('profile.api-tokens.index'))
            ->assertOk()
            ->assertSee('diary:read')
            ->assertSee(__('Aufträge lesen'));
    }

    public function test_read_scope_blocks_write_endpoint(): void {
        $user = $this->apiUser();
        Sanctum::actingAs($user, ['diary:read']);

        $this->getJson(route('api.diary.index'))->assertOk();          // Lesen erlaubt
        $this->postJson(route('api.diary.store'), [])->assertForbidden(); // Schreiben → 403
    }

    public function test_write_scope_allows_write_endpoint(): void {
        $user = $this->apiUser();
        Sanctum::actingAs($user, ['diary:write']);

        // Ability passt → die Middleware lässt durch; der Controller entscheidet weiter.
        $this->assertNotSame(403, $this->postJson(route('api.diary.store'), [])->status());
    }

    public function test_wildcard_token_keeps_full_access(): void {
        $user = $this->apiUser();
        Sanctum::actingAs($user, ['*']);

        $this->getJson(route('api.diary.index'))->assertOk();
        $this->assertNotSame(403, $this->postJson(route('api.diary.store'), [])->status());
    }
}
