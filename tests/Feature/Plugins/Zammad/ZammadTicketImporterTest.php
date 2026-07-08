<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadTicketImporterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Zammad;

use App\Models\{ExternalReference, Organization, Project, Task, ZammadConnection};
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use App\Plugins\Zammad\ZammadPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 060, MVP-129: Ticket-Import als Aufgaben. Prüft Idempotenz über
 * ExternalReference, Queue→Projekt-Zuordnung, Default-Fallback, die
 * Mandantengrenze (nie ein Fremdprojekt) und den Aufholpunkt.
 */
final class ZammadTicketImporterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @param  list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}>  $tickets
     */
    private function gateway(array $tickets): ZammadGateway {
        return new class($tickets) implements ZammadGateway {
            /** @param list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}> $tickets */
            public function __construct(private array $tickets) {}

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return $this->tickets;
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
        };
    }

    /**
     * @param  array<int|string, int>  $queueMap
     */
    private function connection(array $queueMap = [], ?int $defaultProjectId = null): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'base_url' => 'https://support.example.com',
            'api_token' => 'token-123',
            'active' => true,
            'default_project_id' => $defaultProjectId,
            'queue_map' => $queueMap,
        ]);
    }

    /** @return array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null} */
    private function ticket(int $id, int $group = 5, string $state = 'open'): array {
        return ['id' => $id, 'number' => (string) (22000 + $id), 'title' => "Ticket {$id}", 'group_id' => $group, 'state' => $state, 'customer_id' => 3];
    }

    public function test_imports_tickets_as_tasks_with_external_reference(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $connection = $this->connection(queueMap: [5 => (int) $project->id]);

        $gateway = $this->gateway([$this->ticket(1, group: 5), $this->ticket(2, group: 99)]);
        $result = (new ZammadTicketImporter())->import($connection, $gateway);

        $this->assertSame(['created' => 2, 'skipped' => 0, 'inbox' => 0], $result);
        $this->assertSame(2, Task::query()->count());

        // Gruppe 5 → gemapptes Projekt; Gruppe 99 (ohne Treffer/Default) → global.
        $mapped = Task::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($mapped);
        $this->assertFalse($mapped->is_global);
        $this->assertStringContainsString('#22001', (string) $mapped->title);

        $global = Task::query()->whereNull('project_id')->first();
        $this->assertNotNull($global);
        $this->assertTrue($global->is_global);

        $this->assertSame(2, ExternalReference::query()
            ->where('plugin_id', ZammadPlugin::ID)
            ->where('external_type', ZammadPlugin::EXT_TYPE_TICKET)
            ->count());
        $connection->refresh();
        $this->assertNotNull($connection->last_polled_at);
    }

    public function test_replay_is_idempotent(): void {
        $connection = $this->connection();
        $importer = new ZammadTicketImporter();

        $first = $importer->import($connection, $this->gateway([$this->ticket(1), $this->ticket(2)]));
        $second = $importer->import($connection, $this->gateway([$this->ticket(1), $this->ticket(2)]));

        $this->assertSame(['created' => 2, 'skipped' => 0, 'inbox' => 0], $first);
        $this->assertSame(['created' => 0, 'skipped' => 2, 'inbox' => 0], $second);
        $this->assertSame(2, Task::query()->count());
    }

    public function test_default_project_is_used_when_queue_unmapped(): void {
        $default = Project::factory()->create(['organization_id' => $this->organization->id]);
        $connection = $this->connection(defaultProjectId: (int) $default->id);

        (new ZammadTicketImporter())->import($connection, $this->gateway([$this->ticket(1, group: 77)]));

        $this->assertSame(1, Task::query()->where('project_id', $default->id)->count());
    }

    public function test_cross_org_project_falls_back_to_global(): void {
        // Projekt einer FREMDEN Organisation darf nie zugeordnet werden.
        $otherOrg = Organization::factory()->create();
        $foreign = Project::factory()->create(['organization_id' => $otherOrg->id]);
        $connection = $this->connection(queueMap: [5 => (int) $foreign->id]);

        (new ZammadTicketImporter())->import($connection, $this->gateway([$this->ticket(1, group: 5)]));

        $task = Task::query()->first();
        $this->assertNotNull($task);
        $this->assertNull($task->project_id);
        $this->assertTrue($task->is_global);
    }

    public function test_closed_ticket_becomes_done(): void {
        $connection = $this->connection();

        (new ZammadTicketImporter())->import($connection, $this->gateway([$this->ticket(1, state: 'closed')]));

        $task = Task::query()->first();
        $this->assertNotNull($task);
        $this->assertSame('done', $task->status->value);
    }
}
