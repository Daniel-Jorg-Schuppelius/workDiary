<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyOAuthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Etsy;

use App\Models\{EtsyConnection, Organization, PluginSetting, User};
use App\Plugins\Etsy\Api\EtsyOAuthGrant;
use App\Plugins\Etsy\EtsyPlugin;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-494 (Phase 66): OAuth-Verbindungsflow mit Org-eigener Seller-App —
 * PKCE-Pflicht (Public Client, kein client_secret im Token-Tausch),
 * Einmal-State, Shop-Ermittlung über die User-ID aus dem Token-Präfix,
 * Unique-Grenze „ein Shop → eine Organisation".
 */
final class EtsyOAuthTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EtsyPlugin::ID,
            'enabled' => true,
            'settings' => ['keystring' => 'ks-1', 'shared_secret' => 'sec-1'],
        ]);
    }

    /** Bindet den Grant mit gemocktem Token-Endpunkt (Public-Client-Antwort). */
    private function mockTokenEndpoint(): void {
        $mock = new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_token' => '12345.acc-token',
                'refresh_token' => 'ref-token-1',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ])),
        ]);
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $this->app->singleton(EtsyOAuthGrant::class, static fn(): EtsyOAuthGrant => new EtsyOAuthGrant($guzzle));
    }

    /** Startet den Flow und liefert den state aus der Authorize-URL. */
    private function startAndCaptureState(): string {
        $response = $this->post(route('admin.etsy.oauth.start'));
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('https://www.etsy.com/oauth/connect', $location);
        $this->assertStringContainsString('code_challenge=', $location);
        $this->assertStringContainsString('code_challenge_method=S256', $location);
        $this->assertStringContainsString('client_id=ks-1', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return (string) $query['state'];
    }

    public function test_start_requires_configured_seller_app(): void {
        PluginSetting::query()->firstOrFail()->update(['settings' => []]);

        $this->from(route('admin.etsy.index'))
            ->post(route('admin.etsy.oauth.start'))
            ->assertRedirect(route('admin.etsy.index'))
            ->assertSessionHas('error');
    }

    public function test_callback_with_invalid_state_is_rejected(): void {
        $this->get(route('admin.etsy.oauth.callback', ['state' => 'bogus', 'code' => 'x']))
            ->assertRedirect(route('admin.etsy.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, EtsyConnection::query()->count());
    }

    public function test_full_flow_connects_and_determines_shop(): void {
        $this->mockTokenEndpoint();
        FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/users/12345/shops*' => FakePluginHttp::response([
                'shop_id' => 77,
                'shop_name' => 'Muster Shop',
            ]),
        ]);

        $state = $this->startAndCaptureState();

        $this->get(route('admin.etsy.oauth.callback', ['state' => $state, 'code' => 'auth-code-1']))
            ->assertRedirect(route('admin.etsy.index'))
            ->assertSessionHas('success');

        $connection = EtsyConnection::query()->firstOrFail();
        $this->assertSame(EtsyConnection::STATUS_ACTIVE, $connection->status);
        $this->assertSame('12345.acc-token', $connection->access_token);
        $this->assertSame('ref-token-1', $connection->refresh_token);
        $this->assertSame(12345, (int) $connection->etsy_user_id);
        $this->assertSame(77, (int) $connection->shop_id);
        $this->assertSame('Muster Shop', $connection->shop_name);
        $this->assertNotSame('', trim((string) $connection->webhook_token));
        $this->assertNotNull($connection->refresh_issued_at);
        $this->assertNotNull($connection->token_expires_at);
        $this->assertTrue($connection->isActive());
    }

    public function test_state_cannot_be_replayed(): void {
        $this->mockTokenEndpoint();
        FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/users/12345/shops*' => FakePluginHttp::response(['shop_id' => 77, 'shop_name' => 'Muster Shop']),
        ]);

        $state = $this->startAndCaptureState();
        $this->get(route('admin.etsy.oauth.callback', ['state' => $state, 'code' => 'auth-code-1']));

        // Einmal-State: die zweite Einlösung scheitert.
        $this->get(route('admin.etsy.oauth.callback', ['state' => $state, 'code' => 'auth-code-2']))
            ->assertSessionHas('error');
    }

    public function test_shop_bound_to_other_org_is_not_taken_over(): void {
        $other = Organization::factory()->create();
        EtsyConnection::create([
            'organization_id' => $other->id,
            'shop_id' => 77,
            'status' => EtsyConnection::STATUS_ACTIVE,
            'webhook_token' => 'hook-other',
        ]);

        $this->mockTokenEndpoint();
        FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/users/12345/shops*' => FakePluginHttp::response(['shop_id' => 77, 'shop_name' => 'Muster Shop']),
        ]);

        $state = $this->startAndCaptureState();
        $this->get(route('admin.etsy.oauth.callback', ['state' => $state, 'code' => 'auth-code-1']));

        $connection = EtsyConnection::query()->where('organization_id', $this->organization->id)->firstOrFail();
        $this->assertNull($connection->shop_id);
        $this->assertSame('shop_already_bound', $connection->last_error);
        $this->assertFalse($connection->isActive());
    }
}
