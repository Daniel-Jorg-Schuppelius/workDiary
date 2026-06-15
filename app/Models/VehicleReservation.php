<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReservation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\VehicleReservationFactory;
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reservierung eines Fahrzeugs für ein Zeitfenster (Feature 028).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $vehicle_id
 * @property int|null $diary_entry_id
 * @property int $reserved_by_user_id
 * @property Carbon $reserved_from
 * @property Carbon $reserved_to
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class VehicleReservation extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<VehicleReservationFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'diary_entry_id',
        'reserved_by_user_id',
        'reserved_from',
        'reserved_to',
        'note',
    ];

    protected $casts = [
        'reserved_from' => 'datetime',
        'reserved_to' => 'datetime',
    ];

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reservedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'reserved_by_user_id');
    }

    /**
     * Reservierungen eines Fahrzeugs, deren Zeitfenster sich mit [$from, $to]
     * überschneidet. Berührungspunkte (Ende == Start) gelten NICHT als Konflikt.
     *
     * @param Builder<VehicleReservation> $query
     */
    public function scopeForVehicle(Builder $query, int $vehicleId): void {
        $query->where('vehicle_id', $vehicleId);
    }

    /**
     * @param Builder<VehicleReservation> $query
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void {
        $query->where('reserved_from', '<', $to)
            ->where('reserved_to', '>', $from);
    }
}
