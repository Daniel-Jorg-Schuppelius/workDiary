<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignedEntryWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\WorkQueue;

use App\Enums\Diary\Status;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Services\Delegation\Contracts\DelegableWork;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Nicht abgeschlossene Aufträge, die dem Nutzer zugewiesen sind. Zugleich
 * delegierbare Arbeit (Feature 202): die Vertretung sieht sie im Arbeitsvorrat.
 * Abarbeitungsreihenfolge (Feature 198, MVP-1636): Rückfrage, freigegeben, in
 * Arbeit, angenommen, dann der Rest; innerhalb nach Fälligkeit.
 * „Zuletzt bewegt“ (MVP-1670): Änderung am Auftrag (Status, Notiz), letzte
 * Zeitbuchung oder letzter Kommentar — der jüngste Stempel zählt.
 */
final class AssignedEntryWorkItemSource implements DelegableWork, WorkItemSource {
    /** @var array<int, int> Status-Wert → Rang (kleiner zuerst); fehlende zählen wie der Rest des Vorrats (0). */
    private const array WORK_ORDER = [
        Status::ReviewQueried->value => -4,
        Status::ReviewApproved->value => -3,
        Status::InProgress->value => -2,
        Status::Accepted->value => -1,
    ];

    public function key(): string {
        return 'assigned_entry';
    }

    public function label(): string {
        return (string) __('work-queue.source.assigned_entry');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return true;
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $entries = $this->assigned($viewer, (int) $organization->id)
            ->with('customer:id,name')
            // Liegezeit (MVP-1670): letzte Zeitbuchung und letzter Kommentar neben der eigenen Änderung.
            ->withMax('timeEntries', 'created_at')
            ->withMax('comments', 'created_at')
            ->orderBy('due_date')
            ->get()
            ->sortBy(static fn (DiaryEntry $entry): int => self::WORK_ORDER[$entry->status->value] ?? 0)
            ->values();

        foreach ($entries as $entry) {
            if (! $gate->allows('view', $entry)) {
                continue;
            }
            $due = $entry->due_date ?? $entry->planned_at;
            yield new WorkItem(
                source: $this->key(),
                key: $entry->getMorphClass() . ':' . $entry->id,
                title: $entry->title !== null && $entry->title !== '' ? $entry->title : (string) __('work-queue.item.entry_untitled'),
                url: route('diary.show', $entry),
                detail: $entry->customer?->name,
                dueAt: $due !== null ? CarbonImmutable::instance($due) : null,
                personal: true,
                rank: self::WORK_ORDER[$entry->status->value] ?? 0,
                movedAt: WorkItem::lastMoved($entry->updated_at, $entry->getAttribute('time_entries_max_created_at'), $entry->getAttribute('comments_max_created_at')),
            );
        }
    }

    public function count(User $user): int {
        return $this->assigned($user, (int) $user->organization_id)->count();
    }

    /** @return Builder<DiaryEntry> */
    private function assigned(User $user, int $organizationId): Builder {
        return DiaryEntry::query()
            ->where('organization_id', $organizationId)
            ->where('assigned_user_id', $user->id)
            ->notArchived()
            ->open();
    }
}
