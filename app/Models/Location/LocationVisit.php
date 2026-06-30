<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationVisit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Location;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int $customer_geofence_id
 * @property Carbon $entered_at
 * @property Carbon|null $left_at
 * @property int|null $duration_min
 * @property int $sample_count
 * @property string $status
 * @property bool $materialized
 */
class LocationVisit extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id',
        'user_id',
        'customer_geofence_id',
        'entered_at',
        'left_at',
        'duration_min',
        'sample_count',
        'status',
        'materialized',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'left_at' => 'datetime',
        'duration_min' => 'integer',
        'sample_count' => 'integer',
        'materialized' => 'boolean',
    ];

    /** @param Builder<LocationVisit> $query */
    public function scopeClosed(Builder $query): void {
        $query->where('status', self::STATUS_CLOSED);
    }

    /** @param Builder<LocationVisit> $query */
    public function scopeMaterializable(Builder $query): void {
        $query->where('status', self::STATUS_CLOSED)->where('materialized', false);
    }

    /** @return BelongsTo<CustomerGeofence, $this> */
    public function geofence(): BelongsTo {
        return $this->belongsTo(CustomerGeofence::class, 'customer_geofence_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<LocationPendingEntry, $this> */
    public function pendingEntry(): HasOne {
        return $this->hasOne(LocationPendingEntry::class);
    }
}
