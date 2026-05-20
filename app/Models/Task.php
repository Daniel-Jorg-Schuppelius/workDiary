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

use App\Enums\Task\TaskPriority;
use App\Enums\Task\TaskStatus;
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
        return $this->status->label();
    }

    public function statusTone(): string
    {
        return $this->status->tone();
    }

    public function priorityLabel(): string
    {
        return $this->priority->label();
    }

    public function priorityTone(): string
    {
        return $this->priority->tone();
    }

    public function priorityColor(): string
    {
        return $this->priority->color();
    }
}
