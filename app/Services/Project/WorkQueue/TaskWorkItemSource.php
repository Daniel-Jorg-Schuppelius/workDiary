<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\WorkQueue;

use App\Enums\Task\TaskStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Task;
use App\Services\Delegation\Contracts\DelegableWork;
use App\Services\Integration\RecordOrigins;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\EntityUrl;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Offene Aufgaben, die dem Nutzer zugewiesen sind (Bearbeiter oder
 * Mitbearbeiter). Zugleich delegierbare Arbeit (Feature 202).
 */
final class TaskWorkItemSource implements DelegableWork, WorkItemSource {
    public function __construct(private readonly RecordOrigins $origins) {}

    public function key(): string {
        return 'task';
    }

    public function label(): string {
        return (string) __('work-queue.source.task');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return true;
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $tasks = $this->assigned($viewer, (int) $organization->id)
            ->with('project:id,name')
            // Liegezeit (MVP-1670): letzte Zeitbuchung neben der eigenen Änderung.
            ->withMax('timeEntries', 'created_at')
            ->orderBy('due_date')
            ->get();
        // Übernommene Aufgaben nennen ihr Plugin (Feature 181).
        $origins = $this->origins->labels(Task::class, array_map('intval', $tasks->modelKeys()), (int) $organization->id);

        foreach ($tasks as $task) {
            if (! $gate->allows('view', $task)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $task->getMorphClass() . ':' . $task->id,
                title: $task->title,
                url: $task->project !== null ? EntityUrl::for($task->project) : route('tasks.global.index'),
                detail: implode(' · ', array_filter([$task->project?->name, $origins[$task->id] ?? null])) ?: null,
                dueAt: $task->due_date !== null ? CarbonImmutable::instance($task->due_date) : null,
                personal: true,
                movedAt: WorkItem::lastMoved($task->updated_at, $task->getAttribute('time_entries_max_created_at')),
            );
        }
    }

    public function count(User $user): int {
        return $this->assigned($user, (int) $user->organization_id)->count();
    }

    /** @return Builder<Task> */
    private function assigned(User $user, int $organizationId): Builder {
        return Task::query()
            ->where('organization_id', $organizationId)
            ->where('status', '!=', TaskStatus::Done)
            ->whereNull('archived_at')
            ->where(static fn ($q) => $q->where('assigned_to', $user->id)
                ->orWhereHas('assignees', static fn ($a) => $a->whereKey($user->id)));
    }
}
