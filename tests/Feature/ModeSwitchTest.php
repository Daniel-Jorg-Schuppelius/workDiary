<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModeSwitchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeSwitchTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    /**
     * Erstellt einen User mit Zugriff auf BEIDE Bereiche (Legacy + Neu),
     * damit der reine Routing-Aspekt von switchMode unabhängig von der
     * Zugriffstrennung getestet werden kann.
     */
    private function dualAccessUser(): User {
        return User::factory()->user()->create([
            'legacy_user_id' => 1234,
            'is_new_system' => true,
        ]);
    }

    public function test_switching_from_home_redirects_back_to_home(): void {
        $response = $this
            ->actingAs($this->dualAccessUser())
            ->post(route('mode.switch', 'new'), [
                'origin' => 'home',
            ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('work_mode', 'new');
    }

    public function test_switching_from_legacy_diary_index_redirects_to_new_diary_index(): void {
        $response = $this
            ->actingAs($this->dualAccessUser())
            ->post(route('mode.switch', 'new'), [
                'origin' => 'legacy.diary.index',
            ]);

        $response->assertRedirect(route('diary.index'));
        $response->assertSessionHas('work_mode', 'new');
    }

    public function test_switching_from_legacy_only_pages_falls_back_to_new_diary_index(): void {
        $response = $this
            ->actingAs($this->dualAccessUser())
            ->post(route('mode.switch', 'new'), [
                'origin' => 'legacy.archive.index',
            ]);

        $response->assertRedirect(route('diary.index'));
        $response->assertSessionHas('work_mode', 'new');
    }

    public function test_switching_from_new_diary_create_redirects_to_legacy_diary_create(): void {
        config(['database.connections.legacy.database' => 'legacy_test']);

        $response = $this
            ->actingAs($this->dualAccessUser())
            ->post(route('mode.switch', 'legacy'), [
                'origin' => 'diary.create',
            ]);

        $response->assertRedirect(route('legacy.diary.create'));
        $response->assertSessionHas('work_mode', 'legacy');
    }

    public function test_switching_to_legacy_is_blocked_when_legacy_database_is_not_configured(): void {
        config(['database.connections.legacy.database' => '']);

        $response = $this
            ->actingAs($this->dualAccessUser())
            ->post(route('mode.switch', 'legacy'), [
                'origin' => 'diary.index',
            ]);

        $response->assertSessionHas('work_mode', 'new');
        $response->assertSessionHas('success', 'Legacy-Modus ist nicht verfügbar (Legacy-DB nicht konfiguriert).');
    }
}
