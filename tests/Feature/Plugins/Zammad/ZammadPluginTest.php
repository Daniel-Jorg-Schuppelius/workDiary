<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Zammad;

use App\Models\ZammadConnection;
use App\Plugins\Contracts\{PluginCapability, TaskSyncer};
use App\Plugins\{PluginDiscovery, PluginHealth};
use App\Plugins\Zammad\Contracts\{ZammadGateway, ZammadGatewayFactory};
use App\Plugins\Zammad\ZammadPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 060, MVP-129: Plugin-Verdrahtung. Auto-Discovery, angekündigte
 * TaskSync-Fähigkeit, einbahnige syncTasks-Aggregation und der per-Org-
 * Health-Check über die (gefälschte) Gateway-Factory.
 */
final class ZammadPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @param  list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}>  $tickets
     */
    private function fakeFactory(array $tickets = [], bool $ping = true): void {
        $gateway = new class($tickets, $ping) implements ZammadGateway {
            /** @param list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}> $tickets */
            public function __construct(private array $tickets, private bool $pingResult) {}

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return $this->tickets;
            }

            public function ping(): bool {
                return $this->pingResult;
            }

            public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
                return true;
            }

            public function accountTime(int $ticketId, float $timeUnit): bool {
                return true;
            }

            public function addArticle(int $ticketId, string $body, bool $internal = true): bool {
                return true;
            }
        };

        $this->app->instance(ZammadGatewayFactory::class, new class($gateway) implements ZammadGatewayFactory {
            public function __construct(private ZammadGateway $gateway) {}

            public function for(ZammadConnection $connection): ZammadGateway {
                return $this->gateway;
            }
        });
    }

    /** @return array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null} */
    private function ticket(int $id): array {
        return ['id' => $id, 'number' => (string) (22000 + $id), 'title' => "Ticket {$id}", 'group_id' => null, 'state' => 'open', 'customer_id' => null];
    }

    private function connection(bool $active = true): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'base_url' => 'https://support.example.com',
            'api_token' => 'token-123',
            'active' => $active,
        ]);
    }

    public function test_is_discovered_and_announces_task_sync(): void {
        $this->assertContains(ZammadPlugin::class, PluginDiscovery::classes());

        $plugin = new ZammadPlugin();
        $this->assertContains(PluginCapability::TaskSync, $plugin->capabilities());
        $this->assertTrue($plugin->isPerOrganization());
        $this->assertInstanceOf(TaskSyncer::class, $plugin);
    }

    public function test_sync_tasks_aggregates_created_then_unchanged(): void {
        $this->fakeFactory([$this->ticket(1), $this->ticket(2)]);
        $this->connection();

        $first = (new ZammadPlugin())->syncTasks($this->organization);
        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $first['unchanged']);

        $second = (new ZammadPlugin())->syncTasks($this->organization);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['unchanged']);
        $this->assertSame(0, $second['failed']);
    }

    public function test_sync_tasks_skips_inactive_connection(): void {
        $this->fakeFactory([$this->ticket(1)]);
        $this->connection(active: false);

        $result = (new ZammadPlugin())->syncTasks($this->organization);
        $this->assertSame(0, $result['created']);
    }

    public function test_health_ok_when_ping_succeeds(): void {
        $this->fakeFactory(ping: true);
        $this->connection();

        $this->assertTrue((new ZammadPlugin())->healthCheck()->isOk());
    }

    public function test_health_failing_when_ping_fails(): void {
        $this->fakeFactory(ping: false);
        $this->connection();

        $this->assertTrue((new ZammadPlugin())->healthCheck()->isFailing());
    }

    public function test_health_degraded_without_connection(): void {
        $this->fakeFactory();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, (new ZammadPlugin())->healthCheck()->status);
    }
}
