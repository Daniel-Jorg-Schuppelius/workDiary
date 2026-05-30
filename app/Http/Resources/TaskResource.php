<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\{Milestone, Project, Task, User};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource {
    public function __construct(Task $resource) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'project_id' => Sqid::encodeOrNull(Project::class, $this->project_id),
            'milestone_id' => Sqid::encodeOrNull(Milestone::class, $this->milestone_id),
            'parent_task_id' => Sqid::encodeOrNull(Task::class, $this->parent_task_id),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => Sqid::encodeOrNull(User::class, $this->assigned_to),
            'due_date' => optional($this->due_date)->toDateString(),
            'position' => $this->position,
            'is_global' => (bool) $this->is_global,
            'hourly_rate' => $this->hourly_rate,
            'internal_rate' => $this->internal_rate,
            'time_budget' => $this->time_budget,
            'budget' => $this->budget,
            'budget_type' => $this->budget_type,
            'billable' => (bool) $this->billable,
            'color' => $this->color,
            'archived_at' => optional($this->archived_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
