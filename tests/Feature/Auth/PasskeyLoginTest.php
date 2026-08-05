<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasskeyLoginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Auth;

use App\Enums\Auth\{SsoProtocol, TwoFactorType};
use App\Models\{SsoConnection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Passwortloser Passkey-Primär-Login (MS365-Plan G3): Options-Endpunkt ohne
 * Vorab-Identität (Discoverable Credentials, UV-Pflicht, Einmal-Optionen in
 * der Session), Ablehnungspfade (unbekanntes Credential, ungültige Signatur,
 * Portal-/deaktivierte Konten, SSO-Zwang). Die positive Signaturprüfung läuft
 * über den geteilten WebAuthnService (2FA-Produktionspfad).
 */
final class PasskeyLoginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** Simulierte Browser-Antwort (Assertion) mit gegebener Credential-ID (base64url). */
    private function assertionPayload(string $credentialId): string {
        return (string) json_encode([
            'id' => $credentialId,
            'rawId' => $credentialId,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => rtrim(strtr(base64_encode('{"type":"webauthn.get"}'), '+/', '-_'), '='),
                'authenticatorData' => rtrim(strtr(base64_encode(str_repeat('A', 37)), '+/', '-_'), '='),
                'signature' => rtrim(strtr(base64_encode('sig'), '+/', '-_'), '='),
                'userHandle' => null,
            ],
        ]);
    }

    private function credentialFor(User $user, string $credentialId): void {
        $user->twoFactorCredentials()->create([
            'type' => TwoFactorType::Webauthn->value,
            'label' => 'Passkey',
            'credential_id' => $credentialId,
            'data' => ['irrelevant' => true], // Signaturprüfung schlägt kontrolliert fehl
            'confirmed_at' => now(),
        ]);
    }

    public function test_options_endpoint_returns_discoverable_challenge(): void {
        $response = $this->post(route('login.passkey.options'));

        $response->assertOk()->assertJsonStructure(['challenge', 'rpId']);
        $this->assertSame('required', $response->json('userVerification'));
        $this->assertSame([], $response->json('allowCredentials') ?? []);
        $this->assertNotNull(session('auth.passkey.assert'));
    }

    public function test_options_endpoint_rejects_authenticated_users(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        // guest-Middleware leitet eingeloggte Nutzer um (Controller-abort ist zweite Schicht).
        $this->actingAs($user)->post(route('login.passkey.options'))->assertRedirect();
    }

    public function test_verify_without_options_session_fails(): void {
        $this->postJson(route('login.passkey'), [], [])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_verify_with_unknown_credential_fails(): void {
        $this->post(route('login.passkey.options'))->assertOk();

        $this->call('POST', route('login.passkey'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->assertionPayload('unbekannt-123'))
            ->assertStatus(422);
        $this->assertGuest();
    }

    public function test_verify_with_invalid_signature_fails(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->credentialFor($user, 'cred-abc');

        $this->post(route('login.passkey.options'))->assertOk();
        $this->call('POST', route('login.passkey'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->assertionPayload('cred-abc'))
            ->assertStatus(422);
        $this->assertGuest();
    }

    public function test_verify_rejects_portal_and_deactivated_accounts(): void {
        $customer = \App\Models\Customer::factory()->create(['organization_id' => $this->organization->id]);
        $portal = User::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $this->credentialFor($portal, 'cred-portal');

        $this->post(route('login.passkey.options'))->assertOk();
        $this->call('POST', route('login.passkey'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->assertionPayload('cred-portal'))
            ->assertStatus(422);

        $deactivated = User::factory()->create(['organization_id' => $this->organization->id, 'deactivated_at' => now()]);
        $this->credentialFor($deactivated, 'cred-deact');

        $this->post(route('login.passkey.options'))->assertOk();
        $this->call('POST', route('login.passkey'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->assertionPayload('cred-deact'))
            ->assertStatus(422);
        $this->assertGuest();
    }

    public function test_verify_respects_sso_enforcement(): void {
        SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'label' => 'Entra',
            'active' => true,
            'enforced' => true,
            'issuer' => 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0',
            'client_id' => 'client-1',
        ]);
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->credentialFor($user, 'cred-sso');

        $this->post(route('login.passkey.options'))->assertOk();
        $this->call('POST', route('login.passkey'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->assertionPayload('cred-sso'))
            ->assertStatus(403);
        $this->assertGuest();
    }

    public function test_login_page_offers_passkey_button(): void {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-webauthn-assert', false)
            ->assertSee(route('login.passkey.options'), false);
    }
}
