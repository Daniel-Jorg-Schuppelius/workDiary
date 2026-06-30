<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationPendingEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Location;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{Customer, Project, TimeEntry, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aus einem Geofence-Besuch abgeleiteter Zeitvorschlag. Wird über die Inbox
 * bestätigt (Status → imported, erzeugt TimeEntry) oder verworfen (dismissed).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int $location_visit_id
 * @property int|null $customer_id
 * @property int|null $project_id
 * @property Carbon $suggested_date
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property int $minutes
 * @property string|null $description
 * @property string $status
 * @property int|null $time_entry_id
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class LocationPendingEntry extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_OPEN = 'open';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'organization_id',
        'user_id',
        'location_visit_id',
        'customer_id',
        'project_id',
        'suggested_date',
        'started_at',
        'ended_at',
        'minutes',
        'description',
        'status',
        'time_entry_id',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'suggested_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'minutes' => 'integer',
        'resolved_at' => 'datetime',
    ];

    /** @param Builder<LocationPendingEntry> $query */
    public function scopeOpen(Builder $query): void {
        $query->where('status', self::STATUS_OPEN);
    }

    /** @return BelongsTo<LocationVisit, $this> */
    public function visit(): BelongsTo {
        return $this->belongsTo(LocationVisit::class, 'location_visit_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<TimeEntry, $this> */
    public function timeEntry(): BelongsTo {
        return $this->belongsTo(TimeEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
