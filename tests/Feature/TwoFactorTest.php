<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Mail};
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase {
    use RefreshDatabase;

    private function engine(): Google2FA {
        return app(Google2FA::class);
    }

    /** Legt einen Neu-System-User mit aktivem 2FA an und liefert [user, secret]. */
    private function enrolledUser(): array {
        $secret = $this->engine()->generateSecretKey();
        $user = User::factory()->user()->create([
            'email' => 'tfa@firma.de',
            'is_new_system' => true,
            'password' => Hash::make('Sicher!2026Pass'),
            'two_factor_secret' => $secret,
            // Recovery-Codes liegen gehasht (nicht reversibel) vor – wie in Produktion.
            'two_factor_recovery_codes' => array_map(static fn (string $c): string => Hash::make($c), ['aaaaa-bbbbb', 'ccccc-ddddd']),
            'two_factor_confirmed_at' => now(),
        ]);

        return [$user, $secret];
    }

    public function test_login_with_2fa_parks_session_and_does_not_authenticate(): void {
        [$user] = $this->enrolledUser();

        $this->post('/login', ['username' => 'tfa@firma.de', 'password' => 'Sicher!2026Pass'])
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_challenge_with_valid_totp_authenticates(): void {
        [$user, $secret] = $this->enrolledUser();

        $this->post('/login', ['username' => 'tfa@firma.de', 'password' => 'Sicher!2026Pass']);
        $this->assertGuest();

        $this->post(route('two-factor.login.attempt'), ['code' => $this->engine()->getCurrentOtp($secret)]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_recovery_code_authenticates_and_is_consumed(): void {
        [$user] = $this->enrolledUser();

        $this->post('/login', ['username' => 'tfa@firma.de', 'password' => 'Sicher!2026Pass']);
        $this->post(route('two-factor.login.attempt'), ['recovery_code' => 'aaaaa-bbbbb']);

        $this->assertAuthenticated();
        $this->assertNotContains('aaaaa-bbbbb', (array) $user->fresh()->two_factor_recovery_codes);
    }

    public function test_wrong_code_does_not_authenticate(): void {
        $this->enrolledUser();

        $this->post('/login', ['username' => 'tfa@firma.de', 'password' => 'Sicher!2026Pass']);
        $this->post(route('two-factor.login.attempt'), ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_enrollment_then_confirm_enables_2fa(): void {
        $user = User::factory()->user()->create(['is_new_system' => true]);

        $this->actingAs($user)->post(route('account.2fa.enable'))->assertRedirect(route('account.2fa.show'));
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        $code = $this->engine()->getCurrentOtp((string) $user->two_factor_secret);
        $this->actingAs($user)->post(route('account.2fa.confirm'), ['code' => $code])->assertRedirect(route('account.2fa.show'));

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_setup_page_renders_inline_svg_qr_not_data_uri(): void {
        $user = User::factory()->user()->create(['is_new_system' => true]);
        $this->actingAs($user)->post(route('account.2fa.enable'));

        $response = $this->actingAs($user)->get(route('account.2fa.show'));
        $response->assertOk();
        $response->assertSee('<svg', false);     // echtes Inline-SVG eingebettet
        $response->assertDontSee('data:image');  // KEIN data:-URI (alter Bug)
    }

    public function test_webauthn_registration_options_endpoint_returns_challenge(): void {
        $user = User::factory()->user()->create(['is_new_system' => true]);

        $response = $this->actingAs($user)->post(route('account.2fa.webauthn.options'));

        $response->assertOk();
        $this->assertArrayHasKey('challenge', $response->json());
        $this->assertNotNull($response->json('rp.id'));
        $this->assertNotNull(session('webauthn.register'));
    }

    public function test_org_required_redirects_user_without_2fa_to_setup(): void {
        $user = User::factory()->user()->create(['is_new_system' => true]);
        $user->organization?->forceFill(['two_factor_required' => true])->save();

        $this->actingAs($user)->get(route('account.password.edit'))
            ->assertRedirect(route('account.2fa.show'));
    }

    public function test_email_otp_enrollment_confirms_factor(): void {
        Mail::fake();
        $user = User::factory()->user()->create(['is_new_system' => true]);

        $this->actingAs($user)->post(route('account.2fa.email.enable'))->assertRedirect();

        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $m) use (&$code): bool {
            $code = $m->code;

            return true;
        });
        $this->assertNotNull($code);

        $this->actingAs($user)->post(route('account.2fa.email.confirm'), ['email_code' => $code])->assertRedirect();

        $this->assertTrue($user->twoFactorCredentials()->where('type', 'email')->whereNotNull('confirmed_at')->exists());
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_login_challenge_via_email_code_authenticates(): void {
        Mail::fake();
        $user = User::factory()->user()->create(['is_new_system' => true]);
        $user->twoFactorCredentials()->create(['type' => 'email', 'label' => $user->email, 'confirmed_at' => now()]);

        // Geparkte Identität → Code per E-Mail anfordern.
        $this->withSession(['auth.2fa.id' => $user->id])->post(route('two-factor.login.email'))->assertRedirect();

        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $m) use (&$code): bool {
            $code = $m->code;

            return true;
        });

        // Mit dem Code bestätigen → eingeloggt.
        $this->withSession(['auth.2fa.id' => $user->id, 'auth.2fa.remember' => false])
            ->post(route('two-factor.login.attempt'), ['email_code' => $code]);

        $this->assertAuthenticatedAs($user);
    }
}
