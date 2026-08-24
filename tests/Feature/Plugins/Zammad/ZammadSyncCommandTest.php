<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadSyncCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Zammad;

use App\Models\{Task, ZammadConnection};
use App\Plugins\Zammad\Contracts\{ZammadGateway, ZammadGatewayFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: Smoke für zammad:sync — die Gateway-Factory wird
 * im Container durch eine Fake-Variante ersetzt (kein HTTP), Import-Details
 * stehen im ZammadTicketImporterTest.
 */
final class ZammadSyncCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @param  list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}>  $tickets
     */
    private function fakeGatewayFactory(array $tickets): void {
        $gateway = new class($tickets) implements ZammadGateway {
            /** @param list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}> $tickets */
            public function __construct(private array $tickets) {}

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return $page === 1 ? $this->tickets : [];
            }

            public function ping(): bool {
                return true;
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

        $factory = new class($gateway) implements ZammadGatewayFactory {
            public function __construct(private ZammadGateway $gateway) {}

            public function for(ZammadConnection $connection): ZammadGateway {
                return $this->gateway;
            }
        };

        $this->app->instance(ZammadGatewayFactory::class, $factory);
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

    public function test_sync_imports_tickets_as_tasks(): void {
        $connection = $this->connection();
        $this->fakeGatewayFactory([[
            'id' => 1,
            'number' => '22001',
            'title' => 'Ticket 1',
            'group_id' => 5,
            'state' => 'open',
            'customer_id' => 3,
        ]]);

        $this->artisan('zammad:sync', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('created 1')
            ->assertExitCode(0);

        $this->assertSame(1, Task::query()->where('organization_id', $this->organization->id)->count());
        $connection->refresh();
        $this->assertNotNull($connection->last_polled_at, 'Aufholpunkt muss fortgeschrieben werden.');
    }

    public function test_inactive_connection_is_skipped(): void {
        $this->connection(active: false);
        $this->fakeGatewayFactory([[
            'id' => 1,
            'number' => '22001',
            'title' => 'Ticket 1',
            'group_id' => 5,
            'state' => 'open',
            'customer_id' => 3,
        ]]);

        $this->artisan('zammad:sync', ['--organization' => (string) $this->organization->id])
            ->assertExitCode(0);

        $this->assertSame(0, Task::query()->count());
    }
}
