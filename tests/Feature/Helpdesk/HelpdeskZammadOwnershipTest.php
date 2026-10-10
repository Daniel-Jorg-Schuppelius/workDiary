<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskZammadOwnershipTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket};
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\Services\{ZammadOutboxDispatcher, ZammadTicketImporter};
use App\Plugins\Zammad\ZammadPlugin;
use App\Services\Integration\IntegrationOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 065, P8 (MVP-158): Zielmodus je Queue (task-Bestand vs.
 * ServiceTicket nativ), Moduswechsel nur über Preflight-Admin-Aktion mit
 * Migrationsprotokoll, keine Kommentar-Rückmeldung (Phase 137, E20: ohne
 * Erzeuger entfernt) und keine Löschweitergabe.
 */
final class HelpdeskZammadOwnershipTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->actor = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    private function connection(array $overrides = []): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Zammad',
            'base_url' => 'https://zammad.example.com',
            'api_token' => 'secret',
            'active' => true,
            ...$overrides,
        ]);
    }

    /** @param array<int, array<string, mixed>> $tickets */
    private function gateway(array $tickets): ZammadGateway {
        return new class($tickets) implements ZammadGateway {
            /** @param array<int, array<string, mixed>> $tickets */
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

    public function test_native_queue_imports_as_service_ticket(): void {
        $queue = ServiceQueue::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Zammad-Support',
            'is_default' => true,
        ]);
        $connection = $this->connection();

        // Moduswechsel mit Preflight + Migrationsprotokoll.
        $connection = app(ZammadTicketImporter::class)->switchTicketTarget($connection, 'service_ticket', $queue, $this->actor);
        $this->assertSame('external', $queue->fresh()->data_ownership, 'Zammad führt die Queue.');

        $result = app(ZammadTicketImporter::class)->import(
            $connection,
            $this->gateway([['id' => 71, 'number' => '81071', 'title' => 'Mail down', 'state' => 'open', 'group_id' => null]]),
            $this->actor,
        );

        $this->assertSame(1, $result['created']);
        $ticket = ServiceTicket::query()->where('source_reference', 'zammad:71')->firstOrFail();
        $this->assertSame((int) $queue->id, (int) $ticket->queue_id);
        $this->assertSame(
            1,
            ExternalReference::query()
                ->where('plugin_id', ZammadPlugin::ID)
                ->where('referenceable_type', $ticket->getMorphClass())
                ->where('external_id', '71')->count(),
        );

        // Idempotent: zweiter Lauf überspringt.
        $again = app(ZammadTicketImporter::class)->import($connection, $this->gateway([['id' => 71, 'number' => '81071', 'title' => 'Mail down', 'state' => 'open', 'group_id' => null]]), $this->actor);
        $this->assertSame(1, $again['skipped']);
    }

    public function test_switch_requires_queue_and_same_org(): void {
        $connection = $this->connection();
        $importer = app(ZammadTicketImporter::class);

        try {
            $importer->switchTicketTarget($connection, 'service_ticket', null, $this->actor);
            $this->fail('Wechsel ohne Queue wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }

        $foreignQueue = ServiceQueue::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'name' => 'Fremd',
        ]);
        $this->expectException(\RuntimeException::class);
        $importer->switchTicketTarget($connection, 'service_ticket', $foreignQueue, $this->actor);
    }

    /** Die Kommentar-Operation hatte keinen Erzeuger und ist entfernt (Phase 137, E20). */
    public function test_comment_operation_no_longer_exists(): void {
        \Illuminate\Support\Facades\Queue::fake();
        $this->connection();
        $entry = app(IntegrationOutboxService::class)->enqueue(
            $this->org->id,
            ZammadPlugin::ID,
            'ticket.comment',
            ['ticket_id' => 71, 'body' => 'Interne Rückmeldung', 'internal' => true],
            'test-comment-71',
        );

        $this->expectException(\RuntimeException::class);
        app(ZammadOutboxDispatcher::class)->dispatch($entry->refresh());
    }

    public function test_no_delete_propagation_operation_exists(): void {
        \Illuminate\Support\Facades\Queue::fake();
        $this->connection();
        $entry = app(IntegrationOutboxService::class)->enqueue(
            $this->org->id,
            ZammadPlugin::ID,
            'ticket.delete',
            ['ticket_id' => 71],
            'test-delete-71',
        );

        // Löschweitergabe ist KEINE bekannte Operation — harter Fehler.
        $this->expectException(\RuntimeException::class);
        app(ZammadOutboxDispatcher::class)->dispatch($entry->refresh());
    }
}
