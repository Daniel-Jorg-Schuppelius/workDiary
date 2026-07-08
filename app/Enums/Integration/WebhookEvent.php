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

    public function label(): string {
        return (string) __('integration.webhook.event.' . $this->value);
    }

    /**
     * Das zugrunde liegende, real verdrahtete Benachrichtigungs-Ereignis,
     * über das dieser Webhook ausgelöst wird.
     */
    public function source(): NotificationEvent {
        return match ($this) {
            self::OpenIssueAssigned => NotificationEvent::OpenIssueAssigned,
            self::OpenIssueOverdue => NotificationEvent::OpenIssueOverdue,
            self::SafetyEventReported => NotificationEvent::SafetyCriticalEvent,
            self::IsmsIncidentCritical => NotificationEvent::IsmsIncidentCritical,
            self::TimeCorrectionRequested => NotificationEvent::TimeCorrectionRequested,
            self::MonthClosureSubmitted => NotificationEvent::MonthClosureSubmitted,
            self::SlaBreached => NotificationEvent::SlaBreached,
            self::DocumentExpired => NotificationEvent::DocumentExpired,
        };
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
        return $this->source()->icon();
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
        };
    }
}
