<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketTimelineService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Enums\ServiceTicket\{ServiceTicketStatus, TicketMessageKind};
use App\Http\Controllers\AttachmentController;
use App\Models\{Attachment, AuditLog, ServiceTicket, ServiceTicketMessage, SlaClockSegment, SlaViolation, User};

/**
 * Ticket-Timeline (Feature 065, MVP-152): aggregiert read-only und on demand
 * Konversation, Status-/Zuordnungs-Audits, SLA-Ereignisse und Anhänge eines
 * Service-Tickets — pro Quelle genau EINE Query, danach mergen + sortieren.
 * Gleiche Bauart wie {@see DiaryEntryTimelineService}.
 *
 * Leak-Schutz: {@see self::forCustomer()} liefert ausschließlich
 * public_reply-/system_event-Nachrichten und kundensichtbare Anhänge —
 * interne Notizen, Audits und SLA-Interna erreichen das Portal strukturell nie.
 */
class ServiceTicketTimelineService {
    /** Kanonische Filtergruppen (Ereignistypen) der Ticket-Timeline. */
    public const TYPES = [
        'message',
        'status',
        'sla',
        'attachment',
    ];

    /**
     * Fachliche Audit-Ereignisse fürs Status-Band; generische
     * created/updated-Logs bleiben bewusst draußen (Rauschen).
     */
    private const AUDIT_EVENTS = [
        'service_ticket.status_changed',
        'service_ticket.assigned',
        'service_ticket.waiting',
        'service_ticket.resumed',
        'service_ticket.reopened',
        'service_ticket.sla_attached',
    ];

    /**
     * @param  list<string>|null  $types  Filtergruppen aus {@see self::TYPES} (null/leer = alle)
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public function forTicket(ServiceTicket $ticket, ?array $types = null, int $limit = 50, int $offset = 0): array {
        $types = array_values(array_intersect($types ?? [], self::TYPES)) ?: self::TYPES;
        $limit = max(1, min(500, $limit));
        // Pro Quelle reicht offset+limit+1: mehr Einträge einer Quelle können
        // nach dem Merge nie auf der Seite landen; +1 für den hasMore-Blick.
        $cap = $offset + $limit + 1;

        $items = [];
        $sources = [
            'message' => fn(): array => $this->messageItems($ticket, $cap),
            'status' => fn(): array => $this->statusItems($ticket, $cap),
            'sla' => fn(): array => $this->slaItems($ticket, $cap),
            'attachment' => fn(): array => $this->attachmentItems($ticket, $cap),
        ];

        foreach ($sources as $type => $loader) {
            if (in_array($type, $types, true)) {
                $items = array_merge($items, $loader());
            }
        }

        return self::sortAndSlice($items, $limit, $offset);
    }

    /**
     * Portal-Variante (Leak-Schutz, Typfrage statt Filter-Flag): nur
     * public_reply-/system_event-Nachrichten und kundensichtbare Anhänge —
     * ohne Audits, SLA-Interna oder interne Notizen.
     *
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public function forCustomer(ServiceTicket $ticket, int $limit = 100): array {
        $limit = max(1, min(500, $limit));
        $cap = $limit + 1;

        $items = array_merge(
            $this->messageItems($ticket, $cap, customerView: true),
            $this->attachmentItems($ticket, $cap, customerView: true),
        );

        return self::sortAndSlice($items, $limit, 0);
    }

    /**
     * Mergen + chronologisch absteigend sortieren + paginieren. Bei identischen
     * Zeitstempeln entscheidet die numerische Kennung (Sekundärsortierung id) —
     * strcmp würde „message:10“ vor „message:9“ einsortieren.
     *
     * @param  list<TimelineItem>  $items
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public static function sortAndSlice(array $items, int $limit, int $offset = 0): array {
        usort($items, static function (TimelineItem $a, TimelineItem $b): int {
            $left = $b->occurredAt?->getTimestamp() ?? PHP_INT_MIN;
            $right = $a->occurredAt?->getTimestamp() ?? PHP_INT_MIN;
            if ($left !== $right) {
                return $left <=> $right;
            }

            [$prefixA, $numA] = self::splitId($a->id);
            [$prefixB, $numB] = self::splitId($b->id);
            if ($prefixA === $prefixB && $numA !== null && $numB !== null) {
                return $numB <=> $numA; // neuere Datensätze zuerst
            }

            return strcmp($a->id, $b->id);
        });

        return [
            'items' => array_slice($items, $offset, $limit),
            'hasMore' => count($items) > $offset + $limit,
        ];
    }

    /** @return array{0: string, 1: int|null} Quelle + numerischer Teil der Item-Kennung. */
    private static function splitId(string $id): array {
        $pos = strrpos($id, ':');
        if ($pos === false) {
            return [$id, null];
        }
        $suffix = substr($id, $pos + 1);

        return [substr($id, 0, $pos), ctype_digit($suffix) ? (int) $suffix : null];
    }

