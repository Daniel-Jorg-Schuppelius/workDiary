<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryTimelineService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Timeline;

use App\Enums\Diary\Status;
use App\Enums\Protocol\ProtocolEventType;
use App\Models\{AuditLog, CommunicationNote, Customer, DiaryEntry, Document, MaterialUsage, OpenIssueEvent, ProtocolEvent, TimeEntry, User};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Auftrags-Timeline (MVP-010, docs/auftrags-timeline.md): aggregiert read-only
 * und on demand alle relevanten Ereignisse eines Auftrags (DiaryEntry) aus den
 * Originaltabellen — pro Quelle genau EINE Query, danach mergen + sortieren.
 * Gleiche Bauart wie {@see \App\Services\Asset\AssetTimelineService}.
 *
 * Rechte: vertrauliche Kommunikationsnotizen werden über den
 * `visibleTo`-Scope (Logik der CommunicationNotePolicy) ganz weggelassen;
 * Quellen ohne viewAny-Recht des Betrachters entfallen komplett.
 */
class DiaryEntryTimelineService {
    /** Kanonische Filtergruppen (Ereignistypen) der Timeline. */
    public const TYPES = [
        'status',
        'time',
        'comment',
        'attachment',
        'protocol',
        'material',
        'openIssue',
        'communication',
        'document',
    ];

    /**
     * Lebenszyklus-Ereignisse der Protokolle; Item-/Foto-Events bleiben
     * bewusst draußen (Rauschen, vgl. Spec §7 Gruppierung).
     */
    private const PROTOCOL_EVENTS = [
        ProtocolEventType::Created,
        ProtocolEventType::RequestedReview,
        ProtocolEventType::ReturnedToDraft,
        ProtocolEventType::Signed,
        ProtocolEventType::Archived,
        ProtocolEventType::SupersededBy,
    ];

    public function __construct(private readonly FeatureFlagResolver $featureFlags) {}

