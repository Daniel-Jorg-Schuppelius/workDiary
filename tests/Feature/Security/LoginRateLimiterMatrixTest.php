<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginRateLimiterMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Brute-Force-Matrix pro-IP vs. pro-Account über BEIDE Login-Limiter
 * (Bauturbo A17, MVP-335). Quelle: ../WorkDiary-Architecture/security/
 * 2fa-session-testplan-2026-07.md ("vollständige Brute-Force-Matrix pro-IP
 * vs. pro-Account über beide Limiter" — bislang nur je einzeln getestet).
 *
 * Beteiligte Limiter:
 *  1. Middleware `throttle:login` (AppServiceProvider): 5/min je
 *     Account+IP-Paar UND 20/min je IP → HTTP 429.
 *  2. Controller-Limiter (LoginController): 5 Fehlversuche je
 *     Account+IP-Paar, 60 s Decay → Redirect mit auth.throttle-Fehler.
 *
 * Die Middleware zählt jeden Request und schirmt den Controller-Limiter im
 * Normalfall ab; der Controller-Limiter bleibt als zweites Netz bestehen
 * (zählt nur FEHLversuche, wird bei Erfolg geleert) und wird hier isoliert
 * mitgeprüft.
 */
class LoginRateLimiterMatrixTest extends TestCase {
    use RefreshDatabase;

    private const IP1 = '198.51.100.7';

    private const IP2 = '203.0.113.99';

    /** Login-Name = E-Mail (LegacyUserProvider löst Neu-System-Konten per E-Mail auf). */
    private const ACCOUNT_X = 'limit.user.x@example.test';

    private User $userX;

    protected function setUp(): void {
        parent::setUp();

        $org = Organization::factory()->create(['slug' => 'limiter-org']);
        $this->userX = User::factory()->user()->create([
            'organization_id' => $org->id,
            'name' => 'limit.user.x',
            'email' => self::ACCOUNT_X,
        ]);
    }

    private function attemptLogin(string $username, string $password, string $ip): TestResponse {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/login', ['username' => $username, 'password' => $password]);
    }

    public function test_sixth_attempt_for_same_account_and_ip_is_throttled(): void {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->attemptLogin(self::ACCOUNT_X, 'falsches-passwort', self::IP1);
            $response->assertStatus(302);
            $response->assertSessionHasErrors('username');
        }

        // 6. Versuch desselben Account+IP-Paars: Middleware-Limiter → 429.
        $this->attemptLogin(self::ACCOUNT_X, 'falsches-passwort', self::IP1)
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_blocked_account_rejects_even_valid_credentials(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->attemptLogin(self::ACCOUNT_X, 'falsches-passwort', self::IP1);
        }

        // Auch das KORREKTE Passwort kommt während der Sperre nicht durch.
        $this->attemptLogin(self::ACCOUNT_X, 'password', self::IP1)
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_same_account_from_other_ip_is_not_account_blocked(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->attemptLogin(self::ACCOUNT_X, 'falsches-passwort', self::IP1);
        }

        // Beide Limiter schlüsseln Account-Sperren je IP: von einer anderen
        // IP zählt der Account-Zähler neu (kein globaler Account-Lockout —
        // sonst wäre ein Fremd-DoS gegen beliebige Konten möglich).
        $response = $this->attemptLogin(self::ACCOUNT_X, 'falsches-passwort', self::IP2);
        $response->assertStatus(302);
        $response->assertSessionHasErrors('username');
    }

    public function test_ip_limiter_blocks_fresh_accounts_after_twenty_requests_from_same_ip(): void {
        // 4 Accounts × 5 Versuche = 20 Requests von IP1: jeder einzelne
        // Account bleibt unter seiner Paar-Schwelle bzw. läuft in sie hinein,
        // die IP-Quote (20/min) ist danach aufgebraucht.
        foreach (['konto.a', 'konto.b', 'konto.c', 'konto.d'] as $account) {
            for ($i = 0; $i < 5; $i++) {
                $this->attemptLogin($account, 'falsches-passwort', self::IP1);
            }
        }

        // Ein bislang UNBERÜHRTER Account von derselben IP → 429 (pro-IP).
        $this->attemptLogin('konto.frisch', 'falsches-passwort', self::IP1)
            ->assertStatus(429);

        // Derselbe frische Account von einer anderen IP bleibt möglich.
        $response = $this->attemptLogin('konto.frisch', 'falsches-passwort', self::IP2);
        $response->assertStatus(302);
        $response->assertSessionHasErrors('username');
    }

    public function test_controller_limiter_blocks_as_second_net_when_middleware_counter_is_fresh(): void {
        // Controller-Limiter direkt auf 5 Fehlversuche setzen (Key-Schema
        // aus LoginController::throttleKey), Middleware-Zähler bleibt bei 0 —
        // z. B. nach einem Deploy mit geleertem Middleware-Cache-Store.
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('login:' . self::ACCOUNT_X . '|' . self::IP1, 60);
        }

        $response = $this->attemptLogin(self::ACCOUNT_X, 'password', self::IP1);
        $response->assertStatus(302);
        $response->assertSessionHasErrors('username');
        $error = (string) session('errors')->first('username');
        $this->assertStringContainsString('Zu viele Anmeldeversuche', $error);
        $this->assertGuest();
    }

    public function test_attempts_below_threshold_do_not_block_successful_login(): void {
        for ($i = 0; $i < 4; $i++) {
            $this->attemptLogin(self::ACCOUNT_X, 'falsches-passwort', self::IP1);
        }

        // 5. Request (4 Fehlversuche + 1): korrektes Passwort → Login klappt,
        // beide Limiter greifen unterhalb der Schwelle nicht.
        $response = $this->attemptLogin(self::ACCOUNT_X, 'password', self::IP1);
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->userX);
    }
}
