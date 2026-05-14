<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

class LegacyDutyControllersTest extends TestCase
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

    public function test_oncall_store_requires_existing_legacy_user(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'admin',
            'email' => 'admin-duty@example.test',
        ]);

        $this->actingAs($admin)
            ->from(route('legacy.oncall.create'))
            ->post(route('legacy.oncall.store'), [
                'user' => 999,
                'von' => '2026-05-01',
                'bis' => '2026-05-02',
            ])
            ->assertRedirect(route('legacy.oncall.create'))
            ->assertSessionHasErrors('user');
    }

    public function test_notdienst_store_persists_valid_record(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'admin',
            'email' => 'admin-notdienst@example.test',
        ]);

        DB::connection('legacy')->table('user')->insert([
            'id' => 4,
            'uname' => 'legacy-user',
            'userpw' => 'secret',
            'email' => 'legacy@example.test',
        ]);

        $this->actingAs($admin)
            ->post(route('legacy.notdienst.store'), [
                'user' => 4,
                'von' => '2026-05-01',
                'bis' => '2026-05-02',
            ])
            ->assertRedirect(route('legacy.diary.index', ['tab' => 'notdienst']));

        $this->assertDatabaseHas('notdnst', [
            'user' => 4,
            'von' => '2026-05-01 00:00:00',
            'bis' => '2026-05-02 00:00:00',
        ], 'legacy');
    }
}