    /**
     * @param  list<string>|null  $types  Filtergruppen aus {@see self::TYPES} (null/leer = alle)
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public function forDiaryEntry(DiaryEntry $entry, User $viewer, ?array $types = null, int $limit = 50, int $offset = 0): array {
        $types = array_values(array_intersect($types ?? [], self::TYPES)) ?: self::TYPES;
        $limit = max(1, min(500, $limit));
        // Pro Quelle reicht offset+limit+1: mehr Einträge einer Quelle können
        // nach dem Merge nie auf der Seite landen; +1 für den hasMore-Blick.
        $cap = $offset + $limit + 1;

        $items = [];
        $sources = [
            'status' => fn(): array => $this->statusItems($entry, $cap),
            'time' => fn(): array => $this->timeItems($entry, $cap),
            'comment' => fn(): array => $this->commentItems($entry, $cap),
            'attachment' => fn(): array => $this->attachmentItems($entry, $cap),
            'protocol' => fn(): array => $this->protocolItems($entry, $cap),
            'material' => fn(): array => $this->materialItems($entry, $cap),
            'openIssue' => fn(): array => $this->openIssueItems($entry, $cap),
            'communication' => fn(): array => $this->communicationItems($entry, $viewer, $cap),
            'document' => fn(): array => $this->documentItems($entry, $viewer, $cap),
        ];

        foreach ($sources as $type => $loader) {
            if (in_array($type, $types, true)) {
                $items = array_merge($items, $loader());
            }
        }

        return self::sortAndSlice($items, $limit, $offset);
    }

    /**
     * Kunden-Timeline light (Verlaufs-Karte auf der Kunden-Detailseite):
     * Aufträge angelegt/abgeschlossen, Kommunikationsnotizen und Dokumente
     * über die Aufträge des Kunden hinweg.
     *
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public function forCustomer(Customer $customer, User $viewer, int $limit = 15): array {
        $cap = $limit + 1;
        $entryIds = DiaryEntry::query()->where('customer_id', $customer->id)->select('id');

        $items = [];

        foreach (DiaryEntry::query()->where('customer_id', $customer->id)->with('user:id,name')->latest('created_at')->limit($cap)->get() as $entry) {
            $items[] = new TimelineItem(
                id: 'order:' . $entry->id,
                type: 'status',
                icon: 'add_circle',
                occurredAt: $entry->created_at,
                actor: $entry->user?->name,
                title: (string) __('timeline.event.order_created'),
                summary: $entry->title ?: Str::limit((string) $entry->content, 80),
                url: route('diary.show', $entry),
                visibility: TimelineItem::VISIBILITY_CUSTOMER,
            );
        }

        foreach (DiaryEntry::query()->where('customer_id', $customer->id)->where('status', Status::Done->value)->with('user:id,name')->latest('updated_at')->limit($cap)->get() as $entry) {
            $items[] = new TimelineItem(
                id: 'order-done:' . $entry->id,
                type: 'status',
                icon: 'task_alt',
                occurredAt: $entry->end_at ?? $entry->updated_at,
                actor: $entry->user?->name,
                title: (string) __('timeline.event.order_completed'),
                summary: $entry->title ?: Str::limit((string) $entry->content, 80),
                url: route('diary.show', $entry),
                visibility: TimelineItem::VISIBILITY_CUSTOMER,
            );
        }

        if (Gate::forUser($viewer)->allows('viewAny', CommunicationNote::class)) {
            $notes = CommunicationNote::query()
                ->where(function ($q) use ($customer, $entryIds): void {
                    $q->where(fn($sub) => $sub->where('notable_type', Customer::class)->where('notable_id', $customer->id))
                        ->orWhere(fn($sub) => $sub->where('notable_type', DiaryEntry::class)->whereIn('notable_id', $entryIds));
                })
                ->visibleTo($viewer)
                ->with('creator:id,name')
                ->latest('occurred_at')
                ->limit($cap)
                ->get();

            foreach ($notes as $note) {
                $items[] = $this->communicationItem($note, route('customers.show', $customer) . '#communication-notes');
            }
        }

        if ($this->canSeeDocuments($viewer)) {
            $documents = Document::query()
                ->where(function ($q) use ($customer, $entryIds): void {
                    $q->where(fn($sub) => $sub->where('documentable_type', Customer::class)->where('documentable_id', $customer->id))
                        ->orWhere(fn($sub) => $sub->where('documentable_type', DiaryEntry::class)->whereIn('documentable_id', $entryIds));
                })
                ->with('creator:id,name')
                ->latest('created_at')
                ->limit($cap)
                ->get();

            foreach ($documents as $document) {
                $items[] = $this->documentItem($document, route('customers.show', $customer) . '#documents');
            }
        }

        return self::sortAndSlice($items, $limit, 0);
    }

    /**
     * Mergen + chronologisch absteigend sortieren + paginieren.
     * Öffentlich (statisch, pure) für den Unit-Test des Merge-/Sortier-Kerns.
     *
     * @param  list<TimelineItem>  $items
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public static function sortAndSlice(array $items, int $limit, int $offset = 0): array {
        usort($items, static function (TimelineItem $a, TimelineItem $b): int {
            // null-Zeitpunkte ans Ende, danach stabile Sekundärsortierung über die Kennung.
            $left = $b->occurredAt?->getTimestamp() ?? PHP_INT_MIN;
            $right = $a->occurredAt?->getTimestamp() ?? PHP_INT_MIN;

            return $left <=> $right ?: strcmp($a->id, $b->id);
        });

        return [
            'items' => array_slice($items, $offset, $limit),
            'hasMore' => count($items) > $offset + $limit,
        ];
    }

    /** @return list<TimelineItem> */
    private function statusItems(DiaryEntry $entry, int $cap): array {
        // Statuswechsel stecken in den generischen Auditable-Events
        // (`updated` mit before/after.status); `created` liefert die Anlage.
        $logs = AuditLog::query()
            ->where('auditable_type', $entry->getMorphClass())
            ->where('auditable_id', $entry->getKey())
            ->whereIn('event', ['created', 'updated'])
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            // großzügiger Puffer: updated-Logs ohne Statuswechsel werden unten verworfen
            ->limit(max(200, $cap * 4))
            ->get();

        $items = [];
        foreach ($logs as $log) {
            $changes = (array) $log->getAttribute('changes');

            if ($log->event === 'created') {
                $items[] = new TimelineItem(
                    id: 'audit:' . $log->id,
                    type: 'status',
                    icon: 'add_circle',
                    occurredAt: $log->created_at,
                    actor: $log->user?->name,
                    title: (string) __('timeline.event.order_created'),
                    summary: null,
                    url: route('diary.show', $entry),
                    visibility: TimelineItem::VISIBILITY_CUSTOMER,
                );

                continue;
            }

            $before = data_get($changes, 'before.status');
            $after = data_get($changes, 'after.status');
            if ($after === null || $before === $after) {
                continue;
            }

            $items[] = new TimelineItem(
                id: 'audit:' . $log->id,
                type: 'status',
                icon: 'flag',
                occurredAt: $log->created_at,
                actor: $log->user?->name,
                title: (string) __('timeline.event.status_changed'),
                summary: $this->statusLabel($before) . ' → ' . $this->statusLabel($after),
                url: route('diary.show', $entry),
                visibility: TimelineItem::VISIBILITY_CUSTOMER,
            );

            if (count($items) >= $cap) {
                break;
            }
        }

        return $items;
    }

