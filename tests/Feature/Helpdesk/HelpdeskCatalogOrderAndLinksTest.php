<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskCatalogOrderAndLinksTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Enums\Fields\FieldType;
use App\Enums\OpenIssue\OpenIssueEventType;
use App\Enums\ServiceTicket\{ServiceRequestStatus, ServiceTicketSource, TicketMessageDeliveryStatus};
use App\Models\Approval\Approval;
use App\Models\Diary\DiaryEntry;
use App\Models\Form\FormTemplate;
use App\Models\Platform\User;
use App\Models\Procurement\RequestItem;
use App\Models\Sales\ServiceOffering;
use App\Models\ServiceTicket\{BusinessService, ServiceQueue, ServiceRequest, ServiceTicket, ServiceTicketMessage};
use App\Services\OpenIssue\OpenIssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1549: interne Katalogbestellung, Antworten und Erfüllungsobjekt am Ticket
 * und im Genehmigungs-Eingang, Zustellstatus der Antwortmail, Ticket aus offenem Punkt.
 */
final class HelpdeskCatalogOrderAndLinksTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $agent;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->employee = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Intern', 'is_default' => true]);
    }

    /** @param array<string, mixed> $overrides */
    private function item(array $overrides = []): RequestItem {
        $service = BusinessService::query()->firstOrCreate(['organization_id' => $this->organization->id, 'name' => 'Arbeitsplatz']);
        $offering = ServiceOffering::query()->create([
            'organization_id' => $this->organization->id,
            'business_service_id' => $service->id,
            'name' => 'Angebot ' . fake()->unique()->numberBetween(1, 999999),
        ]);
        $template = FormTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->agent->id,
            'fields' => [
                ['key' => 'cpu', 'label' => 'Gewünschte CPU', 'type' => FieldType::Text->value, 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
            ],
        ]);

        return RequestItem::query()->create([
            'organization_id' => $this->organization->id,
            'service_offering_id' => $offering->id,
            'form_template_id' => $template->id,
            'name' => 'Notebook bestellen',
            ...$overrides,
        ]);
    }

    public function test_employee_orders_a_catalog_item_and_the_ticket_shows_answers_and_fulfilment(): void {
        $item = $this->item();

        $this->actingAs($this->employee)->get(route('servicedesk.order.index'))
            ->assertOk()
            ->assertSee(route('servicedesk.order.pick'), false);
        $this->actingAs($this->employee)->get(route('servicedesk.order.pick'))
            ->assertOk()
            ->assertSee('Notebook bestellen')
            ->assertSee(route('servicedesk.order.create', $item), false);
        $this->actingAs($this->employee)->get(route('servicedesk.order.create', $item))
            ->assertOk()
            ->assertSee('Gewünschte CPU');

        $this->actingAs($this->employee)
            ->post(route('servicedesk.order.store', $item), ['values' => []])
            ->assertSessionHasErrors();
        $this->actingAs($this->employee)
            ->post(route('servicedesk.order.store', $item), ['values' => ['cpu' => 'Acht Kerne']])
            ->assertRedirect(route('servicedesk.order.index'))
            ->assertSessionHasNoErrors();

        $request = ServiceRequest::query()->firstOrFail();
        $ticket = $request->ticket()->firstOrFail();
        $this->assertSame($this->employee->id, (int) $ticket->reported_by_user_id);
        $this->assertSame('Acht Kerne', $request->form_snapshot['answers']['cpu']);
        $this->assertSame(ServiceRequestStatus::Done, $request->status);

        $this->actingAs($this->employee)->get(route('servicedesk.order.index'))->assertOk()->assertSee($ticket->ticket_no);

        $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-ticket-service-request', false)
            ->assertSee('Gewünschte CPU')
            ->assertSee('Acht Kerne')
            ->assertSee('Notebook bestellen (' . $ticket->ticket_no . ')');
    }

    public function test_order_needs_the_request_right_and_respects_role_visibility(): void {
        $item = $this->item(['visibility' => ['roles' => ['teamleitung']]]);
        $noRole = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($noRole)->get(route('servicedesk.order.index'))->assertForbidden();
        $this->actingAs($this->employee)->get(route('servicedesk.order.pick'))->assertOk()->assertDontSee('Notebook bestellen');
        $this->actingAs($this->employee)->get(route('servicedesk.order.create', $item))->assertNotFound();
        $this->actingAs($this->employee)->post(route('servicedesk.order.store', $item), ['values' => ['cpu' => 'x']])->assertNotFound();
        $this->assertSame(0, ServiceRequest::query()->count());
    }

    public function test_approval_dialog_shows_the_form_answers(): void {
        $item = $this->item(['approval_chain' => [['approver' => ['type' => 'user', 'value' => (int) $this->agent->id]]]]);

        $this->actingAs($this->employee)
            ->post(route('servicedesk.order.store', $item), ['values' => ['cpu' => 'Sechzehn Kerne']])
            ->assertSessionHasNoErrors();
        $approval = Approval::query()->firstOrFail();

        $this->actingAs($this->agent)->get(route('approvals.decide-form', $approval))
            ->assertOk()
            ->assertSee('data-approval-fields', false)
            ->assertSee('Gewünschte CPU')
            ->assertSee('Sechzehn Kerne');
    }

    public function test_reply_shows_its_delivery_status_in_the_timeline(): void {
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'assigned_to_user_id' => $this->agent->id]);
        ServiceTicketMessage::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'kind' => 'public_reply',
            'author_type' => $this->agent->getMorphClass(),
            'author_id' => $this->agent->id,
            'to' => ['kunde@example.test'],
            'body' => 'Wir melden uns.',
            'channel' => 'manual',
            'delivery_status' => TicketMessageDeliveryStatus::Failed,
        ]);

        $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-message-delivery', false)
            ->assertSee(TicketMessageDeliveryStatus::Failed->label())
            ->assertSee('kunde@example.test');
    }

    public function test_ticket_is_opened_from_an_open_issue_once_and_linked_back(): void {
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->agent->id]);
        $issue = app(OpenIssueService::class)->create($entry, $this->agent, ['title' => 'Heizkreis entlüften', 'description' => 'Pumpe läuft trocken.']);

        $this->actingAs($this->agent)->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee(route('open-issues.ticket', $issue), false);

        $this->actingAs($this->agent)->post(route('open-issues.ticket', $issue))->assertSessionHas('success');
        $this->actingAs($this->agent)->post(route('open-issues.ticket', $issue))->assertSessionHas('success');

        $tickets = ServiceTicket::query()->where('source', ServiceTicketSource::OpenIssue->value)->get();
        $this->assertCount(1, $tickets);
        $ticket = $tickets->first();
        $this->assertSame('open_issue:' . $issue->id, $ticket->source_reference);
        $this->assertSame('Heizkreis entlüften', $ticket->title);
        $this->assertSame($entry->id, (int) $ticket->diary_entry_id);
        $this->assertSame(1, $issue->journal()->where('event', OpenIssueEventType::TicketOpened->value)->count());

        $this->actingAs($this->agent)->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee('data-open-issue-ticket', false)
            ->assertSee($ticket->ticket_no)
            ->assertDontSee(route('open-issues.ticket', $issue), false);
    }
}
