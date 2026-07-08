<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadTimeReturnTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Zammad;

use App\Models\{ExternalReference, IntegrationOutboxEntry, Project, Task, TimeEntry, ZammadConnection};
use App\Plugins\Zammad\Contracts\{ZammadGateway, ZammadGatewayFactory};
use App\Plugins\Zammad\Services\ZammadOutboxDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 060, MVP-129, Rang 23 — Zeit-Rückbuchung: eine zu einer Zammad-
 * verknüpften Aufgabe erfasste Zeit enqueued einen Outbox-Eintrag, den der
 * {@see ZammadOutboxDispatcher} als Time-Accounting ins Ticket bucht.
 * Einheiten-Konvention je Anbindung; opt-in über `time_unit`; idempotent.
 */
final class ZammadTimeReturnTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function connection(?string $timeUnit): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Zammad',
            'base_url' => 'https://zammad.example',
            'api_token' => 'tok',
            'active' => true,
            'time_unit' => $timeUnit,
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

    private function timeEntry(Task $task, int $minutes = 90): TimeEntry {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);

        return TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'minutes' => $minutes,
        ]);
    }

    private function recordingGateway(): ZammadGateway {
        return new class implements ZammadGateway {
            public ?int $ticketId = null;

            public ?float $timeUnit = null;

            public int $calls = 0;

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return [];
            }

            public function ping(): bool {
                return true;
            }

            public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
                return true;
            }

            public function accountTime(int $ticketId, float $timeUnit): bool {
                $this->ticketId = $ticketId;
                $this->timeUnit = $timeUnit;
                $this->calls++;

                return true;
            }

            public function addArticle(int $ticketId, string $body, bool $internal = true): bool {
                return true;
            }
        };
    }

    private function bindGateway(ZammadGateway $gateway): void {
        $this->app->instance(ZammadGatewayFactory::class, new class($gateway) implements ZammadGatewayFactory {
            public function __construct(private ZammadGateway $gateway) {}

            public function for(ZammadConnection $connection): ZammadGateway {
                return $this->gateway;
            }
        });
    }

    private function timeEntryOutboxEntry(): IntegrationOutboxEntry {
        return IntegrationOutboxEntry::query()
            ->where('plugin_id', 'zammad')
            ->where('operation', ZammadOutboxDispatcher::OP_TICKET_TIME)
            ->firstOrFail();
    }

    public function test_time_on_linked_task_enqueues_and_accounts(): void {
        Queue::fake();
        $this->setUpOrganization();
        $this->connection('minute');
        $task = $this->linkedTask();
        $timeEntry = $this->timeEntry($task, 90);

        $entry = $this->timeEntryOutboxEntry();
        $this->assertSame(42, (int) $entry->payload['ticket_id']);
        $this->assertSame(90, (int) $entry->payload['minutes']);

        $recorder = $this->recordingGateway();
        $this->bindGateway($recorder);
        $this->assertTrue((new ZammadOutboxDispatcher())->dispatch($entry));

        $this->assertSame(42, $recorder->ticketId);
        $this->assertSame(90.0, $recorder->timeUnit);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => 'zammad',
            'external_type' => 'time_accounting',
            'external_id' => (string) $timeEntry->id,
        ]);
    }

    public function test_hour_unit_converts_minutes_to_hours(): void {
        Queue::fake();
        $this->setUpOrganization();
        $this->connection('hour');
        $task = $this->linkedTask();
        $this->timeEntry($task, 90);

        $recorder = $this->recordingGateway();
        $this->bindGateway($recorder);
        (new ZammadOutboxDispatcher())->dispatch($this->timeEntryOutboxEntry());

        $this->assertSame(1.5, $recorder->timeUnit); // 90 Min = 1,5 h
    }

    public function test_dispatch_is_idempotent(): void {
        Queue::fake();
        $this->setUpOrganization();
        $this->connection('minute');
        $task = $this->linkedTask();
        $this->timeEntry($task, 60);

        $recorder = $this->recordingGateway();
        $this->bindGateway($recorder);
        $entry = $this->timeEntryOutboxEntry();
        (new ZammadOutboxDispatcher())->dispatch($entry);
        (new ZammadOutboxDispatcher())->dispatch($entry); // Replay

        $this->assertSame(1, $recorder->calls);
    }

    public function test_no_time_channel_without_time_unit(): void {
        Queue::fake();
        $this->setUpOrganization();
        $this->connection(null); // opt-in nicht konfiguriert
        $task = $this->linkedTask();
        $this->timeEntry($task, 90);

        $this->assertSame(0, IntegrationOutboxEntry::query()
            ->where('operation', ZammadOutboxDispatcher::OP_TICKET_TIME)->count());
    }

    public function test_time_on_unlinked_task_is_ignored(): void {
        Queue::fake();
        $this->setUpOrganization();
        $this->connection('minute');
        $task = Task::factory()->create(['organization_id' => $this->organization->id]); // keine Ticket-Referenz
        $this->timeEntry($task, 90);

        $this->assertSame(0, IntegrationOutboxEntry::query()
            ->where('operation', ZammadOutboxDispatcher::OP_TICKET_TIME)->count());
    }
}