    private function statusLabel(mixed $value): string {
        if ($value === null) {
            return '—';
        }

        return Status::tryFrom((int) $value)?->label() ?? (string) $value;
    }

    /** @return list<TimelineItem> */
    private function timeItems(DiaryEntry $entry, int $cap): array {
        $items = [];
        $timeEntries = TimeEntry::query()
            ->where('diary_entry_id', $entry->id)
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit($cap)
            ->get();

        foreach ($timeEntries as $timeEntry) {
            $duration = sprintf('%d:%02d h', intdiv((int) $timeEntry->minutes, 60), ((int) $timeEntry->minutes) % 60);
            $items[] = new TimelineItem(
                id: 'time:' . $timeEntry->id,
                type: 'time',
                icon: 'schedule',
                occurredAt: $timeEntry->created_at,
                actor: $timeEntry->user?->name,
                title: (string) __('timeline.event.time_entry_added'),
                summary: trim($duration . ($timeEntry->description ? ' — ' . $timeEntry->description : '')),
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function commentItems(DiaryEntry $entry, int $cap): array {
        $items = [];
        $comments = $entry->comments()
            ->reorder()
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit($cap)
            ->get();

        foreach ($comments as $comment) {
            $items[] = new TimelineItem(
                id: 'comment:' . $comment->id,
                type: 'comment',
                icon: 'chat_bubble',
                occurredAt: $comment->created_at,
                actor: $comment->user?->name,
                title: (string) __('timeline.event.comment_added'),
                summary: Str::limit((string) $comment->body, 120),
                url: route('diary.show', $entry) . '#comments',
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function attachmentItems(DiaryEntry $entry, int $cap): array {
        $items = [];
        $attachments = $entry->attachments()
            ->with('uploader:id,name')
            ->latest('created_at')
            ->limit($cap)
            ->get();

        foreach ($attachments as $attachment) {
            $items[] = new TimelineItem(
                id: 'attachment:' . $attachment->id,
                type: 'attachment',
                icon: 'attach_file',
                occurredAt: $attachment->created_at,
                actor: $attachment->uploader?->name,
                title: (string) __('timeline.event.attachment_added'),
                summary: $attachment->original_name,
                url: route('attachments.download', $attachment),
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function protocolItems(DiaryEntry $entry, int $cap): array {
        $items = [];
        $events = ProtocolEvent::query()
            ->whereHas('protocol', function ($q) use ($entry): void {
                $q->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->getKey());
            })
            ->whereIn('event', self::PROTOCOL_EVENTS)
            ->with(['actor:id,name', 'protocol:id,title,visibility'])
            ->latest('created_at')
            ->latest('id')
            ->limit($cap)
            ->get();

        foreach ($events as $event) {
            $items[] = new TimelineItem(
                id: 'protocol-event:' . $event->id,
                type: 'protocol',
                icon: match ($event->event) {
                    ProtocolEventType::Signed => 'verified',
                    ProtocolEventType::Archived, ProtocolEventType::SupersededBy => 'inventory_2',
                    default => 'description',
                },
                occurredAt: $event->created_at,
                actor: $event->actor?->name,
                title: $this->eventLabel($event->event),
                summary: $event->protocol?->title,
                visibility: $event->protocol?->visibility->value ?? TimelineItem::VISIBILITY_INTERNAL,
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function materialItems(DiaryEntry $entry, int $cap): array {
        $items = [];
        $usages = MaterialUsage::query()
            ->whereIn('timesheet_id', TimeEntry::query()
                ->where('diary_entry_id', $entry->id)
                ->whereNotNull('timesheet_id')
                ->select('timesheet_id'))
            ->latest('created_at')
            ->latest('id')
            ->limit($cap)
            ->get();

        foreach ($usages as $usage) {
            $items[] = new TimelineItem(
                id: 'material:' . $usage->id,
                type: 'material',
                icon: 'package_2',
                occurredAt: $usage->created_at,
                actor: null,
                title: (string) __('timeline.event.material_added'),
                summary: rtrim(rtrim((string) $usage->quantity, '0'), '.') . ' ' . $usage->unit . ' — ' . $usage->description,
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function openIssueItems(DiaryEntry $entry, int $cap): array {
        $items = [];
        $events = OpenIssueEvent::query()
            ->whereHas('openIssue', function ($q) use ($entry): void {
                $q->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->getKey());
            })
            ->with(['actor:id,name', 'openIssue:id,title,visibility'])
            ->latest('created_at')
            ->latest('id')
            ->limit($cap)
            ->get();

        foreach ($events as $event) {
            $items[] = new TimelineItem(
                id: 'issue-event:' . $event->id,
                type: 'openIssue',
                icon: match ($event->event->value) {
                    'issue.completed' => 'check_circle',
                    'issue.created' => 'error_outline',
                    default => 'adjust',
                },
                occurredAt: $event->created_at,
                actor: $event->actor?->name,
                title: $this->eventLabel($event->event->value),
                summary: $event->openIssue?->title,
                url: route('diary.show', $entry) . '#open-issues',
                visibility: $event->openIssue?->visibility->value ?? TimelineItem::VISIBILITY_INTERNAL,
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function communicationItems(DiaryEntry $entry, User $viewer, int $cap): array {
        if (! Gate::forUser($viewer)->allows('viewAny', CommunicationNote::class)) {
            return [];
        }

        $items = [];
        $notes = $entry->communicationNotes()
            ->reorder()
            ->visibleTo($viewer)
            ->with('creator:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->limit($cap)
            ->get();

        foreach ($notes as $note) {
            $items[] = $this->communicationItem($note, route('diary.show', $entry) . '#communication-notes');
        }

        return $items;
    }

    private function communicationItem(CommunicationNote $note, string $url): TimelineItem {
        return new TimelineItem(
            id: 'communication:' . $note->id,
            type: 'communication',
            icon: 'forum',
            occurredAt: $note->occurred_at,
            actor: $note->creator?->name,
            title: (string) __('timeline.event.communication_added'),
            summary: trim($note->type->label() . ' — ' . $note->subject),
            url: $url,
            visibility: $note->visibility->value,
        );
    }

    /** @return list<TimelineItem> */
    private function documentItems(DiaryEntry $entry, User $viewer, int $cap): array {
        if (! $this->canSeeDocuments($viewer)) {
            return [];
        }

        $items = [];
        $documents = Document::query()
            ->where('documentable_type', $entry->getMorphClass())
            ->where('documentable_id', $entry->getKey())
            ->with('creator:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit($cap)
            ->get();

        foreach ($documents as $document) {
            $items[] = $this->documentItem($document, route('diary.show', $entry) . '#documents');
        }

        return $items;
    }

    private function documentItem(Document $document, string $url): TimelineItem {
        return new TimelineItem(
            id: 'document:' . $document->id,
            type: 'document',
            icon: 'folder_open',
            occurredAt: $document->created_at,
            actor: $document->creator?->name,
            title: (string) __('timeline.event.document_linked'),
            summary: $document->title . ' (' . $document->document_type->label() . ')',
            url: $url,
        );
    }

    private function canSeeDocuments(User $viewer): bool {
        return Gate::forUser($viewer)->allows('viewAny', Document::class)
            && $this->featureFlags->isEnabled('module.documents');
    }

    /**
     * Label für fachliche Event-Schlüssel (protocol.*, issue.*) mit Fallback
     * auf den rohen Schlüssel, falls (noch) keine Übersetzung existiert.
     */
    private function eventLabel(string $event): string {
        $key = 'timeline.event.' . $event;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $event;
    }
}
