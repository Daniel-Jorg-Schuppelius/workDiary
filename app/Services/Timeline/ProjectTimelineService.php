<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimelineService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Models\{CommunicationNote, DiaryEntry, Document, Project, ServiceTicket, User};
use Carbon\CarbonImmutable;

/**
 * Projekt-Timeline / Fallakte je Projekt (MVP-037, Rang 56): aggregiert
 * Aufträge des Projekts plus projektdirekte Objekte (Meilensteine, Dokumente,
 * Kommunikationsnotizen mit visibleTo-Filter, Service-Tickets) zu
 * {@see TimelineItem}s. Read-only; Volumen über Limit/Offset gekappt
 * (Projekte können groß werden).
 */
class ProjectTimelineService {
    private const PER_SOURCE_CAP = 100;

    /**
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public function forProject(Project $project, User $viewer, int $limit = 50, int $offset = 0, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        $items = array_merge(
            $this->entryItems($project),
            $this->milestoneItems($project),
            $this->documentItems($project),
            $this->communicationItems($project, $viewer),
            $this->ticketItems($project),
        );

        if ($from !== null || $to !== null) {
            $items = array_values(array_filter($items, static function (TimelineItem $item) use ($from, $to): bool {
                if ($item->occurredAt === null) {
                    return true;
                }
                if ($from !== null && $item->occurredAt->lessThan($from)) {
                    return false;
                }

                return ! ($to !== null && $item->occurredAt->greaterThan($to));
            }));
        }

        return DiaryEntryTimelineService::sortAndSlice($items, $limit, $offset);
    }

    /** @return list<TimelineItem> */
    private function entryItems(Project $project): array {
        $items = [];
        $entries = DiaryEntry::query()
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_CAP)
            ->get(['id', 'title', 'status', 'start_at', 'created_at', 'completed_at']);

        foreach ($entries as $entry) {
            $items[] = new TimelineItem(
                id: 'diary:' . $entry->id,
                type: 'diary',
                icon: 'assignment',
                occurredAt: $entry->start_at ?? $entry->created_at,
                actor: null,
                title: (string) $entry->title,
                summary: (string) __('Auftrag'),
                url: route('diary.show', $entry),
            );
            if ($entry->completed_at !== null) {
                $items[] = new TimelineItem(
                    id: 'diary-done:' . $entry->id,
                    type: 'diary',
                    icon: 'task_alt',
                    occurredAt: $entry->completed_at,
                    actor: null,
                    title: (string) __('Auftrag abgeschlossen: :title', ['title' => (string) $entry->title]),
                    url: route('diary.show', $entry),
                );
            }
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function milestoneItems(Project $project): array {
        $items = [];
        foreach ($project->milestones()->limit(self::PER_SOURCE_CAP)->get() as $milestone) {
            $items[] = new TimelineItem(
                id: 'milestone:' . $milestone->id,
                type: 'milestone',
                icon: 'flag',
                occurredAt: $milestone->due_date,
                actor: null,
                title: (string) __('Meilenstein: :title', ['title' => (string) $milestone->title]),
                summary: $milestone->is_completed ? (string) __('abgeschlossen') : (string) __('offen'),
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function documentItems(Project $project): array {
        $items = [];
        $documents = Document::query()
            ->where('documentable_type', $project->getMorphClass())
            ->where('documentable_id', $project->id)
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_CAP)
            ->get(['id', 'title', 'created_at']);

        foreach ($documents as $document) {
            $items[] = new TimelineItem(
                id: 'document:' . $document->id,
                type: 'document',
                icon: 'description',
                occurredAt: $document->created_at,
                actor: null,
                title: (string) __('Dokument: :title', ['title' => (string) $document->title]),
                url: route('documents.show', $document),
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function communicationItems(Project $project, User $viewer): array {
        $items = [];
        $notes = CommunicationNote::query()
            ->where('notable_type', $project->getMorphClass())
            ->where('notable_id', $project->id)
            ->visibleTo($viewer)
            ->with('creator:id,name')
            ->orderByDesc('occurred_at')
            ->limit(self::PER_SOURCE_CAP)
            ->get();

        foreach ($notes as $note) {
            $items[] = new TimelineItem(
                id: 'communication:' . $note->id,
                type: 'communication',
                icon: 'forum',
                occurredAt: $note->occurred_at ?? $note->created_at,
                actor: $note->creator->name ?? null,
                title: (string) ($note->subject ?? __('Kommunikationsnotiz')),
            );
        }

        return $items;
    }

    /** @return list<TimelineItem> */
    private function ticketItems(Project $project): array {
        $items = [];
        $tickets = ServiceTicket::query()
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_CAP)
            ->get(['id', 'ticket_no', 'title', 'status', 'created_at']);

        foreach ($tickets as $ticket) {
            $items[] = new TimelineItem(
                id: 'ticket:' . $ticket->id,
                type: 'openIssue',
                icon: 'confirmation_number',
                occurredAt: $ticket->created_at,
                actor: null,
                title: (string) __('Ticket :no: :title', ['no' => (string) $ticket->ticket_no, 'title' => (string) $ticket->title]),
                summary: $ticket->status->label(),
            );
        }

        return $items;
    }
}
