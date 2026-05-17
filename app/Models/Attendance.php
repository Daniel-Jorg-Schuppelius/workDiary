<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Services\Timekeeping\BreakRuleEvaluator;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Authoritative record of an employee's on-the-clock interval.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $date
 * @property int $break_minutes_auto
 * @property int $break_minutes_manual
 * @property int $duration_minutes
 * @property string $source
 * @property string $status
 * @property float|null $started_lat
 * @property float|null $started_lng
 * @property float|null $ended_lat
 * @property float|null $ended_lng
 * @property string|null $started_device
 * @property string|null $ended_device
 * @property string|null $note
 * @property int|null $closed_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read int $break_minutes_total
 */
class Attendance extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    public const SOURCE_CLOCK = 'clock';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_AUTO_CLOSE = 'auto_close';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_AUTO_CLOSED = 'auto_closed';

    public const STATUS_ADJUSTED = 'adjusted';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const SOURCES = [
        self::SOURCE_CLOCK,
        self::SOURCE_MANUAL,
        self::SOURCE_IMPORT,
        self::SOURCE_AUTO_CLOSE,
    ];

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_AUTO_CLOSED,
        self::STATUS_ADJUSTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'started_at',
        'ended_at',
        'date',
        'break_minutes_auto',
        'break_minutes_manual',
        'duration_minutes',
        'source',
        'status',
        'started_lat',
        'started_lng',
        'ended_lat',
        'ended_lng',
        'started_device',
        'ended_device',
        'note',
        'closed_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'date' => 'date',
            'break_minutes_auto' => 'integer',
            'break_minutes_manual' => 'integer',
            'duration_minutes' => 'integer',
            'started_lat' => 'float',
            'started_lng' => 'float',
            'ended_lat' => 'float',
            'ended_lng' => 'float',
        ];
    }

    protected static function booted(): void {
        static::saving(function (Attendance $a): void {
            if (! $a->date && $a->started_at) {
                $a->date = $a->started_at->copy()->startOfDay();
            }
            if ($a->started_at && $a->ended_at) {
                // Apply statutory minimum breaks (ArbZG §4) before computing the
                // net duration so under-recorded breaks are topped up into
                // `break_minutes_auto` once the attendance is closed.
                $eval = app(BreakRuleEvaluator::class);
                if ($eval->autoApplyEnabled()) {
                    $eval->applyMissingBreak($a);
                }

                $gross = (int) $a->started_at->diffInMinutes($a->ended_at, false);
                $breaks = (int) ($a->break_minutes_auto ?? 0)
                    + (int) ($a->break_minutes_manual ?? 0);
                $a->duration_minutes = max(0, $gross - $breaks);
                if (in_array($a->status, [self::STATUS_OPEN], true)) {
                    $a->status = self::STATUS_CLOSED;
                }
            } else {
                $a->duration_minutes = 0;
                if (! $a->status) {
                    $a->status = self::STATUS_OPEN;
                }
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<TravelLog, $this> */
    public function travelLogs(): HasMany {
        return $this->hasMany(TravelLog::class);
    }

    public function isOpen(): bool {
        return $this->ended_at === null;
    }

    /**
     * Localised label for the attendance status (defaults to the current
     * status of the model when no argument is supplied).
     */
    public function statusLabel(?string $status = null): string {
        $key = $status ?? (string) $this->status;
        if ($key === '') {
            return '';
        }

        return (string) __('attendance.status.'.$key);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array {
        $labels = [];
        foreach (self::STATUSES as $status) {
            $labels[$status] = (string) __('attendance.status.'.$status);
        }

        return $labels;
    }

    /**
     * Localised label for the attendance source (defaults to the current
     * source of the model when no argument is supplied).
     */
    public function sourceLabel(?string $source = null): string {
        $key = $source ?? (string) $this->source;
        if ($key === '') {
            return '';
        }

        return (string) __('attendance.source.'.$key);
    }

    /**
     * Convenience accessor: sum of automatic and manual breaks in minutes.
     */
    public function getBreakMinutesTotalAttribute(): int {
        return (int) ($this->break_minutes_auto ?? 0)
            + (int) ($this->break_minutes_manual ?? 0);
    }

    /**
     * @param  Builder<Attendance>  $q
     * @return Builder<Attendance>
     */
    public function scopeOpen(Builder $q): Builder {
        return $q->whereNull('ended_at');
    }

    /**
     * @param  Builder<Attendance>  $q
     * @return Builder<Attendance>
     */
    public function scopeForUser(Builder $q, int $userId): Builder {
        return $q->where('user_id', $userId);
    }

    /**
     * @param  Builder<Attendance>  $q
     * @return Builder<Attendance>
     */
    public function scopeOnDate(Builder $q, string|Carbon $date): Builder {
        $d = $date instanceof Carbon ? $date->toDateString() : $date;

        return $q->where('date', $d);
    }
}
