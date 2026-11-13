<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasskeyPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Auth\{LoginPolicyMode, TwoFactorType};
use App\Models\Auth\{OrganizationLoginPolicy, TwoFactorCredential};
use App\Models\Platform\User;
use App\Services\Auth\WebAuthnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use Webauthn\{PublicKeyCredentialCreationOptions, PublicKeyCredentialRequestOptions};

/**
 * Feature 205 (MVP-1656): Anmeldung mit Passkey ohne Passwort (zählt als zwei
 * Faktoren) unter der Anmelderichtlinie, Passkey-Pflicht je Rolle mit
 * Wiederherstellungscode als Notweg, Verwaltung im Konto (Name, letzte
 * Nutzung, Entfernen nur mit erneuter Anmeldung). Die Signaturprüfung selbst
 * deckt der WebAuthn-Dienst ab; hier wird sie ersetzt.
 */
final class PasskeyPolicyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string PASSWORD = 'Sicher!2026Pass';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
    }

    private function assertionPayload(string $credentialId): string {
        return (string) json_encode([
            'id' => $credentialId,
            'rawId' => $credentialId,
            'type' => 'public-key',
            'response' => ['clientDataJSON' => 'e30', 'authenticatorData' => 'AAAA', 'signature' => 'c2ln', 'userHandle' => null],
        ]);
    }

    private function passkey(User $user, string $credentialId, string $label = 'Laptop'): TwoFactorCredential {
        return $user->twoFactorCredentials()->create([
            'type' => TwoFactorType::Webauthn->value,
            'label' => $label,
            'credential_id' => $credentialId,
            'data' => ['irrelevant' => true],
            'confirmed_at' => now(),
        ]);
    }

    /** Signaturprüfung erfolgreich, alle übrigen Methoden echt. */
    private function acceptAssertions(): void {
        $this->app->instance(WebAuthnService::class, new class extends WebAuthnService {
            public function verifyAssertion(User $user, string $browserJson, PublicKeyCredentialRequestOptions $options, string $host): bool {
                return true;
            }
        });
    }

    /** @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response> */
    private function passkeyLogin(string $credentialId): \Illuminate\Testing\TestResponse {
        $this->post(route('login.passkey.options'))->assertOk();

        return $this->call('POST', route('login.passkey'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $this->assertionPayload($credentialId));
    }

    /** @param list<string> $roles */
    private function requirePasskeyFor(array $roles): void {
        $policy = new OrganizationLoginPolicy;
        $policy->forceFill(['organization_id' => $this->organization->id, 'passkey_roles' => $roles])->save();
    }

    // ── Akzeptanzkriterium 4 ──────────────────────────────────────────────

    public function test_passkey_meldet_ohne_passwort_und_ohne_weiteren_code_an(): void {
        $this->acceptAssertions();
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = $this->orgUser(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
        $this->passkey($user, 'cred-ok');

        $this->passkeyLogin('cred-ok')->assertOk()->assertJsonStructure(['redirect']);

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('auth.2fa.id'));
    }

    public function test_passkey_anmeldung_folgt_der_anmelderichtlinie(): void {
        $this->acceptAssertions();
        $user = $this->orgUser();
        $this->passkey($user, 'cred-netz');
        $policy = new OrganizationLoginPolicy;
        $policy->forceFill(['organization_id' => $this->organization->id, 'network_mode' => LoginPolicyMode::Enforce, 'networks' => ['198.51.100.0/24']])->save();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
        $this->passkeyLogin('cred-netz')->assertForbidden()->assertJsonStructure(['message']);
        $this->assertGuest();
    }

    // ── Passkey-Pflicht je Rolle ──────────────────────────────────────────

    public function test_pflicht_verweigert_code_wer_einen_passkey_hat_wiederherstellungscode_bleibt_notweg(): void {
        $this->requirePasskeyFor(['user']);
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = $this->orgUser([
            'email' => 'pflicht@example.test',
            'is_new_system' => true,
            'password' => Hash::make(self::PASSWORD),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('aaaaa-bbbbb')],
        ]);
        $this->passkey($user, 'cred-pflicht');

        $this->post('/login', ['username' => 'pflicht@example.test', 'password' => self::PASSWORD])->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.attempt'), ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.login.attempt'), ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_pflicht_ohne_passkey_fuehrt_zur_einrichtung(): void {
        $this->requirePasskeyFor(['user']);
        $user = $this->orgUser();

        $this->actingAs($user)->get(route('account.profile.edit'))->assertRedirect(route('account.2fa.show'));
        $this->actingAs($user)->get(route('account.2fa.show'))->assertOk()->assertSee(__('login_policy.passkey.required_hint'));

        $this->passkey($user, 'cred-neu');
        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();
    }

    public function test_pflicht_gilt_nicht_fuer_andere_rollen(): void {
        $this->requirePasskeyFor(['aussendienst']);
        $user = $this->orgUser();

        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();
    }

    // ── Verwaltung im Konto ───────────────────────────────────────────────

    public function test_passkeys_zeigen_letzte_nutzung_und_lassen_sich_umbenennen(): void {
        $user = $this->orgUser();
        $credential = $this->passkey($user, 'cred-name', 'Alt');
        $credential->forceFill(['last_used_at' => now()->subDay()])->save();

        $this->actingAs($user)->get(route('account.2fa.show'))->assertOk()->assertSee('Alt');

        $this->actingAs($user)->patch(route('account.2fa.credential.update', $credential), ['label' => "  Laptop\u{0007} Büro  "])->assertRedirect(route('account.2fa.show'));
        $this->assertSame('Laptop Büro', $credential->fresh()?->label);

        $foreign = $this->passkey($this->orgUser(), 'cred-fremd');
        $this->actingAs($user)->patch(route('account.2fa.credential.update', $foreign), ['label' => 'Gekapert'])->assertNotFound();
    }

    public function test_entfernen_verlangt_erneute_anmeldung(): void {
        $user = $this->orgUser(['is_new_system' => true, 'password' => Hash::make(self::PASSWORD)]);
        $first = $this->passkey($user, 'cred-1');
        $second = $this->passkey($user, 'cred-2');

        $this->actingAs($user)->delete(route('account.2fa.credential.destroy', $first))->assertSessionHasErrors('credential');
        $this->assertNotNull($first->fresh());

        $this->actingAs($user)->delete(route('account.2fa.credential.destroy', $first), ['current_password' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->assertNull($first->fresh());

        $this->actingAs($user)->withRecentAuthentication()->delete(route('account.2fa.credential.destroy', $second))->assertSessionHasNoErrors();
        $this->assertNull($second->fresh());
    }

    public function test_registrierung_uebernimmt_den_namen_aus_der_abfrage(): void {
        $user = $this->orgUser();
        $service = new class extends WebAuthnService {
            public ?string $label = null;

            public function verifyRegistration(User $user, string $browserJson, PublicKeyCredentialCreationOptions $options, string $host, ?string $label = null): TwoFactorCredential {
                $this->label = $label;

                return new TwoFactorCredential;
            }
        };
        $this->app->instance(WebAuthnService::class, $service);

        $this->actingAs($user)->withRecentAuthentication()->postJson(route('account.2fa.webauthn.options'))->assertOk();
        $this->actingAs($user)->postJson(route('account.2fa.webauthn.register', ['label' => ' Tablet Lager ']), ['id' => 'x'])->assertOk();
        $this->assertSame('Tablet Lager', $service->label);
    }
}
