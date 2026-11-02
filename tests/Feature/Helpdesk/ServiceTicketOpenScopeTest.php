<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketOpenScopeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Dashboard\Widgets\ServiceTicketsWidget;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket};
use App\Services\ServiceTicket\Cockpit\ServiceTicketCockpitPanel;
use App\Services\ServiceTicket\WorkQueue\ServiceTicketWorkItemSource;
use App\Support\EntityUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * „Offen“ ist für Tickets eine Definition (Vollscan 2026-11-02, gap-08):
 * der Scope `open()` — nicht gelöst, Wartezustände eingeschlossen —, und
 * Arbeitsvorrat, Cockpit und Kachel zählen dieselbe Menge; `EntityUrl`
 * kennt die Ticketseite.
 */
final class ServiceTicketOpenScopeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin(['name' => 'Anna Admin']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Grünweg Technik GmbH']);
        $queue = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Support', 'is_default' => true]);
        foreach (ServiceTicketStatus::cases() as $status) {
            ServiceTicket::factory()->create([
                'organization_id' => $this->organization->id,
                'queue_id' => $queue->id,
                'customer_id' => $this->customer->id,
                'assigned_to_user_id' => $this->admin->id,
                'status' => $status,
                'title' => 'Ticket ' . $status->value,
                'reported_at' => now()->subDay(),
            ]);
        }
    }

    public function test_open_scope_keeps_waiting_states_and_drops_resolved_ones(): void {
        $open = ServiceTicket::query()->open()->get()->map(static fn (ServiceTicket $t): string => $t->status->value)->sort()->values()->all();

        $this->assertSame(
            ['in_progress', 'paused', 'reported', 'scheduled', 'triaged', 'waiting_customer', 'waiting_external'],
            $open,
        );
        $this->assertSame(
            ['done', 'accepted', 'closed', 'rejected'],
            array_map(static fn (ServiceTicketStatus $s): string => $s->value, array_values(array_filter(ServiceTicketStatus::cases(), static fn (ServiceTicketStatus $s): bool => $s->isResolved()))),
        );
    }

    public function test_work_queue_cockpit_and_tile_count_the_same_open_tickets(): void {
        $expected = ServiceTicket::query()->open()->count();
        $this->assertSame(7, $expected);

        // Factories anderer Datensätze verstellen den Spatie-Team-Kontext; vor den Rechteprüfungen zurücksetzen.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->actingAs($this->admin);
        $items = iterator_to_array(app(ServiceTicketWorkItemSource::class)->items($this->admin, $this->organization), false);
        $this->assertCount($expected, $items, 'Arbeitsvorrat');

        $panel = app(ServiceTicketCockpitPanel::class)->render($this->customer, $this->admin);
        $this->assertInstanceOf(View::class, $panel);
        $this->assertCount($expected, $panel->getData()['rows'], 'Cockpit');

        $tile = app(ServiceTicketsWidget::class)->render($this->admin);
        $this->assertInstanceOf(View::class, $tile);
        $this->assertSame($expected, $tile->getData()['openCount'], 'Kachel');
    }

    public function test_entity_url_resolves_the_ticket_page(): void {
        $ticket = ServiceTicket::query()->firstOrFail();

        $this->assertSame(route('service-tickets.show', $ticket), EntityUrl::for($ticket));
        $this->assertSame(route('service-tickets.show', $ticket), EntityUrl::byType($ticket->getMorphClass(), (int) $ticket->id));
    }
}
