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
use CommonToolkit\Helper\Data\CryptoHelper;
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
 * @property Carbon|null $homeoffice_started_at
 * @property int $homeoffice_minutes
 * @property Carbon|null $errand_started_at
 * @property int $errand_minutes
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
        'homeoffice_started_at',
        'homeoffice_minutes',
        'errand_started_at',
        'errand_minutes',
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
        'homeoffice_started_at' => 'datetime',
        'homeoffice_minutes' => 'integer',
        'errand_started_at' => 'datetime',
        'errand_minutes' => 'integer',
        'duration_minutes' => 'integer',
        'started_lat' => 'float',
        'started_lng' => 'float',
        'ended_lat' => 'float',
        'ended_lng' => 'float',
        'source' => AttendanceSource::class,
        'status' => AttendanceStatus::class,
    ];

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
     * Fingerabdruck der korrigierbaren Felder (Feature 035 Phase 3; Audit
     * 2026-08, W4.1). Grundlage des `base_version`-Vergleichs beim
     * Offline-Nachtrag: Ein Gerät korrigiert immer den Stand, den es zuletzt
     * gesehen hat — hat inzwischen jemand ANDERES an genau diesen Feldern
     * gedreht, ist die Korrektur ein Konflikt und darf nicht still gewinnen.
     *
     * Bewusst NICHT `updated_at`: eine unbeteiligte Änderung (Notiz, Geo,
     * Abschluss-Flag) würde sonst einen Scheinkonflikt auslösen.
     */
    public function correctionVersion(): string {
        return substr((string) CryptoHelper::hash(implode('|', [
            $this->started_at?->toIso8601String() ?? '',
            $this->ended_at?->toIso8601String() ?? '',
            (string) (int) ($this->break_minutes_manual ?? 0),
        ])), 0, 16);
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
