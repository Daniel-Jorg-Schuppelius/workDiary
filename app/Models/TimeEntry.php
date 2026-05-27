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

use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\Concerns\BelongsToOrganization;
use App\Services\RateCalculator;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $project_id
 * @property int|null $timesheet_id
 * @property int|null $task_id
 * @property int|null $diary_entry_id
 * @property int|null $user_id
 * @property int|null $activity_category_id
 * @property int|null $attendance_id
 * @property int|null $travel_log_id
 * @property Carbon|null $date
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int $break_minutes
 * @property TimeEntryKind $kind
 * @property TimeEntryActivityType $activity_type
 * @property int $minutes
 * @property string|null $description
 * @property bool $billable
 * @property float|null $hourly_rate
 * @property float|null $fixed_rate
 * @property float|null $rate
 * @property float|null $internal_rate
 * @property bool $exported
 */
class TimeEntry extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    /**
     * Liefert ein lokalisiertes Label für einen activity_type-Wert.
     * Akzeptiert sowohl Enum-Cases als auch String-Slugs (Backwards-Compat
     * für Blade-Views, die Raw-Werte aus Aggregaten verarbeiten).
     */
    public static function activityLabel(TimeEntryActivityType|string|null $type): string {
        if ($type instanceof TimeEntryActivityType) {
            return $type->label();
        }
        $value = (string) $type;
        if ($value === '') {
            return (string) __('Unbekannt');
        }
        $enum = TimeEntryActivityType::tryFrom($value);

        return $enum?->label() ?? ucfirst($value);
    }

    // High-level distribution category. When ACTIVITY_PROJECT, project_id
    // must be set. Other values use activity_category_id for reporting.
    protected $fillable = [
        'organization_id',
        'project_id',
        'timesheet_id',
        'task_id',
        'diary_entry_id',
        'user_id',
        'activity_category_id',
        'attendance_id',
        'travel_log_id',
        'date',
        'started_at',
        'ended_at',
        'break_minutes',
        'kind',
        'activity_type',
        'minutes',
        'description',
        'billable',
        'hourly_rate',
        'fixed_rate',
        'rate',
        'internal_rate',
        'exported',
    ];

    /** @var array<string, string> */
    protected $casts = [
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
        'kind' => TimeEntryKind::class,
        'activity_type' => TimeEntryActivityType::class,
    ];

    protected static function booted(): void {
        static::saving(function (TimeEntry $entry): void {
            // Default activity_type from kind / project presence.
            if (empty($entry->activity_type)) {
                $entry->activity_type = match (true) {
                    $entry->project_id !== null => TimeEntryActivityType::Project,
                    $entry->kind === TimeEntryKind::Travel => TimeEntryActivityType::Travel,
                    $entry->kind === TimeEntryKind::Standby => TimeEntryActivityType::Standby,
                    default => TimeEntryActivityType::Admin,
                };
            }

            // Enforce: project_id is required when activity_type=project.
            if ($entry->activity_type === TimeEntryActivityType::Project && $entry->project_id === null) {
                throw new \InvalidArgumentException(
                    'TimeEntry with activity_type=project requires a project_id.'
                );
            }

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
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Timesheet, $this> */
    public function timesheet(): BelongsTo {
        return $this->belongsTo(Timesheet::class);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('created_at');
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ActivityCategory, $this> */
    public function activityCategory(): BelongsTo {
        return $this->belongsTo(ActivityCategory::class);
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<TravelLog, $this> */
    public function travelLog(): BelongsTo {
        return $this->belongsTo(TravelLog::class);
    }

    public function isProjectWork(): bool {
        return $this->activity_type === TimeEntryActivityType::Project;
    }

    public function hoursFormatted(): string {
        $h = intdiv($this->minutes, 60);
        $m = $this->minutes % 60;

        return sprintf('%d:%02d h', $h, $m);
    }
}
