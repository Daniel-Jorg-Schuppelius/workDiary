<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginNavigationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Navigation;

use App\Models\Platform\{PluginSetting, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\RemoteSupport\Api\TeamViewerClient;
use App\Plugins\RemoteSupport\Enums\RemotePendingSessionStatus;
use App\Plugins\RemoteSupport\Models\RemotePendingSession;
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use App\Services\Navigation\NavigationRegistry;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\TestCase;

/**
 * Menübeiträge aus Plugins (MVP-1037): Plugins tragen Einträge in Sidebar-
 * Gruppen und Systemmenü bei, samt Zähler und Treffer-Muster; was eine
 * Verbindung braucht, erscheint nur bei aktivem Plugin.
 */
class PluginNavigationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array{sidebar: list<array<string, mixed>>, admin: list<array<string, mixed>>} */
    private function navigation(): array {
        $this->actingAs($this->admin);
        $this->get(route('billing.feed'));
        $built = app(NavigationRegistry::class)->build(false, 'home');
        $sidebar = [];
        foreach ($built['sidebarSections'] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $sidebar[] = $item;
            }
            foreach ($section['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $sidebar[] = $item;
                }
            }
        }

        return ['sidebar' => $sidebar, 'admin' => $built['adminNavItems']];
    }

    private function enable(string $pluginId): void {
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => []]);
    }

    public function test_inactive_plugins_contribute_only_what_works_without_connection(): void {
        $nav = $this->navigation();

        $routes = array_column([...$nav['sidebar'], ...$nav['admin']], 'route');
        $this->assertNotContains('lexoffice.articles.index', $routes);
        $this->assertNotContains('lexoffice.handover.index', $routes);
        $this->assertNotContains('admin.remote-support.pending.index', $routes);
        // Tarifprofil ist der Einstieg in die Lexware-Ergänzungen (ohne API-Schlüssel).
        $this->assertContains('lexoffice.plan.index', $routes);
    }

    public function test_lexware_supplements_show_the_handover_list_without_api_key(): void {
        Setting::set('lexware.local_features', ['recurring_invoices'], SettingScope::Organization, $this->organization, $this->admin->id);
        app()->instance('currentOrganization', $this->organization->fresh());

        $routes = array_column($this->navigation()['sidebar'], 'route');
        $this->assertContains('lexoffice.handover.index', $routes);
        $this->assertNotContains('lexoffice.articles.index', $routes);
    }

    public function test_active_plugins_add_their_entries_badges_and_matches(): void {
        // Lexoffice gilt erst mit API-Schlüssel als aktiv.
        $this->pluginSecret(LexofficePlugin::ID, ['api_key' => 'test-key']);
        $this->enable(RemoteSupportPlugin::ID);
        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id, 'provider' => TeamViewerClient::ID, 'remote_id' => '1', 'session_id' => 's-1',
            'started_at' => now()->subHour(), 'ended_at' => now(), 'status' => RemotePendingSessionStatus::Open,
        ]);

        $nav = $this->navigation();

        $sidebar = collect($nav['sidebar'])->keyBy('route');
        $this->assertTrue($sidebar->has('lexoffice.articles.index'));
        $this->assertTrue($sidebar->has('lexoffice.handover.index'));
        $this->assertContains('lexoffice.vouchers.*', $sidebar->get('billing.feed')['matches'] ?? []);

        $admin = collect($nav['admin'])->keyBy('route');
        $this->assertSame('finance', $admin->get('lexoffice.plan.index')['folder'] ?? null);
        $this->assertSame('data', $admin->get('admin.remote-support.pending.index')['folder'] ?? null);
        $this->assertSame(1, $admin->get('admin.remote-support.pending.index')['badge'] ?? null);
    }
}
