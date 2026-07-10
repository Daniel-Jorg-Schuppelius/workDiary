<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorHardeningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Mail\TwoFactorCodeMail;
use App\Models\{Customer, User};
use App\Services\Auth\EmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\{Auth, Hash, Mail};
use PragmaRX\Google2FAQRCode\Google2FA;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * SA-02/03/04-Regressionen für beide Guards: TOTP-Replay über die Challenge-
 * Route (nicht nur die Service-Einheit), Recovery-Code-Einmaligkeit,
 * Challenge-Rate-Limit sowie Mail-OTP-Ablauf/Einmalverbrauch/Burn.
 */
class TwoFactorHardeningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()
            ->kunde((int) $customer->id, (int) $this->organization->id)
            ->create([
                'organization_id' => $this->organization->id,
                'email' => 'portal2fa@example.test',
                'password' => Hash::make('secret-pass'),
            ]);
    }

    private function engine(): Google2FA {
        return app(Google2FA::class);
    }

    /** Web-Neu-System-User mit aktivem TOTP + gehashten Recovery-Codes (wie Prod). */
    private function webEnrolledUser(): array {
        $secret = $this->engine()->generateSecretKey();
        $user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'web2fa@firma.de',
            'is_new_system' => true,
            'password' => Hash::make('Sicher!2026Passwort'),
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(static fn (string $c): string => Hash::make($c), ['web-aaaaa', 'web-bbbbb']),
            'two_factor_confirmed_at' => now(),
        ]);

        return [$user, $secret];
    }

    private function customerEnable2fa(): string {
        $secret = $this->engine()->generateSecretKey();
        $this->portalUser->forceFill([
            'two_factor_secret' => $secret,
            // Prod-treu gehasht, damit Recovery-Login getestet werden kann.
            'two_factor_recovery_codes' => array_map(static fn (string $c): string => Hash::make($c), ['cust-aaaaa', 'cust-bbbbb']),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    // ---- SA-02: TOTP-Replay über die Customer-Challenge ---------------------

    public function test_customer_totp_code_cannot_be_replayed(): void {
        $secret = $this->customerEnable2fa();
        $code = $this->engine()->getCurrentOtp($secret);

        // Erste Verwendung authentifiziert.
        $this->withSession(['auth.customer.2fa.id' => $this->portalUser->id])
            ->post(route('customer.two-factor.login.attempt'), ['code' => $code]);
        $this->assertTrue(auth('customer')->check());

        auth('customer')->logout();

        // Replay desselben Codes in frischer Challenge → abgelehnt.
        $this->withSession(['auth.customer.2fa.id' => $this->portalUser->id])
            ->post(route('customer.two-factor.login.attempt'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertFalse(auth('customer')->check());
    }

    // ---- SA-03: Recovery-Code-Einmaligkeit ---------------------------------

    public function test_web_recovery_code_cannot_be_reused(): void {
        [$user] = $this->webEnrolledUser();

        $this->withSession(['auth.2fa.id' => $user->id])
            ->post(route('two-factor.login.attempt'), ['recovery_code' => 'web-aaaaa']);
        $this->assertAuthenticated();

        Auth::logout();

        $this->withSession(['auth.2fa.id' => $user->id])
            ->post(route('two-factor.login.attempt'), ['recovery_code' => 'web-aaaaa'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_customer_recovery_code_login_consumes_one_code(): void {
        $this->customerEnable2fa();

        $this->withSession(['auth.customer.2fa.id' => $this->portalUser->id])
            ->post(route('customer.two-factor.login.attempt'), ['recovery_code' => 'cust-aaaaa']);

        $this->assertTrue(auth('customer')->check());
        // Ein Code verbraucht → nur noch einer übrig.
        $this->assertCount(1, (array) $this->portalUser->fresh()->two_factor_recovery_codes);
    }

    // ---- SA-02: Challenge-Rate-Limit (Controller-Limiter isoliert) ----------

    public function test_web_challenge_locks_out_after_repeated_wrong_codes(): void {
        // Route-Throttle entfernen, um den controller-eigenen 5-Versuch-Limiter
        // isoliert zu prüfen.
        $this->withoutMiddleware(ThrottleRequests::class);
        [$user, $secret] = $this->webEnrolledUser();

        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['auth.2fa.id' => $user->id])
                ->post(route('two-factor.login.attempt'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        // Selbst ein GÜLTIGER Code wird jetzt gesperrt abgewiesen (Rate-Limit,
        // nicht Code-Falschheit) — und bleibt Gast.
        $this->withSession(['auth.2fa.id' => $user->id])
            ->post(route('two-factor.login.attempt'), ['code' => $this->engine()->getCurrentOtp($secret)])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    // ---- SA-04: Mail-OTP Ablauf / Einmalverbrauch / Burn --------------------

    private function sendAndCaptureOtp(User $user): string {
        Mail::fake();
        app(EmailOtpService::class)->send($user);
        $captured = null;
        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $m) use (&$captured): bool {
            $captured = $m->code;

            return true;
        });

        return (string) $captured;
    }

    public function test_mail_otp_is_single_use(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $service = app(EmailOtpService::class);
        $code = $this->sendAndCaptureOtp($user);

        $this->assertTrue($service->verify($user, $code));
        // Zweitverwendung nach Treffer → verbraucht.
        $this->assertFalse($service->verify($user, $code));
    }

    public function test_mail_otp_expires_after_ttl(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $service = app(EmailOtpService::class);
        $code = $this->sendAndCaptureOtp($user);

        // TTL ist 300s → nach 301s abgelaufen.
        $this->travel(301)->seconds();
        $this->assertFalse($service->verify($user, $code));
    }

    public function test_mail_otp_is_burned_after_five_wrong_attempts(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $service = app(EmailOtpService::class);
        $code = $this->sendAndCaptureOtp($user);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($service->verify($user, '111111'));
        }
        // Nach 5 Fehlversuchen ist der Code verbrannt — auch korrekt schlägt fehl.
        $this->assertFalse($service->verify($user, $code));
    }
}
