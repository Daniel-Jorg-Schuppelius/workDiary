<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTrip.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Expense\PerDiemTripStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\PerDiemTripFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property int|null $project_id
 * @property int|null $customer_id
 * @property int|null $travel_log_id
 * @property int|null $expense_id
 * @property string $country
 * @property string $purpose
 * @property string $location
 * @property string|null $workplace_key
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property bool $accommodation_provided
 * @property PerDiemTripStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PerDiemDay> $days
 */
class PerDiemTrip extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<PerDiemTripFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'project_id',
        'customer_id',
        'travel_log_id',
        'expense_id',
        'country',
        'purpose',
        'location',
        'workplace_key',
        'started_at',
        'ended_at',
        'accommodation_provided',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'accommodation_provided' => 'boolean',
        'status' => PerDiemTripStatus::class,
    ];

    protected static function booted(): void {
        static::saving(function (self $trip): void {
            $trip->country = strtoupper((string) $trip->country);
            if (empty($trip->workplace_key) && ! empty($trip->location)) {
                $trip->workplace_key = strtolower(trim($trip->location));
            }
        });
    }

    /** Summe aller Tagesbeträge (nach Kürzungen). */
    public function totalAmount(): string {
        return number_format((float) $this->days->sum(fn(PerDiemDay $d): float => (float) $d->amount), 2, '.', '');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<TravelLog, $this> */
    public function travelLog(): BelongsTo {
        return $this->belongsTo(TravelLog::class);
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo {
        return $this->belongsTo(Expense::class);
    }

    /** @return HasMany<PerDiemDay, $this> */
    public function days(): HasMany {
        return $this->hasMany(PerDiemDay::class)->orderBy('date');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<PerDiemTrip>  $query
     * @return Builder<PerDiemTrip>
     */
    public function scopeForUser(Builder $query, int $userId): Builder {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<PerDiemTrip>  $query
     * @return Builder<PerDiemTrip>
     */
    public function scopeWithStatus(Builder $query, PerDiemTripStatus|string $status): Builder {
        $value = $status instanceof PerDiemTripStatus ? $status->value : $status;

        return $query->where('status', $value);
    }
}
