<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<MilestoneFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'project_id',
        'created_by',
        'title',
        'description',
        'due_date',
        'is_completed',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_completed' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function statusLabel(): string
    {
        return $this->is_completed ? __('Erledigt') : __('Offen');
    }

    public function statusTone(): string
    {
        return $this->is_completed ? 'success' : 'neutral';
    }
}
