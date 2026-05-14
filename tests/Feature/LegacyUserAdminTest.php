<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

class LegacyUserAdminTest extends TestCase
{
    use RefreshDatabase;
    use UsesLegacySqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->useLegacySqlite();
        Config::set('app.legacy_write_enabled', true);
    }

    public function test_legacy_user_store_rejects_too_long_password(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'admin',
            'email' => 'admin-users@example.test',
        ]);

        $this->actingAs($admin)
            ->from(route('legacy.users.create'))
            ->post(route('legacy.users.store'), [
                'uname' => 'legacy-user',
                'userpw' => str_repeat('x', 16),
                'email' => 'legacy@example.test',
            ])
            ->assertRedirect(route('legacy.users.create'))
            ->assertSessionHasErrors('userpw');
    }
}
