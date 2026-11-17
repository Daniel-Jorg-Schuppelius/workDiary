<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignedTaskOffboardingHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\Offboarding;

use App\Enums\Task\TaskStatus;
use App\Models\Platform\User;
use App\Models\Project\Task;
use App\Models\Scopes\OrganizationScope;
use App\Services\Org\Contracts\OffboardingHandover;
use Illuminate\Database\Eloquent\Builder;

/**
 * Offene Aufgaben der Person an eine Nachfolge (Feature 202, MVP-1648; auch
 * beim Austritt): Die Person wird unter den Bearbeitern durch die Nachfolge
 * ersetzt, die übrigen Bearbeiter bleiben; war sie Hauptbearbeiter, ist es
 * danach die Nachfolge.
 */
final class AssignedTaskOffboardingHandover implements OffboardingHandover {
    public function key(): string {
        return 'assigned_tasks';
    }

    public function label(int $count): string {
        return trans_choice('delegation.handover.area.assigned_tasks', $count, ['count' => $count]);
    }

    public function count(User $member): int {
        return $this->assigned($member)->count();
    }

    public function handOver(User $member, User $successor, User $actor): int {
        $moved = 0;
        foreach ($this->assigned($member)->orderBy('id')->get() as $task) {
            $primary = $task->assigned_to !== null ? (int) $task->assigned_to : null;
            $ordered = array_values(array_unique(array_filter([$primary, ...array_map('intval', $task->assignees()->pluck('users.id')->all())])));
            $replaced = array_map(static fn (int $id): int => $id === (int) $member->id ? (int) $successor->id : $id, $ordered);
            $task->syncAssignees(array_values(array_unique($replaced)));
            $moved++;
        }

        return $moved;
    }

    /** @return Builder<Task> */
    private function assigned(User $member): Builder {
        return Task::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $member->organization_id)
            ->where('status', '!=', TaskStatus::Done)
            ->whereNull('archived_at')
            ->where(static fn (Builder $q) => $q->where('assigned_to', $member->id)
                ->orWhereHas('assignees', static fn (Builder $a) => $a->whereKey($member->id)));
    }
}
