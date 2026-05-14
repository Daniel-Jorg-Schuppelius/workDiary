<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    public const KIND_WORK = 'work';

    public const KIND_TRAVEL = 'travel';

    public const KIND_STANDBY = 'standby';

    /** @var array<int, string> */
    public const KINDS = [self::KIND_WORK, self::KIND_TRAVEL, self::KIND_STANDBY];

    protected $fillable = [
        'organization_id',
        'project_id',
        'timesheet_id',
        'task_id',
        'user_id',
        'date',
        'started_at',
        'ended_at',
        'break_minutes',
        'kind',
        'minutes',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'break_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TimeEntry $entry): void {
            if ($entry->started_at && $entry->ended_at) {
                $diff = (int) $entry->started_at->diffInMinutes($entry->ended_at, false);
                $diff = max(0, $diff - (int) ($entry->break_minutes ?? 0));
                $entry->minutes = $diff;
                if (! $entry->date) {
                    $entry->date = $entry->started_at->copy()->startOfDay();
                }
            }
        });
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Timesheet, $this> */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hoursFormatted(): string
    {
        $h = intdiv($this->minutes, 60);
        $m = $this->minutes % 60;

        return sprintf('%d:%02d h', $h, $m);
    }
}
