<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadCustomerSuggestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Zammad;

use App\Models\{Customer, IntegrationInboxItem, ZammadConnection};
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 060, MVP-129, Rang 21 — Kundenvorschlag beim Ticket-Import: der Import
 * matcht den Ticket-Kunden (E-Mail/Organisation) über den EntityMatcher und legt
 * bei nicht-eindeutigem/leerem Treffer einen Kundenvorschlag in die Zuordnungs-
 * Inbox, statt still im Default-Projekt zu landen.
 */
final class ZammadCustomerSuggestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function connection(): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Zammad',
            'base_url' => 'https://zammad.example',
            'api_token' => 'tok',
            'active' => true,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     */
    private function gateway(array $tickets): ZammadGateway {
        return new class($tickets) implements ZammadGateway {
            /** @param list<array<string, mixed>> $tickets */
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

            public function addArticle(int $ticketId, string $body, bool $internal = true): bool {
                return true;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function ticket(int $id, string $customer = '', string $organization = ''): array {
        return [
            'id' => $id,
            'number' => (string) (1000 + $id),
            'title' => 'Problem',
            'group_id' => null,
            'state' => 'open',
            'customer_id' => null,
            'customer' => $customer,
            'organization' => $organization,
        ];
    }

    public function test_known_customer_email_creates_ambiguous_suggestion(): void {
        $connection = $this->connection();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'kunde@example.test',
            'name' => 'Muster GmbH',
        ]);

        $result = (new ZammadTicketImporter())->import($connection, $this->gateway([
            $this->ticket(7, customer: 'kunde@example.test'),
        ]));

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['inbox']);

        $item = IntegrationInboxItem::query()
            ->where('plugin_id', 'zammad')
            ->where('external_type', 'ticket_customer')
            ->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_AMBIGUOUS, $item->case_type);
        $this->assertSame($customer->id, $item->referenceable_id);
        $this->assertNotEmpty($item->candidate_ids);
    }

    public function test_unknown_customer_creates_unmatched_suggestion(): void {
        $connection = $this->connection();

        $result = (new ZammadTicketImporter())->import($connection, $this->gateway([
            $this->ticket(8, organization: 'Völlig Fremd AG'),
        ]));

        $this->assertSame(1, $result['inbox']);

        $item = IntegrationInboxItem::query()->where('external_type', 'ticket_customer')->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_UNMATCHED, $item->case_type);
        $this->assertSame([], $item->candidate_ids);
    }

    public function test_ticket_without_customer_data_has_no_suggestion(): void {
        $connection = $this->connection();

        $result = (new ZammadTicketImporter())->import($connection, $this->gateway([$this->ticket(9)]));

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['inbox']);
        $this->assertSame(0, IntegrationInboxItem::query()->where('external_type', 'ticket_customer')->count());
    }

    public function test_reimport_does_not_duplicate_suggestion(): void {
        $connection = $this->connection();
        Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'k@example.test']);
        $importer = new ZammadTicketImporter();

        $importer->import($connection, $this->gateway([$this->ticket(10, customer: 'k@example.test')]));
        // Replay: Ticket ist bereits importiert → wird übersprungen, kein zweiter Vorschlag.
        $importer->import($connection, $this->gateway([$this->ticket(10, customer: 'k@example.test')]));

        $this->assertSame(1, IntegrationInboxItem::query()->where('external_type', 'ticket_customer')->count());
    }
}
