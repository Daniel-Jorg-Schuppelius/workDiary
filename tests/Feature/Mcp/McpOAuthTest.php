<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpOAuthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Mcp;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Models\Mcp\{McpOAuthClient, McpOAuthCode, McpOAuthRefreshToken};
use App\Models\Platform\{Organization, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** MVP-1065: OAuth 2.1 (Code + PKCE, dynamische Registrierung) für Web-Connectoren, Sanctum-Token als Ergebnis. */
class McpOAuthTest extends TestCase {
    use RefreshDatabase;

    private const REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

    private User $user;

    private string $verifier;

    protected function setUp(): void {
        parent::setUp();
        $organization = Organization::factory()->enterprise()->create(['settings' => ['mcp' => ['enabled' => '1']]]);
        $this->user = User::factory()->admin()->create(['organization_id' => $organization->id]);
        $this->verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();
    }

    private function registerClient(): string {
        return (string) $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT]])
            ->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none')->json('client_id');
    }

    /** @return array<string, string> */
    private function authorizeParams(string $clientId, string $scope = 'mcp:read mcp:write'): array {
        return [
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'code_challenge' => OAuth2AuthorizationCodeGrant::pkceChallenge($this->verifier),
            'code_challenge_method' => 'S256',
            'scope' => $scope,
            'state' => 'xyz',
        ];
    }

    private function code(string $clientId, bool $grantWrite = false): string {
        $location = (string) $this->actingAs($this->user)->withRecentAuthentication()
            ->post(route('mcp.oauth.approve'), [...$this->authorizeParams($clientId), 'decision' => 'approve', 'grant_write' => $grantWrite ? '1' : null])
            ->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT . '?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('xyz', $query['state']);

        return (string) $query['code'];
    }

    private function exchange(string $clientId, string $code, ?string $verifier = null): TestResponse {
        $this->app['auth']->forgetGuards();

        return $this->post('/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => self::REDIRECT, 'code_verifier' => $verifier ?? $this->verifier]);
    }

    /** @return list<string> */
    private function toolNames(string $token): array {
        $this->app['auth']->forgetGuards();

        return array_column($this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk()->json('result.tools'), 'name');
    }

    public function test_discovery_metadata_and_unauthorized_hint(): void {
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'));
        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->assertJsonPath('resource', url('/mcp'));

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="' . route('mcp.oauth.protected-resource.nested', ['path' => 'mcp']) . '"');
    }

    public function test_registration_rejects_unsafe_redirects(): void {
        $this->postJson('/oauth/register', ['redirect_uris' => ['http://evil.example/cb']])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
        $this->postJson('/oauth/register', ['redirect_uris' => ['https://ok.example/cb#frag']])->assertStatus(400);
        $this->postJson('/oauth/register', ['redirect_uris' => ['http://127.0.0.1:6274/callback']])->assertCreated();
    }

    public function test_consent_page_and_non_redirectable_errors(): void {
        $clientId = $this->registerClient();

        $this->get(route('mcp.oauth.authorize', $this->authorizeParams($clientId)))->assertRedirect(route('login'));
        $this->actingAs($this->user)->withRecentAuthentication()
            ->get(route('mcp.oauth.authorize', $this->authorizeParams($clientId)))
            ->assertOk()->assertSee('Claude')->assertSee('name="grant_write"', false);

        $this->get(route('mcp.oauth.authorize', [...$this->authorizeParams($clientId), 'redirect_uri' => 'https://evil.example/cb']))->assertStatus(400);
        $this->get(route('mcp.oauth.authorize', [...$this->authorizeParams($clientId), 'code_challenge_method' => 'plain']))
            ->assertRedirect()->assertRedirectContains('error=invalid_request');
    }

    public function test_full_flow_with_read_only_grant_refresh_and_revocation(): void {
        $clientId = $this->registerClient();
        $code = $this->code($clientId);

        $this->exchange($clientId, $code, OAuth2AuthorizationCodeGrant::generatePkceVerifier())->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $tokens = $this->exchange($clientId, $code)->assertOk()->assertJsonPath('scope', 'mcp:read')->assertHeader('Cache-Control')->json();

        $names = $this->toolNames($tokens['access_token']);
        $this->assertContains('customers', $names);
        $this->assertNotContains('create_quote_draft', $names);

        $this->app['auth']->forgetGuards();
        $refreshed = $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']])->assertOk()->json();
        $this->assertNull(PersonalAccessToken::findToken($tokens['access_token']), 'Rotation entwertet das alte Zugriffstoken.');
        $this->app['auth']->forgetGuards();
        $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']])->assertStatus(400);

        $this->app['auth']->forgetGuards();
        $this->post('/oauth/revoke', ['client_id' => $clientId, 'token' => $refreshed['refresh_token']])->assertOk();
        $this->assertNull(PersonalAccessToken::findToken($refreshed['access_token']));
        $this->assertSame(0, McpOAuthRefreshToken::query()->count());
    }

    public function test_write_grant_and_code_replay_revokes_the_token(): void {
        $clientId = $this->registerClient();
        $code = $this->code($clientId, grantWrite: true);

        $tokens = $this->exchange($clientId, $code)->assertOk()->assertJsonPath('scope', 'mcp:read mcp:write')->json();
        $this->assertContains('create_quote_draft', $this->toolNames($tokens['access_token']));

        $this->exchange($clientId, $code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $this->assertNull(PersonalAccessToken::findToken($tokens['access_token']), 'Ein wiederverwendeter Code widerruft das ausgegebene Token.');
    }

    public function test_token_management_revocation_also_kills_the_refresh_token(): void {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->code($clientId))->assertOk()->json();
        $pat = PersonalAccessToken::findToken($tokens['access_token']);
        $this->assertNotNull($pat);
        $this->assertStringStartsWith('MCP · Claude', $pat->name);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->user)->withRecentAuthentication()->delete(route('profile.api-tokens.destroy', Sqid::encode(PersonalAccessToken::class, (int) $pat->id)))->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']])->assertStatus(400);
        $this->assertSame(0, McpOAuthRefreshToken::query()->count());
    }

    public function test_reusing_a_rotated_refresh_token_revokes_the_whole_login(): void {
        $clientId = $this->registerClient();
        $first = $this->exchange($clientId, $this->code($clientId))->assertOk()->json();
        $this->app['auth']->forgetGuards();
        $second = $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $first['refresh_token']])->assertOk()->json();

        // Ein Angreifer spielt das alte Refresh-Token ein: die ganze Anmeldung endet.
        $this->app['auth']->forgetGuards();
        $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $first['refresh_token']])->assertStatus(400);
        $this->assertNull(PersonalAccessToken::findToken($second['access_token']));
        $this->app['auth']->forgetGuards();
        $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $second['refresh_token']])->assertStatus(400);
    }

    public function test_disabled_organization_and_untrusted_targets(): void {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->code($clientId))->assertOk()->json();

        $foreign = (string) $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://connector.example/cb']])->assertCreated()->json('client_id');
        $params = [...$this->authorizeParams($foreign), 'redirect_uri' => 'https://connector.example/cb'];
        $this->actingAs($this->user)->withRecentAuthentication()->get(route('mcp.oauth.authorize', $params))
            ->assertOk()->assertSee('connector.example')->assertSee('name="confirm_untrusted"', false);
        $this->post(route('mcp.oauth.approve'), [...$params, 'decision' => 'approve'])->assertRedirect(route('mcp.oauth.authorize', $params));
        $this->post(route('mcp.oauth.approve'), [...$params, 'decision' => 'approve', 'confirm_untrusted' => '1'])->assertRedirectContains('https://connector.example/cb?code=');

        $this->user->organization->update(['settings' => ['mcp' => ['enabled' => '0']]]);
        $this->get(route('mcp.oauth.authorize', $this->authorizeParams($clientId)))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']])->assertStatus(400);
        $this->assertNull(PersonalAccessToken::findToken($tokens['access_token']));
    }

    public function test_redirect_rules_follow_the_configuration(): void {
        $this->postJson('/oauth/register', ['redirect_uris' => ['cursor://anysphere.cursor-retrieval/oauth/callback']])->assertCreated();
        $this->postJson('/oauth/register', ['redirect_uris' => ['evilapp://cb']])->assertStatus(400);
        config(['mcp.redirect_domains' => ['https://claude.ai']]);
        $this->postJson('/oauth/register', ['redirect_uris' => ['https://other.example/cb']])->assertStatus(400);
        $this->postJson('/oauth/register', ['redirect_uris' => [self::REDIRECT]])->assertCreated();
    }

    public function test_expired_refresh_tokens_and_codes_are_pruned(): void {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->code($clientId))->assertOk()->json();
        McpOAuthRefreshToken::query()->update(['expires_at' => now()->subMinute()]);
        McpOAuthCode::query()->update(['expires_at' => now()->subDays(2)]);

        $this->artisan('model:prune', ['--model' => [McpOAuthRefreshToken::class, McpOAuthCode::class]])->assertSuccessful();

        $this->assertNull(PersonalAccessToken::findToken($tokens['access_token']));
        $this->assertSame(0, McpOAuthRefreshToken::query()->count());
        $this->assertSame(0, McpOAuthCode::query()->count());
    }

    public function test_unused_client_registrations_are_pruned(): void {
        $unused = $this->registerClient();
        $used = $this->registerClient();
        $this->exchange($used, $this->code($used))->assertOk();
        McpOAuthClient::query()->update(['created_at' => now()->subDays(31)]);

        $this->artisan('model:prune', ['--model' => [McpOAuthClient::class]])->assertSuccessful();

        $this->assertNull(McpOAuthClient::query()->where('client_key', $unused)->first());
        $this->assertNotNull(McpOAuthClient::query()->where('client_key', $used)->first());
    }
}
