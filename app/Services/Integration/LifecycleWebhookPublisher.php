<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LifecycleWebhookPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\Integration\WebhookEvent;
use App\Models\{Customer, Invoice, Project, Protocol, PurchaseOrder, ServiceTicket, Supplier, Timesheet};
use App\Support\Sqid;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Veröffentlicht Lifecycle-Ereignisse der Kernobjekte (MVP-718, Vollscan J11)
 * als ausgehende Webhooks. Die Aufrufe hängen an den Service-Schreibstellen
 * der Statusübergänge (InvoiceIssueService, ServiceTicketService,
 * ProtocolService, PurchaseOrderService) bzw. — wo es keine einzelne
 * Service-Schreibstelle gibt (Rechnung bezahlt: Bankabgleich, Kassenbuch,
 * Retainer, Web-Aktion; Stundenzettel eingereicht: Web + API) — am
 * Modell-Statuswechsel (Invoice::booted, TimesheetObserver).
 *
 * Payload = Sqid des Subjekts + Minimalfelder; keine Personendaten über
 * das Nötige hinaus. Empfänger reichern über die REST-API an. Ein Fehler hier
 * darf die auslösende Geschäftslogik nie scheitern lassen.
 */
class LifecycleWebhookPublisher {
    public function __construct(private readonly WebhookDispatchService $dispatch) {}

    public function invoiceIssued(Invoice $invoice): void {
        $this->publish(WebhookEvent::InvoiceIssued, $invoice->organization_id, $this->invoiceData($invoice) + [
            'issued_on' => $invoice->issued_on?->toDateString(),
            'due_on' => $invoice->due_on?->toDateString(),
        ]);
    }

    public function invoicePaid(Invoice $invoice): void {
        $this->publish(WebhookEvent::InvoicePaid, $invoice->organization_id, $this->invoiceData($invoice) + [
            'paid_on' => $invoice->paid_on?->toDateString(),
        ]);
    }

    public function timesheetSubmitted(Timesheet $timesheet): void {
        $this->publish(WebhookEvent::TimesheetSubmitted, $timesheet->organization_id, [
            'subject_type' => 'Timesheet',
            'subject_id' => $timesheet->sqid,
            'project_id' => Sqid::encodeOrNull(Project::class, $timesheet->project_id),
            'work_date' => $timesheet->work_date->toDateString(),
            'status' => $timesheet->status->value,
            'total_minutes' => (int) $timesheet->totals_minutes,
        ]);
    }

    public function ticketCreated(ServiceTicket $ticket): void {
        $this->publish(WebhookEvent::TicketCreated, $ticket->organization_id, $this->ticketData($ticket) + [
            'priority' => $ticket->priority->value,
            'customer_id' => Sqid::encodeOrNull(Customer::class, $ticket->customer_id),
            'reported_at' => $ticket->reported_at?->toIso8601String(),
        ]);
    }

    public function ticketClosed(ServiceTicket $ticket): void {
        $this->publish(WebhookEvent::TicketClosed, $ticket->organization_id, $this->ticketData($ticket) + [
            'closed_at' => $ticket->closed_at?->toIso8601String(),
        ]);
    }

    public function protocolSigned(Protocol $protocol): void {
        $this->publish(WebhookEvent::ProtocolSigned, $protocol->organization_id, [
            'subject_type' => 'Protocol',
            'subject_id' => $protocol->sqid,
            'type' => $protocol->type->value,
            'title' => $protocol->title,
            'status' => $protocol->status->value,
            'signed_at' => $protocol->signed_at?->toIso8601String(),
        ]);
    }

    public function purchaseOrderOrdered(PurchaseOrder $order): void {
        $this->publish(WebhookEvent::PurchaseOrderOrdered, $order->organization_id, [
            'subject_type' => 'PurchaseOrder',
            'subject_id' => $order->sqid,
            'number' => $order->number,
            'status' => $order->status->value,
            'supplier_id' => Sqid::encodeOrNull(Supplier::class, $order->supplier_id),
            'ordered_at' => $order->ordered_at?->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private function invoiceData(Invoice $invoice): array {
        return [
            'subject_type' => 'Invoice',
            'subject_id' => $invoice->sqid,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'customer_id' => Sqid::encodeOrNull(Customer::class, $invoice->customer_id),
            'total' => $invoice->total?->getAmount(),
            // In-Memory-Modelle ohne gesetzte Währung (DB-Default greift erst nach refresh) → null statt Fehler.
            'currency' => $invoice->getAttribute('currency')?->value,
        ];
    }

    /** @return array<string, mixed> */
    private function ticketData(ServiceTicket $ticket): array {
        return [
            'subject_type' => 'ServiceTicket',
            'subject_id' => $ticket->sqid,
            'ticket_no' => $ticket->ticket_no,
            'title' => $ticket->title,
            'status' => $ticket->status->value,
        ];
    }

    /** @param array<string, mixed> $data */
    private function publish(WebhookEvent $event, ?int $organizationId, array $data): void {
        if ($organizationId === null) {
            return;
        }

        try {
            $this->dispatch->publish($event, $organizationId, $data);
        } catch (Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            Log::warning('webhook: lifecycle publish failed', ['event' => $event->value, 'error' => $e->getMessage()]);
        }
    }
}
