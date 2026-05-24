<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyUserProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Legacy\Auth;

use App\Legacy\Auth\LegacyUserProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

class LegacyUserProviderTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();

        // SQLite-In-Memory als "Legacy"-Connection simulieren
        config([
            'database.connections.legacy' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::connection('legacy')->statement(
            'CREATE TABLE user (id INTEGER PRIMARY KEY, uname TEXT, userpw TEXT, email TEXT)'
        );
        DB::connection('legacy')->table('user')->insert([
            'id' => 42,
            'uname' => 'legacyuser',
            'userpw' => 'weak-pw',
            'email' => 'legacy@example.test',
        ]);
    }

    public function test_first_login_creates_shadow_account_without_usable_password(): void {
        $provider = new LegacyUserProvider(Hash::driver());

        $user = $provider->retrieveByCredentials([
            'username' => 'legacyuser',
            'password' => 'weak-pw',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(42, $user->legacy_user_id);
        $this->assertFalse($user->existsInNewSystem(), 'Schatten-Account darf nicht ins neue System');
        $this->assertFalse(
            Hash::check('weak-pw', $user->password),
            'Legacy-Klartext-Passwort darf NICHT in users.password landen',
        );
    }

    public function test_existing_account_password_is_not_overwritten_on_legacy_login(): void {
        $existing = User::factory()->create([
            'legacy_user_id' => 42,
            'is_new_system' => true,
            'password' => Hash::make('strong-new-password'),
        ]);

        $provider = new LegacyUserProvider(Hash::driver());
        $user = $provider->retrieveByCredentials([
            'username' => 'legacyuser',
            'password' => 'weak-pw',
        ]);

        /** @var User $user */
        $this->assertSame($existing->id, $user->id);
        /** @var User $fresh */
        $fresh = User::query()->findOrFail($user->id);
        $this->assertTrue(Hash::check('strong-new-password', $fresh->password));
        $this->assertTrue($fresh->existsInNewSystem());
    }

    public function test_validate_credentials_rejects_local_password_for_legacy_user(): void {
        $provider = new LegacyUserProvider(Hash::driver());

        // Schatten-Account anlegen
        $user = $provider->retrieveByCredentials([
            'username' => 'legacyuser',
            'password' => 'weak-pw',
        ]);

        // Korrektes Legacy-Passwort passt
        $this->assertTrue($provider->validateCredentials($user, ['password' => 'weak-pw']));
        // Falsches Legacy-Passwort schlägt fehl
        $this->assertFalse($provider->validateCredentials($user, ['password' => 'wrong']));
    }
}
