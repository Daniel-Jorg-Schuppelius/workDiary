<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\RateCalculator;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $project_id
 * @property int|null $timesheet_id
 * @property int|null $task_id
 * @property int|null $user_id
 * @property Carbon|null $date
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int $break_minutes
 * @property string $kind
 * @property int $minutes
 * @property string|null $description
 * @property bool $billable
 * @property float|null $hourly_rate
 * @property float|null $fixed_rate
 * @property float|null $rate
 * @property float|null $internal_rate
 * @property bool $exported
 */
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
        'billable',
        'hourly_rate',
        'fixed_rate',
        'rate',
        'internal_rate',
        'exported',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'break_minutes' => 'integer',
            'billable' => 'boolean',
            'exported' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'fixed_rate' => 'decimal:2',
            'rate' => 'decimal:2',
            'internal_rate' => 'decimal:2',
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

            // Recalculate billing snapshot whenever a relevant field changes.
            if ($entry->isDirty([
                'minutes',
                'billable',
                'hourly_rate',
                'fixed_rate',
                'project_id',
                'task_id',
                'user_id',
            ]) || ! $entry->exists) {
                $calc = app(RateCalculator::class);
                $result = $calc->compute($entry);
                $entry->rate = $result['rate'];
                $entry->internal_rate = $result['internal_rate'];
                if ($entry->hourly_rate === null && $result['hourly_rate'] !== null) {
                    // Snapshot resolved hourly rate so historical entries stay stable.
                    $entry->hourly_rate = $result['hourly_rate'];
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
