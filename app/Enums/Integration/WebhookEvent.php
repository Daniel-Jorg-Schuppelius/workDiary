<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\Notification\NotificationEvent;

/**
 * Kuratierte Teilmenge der fachlichen Domänen-Ereignisse, die als ausgehender
 * Webhook publiziert werden dürfen (Feature 008 — Integrationen & offene API).
 *
 * WICHTIG: Webhooks erfinden KEINE eigene Ereignis-Liste. Jeder Case bindet
 * über {@see self::source()} genau einen real verdrahteten
 * {@see NotificationEvent}. Die Auslösung hängt damit an denselben Stellen,
 * die heute schon Benachrichtigungen feuern (Service-Trigger + Fristen-Scanner),
 * additiv im NotificationDispatcher abgegriffen — ohne Umbau der Geschäftslogik.
 *
 * Bewusst ausgewählt sind stabile, klar definierte Statuswechsel/Ereignisse;
 * personenbezogene oder rein interne Eskalationen bleiben außen vor.
 */
enum WebhookEvent: string implements HasLabel {
    use HasOptions;

    /** Offener Punkt wurde einer Person zugewiesen. Quelle: OpenIssueService::assign(). */
    case OpenIssueAssigned = 'openIssue.assigned';
    /** Offener Punkt ist überfällig. Quelle: Fristen-Scanner. */
    case OpenIssueOverdue = 'openIssue.overdue';
    /** Kritisches Sicherheitsereignis gemeldet (Unfall/critical). Quelle: SafetyEventService::create(). */
    case SafetyEventReported = 'safetyEvent.reported';
    /** Kritischer ISMS-Sicherheitsvorfall. Quelle: SecurityIncidentService::create(). */
    case IsmsIncidentCritical = 'isms.incidentCritical';
    /** Arbeitszeit-Korrekturantrag eingereicht. Quelle: TimeCorrectionService::submit(). */
    case TimeCorrectionRequested = 'timeCorrection.requested';
    /** Monatsabschluss zur Freigabe eingereicht. Quelle: MonthClosureService::submit(). */
    case MonthClosureSubmitted = 'monthClosure.submitted';
    /** SLA-Frist eines Service-Tickets verletzt. Quelle: Fristen-Scanner. */
    case SlaBreached = 'sla.breached';
    /** Dokument/Zertifikat abgelaufen. Quelle: Fristen-Scanner. */
    case DocumentExpired = 'document.expired';

    // ── Lifecycle-Ereignisse (MVP-718, Vollscan J11) ────────────────────────
    // Statusübergänge der Kernobjekte. Sie haben KEIN Benachrichtigungs-
    // Pendant (source() = null) und werden direkt an den Service-Schreibstellen
    // über den LifecycleWebhookPublisher veröffentlicht.
    /** Rechnung ausgestellt (draft → issued). Quelle: InvoiceIssueService::issue(). */
    case InvoiceIssued = 'invoice.issued';
    /** Rechnung vollständig bezahlt. Quelle: Statuswechsel auf paid (Invoice::booted). */
    case InvoicePaid = 'invoice.paid';
    /** Stundenzettel eingereicht (draft → submitted). Quelle: TimesheetObserver. */
    case TimesheetSubmitted = 'timesheet.submitted';
    /** Service-Ticket angelegt. Quelle: ServiceTicketService::create(). */
    case TicketCreated = 'ticket.created';
    /** Service-Ticket geschlossen. Quelle: ServiceTicketService::transition(). */
    case TicketClosed = 'ticket.closed';
    /** Protokoll unterzeichnet/abgeschlossen. Quelle: ProtocolService::sign(). */
    case ProtocolSigned = 'protocol.signed';
    /** Bestellung beim Lieferanten ausgelöst (draft → ordered). Quelle: PurchaseOrderService::submit(). */
    case PurchaseOrderOrdered = 'purchaseOrder.ordered';

    public function label(): string {
        // Die Event-Schlüssel enthalten selbst Punkte (`invoice.issued`) — die
        // Punkt-Notation von __() findet sie im verschachtelten Katalog nicht,
        // daher den Katalog als Array holen und direkt nachschlagen (MVP-718).
        $catalog = __('integration.webhook.event');

        return is_array($catalog) && isset($catalog[$this->value]) ? (string) $catalog[$this->value] : $this->value;
    }

    /**
     * Das zugrunde liegende, real verdrahtete Benachrichtigungs-Ereignis,
     * über das dieser Webhook ausgelöst wird — null für Lifecycle-Ereignisse,
     * die direkt an der Service-Schreibstelle publiziert werden.
     */
    public function source(): ?NotificationEvent {
        return match ($this) {
            self::OpenIssueAssigned => NotificationEvent::OpenIssueAssigned,
            self::OpenIssueOverdue => NotificationEvent::OpenIssueOverdue,
            self::SafetyEventReported => NotificationEvent::SafetyCriticalEvent,
            self::IsmsIncidentCritical => NotificationEvent::IsmsIncidentCritical,
            self::TimeCorrectionRequested => NotificationEvent::TimeCorrectionRequested,
            self::MonthClosureSubmitted => NotificationEvent::MonthClosureSubmitted,
            self::SlaBreached => NotificationEvent::SlaBreached,
            self::DocumentExpired => NotificationEvent::DocumentExpired,
            self::InvoiceIssued, self::InvoicePaid, self::TimesheetSubmitted, self::TicketCreated,
            self::TicketClosed, self::ProtocolSigned, self::PurchaseOrderOrdered => null,
        };
    }

