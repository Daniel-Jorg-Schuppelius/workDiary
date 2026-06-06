<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdminSetMemberPasswordTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, Hash};
use Tests\TestCase;

class AdminSetMemberPasswordTest extends TestCase {
    use RefreshDatabase;

    /**
     * Ein vom Admin gesetztes (Neu-System-)Passwort muss greifen, auch wenn der
     * Account ursprünglich Legacy-verknüpft war. Ausschlaggebend ist is_new_system.
     */
    public function test_new_system_password_works_for_legacy_linked_member(): void {
        $user = User::factory()->user()->create([
            'email' => 'leg@firma.de',
            'legacy_user_id' => 27,
            'is_new_system' => false,
            'password' => Hash::make('OldLegacy!1'),
        ]);

        // Vor der Migration: rein Legacy-verknüpft → users.password wird ignoriert,
        // das Neu-System-Passwort darf NICHT funktionieren (keine Legacy-DB im Test).
        $this->assertFalse(Auth::attempt(['username' => 'leg@firma.de', 'password' => 'NewPass!2026']));

        // Admin setzt ein neues Passwort und stellt den Account auf Neu-System um.
        $user->forceFill([
            'is_new_system' => true,
            'password' => Hash::make('NewPass!2026'),
            'must_change_password' => true,
        ])->save();
        Auth::logout();

        // Jetzt muss der Login mit dem neuen Passwort (per E-Mail) gelingen.
        $this->assertTrue(Auth::attempt(['username' => 'leg@firma.de', 'password' => 'NewPass!2026']));
        $this->assertFalse(Auth::attempt(['username' => 'leg@firma.de', 'password' => 'OldLegacy!1']));
    }
}
