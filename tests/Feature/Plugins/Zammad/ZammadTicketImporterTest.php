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

use App\Enums\Task\TaskStatus;
use App\Models\Integration\{ExternalReference, IntegrationOutboxEntry};
use App\Models\Platform\Organization;
use App\Models\Project\{Project, Task};
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use App\Plugins\Zammad\ZammadPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 060, MVP-129: Ticket-Import als Aufgaben. Prüft Idempotenz über
 * ExternalReference, Queue→Projekt-Zuordnung, Default-Fallback, die
 * Mandantengrenze (nie ein Fremdprojekt), den Aufholpunkt sowie Gruppenfilter
 * und Statusabgleich (Phase 137, E18/E19).
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
    private function gateway(array $tickets, bool $ignoresPaging = false): ZammadGateway {
        return new class($tickets, $ignoresPaging) implements ZammadGateway {
            /** @param list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null}> $tickets */
            public function __construct(private array $tickets, private bool $ignoresPaging) {}

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return $this->ignoresPaging ? array_slice($this->tickets, 0, $perPage) : array_slice($this->tickets, ($page - 1) * $perPage, $perPage);
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

        $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0, 'inbox' => 0], $result);
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

        $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0, 'inbox' => 0], $first);
        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 2, 'inbox' => 0], $second);
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

    /** Zammad liefert höchstens 100 Tickets je Seite — der Import liest alle Seiten (Phase 137). */
    public function test_imports_every_page_of_the_ticket_list(): void {
        $connection = $this->connection();
        $tickets = array_map(fn (int $id): array => $this->ticket($id), range(1, 250));

        $result = (new ZammadTicketImporter())->import($connection, $this->gateway($tickets));

        $this->assertSame(250, $result['created']);
        $this->assertSame(250, Task::query()->count());
    }

    /** Wertet ein Server `page` nicht aus, endet der Lauf nach der ersten Wiederholung. */
    public function test_server_ignoring_the_page_parameter_does_not_loop(): void {
        $connection = $this->connection();
        $tickets = array_map(fn (int $id): array => $this->ticket($id), range(1, 100));

        $result = (new ZammadTicketImporter())->import($connection, $this->gateway($tickets, ignoresPaging: true));

        $this->assertSame(100, $result['created']);
    }

    /** Geschlossene Tickets, die nie importiert wurden, holt der Import nicht nach (Phase 137, E19). */
    public function test_closed_ticket_that_was_never_imported_is_skipped(): void {
        $connection = $this->connection();

        $result = (new ZammadTicketImporter())->import($connection, $this->gateway([
            $this->ticket(1, state: 'closed'),
            $this->ticket(2, state: 'merged'),
            $this->ticket(3),
        ]));

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 2, 'inbox' => 0], $result);
        $this->assertSame(['#22003 Ticket 3'], Task::query()->pluck('title')->all());
        $this->assertSame(1, ExternalReference::query()->where('plugin_id', ZammadPlugin::ID)->count());
    }

    /**
     * Schließt Zammad ein importiertes Ticket, wird die Aufgabe erledigt — ohne
     * Rückmeldung ans Ticket (E19). Maßgeblich ist der Übergang: eine lokal
     * wieder geöffnete Aufgabe bleibt offen.
     */
    public function test_closing_an_imported_ticket_completes_the_task_once_and_without_echo(): void {
        Queue::fake();
        $connection = $this->connection();
        $connection->forceFill(['resolved_state' => 'closed'])->save();
        $importer = new ZammadTicketImporter();

        $importer->import($connection, $this->gateway([$this->ticket(1)]));
        $task = Task::query()->firstOrFail();
        $this->assertSame(TaskStatus::Open, $task->status);

        $closed = $importer->import($connection, $this->gateway([$this->ticket(1, state: 'closed')]));
        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0, 'inbox' => 0], $closed);
        $this->assertSame(TaskStatus::Done, $task->refresh()->status);
        $this->assertSame(0, IntegrationOutboxEntry::query()->withoutGlobalScopes()->where('plugin_id', ZammadPlugin::ID)->count(), 'Der Abschluss kam aus Zammad und geht nicht zurück.');

        $task->forceFill(['status' => TaskStatus::Open->value])->save();
        $again = $importer->import($connection, $this->gateway([$this->ticket(1, state: 'closed')]));
        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1, 'inbox' => 0], $again);
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    /** Mit „Nur zugeordnete Gruppen“ kommen nur Tickets der Gruppen mit Projektzuordnung an (E18). */
    public function test_limited_connection_imports_only_mapped_groups(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $connection = $this->connection(queueMap: [5 => (int) $project->id]);
        $connection->forceFill(['is_limited_to_mapped_groups' => true])->save();

        $result = (new ZammadTicketImporter())->import($connection, $this->gateway([
            $this->ticket(1, group: 5),
            $this->ticket(2, group: 99),
        ]));

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Task::query()->where('project_id', $project->id)->count());
        $this->assertSame(1, Task::query()->count());
    }
}
