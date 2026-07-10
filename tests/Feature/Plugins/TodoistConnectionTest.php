<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{TodoistConnection, User};
use App\Plugins\Todoist\Api\TodoistOAuth;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 055, MVP-111: OAuth-Verbindung je Organisation — state org- und
 * sitzungsgebunden + einmalig (Replay-Schutz), Tokens verschlüsselt at-rest
 * und nie in Audit-Payloads, auditierte Verbindung/Trennung.
 */
final class TodoistConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config()->set('plugins.todoist.client_id', 'test-client');
        config()->set('plugins.todoist.client_secret', 'test-secret');
    }

    /** Ersetzt den OAuth-Singleton durch eine Variante mit Guzzle-MockHandler. */
    private function fakeTokenEndpoint(array $tokenResponse): void {
        $mock = new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], (string) json_encode($tokenResponse)),
        ]);
        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        app()->instance(TodoistOAuth::class, new TodoistOAuth($client));
    }

    /** Startet den Flow und liefert den state aus der Redirect-URL. */
    private function startFlowAndGetState(): string {
        $response = $this->actingAs($this->admin)->post(route('admin.todoist.oauth.start'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('test-client', $query['client_id'] ?? null);
        $this->assertNotEmpty($query['state'] ?? '');

        return (string) $query['state'];
    }

    public function test_api_client_uses_bearer_token_from_connection(): void {
        $connection = TodoistConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-123',
            'status' => TodoistConnection::STATUS_ACTIVE,
        ]);

        $fake = FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/user*' => FakePluginHttp::response(['id' => 'u-1', 'email' => 'x@y.z']),
        ]);

        $user = (new \App\Plugins\Todoist\Api\TodoistApiClient($connection))->getUser();

        $this->assertSame('u-1', $user['id'] ?? null);
        $fake->assertSent(fn ($request) => ($request->getHeader('Authorization')[0] ?? '') === 'Bearer secret-token-123');
    }

    public function test_admin_panel_requires_admin(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.todoist.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.todoist.index'))->assertOk();
    }

    public function test_oauth_callback_stores_encrypted_connection_and_audits(): void {
        $this->fakeTokenEndpoint(['access_token' => 'secret-token-123', 'token_type' => 'Bearer']);
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/user*' => FakePluginHttp::response(['id' => 'u-1', 'email' => 'chef@example.com']),
        ]);

        $state = $this->startFlowAndGetState();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.todoist.oauth.callback', ['state' => $state, 'code' => 'auth-code']));
        $this->assertNull(session('error'), 'Unerwarteter Fehler-Flash: ' . (string) session('error'));
        $response->assertRedirect(route('admin.todoist.index'))->assertSessionHas('success');

        $connection = TodoistConnection::query()->firstOrFail();
        $this->assertSame(TodoistConnection::STATUS_ACTIVE, $connection->status);
        $this->assertSame('secret-token-123', $connection->access_token); // entschlüsselt über Cast
        $this->assertSame('u-1', $connection->todoist_user_id);

        // At-rest verschlüsselt: Rohwert enthält den Token nicht im Klartext.
        $raw = (string) DB::table('todoist_connections')->where('id', $connection->id)->value('access_token');
        $this->assertNotSame('secret-token-123', $raw);
        $this->assertStringNotContainsString('secret-token-123', $raw);

        // Audit ohne Token-Payload.
        $audit = DB::table('audit_logs')->where('event', 'todoist.connected')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('secret-token-123', (string) $audit->changes);
    }

    public function test_oauth_state_is_single_use(): void {
        $this->fakeTokenEndpoint(['access_token' => 'secret-token-123', 'token_type' => 'Bearer']);
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/user*' => FakePluginHttp::response(['id' => 'u-1', 'email' => 'x@y.z']),
        ]);

        $state = $this->startFlowAndGetState();

        $this->actingAs($this->admin)
            ->get(route('admin.todoist.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('success');

        // Replay desselben state → abgelehnt.
        $this->actingAs($this->admin)
            ->get(route('admin.todoist.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('error');
    }

    public function test_oauth_callback_rejects_unknown_state(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.todoist.oauth.callback', ['state' => 'forged', 'code' => 'auth-code']))
            ->assertRedirect(route('admin.todoist.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, TodoistConnection::query()->count());
    }

    public function test_disconnect_clears_tokens_and_audits(): void {
        $connection = TodoistConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-123',
            'status' => TodoistConnection::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->admin)->post(route('admin.todoist.disconnect'))
            ->assertRedirect()->assertSessionHas('success');

        $fresh = $connection->fresh();
        $this->assertSame(TodoistConnection::STATUS_DISCONNECTED, $fresh->status);
        $this->assertNull($fresh->access_token);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $connection->getMorphClass(),
            'auditable_id' => $connection->id,
            'event' => 'todoist.disconnected',
        ]);
    }

    public function test_start_without_configuration_shows_error(): void {
        config()->set('plugins.todoist.client_id', '');

        $this->actingAs($this->admin)->from(route('admin.todoist.index'))
            ->post(route('admin.todoist.oauth.start'))
            ->assertRedirect(route('admin.todoist.index'))
            ->assertSessionHas('error');
    }
}
