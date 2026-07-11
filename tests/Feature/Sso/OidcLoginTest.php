<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OidcLoginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sso;

use App\Enums\Auth\SsoProtocol;
use App\Models\{Organization, SsoConnection, SsoIdentity, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\{AlgorithmManager, JWK};
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-120: OIDC-SSO-Login. IdP komplett über Http::fake (Discovery, JWKS,
 * Token-Endpoint); ID-Tokens werden mit einem eigenen RSA-Testschlüssel
 * signiert (web-token). Verifiziert Pflichtprüfungen (state, nonce, iss, aud,
 * exp, Signatur), Kontoverknüpfung nur über Subject, E-Mail-Erstverknüpfung
 * als Opt-in, Deaktivierungs-Sperre und Modul-Gating.
 */
final class OidcLoginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const ISSUER = 'https://idp.example';

    private JWK $idpKey;
    private SsoConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);

        $this->idpKey = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-1']);

        $this->connection = SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'label' => 'Test-IdP',
            'active' => true,
            'issuer' => self::ISSUER,
            'client_id' => 'workdiary-client',
            'client_secret' => 'secret-123',
        ]);
    }

    private function fakeDiscoveryAndJwks(): void {
        Http::fake([
            self::ISSUER . '/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/authorize',
                'token_endpoint' => self::ISSUER . '/token',
                'jwks_uri' => self::ISSUER . '/jwks',
                'end_session_endpoint' => self::ISSUER . '/logout',
            ]),
            self::ISSUER . '/jwks' => Http::response([
                'keys' => [$this->idpKey->toPublic()->jsonSerialize()],
            ]),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function idToken(string $nonce, array $overrides = []): string {
        $claims = array_merge([
            'iss' => self::ISSUER,
            'sub' => 'subject-1',
            'aud' => 'workdiary-client',
            'exp' => now()->addMinutes(5)->getTimestamp(),
            'iat' => now()->getTimestamp(),
            'nonce' => $nonce,
        ], $overrides);

        $jws = (new JWSBuilder(new AlgorithmManager([new RS256()])))
            ->create()
            ->withPayload((string) json_encode($claims))
            ->addSignature($this->idpKey, ['alg' => 'RS256', 'kid' => 'test-1'])
            ->build();

        return (new CompactSerializer())->serialize($jws, 0);
    }

    /**
     * Startet den Flow, baut mit dem Session-Nonce ein ID-Token und ruft den
     * Callback auf.
     *
     * @param array<string, mixed> $claimOverrides
     */
    private function runFlow(array $claimOverrides = [], ?string $stateOverride = null): \Illuminate\Testing\TestResponse {
        $this->fakeDiscoveryAndJwks();

        $start = $this->get(route('sso.start', ['slug' => $this->organization->slug]));
        $start->assertRedirect();
        $this->assertStringStartsWith(self::ISSUER . '/authorize?', (string) $start->headers->get('Location'));

        /** @var array{state: string, nonce: string} $flow */
        $flow = (array) session('sso.oidc');

        $idToken = $this->idToken($flow['nonce'], $claimOverrides);
        Http::fake([
            self::ISSUER . '/token' => Http::response([
                'access_token' => 'at-1',
                'token_type' => 'Bearer',
                'id_token' => $idToken,
            ]),
        ]);

        return $this->get(route('sso.oidc.callback', [
            'code' => 'code-1',
            'state' => $stateOverride ?? $flow['state'],
        ]));
    }

    public function test_login_with_linked_identity_succeeds(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'subject-1',
        ]);

        $this->runFlow()->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->assertNotNull(
            SsoIdentity::query()->where('user_id', $user->id)->first()?->last_login_at,
            'last_login_at der Identität muss gesetzt sein.'
        );
    }

    public function test_authorization_redirect_carries_pkce_and_nonce(): void {
        $this->fakeDiscoveryAndJwks();
        $start = $this->get(route('sso.start', ['slug' => $this->organization->slug]));
        $location = (string) $start->headers->get('Location');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('code', $params['response_type'] ?? null);
        $this->assertSame('S256', $params['code_challenge_method'] ?? null);
        $this->assertNotEmpty($params['code_challenge'] ?? null);
        $this->assertNotEmpty($params['nonce'] ?? null);
        $this->assertNotEmpty($params['state'] ?? null);
        $this->assertStringContainsString('openid', (string) ($params['scope'] ?? ''));
    }

    public function test_state_mismatch_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'subject-1',
        ]);

        $this->runFlow(stateOverride: 'wrong-state')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_nonce_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'subject-1',
        ]);

        $this->runFlow(['nonce' => 'evil-nonce'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_audience_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'subject-1',
        ]);

        $this->runFlow(['aud' => 'other-client'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_expired_token_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'subject-1',
        ]);

        $this->runFlow(['exp' => now()->subMinutes(10)->getTimestamp()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unknown_identity_without_email_link_is_rejected(): void {
        User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'person@example.org',
        ]);

        $this->runFlow(['email' => 'person@example.org'])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, SsoIdentity::query()->count(), 'Ohne Opt-in darf keine Verknüpfung entstehen.');
    }

    public function test_email_link_optin_links_exactly_one_account(): void {
        $this->connection->forceFill(['allow_email_link' => true])->save();
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'person@example.org',
        ]);

        $this->runFlow(['email' => 'person@example.org'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('sso_identities', [
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'subject-1',
        ]);
    }

    public function test_email_link_never_crosses_tenant_boundary(): void {
        $this->connection->forceFill(['allow_email_link' => true])->save();
        $foreignOrg = Organization::factory()->create();
        User::factory()->create([
            'organization_id' => $foreignOrg->id,
            'email' => 'person@example.org',
        ]);

        $this->runFlow(['email' => 'person@example.org'])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, SsoIdentity::query()->count());
    }

    public function test_deactivated_user_is_rejected_after_idp_login(): void {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'deactivated_at' => now(),
        ]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'subject-1',
        ]);

        $this->runFlow()->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sso_never_creates_accounts(): void {
        $this->connection->forceFill(['allow_email_link' => true])->save();
        $before = User::query()->count();

        $this->runFlow(['email' => 'nobody@example.org'])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame($before, User::query()->count(), 'SSO darf nie Konten anlegen.');
    }

    public function test_module_gating_blocks_free_plan(): void {
        $this->organization->forceFill(['plan' => Organization::PLAN_FREE])->save();
        app(\App\Services\Licensing\FeatureFlagResolver::class)->flush();

        $this->get(route('sso.start', ['slug' => $this->organization->slug]))->assertStatus(403);
    }

    public function test_start_prefers_oidc_and_requires_active_connection(): void {
        $this->connection->forceFill(['active' => false])->save();

        $this->get(route('sso.start', ['slug' => $this->organization->slug]))->assertNotFound();
    }

    public function test_discovery_issuer_mismatch_is_rejected(): void {
        Http::fake([
            self::ISSUER . '/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://evil.example',
                'authorization_endpoint' => self::ISSUER . '/authorize',
                'token_endpoint' => self::ISSUER . '/token',
                'jwks_uri' => self::ISSUER . '/jwks',
            ]),
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->get(route('sso.start', ['slug' => $this->organization->slug]))->assertRedirect(route('login'));
    }
}
