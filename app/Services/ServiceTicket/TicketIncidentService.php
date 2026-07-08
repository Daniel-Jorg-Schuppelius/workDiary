<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketIncidentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketPriority, TicketSeverity};
use App\Models\{Organization, ServiceTicket, ServiceTicketLink, User};
use App\Support\Setting;
use Illuminate\Database\Eloquent\Model;

/**
 * Incident-Werkzeuge (Feature 065, MVP-155): Impact×Urgency-Matrix je Org
 * (Setting 'helpdesk.priority_matrix', Default 3×3) — die Matrix liefert
 * einen VORSCHLAG, manueller Override wird auditiert; Major-Incident-
 * Kennzeichnung mit Zeitlinie über system_event-Messages;
 * Ticketverknüpfungen mit harter Tenant-Grenze.
 */
class TicketIncidentService {
    /** Default-Matrix: [impact][urgency] → Priorität (1=low … 3=high). */
    private const DEFAULT_MATRIX = [
        1 => [1 => 'low', 2 => 'low', 3 => 'normal'],
        2 => [1 => 'low', 2 => 'normal', 3 => 'high'],
        3 => [1 => 'normal', 2 => 'high', 3 => 'urgent'],
    ];

    public function suggestPriority(Organization $organization, TicketSeverity $impact, TicketSeverity $urgency): ServiceTicketPriority {
        /** @var array<int|string, array<int|string, string>> $matrix */
        $matrix = (array) Setting::get('helpdesk.priority_matrix', self::DEFAULT_MATRIX);
        $value = (string) ($matrix[$impact->value][$urgency->value]
            ?? self::DEFAULT_MATRIX[$impact->value][$urgency->value]);

        return ServiceTicketPriority::tryFrom($value) ?? ServiceTicketPriority::Normal;
    }

    /**
     * Impact/Urgency setzen: Priorität folgt der Matrix als Vorschlag;
     * ein abweichender manueller Override wird auditiert (DoD).
     */
    public function classify(ServiceTicket $ticket, TicketSeverity $impact, TicketSeverity $urgency, ?ServiceTicketPriority $override = null, ?User $actor = null): ServiceTicket {
        $suggested = $this->suggestPriority($ticket->organization()->firstOrFail(), $impact, $urgency);
        $priority = $override ?? $suggested;

        $ticket->forceFill([
            'impact' => $impact->value,
            'urgency' => $urgency->value,
            'priority' => $priority->value,
        ])->save();

        if ($override !== null && $override !== $suggested) {
            $ticket->audit('service_ticket.priority_overridden', [
                'suggested' => $suggested->value,
                'chosen' => $override->value,
                'actor' => $actor?->id,
            ]);
        }

        return $ticket->refresh();
    }

    /**
     * Major-Incident-Kennzeichnung: Lead ist Pflicht; Beginn/Ende landen
     * als system_event in der Konversation (Zeitlinie = Konversation).
     *
     * @param array<int, string> $stakeholders
     */
    public function markMajor(ServiceTicket $ticket, User $lead, array $stakeholders = [], ?string $commRhythm = null, ?User $actor = null): ServiceTicket {
        $ticket->forceFill([
            'is_major' => true,
            'incident_lead_id' => $lead->id,
            'stakeholders' => array_values($stakeholders),
            'comm_rhythm' => $commRhythm,
        ])->save();

        $ticket->audit('service_ticket.major_marked', ['lead' => $lead->id, 'actor' => $actor?->id]);
        app(TicketConversationService::class)->systemEvent(
            $ticket,
            (string) __('Major Incident ausgerufen — Leitung: :name', ['name' => $lead->name]),
        );

        return $ticket->refresh();
    }

    public function unmarkMajor(ServiceTicket $ticket, ?User $actor = null): ServiceTicket {
        $ticket->forceFill(['is_major' => false])->save();
        $ticket->audit('service_ticket.major_cleared', ['actor' => $actor?->id]);
        app(TicketConversationService::class)->systemEvent($ticket, (string) __('Major-Incident-Status aufgehoben.'));

        return $ticket->refresh();
    }

    /**
     * Verknüpfung mit harter Tenant-Grenze: das Ziel muss zur selben
     * Organisation gehören — sonst 404-äquivalente Exception. Idempotent
     * über den Unique-Index (firstOrCreate).
     */
    public function link(ServiceTicket $ticket, Model $target, string $kind, ?User $actor = null): ServiceTicketLink {
        if (! in_array($kind, ServiceTicketLink::KINDS, true)) {
            throw new \InvalidArgumentException("Unbekannte Verknüpfungsart: {$kind}");
        }
        $targetOrg = (int) $target->getAttribute('organization_id');
        if ($targetOrg !== (int) $ticket->organization_id) {
            throw new \RuntimeException((string) __('Verknüpfung über Organisationsgrenzen ist nicht zulässig.'));
        }
        if ($target instanceof ServiceTicket && (int) $target->id === (int) $ticket->id) {
            throw new \InvalidArgumentException((string) __('Ein Ticket kann nicht mit sich selbst verknüpft werden.'));
        }

        $link = ServiceTicketLink::query()->firstOrCreate([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'linked_type' => $target->getMorphClass(),
            'linked_id' => $target->getKey(),
            'kind' => $kind,
        ]);

        if ($link->wasRecentlyCreated) {
            $ticket->audit('service_ticket.linked', ['kind' => $kind, 'target' => $target->getMorphClass() . ':' . $target->getKey(), 'actor' => $actor?->id]);
        }

        return $link;
    }
}
