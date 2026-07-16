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

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Services\Timekeeping\BreakRuleEvaluator;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
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
 * @property Carbon|null $break_started_at
 * @property int $duration_minutes
 * @property AttendanceSource|null $source
 * @property AttendanceStatus|null $status
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
 * @property-read int $break_minutes_total
 */
class Attendance extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'started_at',
        'ended_at',
        'date',
        'break_minutes_auto',
        'break_minutes_manual',
        'break_started_at',
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

    /** @var array<string, string> */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'date' => 'date',
        'break_minutes_auto' => 'integer',
        'break_minutes_manual' => 'integer',
        'break_started_at' => 'datetime',
        'duration_minutes' => 'integer',
        'started_lat' => 'float',
        'started_lng' => 'float',
        'ended_lat' => 'float',
        'ended_lng' => 'float',
        'source' => AttendanceSource::class,
        'status' => AttendanceStatus::class,
    ];

    protected static function booted(): void {
        static::saving(function (Attendance $a): void {
            if (! $a->date && $a->started_at) {
                // Kalendertag in der Anzeige-Zeitzone, nicht UTC (23:30 lokal sonst auf Folgetag); started_at bleibt UTC.
                $a->date = $a->started_at->copy()->setTimezone(\App\Support\Tz::current())->startOfDay();
            }
            if ($a->started_at && $a->ended_at) {
                // Gesetzliche Mindestpausen (ArbZG §4) in break_minutes_auto ergänzen, bevor die Netto-Dauer folgt.
                $eval = app(BreakRuleEvaluator::class);
                if ($eval->autoApplyEnabled()) {
                    $eval->applyMissingBreak($a);
                }

                $gross = (int) $a->started_at->diffInMinutes($a->ended_at, false);
                $breaks = (int) ($a->break_minutes_auto ?? 0)
                    + (int) ($a->break_minutes_manual ?? 0);
                $a->duration_minutes = max(0, $gross - $breaks);
                if ($a->status === AttendanceStatus::Open) {
                    $a->status = AttendanceStatus::Closed;
                }
            } else {
                $a->duration_minutes = 0;
                if (! $a->status) {
                    $a->status = AttendanceStatus::Open;
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

    /** Läuft gerade eine (Terminal-)Pause? (Feature 061, Rang 13) */
    public function isOnBreak(): bool {
        return $this->break_started_at !== null;
    }

    /**
     * Localised label for the attendance status (defaults to the current
     * status of the model when no argument is supplied).
     */
    public function statusLabel(?string $status = null): string {
        if ($status !== null) {
            return $status === '' ? '' : (string) __('attendance.status.' . $status);
        }

        return $this->status?->label() ?? '';
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array {
        $labels = [];
        foreach (AttendanceStatus::cases() as $status) {
            $labels[$status->value] = $status->label();
        }

        return $labels;
    }

    /**
     * Localised label for the attendance source (defaults to the current
     * source of the model when no argument is supplied).
     */
    public function sourceLabel(?string $source = null): string {
        if ($source !== null) {
            return $source === '' ? '' : (string) __('attendance.source.' . $source);
        }

        return $this->source?->label() ?? '';
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
