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
use App\Services\Integration\RecordOrigins;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\EntityUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Offene Aufgaben, die dem Nutzer zugewiesen sind (Bearbeiter oder Mitbearbeiter). */
final class TaskWorkItemSource implements WorkItemSource {
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
        $tasks = Task::query()
            ->where('organization_id', $organization->id)
            ->where('status', '!=', TaskStatus::Done)
            ->whereNull('archived_at')
            ->where(static fn ($q) => $q->where('assigned_to', $viewer->id)
                ->orWhereHas('assignees', static fn ($a) => $a->whereKey($viewer->id)))
            ->with('project:id,name')
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
            );
        }
    }
}
