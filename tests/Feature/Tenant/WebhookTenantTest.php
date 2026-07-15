<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Enums\Auth\SsoProtocol;
use App\Http\Controllers\Api\LocationController;
use App\Models\{Attendance, CommunicationNote, CtiConnection, Customer, Organization, PluginSetting, ScimToken, SsoConnection, SsoIdentity, Task, TodoistConnection, TodoistWebhookDelivery, User, UserBadge, ZammadConnection};
use App\Models\Location\{LocationDeviceToken, LocationPoint};
use App\Plugins\Github\GithubPlugin;
use App\Plugins\Gitlab\GitlabPlugin;
use App\Plugins\Todoist\Jobs\TodoistWebhookSyncJob;
use App\Services\Auth\Sso\{SsoLoginException, SsoLoginService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Route};
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Bauturbo Welle D — systematischer Webhook-/öffentliche-API-Tenant-Audit
 * (Muster A17 `ReportPdfTenantTest`). Regressionssicheres Gate über ALLE
 * eingehenden externen Endpunkte (Webhooks + Token-Ingest + SCIM + SSO). Prüft
 * pro Endpunkt die verbindliche Webhook-Sicherheitsarchitektur
 * ([adr-webhook-security.md]):
 *
 *  (1) Ungültiges/fremdes Secret/Token → abgewiesen, keine Verarbeitung.
 *  (2) Confused-Deputy: ein gültiges Secret/Token von Organisation A wirkt
 *      NIE auf Daten von Organisation B (die serverseitig gespeicherte
 *      Verbindung bestimmt den Mandanten, nicht der Request).
 *  (3) Payload-Org-Spoofing wird ignoriert — die signierte/serverseitige
 *      Verbindung gewinnt.
 *
 * Die Endpunkt-Registry ({@see self::INBOUND_ENDPOINTS}) wird über einen
 * Routen-Abgleich erzwungen: jeder neue eingehende externe Endpunkt muss hier
 * erscheinen, sonst schlägt {@see self::test_all_inbound_endpoints_are_registered}
 * fehl.
 */
