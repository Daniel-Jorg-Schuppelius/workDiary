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

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    /** @var array<int, string> */
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_DONE];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    /** @var array<int, string> */
    public const PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT];

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
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Milestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /** @return HasMany<Task, $this> */
    public function subTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => __('Offen'),
            self::STATUS_IN_PROGRESS => __('In Arbeit'),
            self::STATUS_DONE => __('Erledigt'),
            default => $this->status,
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'neutral',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_DONE => 'success',
            default => 'ghost',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => __('Niedrig'),
            self::PRIORITY_MEDIUM => __('Mittel'),
            self::PRIORITY_HIGH => __('Hoch'),
            self::PRIORITY_URGENT => __('Dringend'),
            default => $this->priority,
        };
    }

    public function priorityTone(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'ghost',
            self::PRIORITY_MEDIUM => 'info',
            self::PRIORITY_HIGH => 'warning',
            self::PRIORITY_URGENT => 'error',
            default => 'ghost',
        };
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => '#94a3b8',
            self::PRIORITY_MEDIUM => '#3b82f6',
            self::PRIORITY_HIGH => '#f59e0b',
            self::PRIORITY_URGENT => '#ef4444',
            default => '#94a3b8',
        };
    }
}
