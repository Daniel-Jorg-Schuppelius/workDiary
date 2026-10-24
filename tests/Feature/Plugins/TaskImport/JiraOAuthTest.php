<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraOAuthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\TaskImport;

use APIToolkit\Exceptions\UnauthorizedException;
use App\Enums\Integration\TaskImportLinkStatus;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Plugins\Jira\Api\{JiraClientFactory, JiraOAuthGrant};
use App\Plugins\Jira\JiraPlugin;
use App\Plugins\Jira\Models\JiraConnection;
use App\Plugins\Support\OAuthConnectionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Jira-Anmeldung und Site-Wahl (MVP-1305): Atlassian 3LO mit Einmal-state,
 * Bindung an Organisation und Nutzer, Site nur aus den zugänglichen
 * Ressourcen, gesperrter Refresh mit Tokenrotation, Trennen entwertet
 * ausstehende Autorisierungen.
 */
class JiraOAuthTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->configureTaskImportApps();
        $this->enableTaskImportPlugin(JiraPlugin::ID);
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
    }

    private function startAndCaptureState(): string {
        $location = (string) $this->post(route('admin.jira.oauth.start'))->headers->get('Location');
        $this->assertStringStartsWith('https://auth.atlassian.com/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('api.atlassian.com', $query['audience'] ?? null);
        $this->assertSame('consent', $query['prompt'] ?? null);
        $this->assertSame('read:jira-work offline_access', $query['scope'] ?? null);
        $this->assertArrayNotHasKey('code_challenge', $query, 'Jira nutzt den dokumentierten 3LO-Flow ohne PKCE.');

        return (string) ($query['state'] ?? '');
    }

    private function fakeResources(): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://api.atlassian.com/oauth/token/accessible-resources' => FakePluginHttp::response([
                ['id' => self::JIRA_CLOUD, 'name' => 'Muster GmbH', 'url' => self::JIRA_SITE, 'scopes' => ['read:jira-work', 'offline_access']],
                ['id' => 'b1b2c3d4-0000-4000-8000-000000000002', 'name' => 'Nur Confluence', 'url' => 'https://andere.atlassian.net', 'scopes' => ['read:confluence-content.all']],
                ['id' => 'c1b2c3d4-0000-4000-8000-000000000003', 'name' => 'Fremde Adresse', 'url' => 'https://evil.example.com', 'scopes' => ['read:jira-work']],
            ]),
        ]);
    }

    public function test_start_requires_the_installation_app(): void {
        config(['plugins.jira.client_id' => '']);

        $this->from(route('admin.jira.index'))->post(route('admin.jira.oauth.start'))
            ->assertRedirect(route('admin.jira.index'))
            ->assertSessionHas('error');
        $this->get(route('admin.jira.index'))->assertOk()->assertSee(__('integration.task_import.connection.not_configured', ['provider' => 'Jira', 'source' => 'Site']));
    }

    public function test_consent_stores_tokens_and_only_readable_atlassian_sites_without_choosing_one(): void {
        $this->fakeTokenEndpoint(JiraOAuthGrant::class, [self::tokenResponse()]);
        $this->fakeResources();
        $state = $this->startAndCaptureState();

        $this->get(route('admin.jira.oauth.callback', ['state' => $state, 'code' => 'code-1']))
            ->assertRedirect(route('admin.jira.index'))
            ->assertSessionHas('success');

        $connection = JiraConnection::query()->firstOrFail();
        $this->assertSame(OAuthConnectionStatus::Active, $connection->status);
        $this->assertSame('new-access', $connection->access_token);
        $this->assertNull($connection->source_id, 'Die Site wird ausdrücklich gewählt, nie automatisch.');
        $this->assertSame([self::JIRA_CLOUD], array_column($connection->availableSources(), 'id'));
        $this->assertStringNotContainsString('new-access', (string) json_encode($connection->toArray()));

        $this->post(route('admin.jira.source'), ['source_id' => 'c1b2c3d4-0000-4000-8000-000000000003'])->assertSessionHas('error');
        $this->post(route('admin.jira.source'), ['source_id' => self::JIRA_CLOUD])->assertSessionHas('success');
        $this->assertSame(self::JIRA_SITE, $connection->fresh()?->source_url);
    }

    public function test_state_is_single_use_and_bound_to_organization_and_user(): void {
        $this->fakeTokenEndpoint(JiraOAuthGrant::class, [self::tokenResponse(), self::tokenResponse()]);
        $this->fakeResources();
        $state = $this->startAndCaptureState();

        $other = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $other->id]);
        $this->actingAs($foreignAdmin)->get(route('admin.jira.oauth.callback', ['state' => $state, 'code' => 'code-1']))
            ->assertSessionHas('error');
        $this->assertSame(0, JiraConnection::query()->withoutGlobalScopes()->count());

        // Fremder Versuch hat den state verbraucht — auch der Starter kann ihn nicht mehr einlösen.
        $this->actingAs($this->admin)->get(route('admin.jira.oauth.callback', ['state' => $state, 'code' => 'code-1']))
            ->assertSessionHas('error');
        $this->assertSame(0, JiraConnection::query()->withoutGlobalScopes()->count());

        $fresh = $this->startAndCaptureState();
        $this->get(route('admin.jira.oauth.callback', ['state' => $fresh, 'code' => 'code-2']))->assertSessionHas('success');
        $this->get(route('admin.jira.oauth.callback', ['state' => $fresh, 'code' => 'code-2']))->assertSessionHas('error');
        $this->assertCount(1, $this->tokenRequests, 'Ein wiederholter Callback tauscht keinen Code.');
    }

    public function test_denied_consent_and_disconnect_during_authorization_leave_the_connection_unchanged(): void {
        $connection = $this->jiraConnection();
        $this->fakeTokenEndpoint(JiraOAuthGrant::class, [self::tokenResponse()]);
        $this->fakeResources();

        $denied = $this->startAndCaptureState();
        $this->get(route('admin.jira.oauth.callback', ['state' => $denied, 'error' => 'access_denied']))->assertSessionHas('error');
        $this->assertSame('jira-access', $connection->fresh()?->access_token);

        $pending = $this->startAndCaptureState();
        $this->travel(5)->seconds();
        $this->post(route('admin.jira.disconnect'))->assertSessionHas('success');
        $this->get(route('admin.jira.oauth.callback', ['state' => $pending, 'code' => 'late']))->assertSessionHas('error');

        $connection->refresh();
        $this->assertSame(OAuthConnectionStatus::Disconnected, $connection->status);
        $this->assertNull($connection->access_token);
        $this->assertSame([], $this->tokenRequests, 'Nach dem Trennen wird kein Code mehr getauscht.');
    }

    public function test_disconnect_pauses_links_and_reconnect_with_the_same_site_resumes_them(): void {
        $this->jiraConnection();
        $link = $this->jiraLink(Project::factory()->create(['organization_id' => $this->organization->id]));
        $manual = $this->jiraLink(Project::factory()->create(['organization_id' => $this->organization->id]), '10001');
        $manual->forceFill(['status' => TaskImportLinkStatus::Paused, 'paused_reason' => 'manual'])->save();

        $this->post(route('admin.jira.disconnect'))->assertSessionHas('success');
        $this->assertSame(TaskImportLinkStatus::Paused, $link->fresh()?->status);

        $this->fakeTokenEndpoint(JiraOAuthGrant::class, [self::tokenResponse()]);
        $this->fakeResources();
        $this->travel(5)->seconds();
        $this->get(route('admin.jira.oauth.callback', ['state' => $this->startAndCaptureState(), 'code' => 'again']))->assertSessionHas('success');

        $this->assertSame(TaskImportLinkStatus::Active, $link->fresh()?->status);
        $this->assertSame(TaskImportLinkStatus::Paused, $manual->fresh()?->status, 'Von Hand pausierte Zuordnungen bleiben pausiert.');
    }

    public function test_site_cannot_change_while_mappings_exist(): void {
        $connection = $this->jiraConnection(['available_sources' => [
            ['id' => self::JIRA_CLOUD, 'name' => 'Muster GmbH', 'url' => self::JIRA_SITE],
            ['id' => 'd1b2c3d4-0000-4000-8000-000000000004', 'name' => 'Zweite', 'url' => 'https://zweite.atlassian.net'],
        ]]);
        $this->jiraLink(Project::factory()->create(['organization_id' => $this->organization->id]));

        $this->post(route('admin.jira.source'), ['source_id' => 'd1b2c3d4-0000-4000-8000-000000000004'])->assertSessionHas('error');
        $this->assertSame(self::JIRA_CLOUD, $connection->fresh()?->source_id);
    }

    public function test_expired_token_is_refreshed_once_and_the_rotated_pair_is_stored(): void {
        $connection = $this->jiraConnection(['token_expires_at' => now()->subMinute()]);
        $this->fakeTokenEndpoint(JiraOAuthGrant::class, [self::tokenResponse(['refresh_token' => 'rotated-refresh'])]);
        $http = FakePluginHttp::fake(['*/project/search*' => FakePluginHttp::response(['values' => [], 'isLast' => true])]);

        app(JiraClientFactory::class)->for((int) $this->organization->id)->probe();
        app(JiraClientFactory::class)->for((int) $this->organization->id)->probe();

        $this->assertCount(1, $this->tokenRequests);
        parse_str((string) $this->tokenRequests[0]->getBody(), $form);
        $this->assertSame('refresh_token', $form['grant_type'] ?? null);
        $this->assertSame('jira-refresh', $form['refresh_token'] ?? null);
        $connection->refresh();
        $this->assertSame('new-access', $connection->access_token);
        $this->assertSame('rotated-refresh', $connection->refresh_token);
        $http->assertSent(fn ($request): bool => $request->getHeaderLine('Authorization') === 'Bearer new-access');
    }

    public function test_refresh_done_by_a_parallel_run_is_reused_instead_of_spending_the_rotated_token(): void {
        $stale = $this->jiraConnection(['token_expires_at' => now()->subMinute()]);
        $this->fakeTokenEndpoint(JiraOAuthGrant::class, [self::tokenResponse()]);
        FakePluginHttp::fake(['*/project/search*' => FakePluginHttp::response(['values' => [], 'isLast' => true])]);
        $client = app(JiraClientFactory::class)->for((int) $this->organization->id);

        // Ein anderer Lauf hat inzwischen erneuert — diese Instanz kennt noch den alten Stand.
        JiraConnection::query()->whereKey($stale->id)->update([
            'access_token' => encrypt('parallel-access', false),
            'refresh_token' => encrypt('parallel-refresh', false),
            'token_expires_at' => now()->addHour(),
        ]);
        $client->probe();

        $this->assertSame([], $this->tokenRequests);
    }

    public function test_rejected_refresh_is_an_authentication_failure(): void {
        $this->jiraConnection(['token_expires_at' => now()->subMinute()]);
        // Der Client versucht nach der 401 einmal selbst zu erneuern — beide Versuche scheitern.
        $this->fakeTokenEndpoint(JiraOAuthGrant::class, [self::tokenResponse(['error' => 'invalid_grant'], 403), self::tokenResponse(['error' => 'invalid_grant'], 403)]);
        FakePluginHttp::fake(['*/project/search*' => FakePluginHttp::response(['values' => [], 'isLast' => true])]);

        $this->expectException(UnauthorizedException::class);
        app(JiraClientFactory::class)->for((int) $this->organization->id)->probe();
    }
}
