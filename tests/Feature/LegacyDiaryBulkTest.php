<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyDiaryBulkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

/**
 * Vollreview W0.1: Massenaktionen der Legacy-Auftragsliste
 * (legacy.diary.bulk — Status setzen / Löschen mit Berechtigungsgrenze).
 */
class LegacyDiaryBulkTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.legacy_write_enabled', true);
    }

    /** @param array<string, mixed> $attributes */
    private function makeEntry(array $attributes = []): int {
        return (int) DB::connection('legacy')->table('tagebuch')->insertGetId(array_merge([
            'user' => 4,
            'inhalt' => 'Eintrag',
            'von' => '2026-07-01 08:00:00',
            'bis' => '2026-07-01 10:00:00',
            'aktuell' => '2026-07-01 10:00:00',
            'gelesen' => 2,
            'sms' => '',
        ], $attributes));
    }

    public function test_admin_can_bulk_set_status(): void {
        $admin = User::factory()->create(['legacy_user_id' => 1]);
        $a = $this->makeEntry();
        $b = $this->makeEntry();

        $this->actingAs($admin)
            ->post(route('legacy.diary.bulk'), ['action' => 'status_done', 'ids' => [$a, $b]])
            ->assertRedirect(route('legacy.diary.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tagebuch', ['id' => $a, 'gelesen' => -1], 'legacy');
        $this->assertDatabaseHas('tagebuch', ['id' => $b, 'gelesen' => -1], 'legacy');
    }

    public function test_admin_can_bulk_delete(): void {
        $admin = User::factory()->create(['legacy_user_id' => 1]);
        $a = $this->makeEntry();
        $b = $this->makeEntry();

        $this->actingAs($admin)
            ->post(route('legacy.diary.bulk'), ['action' => 'delete', 'ids' => [$a, $b]])
            ->assertRedirect(route('legacy.diary.index'));

        $this->assertDatabaseMissing('tagebuch', ['id' => $a], 'legacy');
        $this->assertDatabaseMissing('tagebuch', ['id' => $b], 'legacy');
    }

    public function test_non_admin_only_touches_own_entries(): void {
        $user = User::factory()->create(['legacy_user_id' => 5]);
        $own = $this->makeEntry(['user' => 5]);
        $foreign = $this->makeEntry(['user' => 6]);

        $this->actingAs($user)
            ->post(route('legacy.diary.bulk'), ['action' => 'status_done', 'ids' => [$own, $foreign]])
            ->assertRedirect(route('legacy.diary.index'));

        $this->assertDatabaseHas('tagebuch', ['id' => $own, 'gelesen' => -1], 'legacy');
        // Fremder Eintrag bleibt unangetastet.
        $this->assertDatabaseHas('tagebuch', ['id' => $foreign, 'gelesen' => 2], 'legacy');
    }

    public function test_invalid_action_is_rejected(): void {
        $admin = User::factory()->create(['legacy_user_id' => 1]);
        $a = $this->makeEntry();

        $this->actingAs($admin)
            ->from(route('legacy.diary.index'))
            ->post(route('legacy.diary.bulk'), ['action' => 'drop_table', 'ids' => [$a]])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseHas('tagebuch', ['id' => $a, 'gelesen' => 2], 'legacy');
    }

    public function test_legacy_commands_are_registered(): void {
        // Vollreview W0.2: die Legacy-Commands liegen außerhalb des
        // Auto-Discovery-Pfads und müssen explizit registriert sein.
        $commands = array_keys(\Illuminate\Support\Facades\Artisan::all());

        $this->assertContains('legacy:import', $commands);
        $this->assertContains('legacy:import-plan', $commands);
        $this->assertContains('legacy:archive', $commands);
    }
}
