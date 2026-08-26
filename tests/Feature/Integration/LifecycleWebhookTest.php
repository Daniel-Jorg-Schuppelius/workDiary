<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LifecycleWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\Integration\WebhookEvent;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Jobs\Integration\WebhookDeliveryJob;
use App\Models\{Article, Customer, Invoice, Protocol, Supplier, Timesheet, User, Warehouse};
use App\Models\Integration\{WebhookDelivery, WebhookEndpoint};
use App\Services\Invoicing\InvoiceIssueService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Protocol\{ProtocolPdfRenderer, ProtocolService};
use App\Services\ServiceTicket\ServiceTicketService;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-718 (Vollscan J11): Lifecycle-Webhooks an den Service-Schreibstellen
 * der Statusübergänge — Übergang löst genau eine Zustellung aus (Queue-Fake),
 * deaktivierte/nicht abonnierte Endpunkte bleiben still, Payload trägt Sqid +
 * Minimalfelder.
 */
final class LifecycleWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        Queue::fake();
    }

    private function subscribe(WebhookEvent $event, bool $disabled = false): WebhookEndpoint {
        $factory = WebhookEndpoint::factory()->subscribedTo([$event]);

        return ($disabled ? $factory->disabled() : $factory)->create(['organization_id' => $this->organization->id]);
    }

    /** @return array<string, mixed> */
    private function pushedPayload(): array {
        $payload = [];
        Queue::assertPushed(WebhookDeliveryJob::class, function (WebhookDeliveryJob $job) use (&$payload): bool {
            $body = $job->body;
            $decoded = JsonHelper::decode($body);
            $payload = is_array($decoded) ? $decoded : [];

            return true;
        });

        return $payload;
    }

    private function draftInvoice(): Invoice {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'country' => 'DE']);

        return Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'R2026-' . random_int(1000, 9999),
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
        ]);
    }

    public function test_invoice_issue_publishes_invoice_issued(): void {
        $this->subscribe(WebhookEvent::InvoiceIssued);
        $invoice = $this->draftInvoice();

        app(InvoiceIssueService::class)->issue($invoice);

        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->sole();
        $this->assertSame(WebhookEvent::InvoiceIssued->value, $delivery->event);
        $payload = $this->pushedPayload();
        $this->assertSame('invoice.issued', $payload['event']);
        $this->assertSame($invoice->sqid, $payload['data']['subject_id']);
        $this->assertSame('issued', $payload['data']['status']);
        $this->assertArrayNotHasKey('notes', $payload['data']);
    }

    public function test_invoice_paid_transition_publishes_from_every_write_site(): void {
        $this->subscribe(WebhookEvent::InvoicePaid);
        $invoice = $this->draftInvoice();
        $invoice->update(['status' => Invoice::STATUS_ISSUED, 'issued_on' => now()]);
        $this->assertSame(0, WebhookDelivery::query()->withoutGlobalScopes()->count(), 'issued ist nicht paid');

        $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_on' => now()]);

        $this->assertSame(1, WebhookDelivery::query()->withoutGlobalScopes()->where('event', 'invoice.paid')->count());
        $invoice->update(['notes' => 'nachträglich']);
        $this->assertSame(1, WebhookDelivery::query()->withoutGlobalScopes()->count(), 'nur der Statuswechsel feuert');
    }

    public function test_timesheet_submit_publishes_timesheet_submitted(): void {
        $this->subscribe(WebhookEvent::TimesheetSubmitted);
        $timesheet = Timesheet::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'work_date' => now()->toDateString(),
            'status' => TimesheetStatus::Draft->value,
        ]);

        $timesheet->update(['status' => TimesheetStatus::Submitted->value]);

        $payload = $this->pushedPayload();
        $this->assertSame('timesheet.submitted', $payload['event']);
        $this->assertSame($timesheet->sqid, $payload['data']['subject_id']);
        $this->assertSame('submitted', $payload['data']['status']);
    }

    public function test_ticket_create_and_close_publish(): void {
        $this->subscribe(WebhookEvent::TicketCreated);
        $this->subscribe(WebhookEvent::TicketClosed);
        $service = app(ServiceTicketService::class);

        $ticket = $service->create($this->organization, $this->admin, ['title' => 'Drucker offline']);
        $this->assertSame(1, WebhookDelivery::query()->withoutGlobalScopes()->where('event', 'ticket.created')->count());

        $ticket->forceFill(['status' => ServiceTicketStatus::Done->value, 'resolved_at' => now()])->save();
        $service->transition($ticket, $this->admin, ServiceTicketStatus::Closed);

        $this->assertSame(1, WebhookDelivery::query()->withoutGlobalScopes()->where('event', 'ticket.closed')->count());
        Queue::assertPushed(WebhookDeliveryJob::class, 2);
    }

    public function test_protocol_sign_publishes_protocol_signed(): void {
        $this->subscribe(WebhookEvent::ProtocolSigned);
        $renderer = $this->mock(ProtocolPdfRenderer::class);
        $renderer->shouldReceive('render')->andReturn('protocols/fake.pdf');
        $renderer->shouldReceive('hashFor')->andReturn(str_repeat('c', 64));
        $protocol = Protocol::factory()->inReview()->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $this->admin->id]);

        $signed = app(ProtocolService::class)->sign($protocol, $this->admin);

        $payload = $this->pushedPayload();
        $this->assertSame('protocol.signed', $payload['event']);
        $this->assertSame($signed->sqid, $payload['data']['subject_id']);
        $this->assertSame('signed', $payload['data']['status']);
    }

    public function test_purchase_order_submit_publishes_purchase_order_ordered(): void {
        $this->subscribe(WebhookEvent::PurchaseOrderOrdered);
        $service = app(PurchaseOrderService::class);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $order = $service->createDraft($this->organization, $supplier, $warehouse);
        $service->addLine($order, Article::factory()->create(['organization_id' => $this->organization->id]), '2');
        $this->assertSame(0, WebhookDelivery::query()->withoutGlobalScopes()->count());

        $service->submit($order);

        $payload = $this->pushedPayload();
        $this->assertSame('purchaseOrder.ordered', $payload['event']);
        $this->assertSame($order->sqid, $payload['data']['subject_id']);
        $this->assertSame($supplier->sqid, $payload['data']['supplier_id']);
    }

    public function test_disabled_or_unsubscribed_endpoint_receives_nothing(): void {
        $this->subscribe(WebhookEvent::InvoiceIssued, disabled: true);
        $this->subscribe(WebhookEvent::TicketCreated); // anderes Ereignis abonniert

        app(InvoiceIssueService::class)->issue($this->draftInvoice());

        $this->assertSame(0, WebhookDelivery::query()->withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }

    public function test_lifecycle_events_are_part_of_hook_catalog_with_labels(): void {
        foreach ([WebhookEvent::InvoiceIssued, WebhookEvent::InvoicePaid, WebhookEvent::TimesheetSubmitted, WebhookEvent::TicketCreated, WebhookEvent::TicketClosed, WebhookEvent::ProtocolSigned, WebhookEvent::PurchaseOrderOrdered] as $event) {
            $this->assertTrue($event->isLifecycle());
            $this->assertNull($event->source());
            $this->assertNotSame('integration.webhook.event.' . $event->value, $event->label(), $event->value . ' braucht ein Label');
            $this->assertArrayHasKey('subject_id', $event->sampleData());
            $this->assertNotSame('', $event->icon());
        }
    }
}
