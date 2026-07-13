<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\GoogleCalendar;

use App\Models\{GoogleCalendarConnection, User};
use App\Plugins\Contracts\{CalendarPublisher, PluginCapability};
use App\Plugins\GoogleCalendar\Api\{GoogleCalendarClient, GoogleCalendarOAuth};
use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use App\Plugins\{PluginDiscovery, PluginHealth};
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
 * MVP-328 (Bauturbo A8): Google-OAuth-Verbindung je Organisation — state
 * org- und sitzungsgebunden + einmalig, PKCE + access_type=offline im
 * Authorize-Redirect, Tokens verschlüsselt at-rest und nie in
 * Audit-Payloads, Refresh bei 401 (genau ein Retry), Health-Check über
 * die Kalenderliste.
 */
final class GoogleCalendarConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config()->set('plugins.google_calendar.client_id', 'test-client');
        config()->set('plugins.google_calendar.client_secret', 'test-secret');
    }

    /**
     * Ersetzt den OAuth-Singleton durch eine Variante mit Guzzle-MockHandler.
     *
     * @param  array<string, mixed>  $tokenResponse
     */
    private function fakeTokenEndpoint(array $tokenResponse): void {
        $mock = new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], (string) json_encode($tokenResponse)),
        ]);
        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        app()->instance(GoogleCalendarOAuth::class, new GoogleCalendarOAuth($client));
    }

    /** @param  array<int|string, mixed>  $query */
    private function queryParam(array $query, string $key): string {
        $value = $query[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** Startet den Flow und liefert den state (inkl. Offline-/PKCE-Prüfung). */
    private function startFlowAndGetState(): string {
        $response = $this->actingAs($this->admin)->post(route('admin.google-calendar.oauth.start'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('test-client', $query['client_id'] ?? null);
        // Offline-Access + Consent: nur so liefert Google ein Refresh-Token.
        $this->assertSame('offline', $query['access_type'] ?? null);
        $this->assertSame('consent', $query['prompt'] ?? null);
        $this->assertStringContainsString('calendar.events', $this->queryParam($query, 'scope'));
        // PKCE (S256) muss im Authorize-Redirect stecken.
        $this->assertNotSame('', $this->queryParam($query, 'code_challenge'));
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $state = $this->queryParam($query, 'state');
        $this->assertNotSame('', $state);

        return $state;
    }

    /** @param  array<string, mixed>  $attributes */
    private function connection(array $attributes = []): GoogleCalendarConnection {
        return GoogleCalendarConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-123',
            'status' => GoogleCalendarConnection::STATUS_ACTIVE,
        ]);
    }

    public function test_is_discovered_and_announces_calendar_publish(): void {
        $this->assertContains(GoogleCalendarPlugin::class, PluginDiscovery::classes());

        $plugin = new GoogleCalendarPlugin();
        $this->assertContains(PluginCapability::CalendarPublish, $plugin->capabilities());
        $this->assertTrue($plugin->isPerOrganization());
        $this->assertInstanceOf(CalendarPublisher::class, $plugin);
    }

    public function test_admin_panel_requires_admin(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.google-calendar.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.google-calendar.index'))->assertOk();
    }

    public function test_oauth_callback_stores_encrypted_connection_and_audits(): void {
        $this->fakeTokenEndpoint([
            'access_token' => 'secret-token-123',
            'refresh_token' => 'refresh-token-456',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);

        $state = $this->startFlowAndGetState();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.google-calendar.oauth.callback', ['state' => $state, 'code' => 'auth-code']));
        $this->assertNull(session('error'), 'Unerwarteter Fehler-Flash: ' . (string) session('error'));
        $response->assertRedirect(route('admin.google-calendar.index'))->assertSessionHas('success');

        $connection = GoogleCalendarConnection::query()->firstOrFail();
        $this->assertSame(GoogleCalendarConnection::STATUS_ACTIVE, $connection->status);
        $this->assertSame('secret-token-123', $connection->access_token); // entschlüsselt über Cast
        $this->assertSame('refresh-token-456', $connection->refresh_token);

        // At-rest verschlüsselt: Rohwerte enthalten die Tokens nicht im Klartext.
        $rawAccess = (string) DB::table('google_calendar_connections')->where('id', $connection->id)->value('access_token');
        $rawRefresh = (string) DB::table('google_calendar_connections')->where('id', $connection->id)->value('refresh_token');
        $this->assertStringNotContainsString('secret-token-123', $rawAccess);
        $this->assertStringNotContainsString('refresh-token-456', $rawRefresh);

        // Audit ohne Token-Payload.
        $this->assertDatabaseHas('audit_logs', ['event' => 'google_calendar.connected']);
        $auditChanges = (string) DB::table('audit_logs')->where('event', 'google_calendar.connected')->value('changes');
        $this->assertStringNotContainsString('secret-token-123', $auditChanges);
        $this->assertStringNotContainsString('refresh-token-456', $auditChanges);
    }

    public function test_reconnect_without_refresh_token_keeps_existing_one(): void {
        // Google lässt das Refresh-Token bei erneuter Verbindung oft weg —
        // das bestehende muss erhalten bleiben.
        $this->connection(['refresh_token' => 'keep-me']);

        $this->fakeTokenEndpoint(['access_token' => 'renewed-token', 'token_type' => 'Bearer']);
        $state = $this->startFlowAndGetState();

        $this->actingAs($this->admin)
            ->get(route('admin.google-calendar.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('success');

        $connection = GoogleCalendarConnection::query()->firstOrFail();
        $this->assertSame('renewed-token', $connection->access_token);
        $this->assertSame('keep-me', $connection->refresh_token);
    }

    public function test_oauth_state_is_single_use(): void {
        $this->fakeTokenEndpoint(['access_token' => 'secret-token-123', 'token_type' => 'Bearer']);

        $state = $this->startFlowAndGetState();

        $this->actingAs($this->admin)
            ->get(route('admin.google-calendar.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('success');

        // Replay desselben state → abgelehnt.
        $this->actingAs($this->admin)
            ->get(route('admin.google-calendar.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('error');
    }

    public function test_disconnect_clears_tokens_and_audits(): void {
        $connection = $this->connection(['refresh_token' => 'refresh-token-456']);

        $this->actingAs($this->admin)->post(route('admin.google-calendar.disconnect'))
            ->assertRedirect()->assertSessionHas('success');

        $fresh = $connection->fresh();
        $this->assertInstanceOf(GoogleCalendarConnection::class, $fresh);
        $this->assertSame(GoogleCalendarConnection::STATUS_DISCONNECTED, $fresh->status);
        $this->assertNull($fresh->access_token);
        $this->assertNull($fresh->refresh_token);
        $this->assertFalse($fresh->isActive());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $connection->getMorphClass(),
            'auditable_id' => $connection->id,
            'event' => 'google_calendar.disconnected',
        ]);
    }

    public function test_health_check_reflects_calendar_list_probe(): void {
        $this->connection(); // ohne Refresh-Token → 401 bleibt 401 (kein Netz)

        FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => FakePluginHttp::response(['items' => [['id' => 'primary', 'summary' => 'Kalender']]]),
        ]);
        $this->assertTrue((new GoogleCalendarPlugin())->healthCheck()->isOk());

        FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => FakePluginHttp::response(['error' => ['code' => 401]], 401),
        ]);
        $this->assertTrue((new GoogleCalendarPlugin())->healthCheck()->isFailing());
    }

    public function test_health_degraded_without_connection(): void {
        $this->assertSame(PluginHealth::STATUS_DEGRADED, (new GoogleCalendarPlugin())->healthCheck()->status);
    }

    public function test_401_triggers_refresh_and_exactly_one_retry(): void {
        $this->fakeTokenEndpoint([
            'access_token' => 'new-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);

        $connection = $this->connection([
            'access_token' => 'old-token',
            'refresh_token' => 'refresh-1',
            'token_expires_at' => null, // nicht abgelaufen → kein Vorab-Refresh
        ]);

        $fake = FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => [
                FakePluginHttp::response(['error' => ['code' => 401]], 401),
                FakePluginHttp::response(['items' => []]),
            ],
        ]);

        $calendars = (new GoogleCalendarClient($connection))->listCalendars();

        $this->assertSame([], $calendars);
        // Genau ein Retry: 401 → Refresh → zweiter Versuch, nicht mehr.
        $fake->assertSentCount(2);
        $requests = $fake->recorded();
        $this->assertSame('Bearer old-token', $requests[0]['request']->getHeader('Authorization')[0] ?? '');
        $this->assertSame('Bearer new-token', $requests[1]['request']->getHeader('Authorization')[0] ?? '');

        // Google liefert beim Refresh kein neues Refresh-Token → bestehendes bleibt.
        $fresh = $connection->fresh();
        $this->assertInstanceOf(GoogleCalendarConnection::class, $fresh);
        $this->assertSame('new-token', $fresh->access_token);
        $this->assertSame('refresh-1', $fresh->refresh_token);
    }
}
