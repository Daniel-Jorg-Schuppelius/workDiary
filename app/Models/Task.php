<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Task.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\Concerns\{BelongsToOrganization, HasAttachments, HasSqid};
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $project_id
 * @property int|null $milestone_id
 * @property int|null $parent_task_id
 * @property int|null $created_by
 * @property int|null $assigned_to
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property Carbon|null $due_date
 * @property int|null $position
 * @property string|null $hourly_rate
 * @property string|null $internal_rate
 * @property int|null $time_budget
 * @property string|null $budget
 * @property string|null $budget_type
 * @property bool $billable
 * @property bool $is_global
 * @property string|null $color
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Task extends Model {
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'milestone_id',
        'parent_task_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'position',
        'hourly_rate',
        'internal_rate',
        'time_budget',
        'budget',
        'budget_type',
        'billable',
        'is_global',
        'color',
        'archived_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_date' => 'date',
        'archived_at' => 'datetime',
        'hourly_rate' => 'decimal:2',
        'internal_rate' => 'decimal:2',
        'budget' => 'decimal:2',
        'time_budget' => 'integer',
        'billable' => 'boolean',
        'is_global' => 'boolean',
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Milestone, $this> */
    public function milestone(): BelongsTo {
        return $this->belongsTo(Milestone::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /** @return HasMany<Task, $this> */
    public function subTasks(): HasMany {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    public function statusLabel(): string {
        return $this->status->label();
    }

    public function statusTone(): string {
        return $this->status->tone();
    }

    public function priorityLabel(): string {
        return $this->priority->label();
    }

    public function priorityTone(): string {
        return $this->priority->tone();
    }

    public function priorityColor(): string {
        return $this->priority->color();
    }
}