    /** @return list<TimelineItem> */
    private function messageItems(ServiceTicket $ticket, int $cap, bool $customerView = false): array {
        $query = ServiceTicketMessage::query()
            ->where('service_ticket_id', $ticket->id)
            ->with('author')
            ->latest('created_at')
            ->latest('id')
            ->limit($cap);

        if ($customerView) {
            // Typfrage: interne Notizen erreichen das Portal strukturell nie.
            $query->whereIn('kind', [TicketMessageKind::PublicReply->value, TicketMessageKind::SystemEvent->value]);
        }

        $items = [];
        foreach ($query->get() as $message) {
            $items[] = new TimelineItem(
                id: 'message:' . $message->id,
                type: 'message',
                icon: match ($message->kind) {
                    TicketMessageKind::InternalNote => 'sticky_note_2',
                    TicketMessageKind::SystemEvent => 'info',
                    default => 'reply',
                },
                occurredAt: $message->created_at,
                actor: $message->author?->getAttribute('name'),
                title: $message->kind->label(),
                summary: trim(($message->subject !== null && $message->subject !== '' ? $message->subject . "\n" : '') . $message->body),
                visibility: $message->kind->isCustomerVisible()
                    ? TimelineItem::VISIBILITY_CUSTOMER
                    : TimelineItem::VISIBILITY_INTERNAL,
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function statusItems(ServiceTicket $ticket, int $cap): array {
        $logs = AuditLog::query()
            ->where('auditable_type', $ticket->getMorphClass())
            ->where('auditable_id', $ticket->getKey())
            ->whereIn('event', self::AUDIT_EVENTS)
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit($cap)
            ->get();

        // Zuweisungs-Logs tragen nur User-IDs — Namen in EINER Query nachladen.
        $assigneeIds = [];
        foreach ($logs as $log) {
            if ($log->event === 'service_ticket.assigned') {
                $to = data_get((array) $log->getAttribute('changes'), 'to');
                if ($to !== null) {
                    $assigneeIds[] = (int) $to;
                }
            }
        }
        $assigneeNames = $assigneeIds === []
            ? collect()
            : User::query()->whereIn('id', array_unique($assigneeIds))->pluck('name', 'id');

        $items = [];
        foreach ($logs as $log) {
            $changes = (array) $log->getAttribute('changes');
            [$icon, $title, $summary] = match ($log->event) {
                'service_ticket.status_changed' => [
                    'flag',
                    (string) __('Status geändert'),
                    $this->statusLabel(data_get($changes, 'from')) . ' → ' . $this->statusLabel(data_get($changes, 'to')),
                ],
                'service_ticket.assigned' => [
                    'person',
                    (string) __('Ticket zugewiesen'),
                    data_get($changes, 'to') !== null
                        ? (string) ($assigneeNames[(int) data_get($changes, 'to')] ?? data_get($changes, 'to'))
                        : (string) __('Nicht zugewiesen'),
                ],
                'service_ticket.waiting' => [
                    'hourglass_empty',
                    (string) __('Ticket wartet'),
                    implode(' — ', array_filter([
                        $this->statusLabel(data_get($changes, 'status')),
                        (string) data_get($changes, 'reason', ''),
                    ], static fn(string $part): bool => $part !== '' && $part !== '—')) ?: null,
                ],
                'service_ticket.resumed' => [
                    'play_circle',
                    (string) __('Bearbeitung fortgesetzt'),
                    null,
                ],
                'service_ticket.reopened' => [
                    'restart_alt',
                    (string) __('Ticket wiedereröffnet'),
                    (string) data_get($changes, 'reason', '') ?: null,
                ],
                default => [
                    'assignment_turned_in',
                    (string) __('SLA zugeordnet'),
                    (string) data_get($changes, 'contract_name', '') ?: null,
                ],
            };

            $items[] = new TimelineItem(
                id: 'audit:' . $log->id,
                type: 'status',
                icon: $icon,
                occurredAt: $log->created_at,
                actor: $log->user?->name,
                title: $title,
                summary: $summary,
            );
        }

        return $items;
    }

    private function statusLabel(mixed $value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return ServiceTicketStatus::tryFrom((string) $value)?->label() ?? (string) $value;
    }

    /** @return list<TimelineItem> */
    private function slaItems(ServiceTicket $ticket, int $cap): array {
        $items = [];

        $violations = SlaViolation::query()
            ->where('service_ticket_id', $ticket->id)
            ->latest('created_at')
            ->latest('id')
            ->limit($cap)
            ->get();
        foreach ($violations as $violation) {
            $items[] = new TimelineItem(
                id: 'sla-violation:' . $violation->id,
                type: 'sla',
                icon: 'warning',
                occurredAt: $violation->breached_at ?? $violation->created_at,
                actor: null,
                title: (string) __('SLA-Verletzung: :kind', ['kind' => $violation->kind->label()]),
                summary: (string) __('sla.overdue_by', ['min' => $violation->overdue_minutes]),
            );
        }

        $segments = SlaClockSegment::query()
            ->where('service_ticket_id', $ticket->id)
            ->latest('paused_from')
            ->latest('id')
            ->limit($cap)
            ->get();
        foreach ($segments as $segment) {
            $items[] = new TimelineItem(
                id: 'sla-pause:' . $segment->id,
                type: 'sla',
                icon: 'pause_circle',
                occurredAt: $segment->paused_from,
                actor: null,
                title: (string) __('SLA-Uhr pausiert'),
                summary: trim($segment->reason . ($segment->paused_to !== null
                    ? ' — ' . __('fortgesetzt am :date', ['date' => $segment->paused_to->translatedFormat('d.m.Y H:i')])
                    : '')),
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function attachmentItems(ServiceTicket $ticket, int $cap, bool $customerView = false): array {
        $messageIds = ServiceTicketMessage::query()
            ->where('service_ticket_id', $ticket->id)
            ->select('id');

        // Anhänge hängen am Ticket selbst (attachments.store) ODER an einer
        // Konversationsnachricht (Composer/Portal/Mail) — beide Morph-Quellen.
        $query = Attachment::query()
            ->where(function ($q) use ($ticket, $messageIds): void {
                $q->where(fn($sub) => $sub->where('attachable_type', $ticket->getMorphClass())->where('attachable_id', $ticket->getKey()))
                    ->orWhere(fn($sub) => $sub->where('attachable_type', (new ServiceTicketMessage())->getMorphClass())->whereIn('attachable_id', $messageIds));
            })
            ->with('uploader:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit($cap);

        if ($customerView) {
            // Nur explizit freigegebene Anhänge; Anhänge interner Notizen sind
            // zusätzlich über die Nachricht ausgeschlossen (doppelter Riegel).
            $internalMessageIds = ServiceTicketMessage::query()
                ->where('service_ticket_id', $ticket->id)
                ->where('kind', TicketMessageKind::InternalNote->value)
                ->select('id');
            $query->where('customer_visible', true)
                ->whereNot(fn($q) => $q->where('attachable_type', (new ServiceTicketMessage())->getMorphClass())->whereIn('attachable_id', $internalMessageIds));
        }

        $items = [];
        foreach ($query->get() as $attachment) {
            $items[] = new TimelineItem(
                id: 'attachment:' . $attachment->id,
                type: 'attachment',
                icon: 'attach_file',
                occurredAt: $attachment->created_at,
                actor: $attachment->uploader?->name,
                title: (string) __('timeline.event.attachment_added'),
                summary: $attachment->original_name . ' (' . $attachment->humanSize() . ')',
                // Portal: Download über den kunden-gescopten Ticket-Endpunkt
                // (W5.1); intern: signierter Attachment-Link wie gehabt.
                url: $customerView
                    ? route('customer.tickets.attachments.download', ['ticket' => $ticket, 'attachment' => $attachment])
                    : AttachmentController::downloadUrl($attachment),
                visibility: $attachment->customer_visible
                    ? TimelineItem::VISIBILITY_CUSTOMER
                    : TimelineItem::VISIBILITY_INTERNAL,
            );
        }

        return $items;
    }
}
