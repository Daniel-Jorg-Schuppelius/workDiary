<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerTwoFactorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Mail\TwoFactorCodeMail;
use App\Models\{Customer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Mail};
use PragmaRX\Google2FAQRCode\Google2FA;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CustomerTwoFactorTest extends TestCase {
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
                'email' => 'portal@example.test',
                'password' => Hash::make('secret-pass'),
            ]);
    }

    private function engine(): Google2FA {
        return app(Google2FA::class);
    }

    private function enable2fa(): string {
        $secret = $this->engine()->generateSecretKey();
        $this->portalUser->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['rec-aaaaa', 'rec-bbbbb'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    public function test_login_with_2fa_parks_session_and_requires_code(): void {
        $this->enable2fa();

        $this->post(route('customer.login.attempt'), ['email' => 'portal@example.test', 'password' => 'secret-pass'])
            ->assertRedirect(route('customer.two-factor.login'));
        $this->assertFalse(auth('customer')->check());
    }

    public function test_valid_totp_completes_customer_login(): void {
        $secret = $this->enable2fa();

        $this->post(route('customer.login.attempt'), ['email' => 'portal@example.test', 'password' => 'secret-pass']);
        $this->post(route('customer.two-factor.login.attempt'), ['code' => $this->engine()->getCurrentOtp($secret)]);

        $this->assertTrue(auth('customer')->check());
        $this->assertSame($this->portalUser->id, auth('customer')->id());
    }

    public function test_enrollment_and_confirm_in_portal(): void {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.2fa.enable'))
            ->assertRedirect(route('customer.2fa.show'));

        $this->portalUser->refresh();
        $code = $this->engine()->getCurrentOtp((string) $this->portalUser->two_factor_secret);

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.2fa.confirm'), ['code' => $code])
            ->assertRedirect(route('customer.2fa.show'));

        $this->assertTrue($this->portalUser->fresh()->hasTwoFactorEnabled());
    }

    public function test_webauthn_registration_options_in_portal(): void {
        $response = $this->actingAs($this->portalUser, 'customer')->post(route('customer.2fa.webauthn.options'));

        $response->assertOk();
        $this->assertArrayHasKey('challenge', $response->json());
        $this->assertNotNull(session('webauthn.register'));
    }

    public function test_email_otp_enrollment_and_login_in_portal(): void {
        Mail::fake();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.2fa.email.enable'))
            ->assertRedirect(route('customer.2fa.show'));

        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $m) use (&$code): bool {
            $code = $m->code;

            return true;
        });
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.2fa.email.confirm'), ['email_code' => $code])
            ->assertRedirect(route('customer.2fa.show'));
        $this->assertTrue($this->portalUser->twoFactorCredentials()->where('type', 'email')->whereNotNull('confirmed_at')->exists());

        // Login-Challenge per E-Mail. 30s-Versanddrossel abwarten (sonst greift canSend).
        $this->travel(31)->seconds();
        Mail::fake();
        $this->withSession(['auth.customer.2fa.id' => $this->portalUser->id])
            ->post(route('customer.two-factor.login.email'))->assertRedirect();
        $challengeCode = null;
        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $m) use (&$challengeCode): bool {
            $challengeCode = $m->code;

            return true;
        });
        $this->withSession(['auth.customer.2fa.id' => $this->portalUser->id])
            ->post(route('customer.two-factor.login.attempt'), ['email_code' => $challengeCode]);

        $this->assertTrue(auth('customer')->check());
    }
}
