<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationPendingTrip.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Location;

use App\Enums\Location\LocationPendingTripStatus;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use App\Models\Travel\TravelLog;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fahrtvorschlag aus der Spur zwischen zwei Besuchen (MVP-1185): Beginn,
 * Ende, Strecke (Kartenabgleich oder Spurlänge). Wird im Prüf-Eingang mit
 * Fahrzeug und Zweck ins Fahrtenbuch übernommen oder verworfen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int|null $from_visit_id
 * @property int|null $to_visit_id
 * @property string|null $from_label
 * @property string|null $to_label
 * @property string|null $from_lat
 * @property string|null $from_lng
 * @property string|null $to_lat
 * @property string|null $to_lng
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property string $distance_km
 * @property string $distance_source
 * @property int $point_count
 * @property LocationPendingTripStatus $status
 * @property int|null $travel_log_id
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class LocationPendingTrip extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const SOURCE_MATCH = 'match';

    public const SOURCE_TRACK = 'track';

    protected $fillable = [
        'organization_id',
        'user_id',
        'from_visit_id',
        'to_visit_id',
        'from_label',
        'to_label',
        'from_lat',
        'from_lng',
        'to_lat',
        'to_lng',
        'started_at',
        'ended_at',
        'distance_km',
        'distance_source',
        'point_count',
        'status',
        'travel_log_id',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'distance_km' => 'decimal:2',
        'point_count' => 'integer',
        'status' => LocationPendingTripStatus::class,
        'resolved_at' => 'datetime',
    ];

    /** @param Builder<LocationPendingTrip> $query */
    public function scopeOpen(Builder $query): void {
        $query->where('status', LocationPendingTripStatus::Open);
    }

    /** @return BelongsTo<LocationVisit, $this> */
    public function fromVisit(): BelongsTo {
        return $this->belongsTo(LocationVisit::class, 'from_visit_id');
    }

    /** @return BelongsTo<LocationVisit, $this> */
    public function toVisit(): BelongsTo {
        return $this->belongsTo(LocationVisit::class, 'to_visit_id');
    }

    /** @return BelongsTo<TravelLog, $this> */
    public function travelLog(): BelongsTo {
        return $this->belongsTo(TravelLog::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
