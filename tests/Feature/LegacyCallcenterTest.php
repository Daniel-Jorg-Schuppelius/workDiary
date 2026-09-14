<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyCallcenterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

class LegacyCallcenterTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
    }

    public function test_callcenter_login_succeeds_and_stores_session(): void {
        DB::connection('legacy')->table('calluser')->insert([
            'uname' => 'agent',
            'userpw' => 'secret',
        ]);

        $this->post(route('legacy.callcenter.login.submit'), [
            'username' => 'agent',
            'password' => 'secret',
        ])->assertRedirect(route('legacy.callcenter.notdienst'));

        $this->assertSame('agent', session('legacy_callcenter_user'));
    }

    /**
     * Sicherheitsaudit 2026-09-13: Der Controller drosselte je Benutzername,
     * die Route selbst gar nicht. Passwort-Spraying — ein Versuch je Name ueber
     * viele Namen von derselben Adresse — lief damit ungebremst gegen die
     * Klartext-Passwoerter der Legacy-Datenbank.
     */
    public function test_password_spraying_from_one_address_is_throttled(): void {
        $lastStatus = 200;
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $lastStatus = $this->from(route('legacy.callcenter.login'))
                ->post(route('legacy.callcenter.login.submit'), [
                    'username' => 'agent' . $attempt, // jedes Mal ein anderer Name
                    'password' => 'wrong',
                ])->getStatusCode();
            if ($lastStatus === 429) {
                break;
            }
        }

        $this->assertSame(429, $lastStatus, 'Viele Namen von einer Adresse muessen in die Drossel laufen.');
    }

    public function test_callcenter_login_is_rate_limited_after_repeated_failures(): void {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('legacy.callcenter.login'))->post(route('legacy.callcenter.login.submit'), [
                'username' => 'agent',
                'password' => 'wrong',
            ])->assertRedirect(route('legacy.callcenter.login'));
        }

        $this->from(route('legacy.callcenter.login'))->post(route('legacy.callcenter.login.submit'), [
            'username' => 'agent',
            'password' => 'wrong',
        ])->assertRedirect(route('legacy.callcenter.login'))
            ->assertSessionHasErrors('username');
    }
}