    /** Lifecycle-Ereignis ohne Benachrichtigungs-Pendant (direkte Publikation). */
    public function isLifecycle(): bool {
        return $this->source() === null;
    }

    /**
     * Findet den (höchstens einen) Webhook-Event-Key zu einem gefeuerten
     * NotificationEvent. Liefert null, wenn das Ereignis nicht webhook-fähig ist.
     */
    public static function forSource(NotificationEvent $event): ?self {
        foreach (self::cases() as $case) {
            if ($case->source() === $event) {
                return $case;
            }
        }

        return null;
    }

    /** Material-Symbols-Icon (UI-Liste der abonnierbaren Ereignisse). */
    public function icon(): string {
        return $this->source()?->icon() ?? match ($this) {
            self::InvoiceIssued, self::InvoicePaid => 'receipt_long',
            self::TimesheetSubmitted => 'schedule',
            self::TicketCreated, self::TicketClosed => 'confirmation_number',
            self::ProtocolSigned => 'draw',
            self::PurchaseOrderOrdered => 'shopping_cart',
            default => 'webhook',
        };
    }

    /**
     * Beispiel-`data`-Objekt je Ereignis (Feature 008 → Rang 61). Spiegelt die
     * Struktur eines real publizierten Payloads (subject_type/subject_id/title)
     * und dient dem Event-Katalog (`GET /api/hooks/events`), an dem n8n/Make/
     * Zapier das Schema lernen. Rein illustrativ, keine echten Daten.
     *
     * @return array<string, mixed>
     */
    public function sampleData(): array {
        return match ($this) {
            self::OpenIssueAssigned => ['subject_type' => 'OpenIssue', 'subject_id' => 42, 'title' => 'Aufzug prüfen'],
            self::OpenIssueOverdue => ['subject_type' => 'OpenIssue', 'subject_id' => 42, 'title' => 'Aufzug prüfen'],
            self::SafetyEventReported => ['subject_type' => 'SafetyEvent', 'subject_id' => 7, 'title' => 'Beinaheunfall Halle 2'],
            self::IsmsIncidentCritical => ['subject_type' => 'IsmsIncident', 'subject_id' => 3, 'title' => 'Kritischer Sicherheitsvorfall'],
            self::TimeCorrectionRequested => ['subject_type' => 'TimeCorrectionRequest', 'subject_id' => 11, 'title' => 'Zeitkorrektur 12.06.'],
            self::MonthClosureSubmitted => ['subject_type' => 'MonthClosure', 'subject_id' => 5, 'title' => 'Monatsabschluss Juni 2026'],
            self::SlaBreached => ['subject_type' => 'ServiceTicket', 'subject_id' => 88, 'title' => 'ST-2026-00088'],
            self::DocumentExpired => ['subject_type' => 'Document', 'subject_id' => 15, 'title' => 'Prüfzertifikat abgelaufen'],
            self::InvoiceIssued => ['subject_type' => 'Invoice', 'subject_id' => 'k7Qx2Ab', 'number' => 'R2026-0042', 'status' => 'issued', 'customer_id' => 'Pq9zR1', 'issued_on' => '2026-08-25', 'due_on' => '2026-09-08', 'total' => '1190.00', 'currency' => 'EUR'],
            self::InvoicePaid => ['subject_type' => 'Invoice', 'subject_id' => 'k7Qx2Ab', 'number' => 'R2026-0042', 'status' => 'paid', 'customer_id' => 'Pq9zR1', 'paid_on' => '2026-09-01', 'total' => '1190.00', 'currency' => 'EUR'],
            self::TimesheetSubmitted => ['subject_type' => 'Timesheet', 'subject_id' => 'Tz4m8Q', 'project_id' => 'Wq2Lx9', 'work_date' => '2026-08-25', 'status' => 'submitted', 'total_minutes' => 480],
            self::TicketCreated => ['subject_type' => 'ServiceTicket', 'subject_id' => 'Sx7Kd2', 'ticket_no' => 'ST-2026-00088', 'title' => 'Drucker offline', 'status' => 'reported', 'priority' => 'normal', 'customer_id' => 'Pq9zR1'],
            self::TicketClosed => ['subject_type' => 'ServiceTicket', 'subject_id' => 'Sx7Kd2', 'ticket_no' => 'ST-2026-00088', 'title' => 'Drucker offline', 'status' => 'closed', 'closed_at' => '2026-08-26T09:15:00+02:00'],
            self::ProtocolSigned => ['subject_type' => 'Protocol', 'subject_id' => 'Pr5Vn3', 'type' => 'acceptance', 'title' => 'Abnahme Halle 2', 'status' => 'signed', 'signed_at' => '2026-08-25T16:00:00+02:00'],
            self::PurchaseOrderOrdered => ['subject_type' => 'PurchaseOrder', 'subject_id' => 'Bo3Hf6', 'number' => 'BE-2026-0007', 'status' => 'ordered', 'supplier_id' => 'Lf8Qw1', 'ordered_at' => '2026-08-25T10:30:00+02:00'],
        };
    }
}
