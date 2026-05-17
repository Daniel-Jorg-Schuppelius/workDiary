<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $customer_id
 * @property int|null $project_id
 * @property int|null $assigned_user_id
 * @property string $title
 * @property string|null $description
 * @property string|null $address_line
 * @property string|null $address_zip
 * @property string|null $address_city
 * @property string|null $address_country
 * @property string|null $address_lat
 * @property string|null $address_lng
 * @property Carbon|null $scheduled_for
 * @property string|null $time_window_start
 * @property string|null $time_window_end
 * @property int $service_minutes
 * @property string $priority
 * @property string $status
 * @property int|null $tour_id
 * @property int|null $tour_position
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServiceOrder extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    /** @var list<string> */
    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'organization_id',
        'customer_id',
        'project_id',
        'assigned_user_id',
        'title',
        'description',
        'address_line',
        'address_zip',
        'address_city',
        'address_country',
        'address_lat',
        'address_lng',
        'scheduled_for',
        'time_window_start',
        'time_window_end',
        'service_minutes',
        'priority',
        'status',
        'tour_id',
        'tour_position',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'address_lat' => 'decimal:7',
            'address_lng' => 'decimal:7',
            'service_minutes' => 'integer',
            'tour_position' => 'integer',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * @param  Builder<ServiceOrder>  $query
     * @return Builder<ServiceOrder>
     */
    public function scopeScheduledOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('scheduled_for', $date);
    }

    /**
     * @param  Builder<ServiceOrder>  $query
     * @return Builder<ServiceOrder>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * @param  Builder<ServiceOrder>  $query
     * @return Builder<ServiceOrder>
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('tour_id');
    }

    /**
     * @param  Builder<ServiceOrder>  $query
     * @return Builder<ServiceOrder>
     */
    public function scopeByTour(Builder $query, int $tourId): Builder
    {
        return $query->where('tour_id', $tourId);
    }

    public function hasCoordinates(): bool
    {
        return $this->address_lat !== null && $this->address_lng !== null;
    }
}
