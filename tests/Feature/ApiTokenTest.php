<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_index_lists_tokens(): void
    {
        $user = User::factory()->user()->create();
        $user->createToken('Mein Token');

        $this->actingAs($user)->get(route('profile.api-tokens.index'))
            ->assertOk()
            ->assertSee('Mein Token');
    }

    public function test_create_token_shows_plain_text_once(): void
    {
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->post(route('profile.api-tokens.store'), [
            'name' => 'CLI',
        ])->assertRedirect(route('profile.api-tokens.index'));

        $response->assertSessionHas('newToken');
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'CLI']);
    }

    public function test_destroy_revokes_token(): void
    {
        $user = User::factory()->user()->create();
        $token = $user->createToken('temp')->accessToken;

        $this->actingAs($user)
            ->delete(route('profile.api-tokens.destroy', $token->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }
}
