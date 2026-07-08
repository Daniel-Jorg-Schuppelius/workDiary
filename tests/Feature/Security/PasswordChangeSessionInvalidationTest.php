<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasswordChangeSessionInvalidationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SA-08 / Feature-051: Eine Passwort-Änderung (Reset ODER eingeloggter Wechsel)
 * widerruft bestehende Sitzungen und entwertet „remember me"-Cookies —
 * Account-Takeover-Schutz. Serverseitige Session-Löschung greift nur beim
 * Datenbank-Treiber, daher hier explizit erzwungen.
 */
class PasswordChangeSessionInvalidationTest extends TestCase {
    use RefreshDatabase;

    private function seedSession(int $userId): string {
        $id = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('demo'),
            'last_activity' => now()->getTimestamp(),
        ]);

        return $id;
    }

    public function test_password_reset_invalidates_all_sessions_and_rotates_remember_token(): void {
        config(['session.driver' => 'database']);

        $user = User::factory()->user()->create([
            'email' => 'reset@firma.de',
            'is_new_system' => true,
            'remember_token' => 'old-remember-token',
        ]);
        $stranger = User::factory()->user()->create();

        $ownA = $this->seedSession((int) $user->id);
        $ownB = $this->seedSession((int) $user->id);
        $foreign = $this->seedSession((int) $stranger->id);

        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NeuesPass!2026',
            'password_confirmation' => 'NeuesPass!2026',
        ])->assertRedirect(route('login'));

        // Alle eigenen Sitzungen widerrufen …
        $this->assertDatabaseMissing('sessions', ['id' => $ownA]);
        $this->assertDatabaseMissing('sessions', ['id' => $ownB]);
        // … fremde Sitzung unangetastet …
        $this->assertDatabaseHas('sessions', ['id' => $foreign]);
        // … remember_token rotiert.
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }

    public function test_account_password_change_revokes_other_sessions_keeps_current_and_rotates_token(): void {
        config(['session.driver' => 'database']);

        $user = User::factory()->user()->create([
            'is_new_system' => true,
            'password' => Hash::make('AltesPass!2026'),
            'remember_token' => 'old-remember-token',
        ]);

        $otherA = $this->seedSession((int) $user->id);
        $otherB = $this->seedSession((int) $user->id);

        $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'AltesPass!2026',
            'password' => 'NeuesPass!2026',
            'password_confirmation' => 'NeuesPass!2026',
        ])->assertRedirect(route('dashboard'));

        // Fremd-Geräte-Sitzungen weg …
        $this->assertDatabaseMissing('sessions', ['id' => $otherA]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherB]);
        // … das eigene Gerät bleibt angemeldet …
        $this->assertAuthenticated();
        // … remember_token rotiert.
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }
}
