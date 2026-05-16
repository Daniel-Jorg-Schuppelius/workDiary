<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfileTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_user_can_view_profile(): void
    {
        $user = User::factory()->user()->create();
        $this->actingAs($user)
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->user()->create(['name' => 'Alt', 'email' => 'alt@example.test']);

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Neu',
            'email' => 'neu@example.test',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Neu', $user->name);
        $this->assertSame('neu@example.test', $user->email);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->user()->create(['email' => 'taken@example.test']);
        $user = User::factory()->user()->create();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => $user->name,
            'email' => 'taken@example.test',
        ])->assertSessionHasErrors('email');
    }
}
