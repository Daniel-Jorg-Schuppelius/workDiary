<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model {
    /** @use HasFactory<\Database\Factories\TimeEntryFactory> */
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'project_id',
        'task_id',
        'user_id',
        'date',
        'minutes',
        'description',
    ];

    protected function casts(): array {
        return [
            'date'    => 'date',
            'minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function hoursFormatted(): string {
        $h = intdiv($this->minutes, 60);
        $m = $this->minutes % 60;

        return sprintf('%d:%02d h', $h, $m);
    }
}
