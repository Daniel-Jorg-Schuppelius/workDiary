<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\TravelLogFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property int|null $customer_id
 * @property int|null $attendance_id
 * @property int|null $vehicle_id
 * @property Carbon|null $date
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int $duration_minutes
 * @property string|null $from_address
 * @property string|null $to_address
 * @property float|null $from_lat
 * @property float|null $from_lng
 * @property float|null $to_lat
 * @property float|null $to_lng
 * @property string $distance_km
 * @property int|null $odometer_start_km
 * @property int|null $odometer_end_km
 * @property TripKind $trip_kind
 * @property TravelLogVehicle $vehicle
 * @property string|null $vehicle_label
 * @property string|null $purpose
 * @property bool $round_trip
 * @property bool $reimbursable
 * @property string|null $rate_per_km
 * @property string $reimbursement_total
 * @property string|null $notes
 * @property Carbon|null $locked_at
 * @property int|null $corrects_travel_log_id
 * @property string|null $correction_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TravelLog extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<TravelLogFactory> */
    use HasFactory;

    use HasSqid;

    /**
     * Nach der Festschreibung (Feature 137, GoBD „unveränderlich") dürfen sich
     * über normale save()-Wege nur noch diese Felder ändern — Korrekturen
     * laufen als Stornofahrt mit Referenz (corrects_travel_log_id) + Grund.
     */
    public const MUTABLE_AFTER_LOCK = [
        'updated_at',
        'updated_by',
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'project_id',
        'task_id',
        'customer_id',
        'attendance_id',
        'vehicle_id',
        'date',
        'started_at',
        'ended_at',
        'duration_minutes',
        'from_address',
        'to_address',
        'from_lat',
        'from_lng',
        'to_lat',
        'to_lng',
        'distance_km',
        'odometer_start_km',
        'odometer_end_km',
        'trip_kind',
        'vehicle',
        'vehicle_label',
        'purpose',
        'round_trip',
        'reimbursable',
        'rate_per_km',
        'reimbursement_total',
        'notes',
        'locked_at',
        'corrects_travel_log_id',
        'correction_reason',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'from_lat' => 'float',
        'from_lng' => 'float',
        'to_lat' => 'float',
        'to_lng' => 'float',
        'distance_km' => 'decimal:2',
        'odometer_start_km' => 'integer',
        'odometer_end_km' => 'integer',
        'trip_kind' => TripKind::class,
        'locked_at' => 'datetime',
        'rate_per_km' => 'decimal:4',
        'reimbursement_total' => 'decimal:2',
        'round_trip' => 'boolean',
        'reimbursable' => 'boolean',
        'vehicle' => TravelLogVehicle::class,
    ];

    protected static function booted(): void {
        // Festschreibungs-Guard (Feature 137, MVP-702): Anker ist locked_at im
        // Original — ab dann sind fachliche Felder gesperrt (nur MUTABLE_AFTER_LOCK).
        static::updating(function (TravelLog $t): void {
            if ($t->getRawOriginal('locked_at') === null) {
                return;
            }
            $blocked = array_diff(array_keys($t->getDirty()), self::MUTABLE_AFTER_LOCK);
            if ($blocked !== []) {
                throw new \RuntimeException(
                    'Festgeschriebene Fahrten sind unveränderlich (Felder: ' . implode(', ', $blocked) . ') — Stornofahrt erfassen.',
                );
            }
        });
        static::deleting(function (TravelLog $t): void {
            if ($t->getRawOriginal('locked_at') !== null) {
                throw new \RuntimeException('Festgeschriebene Fahrten können nicht gelöscht werden — Stornofahrt erfassen.');
            }
        });

        static::saving(function (TravelLog $t): void {
            if (! $t->date && $t->started_at) {
                $t->date = $t->started_at->copy()->startOfDay();
            }
            if ($t->started_at && $t->ended_at) {
                $t->duration_minutes = max(0, (int) $t->started_at->diffInMinutes($t->ended_at, false));
            }
            $km = (float) $t->distance_km;
            if ($t->round_trip) {
                // Distance is stored one-way; reimbursement covers both legs.
                $km *= 2;
            }
            $rate = $t->rate_per_km !== null ? (float) $t->rate_per_km : 0.0;
            $t->reimbursement_total = (string) round($km * $rate, 2);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicleEntity(): BelongsTo {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    /** Stornierte Original-Fahrt, die diese Fahrt korrigiert (Feature 137). */
    /** @return BelongsTo<TravelLog, $this> */
    public function corrects(): BelongsTo {
        return $this->belongsTo(TravelLog::class, 'corrects_travel_log_id');
    }

    /** @return HasMany<TravelLog, $this> */
    public function corrections(): HasMany {
        return $this->hasMany(TravelLog::class, 'corrects_travel_log_id');
    }

    public function isLocked(): bool {
        return $this->locked_at !== null;
    }

    /** Fahrt gehört zu einem Fahrzeug im Fahrtenbuch-Modus (Feature 137). */
    public function isLogbook(): bool {
        return $this->vehicle_id !== null && (bool) $this->vehicleEntity?->logbook_mode;
    }

    public function isCorrection(): bool {
        return $this->corrects_travel_log_id !== null;
    }

    /** Gefahrene km laut Tacho (End − Start), null ohne Stände. */
    public function odometerDistance(): ?int {
        if ($this->odometer_start_km === null || $this->odometer_end_km === null) {
            return null;
        }

        return $this->odometer_end_km - $this->odometer_start_km;
    }

    /**
     * @param  Builder<TravelLog>  $q
     * @return Builder<TravelLog>
     */
    public function scopeForUser(Builder $q, int $userId): Builder {
        return $q->where('user_id', $userId);
    }

    /**
     * @param  Builder<TravelLog>  $q
     * @return Builder<TravelLog>
     */
    public function scopeReimbursable(Builder $q): Builder {
        return $q->where('reimbursable', true);
    }

    /**
     * Wirksame Fahrten: ohne stornierte Originale (deren Korrektur existiert).
     *
     * @param  Builder<TravelLog>  $q
     * @return Builder<TravelLog>
     */
    public function scopeEffective(Builder $q): Builder {
        return $q->whereDoesntHave('corrections');
    }
}
