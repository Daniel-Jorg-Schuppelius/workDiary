<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyDutyControllersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

class LegacyDutyControllersTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.legacy_write_enabled', true);
    }

    public function test_oncall_store_requires_existing_legacy_user(): void {
        $admin = User::factory()->admin()->create([
            // Legacy-Verwaltung verlangt eine verknüpfte Legacy-ID (S-01).
            'legacy_user_id' => 1,
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

    public function test_notdienst_store_persists_valid_record(): void {
        $admin = User::factory()->admin()->create([
            // Legacy-Verwaltung verlangt eine verknüpfte Legacy-ID (S-01).
            'legacy_user_id' => 1,
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