final class WebhookTenantTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /**
     * Verbindliche Liste der eingehenden externen Endpunkte (Mandant wird
     * serverseitig aufgelöst). SCIM ist über den Präfix `scim/v2/*` gesammelt
     * geführt (viele Ressourcen, identische Auth). Ändert sich die Route-Fläche,
     * muss diese Liste angepasst werden.
     *
     * @var list<string>
     */
    private const INBOUND_ENDPOINTS = [
        'api/webhooks/dropbox',
        'api/webhooks/google-drive',
        'api/webhooks/msgraph-intake',
        'api/webhooks/github/{setting}',
        'api/webhooks/gitlab/{setting}',
        'api/webhooks/zammad/{connection}',
        'api/webhooks/todoist',
        'api/cti/webhook/{token}',
        'api/terminal/ingest/{token}',
        'api/location/ingest/{token}',
        'scim/v2/*',
        'sso/{slug}/saml/acs',
        'sso/oidc/callback',
    ];

    private Organization $orgB;

    protected function setUp(): void {
        parent::setUp();
        // Org A = Heim-/Angreifer-Organisation (currentOrganization gebunden).
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        // Org B = Opfer-Organisation.
        $this->orgB = Organization::factory()->create(['plan' => Organization::PLAN_ENTERPRISE]);
    }

    // ────────────────────────────────────────────────────────────────────
    // Registry-Gate: keine unbeaufsichtigten neuen Webhook-Endpunkte.
    // ────────────────────────────────────────────────────────────────────

    public function test_all_inbound_endpoints_are_registered(): void {
        $discovered = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            $isInbound = str_starts_with($uri, 'api/webhooks/')
                || str_contains($uri, '/ingest/')
                || str_starts_with($uri, 'api/cti/webhook')
                || str_starts_with($uri, 'scim/v2/')
                || str_contains($uri, 'saml/acs')
                || $uri === 'sso/oidc/callback';
            if (! $isInbound) {
                continue;
            }
            // SCIM-Ressourcen zu einem Eintrag zusammenfassen.
            $discovered[] = str_starts_with($uri, 'scim/v2/') ? 'scim/v2/*' : $uri;
        }
        $discovered = array_values(array_unique($discovered));
        sort($discovered);

        $documented = self::INBOUND_ENDPOINTS;
        sort($documented);

        $this->assertSame(
            $documented,
            $discovered,
            'Neue oder entfernte eingehende Webhook-/Ingest-Endpunkte gefunden. '
            . 'Registry in WebhookTenantTest::INBOUND_ENDPOINTS pflegen und den '
            . 'Endpunkt im Tenant-Audit + Test-Gate abdecken.',
        );
    }

    // ────────────────────────────────────────────────────────────────────
    // GitHub — HMAC-SHA256 (X-Hub-Signature-256), Mandant über {setting}.
    // ────────────────────────────────────────────────────────────────────

    public function test_github_foreign_secret_cannot_write_into_other_org(): void {
        $settingB = $this->githubSetting($this->orgB->id, 'secret-B');
        $body = (string) json_encode(['action' => 'opened', 'issue' => ['number' => 1]]);
        // Org A signiert mit IHREM Secret, zielt aber auf Org B's Setting.
        $forged = 'sha256=' . hash_hmac('sha256', $body, 'secret-A');

        $this->call('POST', "/api/webhooks/github/{$settingB->id}", [], [], [], [
            'HTTP_X-Hub-Signature-256' => $forged,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertForbidden();

        $this->assertSame(0, Task::query()->withoutGlobalScopes()->count());
    }

    // ────────────────────────────────────────────────────────────────────
    // GitLab — statischer X-Gitlab-Token, Mandant über {setting}.
    // ────────────────────────────────────────────────────────────────────

    public function test_gitlab_foreign_token_cannot_write_into_other_org(): void {
        $settingB = $this->gitlabSetting($this->orgB->id, 'token-B');

        $this->call('POST', "/api/webhooks/gitlab/{$settingB->id}", [], [], [], [
            'HTTP_X-Gitlab-Token' => 'token-A', // Org A's Token gegen Org B's Setting
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['object_kind' => 'issue']))->assertForbidden();

        $this->assertSame(0, Task::query()->withoutGlobalScopes()->count());
    }

    // ────────────────────────────────────────────────────────────────────
    // Zammad — HMAC-SHA1 (X-Hub-Signature), Mandant über {connection}.
    // ────────────────────────────────────────────────────────────────────

    public function test_zammad_foreign_secret_cannot_write_into_other_org(): void {
        $connB = ZammadConnection::query()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Support B',
            'base_url' => 'https://b.example.com',
            'api_token' => 'token-b',
            'webhook_secret' => 'secret-B',
            'active' => true,
        ]);
        $body = (string) json_encode(['ticket' => ['id' => 1]]);
        $forged = 'sha1=' . hash_hmac('sha1', $body, 'secret-A');

        $this->call('POST', "/api/webhooks/zammad/{$connB->id}", [], [], [], [
            'HTTP_X-Hub-Signature' => $forged,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertForbidden();

        $this->assertSame(0, Task::query()->withoutGlobalScopes()->count());
    }

    // ────────────────────────────────────────────────────────────────────
    // Todoist — globales HMAC-Secret, Mandant NUR über die serverseitig
    // gespeicherte Verbindung (todoist_user_id → organization_id).
    // ────────────────────────────────────────────────────────────────────

    public function test_todoist_payload_user_id_maps_only_to_owning_org(): void {
        Queue::fake();
        config()->set('plugins.todoist.client_id', 'cid');
        config()->set('plugins.todoist.client_secret', 'sec');

        // u-A gehört Org A, u-B gehört Org B — beide korrekt signiert.
        TodoistConnection::query()->create([
            'organization_id' => $this->organization->id,
            'todoist_user_id' => 'u-A',
            'access_token' => 'tok-a',
            'status' => TodoistConnection::STATUS_ACTIVE,
        ]);
        TodoistConnection::query()->create([
            'organization_id' => $this->orgB->id,
            'todoist_user_id' => 'u-B',
            'access_token' => 'tok-b',
            'status' => TodoistConnection::STATUS_ACTIVE,
        ]);

        $this->postTodoist(['event_name' => 'item:updated', 'user_id' => 'u-A', 'event_data' => ['id' => 't', 'project_id' => 'p']], 'd-a')
            ->assertOk()->assertJson(['status' => 'queued']);

        // Der Impuls für user_id=u-A erreicht ausschließlich Org A.
        Queue::assertPushed(TodoistWebhookSyncJob::class, fn (TodoistWebhookSyncJob $job): bool => $job->organizationId === $this->organization->id);
        Queue::assertNotPushed(TodoistWebhookSyncJob::class, fn (TodoistWebhookSyncJob $job): bool => $job->organizationId === $this->orgB->id);

        $delivery = TodoistWebhookDelivery::query()->withoutGlobalScopes()->where('delivery_id', 'd-a')->firstOrFail();
        $this->assertSame($this->organization->id, (int) $delivery->organization_id);
    }

    public function test_todoist_invalid_signature_is_rejected_before_processing(): void {
        Queue::fake();
        config()->set('plugins.todoist.client_secret', 'sec');
        TodoistConnection::query()->create([
            'organization_id' => $this->orgB->id,
            'todoist_user_id' => 'u-B',
            'access_token' => 'tok-b',
            'status' => TodoistConnection::STATUS_ACTIVE,
        ]);

        $raw = (string) json_encode(['event_name' => 'item:updated', 'user_id' => 'u-B', 'event_data' => []]);
        $this->call('POST', '/api/webhooks/todoist', [], [], [], [
            'HTTP_X-Todoist-Hmac-SHA256' => base64_encode('falsch'),
            'HTTP_X-Todoist-Delivery-ID' => 'd-x',
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertStatus(401);

        $this->assertSame(0, TodoistWebhookDelivery::query()->withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }

    // ────────────────────────────────────────────────────────────────────
    // Dropbox (Feature 080) — signaturgeprüftes Aufwecksignal; der Mandant
    // wird ausschließlich serverseitig über external_account_id gespeicherter
    // Verbindungen aufgelöst, nie aus dem Payload.
    // ────────────────────────────────────────────────────────────────────

    public function test_dropbox_webhook_rejects_invalid_signature_and_wakes_only_matching_connections(): void {
        config(['plugins.dropbox.client_secret' => 'app-secret']);

        $connB = \App\Models\CloudIntake\CloudDocumentConnection::factory()->create([
            'organization_id' => $this->orgB->id,
            'external_account_id' => 'dbid:org-b',
        ]);
        $connA = \App\Models\CloudIntake\CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'external_account_id' => 'dbid:org-a',
        ]);

        $raw = (string) json_encode(['list_folder' => ['accounts' => ['dbid:org-b']], 'delta' => ['users' => []]]);

        // Falsche Signatur ⇒ 403, kein Aufwecksignal.
        $this->call('POST', '/api/webhooks/dropbox', [], [], [], [
            'HTTP_X-Dropbox-Signature' => 'falsch',
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertStatus(403);

        $wake = app(\App\Services\CloudIntake\IntakeWakeSignal::class);
        $this->assertFalse($wake->consume((int) $connB->id));

        // Gültige Signatur ⇒ 200; NUR die Verbindung mit passender Konto-ID
        // wird geweckt (Payload-Konto bestimmt nie den Mandanten direkt).
        $this->call('POST', '/api/webhooks/dropbox', [], [], [], [
            'HTTP_X-Dropbox-Signature' => hash_hmac('sha256', $raw, 'app-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertStatus(200);

        $this->assertTrue($wake->consume((int) $connB->id));
        $this->assertFalse($wake->consume((int) $connA->id));

        // Verifikations-Challenge (GET) wird als text/plain gespiegelt.
        $this->get('/api/webhooks/dropbox?challenge=abc123')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('abc123');
    }

    // ────────────────────────────────────────────────────────────────────
    // Microsoft Graph Intake (Feature 080) — Zuordnung ausschließlich über
    // subscriptionId + clientState (Konstantzeit) der gespeicherten
    // Verbindung; falsches clientState wird still ignoriert (kein Oracle).
    // ────────────────────────────────────────────────────────────────────

    public function test_msgraph_intake_webhook_validates_client_state_per_connection(): void {
        $connB = \App\Models\CloudIntake\CloudDocumentConnection::factory()->create([
            'organization_id' => $this->orgB->id,
            'provider' => \App\Enums\CloudIntake\CloudIntakeProvider::Microsoft,
            'subscription_id' => 'sub-b',
            'webhook_secret' => 'geheim-b',
        ]);

        $wake = app(\App\Services\CloudIntake\IntakeWakeSignal::class);

        // Subscription-Validierung wird als text/plain gespiegelt.
        $this->post('/api/webhooks/msgraph-intake?validationToken=tok-123')
            ->assertOk()
            ->assertSee('tok-123');

        // Falsches clientState ⇒ 202, aber KEIN Aufwecksignal.
        $this->postJson('/api/webhooks/msgraph-intake', [
            'value' => [['subscriptionId' => 'sub-b', 'clientState' => 'falsch']],
        ])->assertStatus(202);
        $this->assertFalse($wake->consume((int) $connB->id));

        // Korrektes clientState weckt genau die zugehörige Verbindung.
        $this->postJson('/api/webhooks/msgraph-intake', [
            'value' => [['subscriptionId' => 'sub-b', 'clientState' => 'geheim-b']],
        ])->assertStatus(202);
        $this->assertTrue($wake->consume((int) $connB->id));
    }

    // ────────────────────────────────────────────────────────────────────
    // Google Drive Watch-Channel (Feature 080) — Channel-ID + Channel-Token
    // (Konstantzeit) der gespeicherten Verbindung; Payload zählt nicht.
    // ────────────────────────────────────────────────────────────────────

    public function test_google_drive_channel_requires_matching_token(): void {
        $connB = \App\Models\CloudIntake\CloudDocumentConnection::factory()->create([
            'organization_id' => $this->orgB->id,
            'provider' => \App\Enums\CloudIntake\CloudIntakeProvider::Google,
            'subscription_id' => 'chan-b',
            'webhook_secret' => 'token-b',
        ]);

        $wake = app(\App\Services\CloudIntake\IntakeWakeSignal::class);

        // Fehlende/falsche Header ⇒ 403, kein Signal.
        $this->post('/api/webhooks/google-drive')->assertStatus(403);
        $this->call('POST', '/api/webhooks/google-drive', [], [], [], [
            'HTTP_X-Goog-Channel-ID' => 'chan-b',
            'HTTP_X-Goog-Channel-Token' => 'falsch',
        ])->assertStatus(403);
        $this->assertFalse($wake->consume((int) $connB->id));

        // Korrekte Kombination weckt genau diese Verbindung.
        $this->call('POST', '/api/webhooks/google-drive', [], [], [], [
            'HTTP_X-Goog-Channel-ID' => 'chan-b',
            'HTTP_X-Goog-Channel-Token' => 'token-b',
        ])->assertStatus(200);
        $this->assertTrue($wake->consume((int) $connB->id));
    }

    // ────────────────────────────────────────────────────────────────────
    // CTI — Token im Pfad, Mandant über die Verbindung; Nummer→Kunde ist
    // org-gescopt, ein fremder Token erreicht nie fremde Kunden.
    // ────────────────────────────────────────────────────────────────────

    public function test_cti_token_never_matches_customers_of_another_org(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $user->id])->save();
        [, $tokenA] = CtiConnection::issue($this->organization->id, 'Zentrale A', 'generic');

        // Kunde mit dieser Nummer existiert NUR in Org B.
        Customer::factory()->create(['organization_id' => $this->orgB->id, 'phone' => '+493012345678']);

        // Org A's Token + Org B's Kundennummer → aufgelöst im Kontext Org A → unmatched.
        $this->postJson("/api/cti/webhook/{$tokenA}", [
            'call_id' => 'x-1', 'direction' => 'inbound', 'from' => '+493012345678', 'to' => '+4930999',
        ])->assertOk()->assertJsonPath('status', 'unmatched');

        $this->assertSame(0, CommunicationNote::query()->withoutGlobalScopes()->count());
    }

    public function test_cti_unknown_token_is_rejected(): void {
        $this->postJson('/api/cti/webhook/cti_unknown', [
            'call_id' => 'y-1', 'direction' => 'inbound', 'from' => '+493012345678', 'to' => '+4930999',
        ])->assertStatus(404);
    }

    // ────────────────────────────────────────────────────────────────────
    // Terminal — Token im Pfad, Mandant über das Terminal; Badge-Auflösung
    // ist org-gescopt, ein fremder Token erreicht nie fremde Badges.
    // ────────────────────────────────────────────────────────────────────

    public function test_terminal_token_never_stamps_badge_of_another_org(): void {
        [, $tokenA] = \App\Models\AttendanceTerminal::issue($this->organization->id, 'Halle A');

        // Badge + Nutzer existieren NUR in Org B.
        $userB = User::factory()->create(['organization_id' => $this->orgB->id]);
        UserBadge::query()->create([
            'organization_id' => $this->orgB->id,
            'user_id' => $userB->id,
            'label' => 'B',
            'badge_hash' => UserBadge::hashBadge('BADGE-B'),
        ]);

        // Org A's Terminal-Token + Org B's Badge → unknown_badge (org-gescopt).
        $this->postJson("/api/terminal/ingest/{$tokenA}", ['badge_uid' => 'BADGE-B'])
            ->assertOk()->assertJsonPath('status', 'unknown_badge');

        $this->assertSame(0, Attendance::query()->withoutGlobalScopes()->count());
    }

    public function test_terminal_unknown_token_is_rejected(): void {
        $this->postJson('/api/terminal/ingest/term_unknown', ['badge_uid' => 'x'])->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────────────
    // Location — Token im Pfad, Mandant über Gerät→Nutzer; ein Gerät schreibt
    // ausschließlich Punkte des eigenen Nutzers/der eigenen Org.
    // ────────────────────────────────────────────────────────────────────

    public function test_location_device_token_writes_only_into_owning_org(): void {
        $userA = User::factory()->create(['organization_id' => $this->organization->id]);
        $userA->setPreference(LocationController::OPT_IN_PREFERENCE, true);
        [, $tokenA] = LocationDeviceToken::issue($userA, 'Gerät A');

        $this->postJson("/api/location/ingest/{$tokenA}", ['points' => [
            ['lat' => 52.5, 'lng' => 13.4, 'recorded_at' => '2026-07-01T08:00:00Z'],
        ]])->assertOk();

        $this->assertSame(1, LocationPoint::query()->withoutGlobalScopes()->count());
        $point = LocationPoint::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->organization->id, (int) $point->organization_id);
    }

    public function test_location_invalid_token_is_rejected(): void {
        // Location-Token-Pattern erlaubt nur [A-Za-z0-9]+ (kein Unterstrich),
        // daher rein alphanumerisch, damit die Route greift und der Controller
        // (nicht der Router) mit 401 ablehnt.
        $this->postJson('/api/location/ingest/locunknown', ['points' => []])->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────────────
    // SCIM — Bearer-Token, Mandant über den Token; ein Org-A-Token listet
    // niemals Org-B-Nutzer.
    // ────────────────────────────────────────────────────────────────────

    public function test_scim_bearer_token_never_lists_users_of_another_org(): void {
        [, $plainA] = ScimToken::issue($this->organization->id, 'IdP A');

        // Auffindbarer Nutzer nur in Org B.
        User::factory()->create(['organization_id' => $this->orgB->id, 'email' => 'victim-b@example.org']);

        $response = $this->call('GET', '/scim/v2/Users', [], [], [], [
            'HTTP_Authorization' => 'Bearer ' . $plainA,
            'HTTP_ACCEPT' => 'application/scim+json',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('victim-b@example.org', (string) $response->getContent());
    }

    public function test_scim_invalid_bearer_token_is_rejected(): void {
        $this->call('GET', '/scim/v2/Users', [], [], [], [
            'HTTP_Authorization' => 'Bearer wrong',
            'HTTP_ACCEPT' => 'application/scim+json',
        ])->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────────────
    // SSO — die Mandantengrenze im Login-Service: eine Verbindung aus Org A
    // meldet niemals einen Nutzer aus Org B an (assertLoginAllowed). Die
    // SAML-Signatur-/ACS-Fläche ist in SamlLoginTest/OidcLoginTest gedeckt.
    // ────────────────────────────────────────────────────────────────────

    public function test_sso_connection_never_resolves_user_of_another_org(): void {
        $connectionA = SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Saml->value,
            'label' => 'IdP A',
            'active' => true,
            'idp_entity_id' => 'https://idp.example/a',
            'idp_sso_url' => 'https://idp.example/a/sso',
            'idp_certificate' => 'dummy',
        ]);

        // Manipulierte Identitäts-Verknüpfung: Org-A-Verbindung → Org-B-Nutzer.
        $userB = User::factory()->create(['organization_id' => $this->orgB->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $connectionA->id,
            'user_id' => $userB->id,
            'subject' => 'sub-cross',
        ]);

        $this->expectException(SsoLoginException::class);
        app(SsoLoginService::class)->resolveUser($connectionA, ['subject' => 'sub-cross', 'email' => null]);
    }

    // ────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────

    private function githubSetting(int $organizationId, string $secret): PluginSetting {
        return PluginSetting::query()->create([
            'organization_id' => $organizationId,
            'plugin_id' => GithubPlugin::ID,
            'enabled' => true,
            'settings' => [
                'api_token' => 'ghp-token',
                'repo_owner' => 'acme',
                'repo_name' => 'support',
                'webhook_secret' => $secret,
            ],
        ]);
    }

    private function gitlabSetting(int $organizationId, string $token): PluginSetting {
        return PluginSetting::query()->create([
            'organization_id' => $organizationId,
            'plugin_id' => GitlabPlugin::ID,
            'enabled' => true,
            'settings' => [
                'api_token' => 'glpat-token',
                'project_id' => '42',
                'base_url' => 'https://gitlab.example.com',
                'webhook_token' => $token,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function postTodoist(array $payload, string $deliveryId): TestResponse {
        $raw = (string) json_encode($payload);

        return $this->call('POST', '/api/webhooks/todoist', [], [], [], [
            'HTTP_X-Todoist-Hmac-SHA256' => base64_encode(hash_hmac('sha256', $raw, 'sec', true)),
            'HTTP_X-Todoist-Delivery-ID' => $deliveryId,
            'CONTENT_TYPE' => 'application/json',
        ], $raw);
    }
}
