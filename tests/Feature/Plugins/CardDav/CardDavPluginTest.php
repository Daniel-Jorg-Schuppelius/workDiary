<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\CardDav;

use App\Models\CardDavConnection;
use App\Plugins\CardDav\CardDavPlugin;
use App\Plugins\CardDav\Contracts\{CardDavGateway, CardDavGatewayFactory};
use App\Plugins\{PluginDiscovery, PluginHealth};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeCardDavGateway;
use Tests\TestCase;

/**
 * Bauturbo A9 (MVP-329): Plugin-Verdrahtung. Auto-Discovery, per-Org-Betrieb
 * und der Health-Check über die (gefälschte) Gateway-Factory — degraded ohne
 * bzw. mit unvollständiger Anbindung, ok/failing je nach Ping.
 */
final class CardDavPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function bindGateway(FakeCardDavGateway $gateway): void {
        $this->app->instance(CardDavGatewayFactory::class, new class($gateway) implements CardDavGatewayFactory {
            public function __construct(private CardDavGateway $gateway) {}

            public function for(CardDavConnection $connection): CardDavGateway {
                return $this->gateway;
            }
        });
    }

    private function connection(bool $active = true): CardDavConnection {
        return CardDavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'addressbook_url' => 'https://cloud.example.com/remote.php/dav/addressbooks/users/svc/contacts/',
            'addressbook_name' => 'Kontakte',
            'active' => $active,
        ]);
    }

    public function test_is_discovered_per_organization_and_read_only(): void {
        $this->assertContains(CardDavPlugin::class, PluginDiscovery::classes());

        $plugin = new CardDavPlugin();
        $this->assertSame('carddav', $plugin->id());
        $this->assertTrue($plugin->isPerOrganization());
        // Rein lesende Matching-Quelle: bewusst kein Push-Vertrag (ContactSyncer).
        $this->assertSame([], $plugin->capabilities());
        $this->assertSame('admin.carddav.index', $plugin->adminPanel()['route'] ?? null);
    }

    public function test_health_degraded_without_connection(): void {
        $this->bindGateway(new FakeCardDavGateway());

        $this->assertSame(PluginHealth::STATUS_DEGRADED, (new CardDavPlugin())->healthCheck()->status);
    }

    public function test_health_degraded_with_inactive_connection(): void {
        $this->bindGateway(new FakeCardDavGateway());
        $this->connection(active: false);

        $this->assertSame(PluginHealth::STATUS_DEGRADED, (new CardDavPlugin())->healthCheck()->status);
    }

    public function test_health_reflects_ping(): void {
        $this->connection();

        $this->bindGateway(new FakeCardDavGateway(pingOk: true));
        $this->assertTrue((new CardDavPlugin())->healthCheck()->isOk());

        $this->bindGateway(new FakeCardDavGateway(pingOk: false));
        $this->assertTrue((new CardDavPlugin())->healthCheck()->isFailing());
    }
}
