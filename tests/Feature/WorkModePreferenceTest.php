<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkModePreferenceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sichert die Konsolidierung der Arbeitsmodus-Präferenz in die Per-User-Bag
 * users.preferences['work_mode'] (statt eigener Spalte) ab.
 */
class WorkModePreferenceTest extends TestCase {
    use RefreshDatabase;

    public function test_work_mode_lives_in_preferences_bag(): void {
        $user = User::factory()->create();

        $user->setPreference('work_mode', 'new');
        $user = $user->fresh();

        // Wert liegt in der Per-User-Bag users.preferences – nicht in einer
        // eigenen Spalte (die ist konsolidiert/entfernt).
        $this->assertSame('new', $user->getPreference('work_mode'));
        $this->assertSame('new', ($user->preferences ?? [])['work_mode'] ?? null);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('users', 'preferred_work_mode'));
    }

    public function test_preferred_work_mode_reflects_stored_value_for_dual_access(): void {
        // Zugriff auf beide Bereiche → kein Normalisieren des Werts. Seit dem
        // Datenfluss-Audit 2026-09-17 (tenant-legacy-2) genügt dafür die
        // Org-Admin-Rolle nicht mehr: es braucht einen Legacy-Bezug.
        $user = User::factory()->admin()->create(['legacy_user_id' => 1, 'is_new_system' => true]);

        $this->assertSame('legacy', $user->preferredWorkMode(), 'Default ohne gespeicherten Wert.');

        $user->setPreference('work_mode', 'new');
        $this->assertSame('new', $user->fresh()->preferredWorkMode());
    }

    /** Ohne Legacy-Bezug gibt es nichts zu wählen — der Default ist der neue Bereich. */
    public function test_preferred_work_mode_is_new_without_legacy_binding(): void {
        $user = User::factory()->admin()->create(['legacy_user_id' => null, 'is_new_system' => true]);

        $this->assertSame('new', $user->preferredWorkMode());
    }

    public function test_switch_mode_route_persists_into_preferences(): void {
        $user = User::factory()->create(['is_new_system' => true]);

        $this->actingAs($user)->post('/mode/new', ['origin' => 'home'])->assertRedirect();

        $this->assertSame('new', $user->fresh()->getPreference('work_mode'));
    }
}
