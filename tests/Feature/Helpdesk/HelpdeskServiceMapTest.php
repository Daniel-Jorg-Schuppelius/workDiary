<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskServiceMapTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\ServiceOffering;
use App\Models\ServiceTicket\{BusinessService, ServiceTicket};
use App\Services\ServiceTicket\{ServiceImpactAnalyzer, ServiceTicketService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fachdienst am Ticket (MVP-1257) und Servicelandkarte mit
 * Auswirkungsanalyse (MVP-1256).
 */
final class HelpdeskServiceMapTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->agent = User::factory()->teamleitung()->create();
        $this->org = $this->agent->organization;
        app()->instance('currentOrganization', $this->org);
    }

    private function service(string $name, ?Organization $org = null): BusinessService {
        return BusinessService::query()->create(['organization_id' => ($org ?? $this->org)->id, 'name' => $name]);
    }

    private function offering(BusinessService $service, string $name): ServiceOffering {
        return ServiceOffering::query()->create(['organization_id' => $service->organization_id, 'business_service_id' => $service->id, 'name' => $name]);
    }

    public function test_offering_brings_its_service_and_foreign_or_mismatched_pairs_drop(): void {
        $mail = $this->service('E-Mail');
        $network = $this->service('Netzwerk');
        $mailbox = $this->offering($mail, 'Postfach einrichten');
        $foreign = $this->offering($this->service('Fremd', Organization::factory()->create()), 'Fremdangebot');
        $tickets = app(ServiceTicketService::class);

        $derived = $tickets->create($this->org, $this->agent, ['title' => 'Postfach voll', 'service_offering_id' => $mailbox->id]);
        $this->assertSame([(int) $mail->id, (int) $mailbox->id], [(int) $derived->business_service_id, (int) $derived->service_offering_id]);

        $mismatch = $tickets->create($this->org, $this->agent, ['title' => 'x', 'business_service_id' => $network->id, 'service_offering_id' => $mailbox->id]);
        $this->assertSame((int) $network->id, (int) $mismatch->business_service_id);
        $this->assertNull($mismatch->service_offering_id);

        $alien = $tickets->create($this->org, $this->agent, ['title' => 'y', 'service_offering_id' => $foreign->id]);
        $this->assertNull($alien->business_service_id);
        $this->assertNull($alien->service_offering_id);
    }

    public function test_create_dialog_offers_selects_with_sqids_and_store_decodes_them(): void {
        $mail = $this->service('E-Mail');
        $mailbox = $this->offering($mail, 'Postfach einrichten');
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->agent)->get(route('service-tickets.create'))
            ->assertOk()
            ->assertSee('value="' . $customer->sqid . '"', false)
            ->assertSee('value="' . $mailbox->sqid . '"', false)
            ->assertDontSee(__('Kunden-ID'));

        $this->actingAs($this->agent)->post(route('service-tickets.store'), [
            'title' => 'Postfach voll',
            'priority' => 'normal',
            'customer_id' => $customer->sqid,
            'business_service_id' => $mail->sqid,
            'service_offering_id' => $mailbox->sqid,
        ])->assertRedirect();

        $ticket = ServiceTicket::query()->latest('id')->firstOrFail();
        $this->assertSame([(int) $customer->id, (int) $mail->id, (int) $mailbox->id], [(int) $ticket->customer_id, (int) $ticket->business_service_id, (int) $ticket->service_offering_id]);
    }

    public function test_classify_sets_the_service_and_the_list_filters_by_it(): void {
        $mail = $this->service('E-Mail');
        $ticket = app(ServiceTicketService::class)->create($this->org, $this->agent, ['title' => 'Mail kaputt']);
        $other = app(ServiceTicketService::class)->create($this->org, $this->agent, ['title' => 'Drucker kaputt']);

        $this->actingAs($this->agent)->post(route('service-tickets.classify', $ticket), [
            'impact' => 2, 'urgency' => 2, 'business_service_id' => $mail->sqid, 'service_offering_id' => '',
        ])->assertSessionHas('success');

        $this->assertSame((int) $mail->id, (int) $ticket->fresh()->business_service_id);
        $this->assertSame(1, AuditLog::query()->where('event', 'service_ticket.service_changed')->count());

        $this->actingAs($this->agent)->get(route('service-tickets.index', ['service' => $mail->sqid]))
            ->assertOk()
            ->assertSee($ticket->ticket_no)
            ->assertDontSee($other->ticket_no);
    }

    public function test_impact_is_transitive_and_cycles_are_rejected(): void {
        $network = $this->service('Netzwerk');
        $mail = $this->service('E-Mail');
        $erp = $this->service('ERP');
        $analyzer = app(ServiceImpactAnalyzer::class);

        $this->actingAs($this->agent)->patch(route('servicedesk.catalog.services.update', $mail), [
            'name' => 'E-Mail', 'active' => '1', 'depends_on' => [$network->sqid],
        ])->assertRedirect();
        $this->actingAs($this->agent)->patch(route('servicedesk.catalog.services.update', $erp), [
            'name' => 'ERP', 'active' => '1', 'depends_on' => [$mail->sqid],
        ])->assertRedirect();

        $analyzer->forget((int) $this->org->id);
        $this->assertEqualsCanonicalizing([(int) $mail->id, (int) $erp->id], $analyzer->affectedBy((int) $this->org->id, (int) $network->id));
        $this->assertSame(1, AuditLog::query()->where('event', 'business_service.dependencies_changed')->where('auditable_id', $mail->id)->count());

        $this->actingAs($this->agent)->patch(route('servicedesk.catalog.services.update', $network), [
            'name' => 'Netzwerk', 'active' => '1', 'depends_on' => [$erp->sqid],
        ])->assertSessionHasErrors('depends_on');
        $this->assertSame(0, $network->dependsOn()->count());
    }

    public function test_ticket_shows_the_impact_and_the_map_lists_dependencies(): void {
        $network = $this->service('Netzwerk');
        $mail = $this->service('E-Mail');
        app(ServiceImpactAnalyzer::class)->syncDependencies($mail, [(int) $network->id]);
        $tickets = app(ServiceTicketService::class);
        $outage = $tickets->create($this->org, $this->agent, ['title' => 'Switch ausgefallen', 'business_service_id' => $network->id]);
        $tickets->create($this->org, $this->agent, ['title' => 'Keine Mails', 'business_service_id' => $mail->id]);

        $this->actingAs($this->agent)->get(route('service-tickets.show', $outage))
            ->assertOk()
            ->assertSee('data-ticket-impact', false)
            ->assertSee('E-Mail')
            ->assertSee(trans_choice('helpdesk.impact.openTickets', 1, ['count' => 1]));

        $this->actingAs($this->agent)->get(route('servicedesk.service-map.index'))
            ->assertOk()
            ->assertSee('Netzwerk')
            ->assertSee(route('service-tickets.index', ['service' => $mail->sqid]), false);
    }
}
