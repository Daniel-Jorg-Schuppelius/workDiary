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

use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Nicht abgeschlossene Aufträge, die dem Nutzer zugewiesen sind. */
final class AssignedEntryWorkItemSource implements WorkItemSource {
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
        $entries = DiaryEntry::query()
            ->where('organization_id', $organization->id)
            ->where('assigned_user_id', $viewer->id)
            ->notArchived()
            ->open()
            ->with('customer:id,name')
            ->orderBy('due_date')
            ->get();

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
            );
        }
    }
}
