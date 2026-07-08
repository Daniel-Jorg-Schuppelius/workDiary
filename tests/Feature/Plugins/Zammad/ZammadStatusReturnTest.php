<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadStatusReturnTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Zammad;

use App\Models\{ExternalReference, IntegrationOutboxEntry, Task, ZammadConnection};
use App\Plugins\Zammad\Contracts\{ZammadGateway, ZammadGatewayFactory};
use App\Plugins\Zammad\Services\ZammadOutboxDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 060, 2. Ausbaustufe — Status-Rückkanal: eine erledigte, mit einem
 * Zammad-Ticket verknüpfte Aufgabe enqueued einen Outbox-Eintrag, den der
 * {@see ZammadOutboxDispatcher} als Ticket-Rückmeldung (Zielstatus) überträgt.
 * Ohne konfigurierten `resolved_state` passiert nichts (opt-in).
 */
final class ZammadStatusReturnTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function connection(?string $resolvedState): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Zammad',
            'base_url' => 'https://zammad.example',
            'api_token' => 'tok',
            'active' => true,
            'resolved_state' => $resolvedState,
        ]);
    }

    private function linkedTask(): Task {
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'status' => 'open']);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'zammad',
            'external_type' => 'ticket',
            'external_id' => '42',
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->id,
        ]);

        return $task;
    }

    /** @return ZammadGateway&object{ticketId: int|null, state: string|null} */
    private function recordingGateway(): ZammadGateway {
        return new class implements ZammadGateway {
            public ?int $ticketId = null;

            public ?string $state = null;

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return [];
            }

            public function ping(): bool {
                return true;
            }

            public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
                $this->ticketId = $ticketId;
                $this->state = $state;

                return true;
            }

            public function accountTime(int $ticketId, float $timeUnit): bool {
                return true;
            }

            public function addArticle(int $ticketId, string $body, bool $internal = true): bool {
                return true;
            }
        };
    }

    public function test_completing_linked_task_enqueues_and_pushes_ticket_state(): void {
        Queue::fake(); // Delivery-Job nicht ausführen — Eintrag + Dispatch getrennt prüfen
        $this->setUpOrganization();
        $this->connection('closed');
        $task = $this->linkedTask();

        $task->update(['status' => 'done']);

        $entry = IntegrationOutboxEntry::query()
            ->where('plugin_id', 'zammad')
            ->where('operation', ZammadOutboxDispatcher::OP_TICKET_RESOLVE)
            ->firstOrFail();
        $this->assertSame(42, (int) $entry->payload['ticket_id']);

        $recorder = $this->recordingGateway();
        $this->app->instance(ZammadGatewayFactory::class, new class($recorder) implements ZammadGatewayFactory {
            public function __construct(private ZammadGateway $gateway) {}

            public function for(ZammadConnection $connection): ZammadGateway {
                return $this->gateway;
            }
        });

        $this->assertTrue((new ZammadOutboxDispatcher())->dispatch($entry));
        $this->assertSame(42, $recorder->ticketId);
        $this->assertSame('closed', $recorder->state);
    }

    public function test_no_return_channel_without_resolved_state(): void {
        Queue::fake();
        $this->setUpOrganization();
        $this->connection(null); // opt-in nicht konfiguriert
        $task = $this->linkedTask();

        $task->update(['status' => 'done']);

        $this->assertSame(0, IntegrationOutboxEntry::query()
            ->where('plugin_id', 'zammad')
            ->where('operation', ZammadOutboxDispatcher::OP_TICKET_RESOLVE)
            ->count());
    }
}
