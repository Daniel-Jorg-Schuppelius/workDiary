<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Vehicle.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $license_plate
 * @property string|null $label
 * @property string $vehicle_type
 * @property string $propulsion
 * @property string $ownership
 * @property string|null $rental_provider
 * @property Carbon|null $rental_start
 * @property Carbon|null $rental_end
 * @property string|null $rental_cost_per_day
 * @property int|null $rental_included_km
 * @property string|null $rental_extra_cost_per_km
 * @property int|null $default_user_id
 * @property string|null $default_rate_per_km
 * @property string|null $tank_capacity_liters
 * @property string|null $battery_capacity_kwh
 * @property string|null $wltp_consumption
 * @property int|null $odometer_km
 * @property string|null $notes
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Vehicle extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    public const TYPE_CAR = 'car';

    public const TYPE_VAN = 'van';

    public const TYPE_TRUCK = 'truck';

    public const TYPE_BICYCLE = 'bicycle';

    public const TYPE_OTHER = 'other';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_CAR,
        self::TYPE_VAN,
        self::TYPE_TRUCK,
        self::TYPE_BICYCLE,
        self::TYPE_OTHER,
    ];

    public const PROPULSION_DIESEL = 'diesel';

    public const PROPULSION_PETROL = 'petrol';

    public const PROPULSION_GAS = 'gas';

    public const PROPULSION_HYBRID = 'hybrid';

    public const PROPULSION_ELECTRIC = 'electric';

    public const PROPULSION_MUSCLE = 'muscle';

    public const PROPULSION_OTHER = 'other';

    /** @var list<string> */
    public const PROPULSIONS = [
        self::PROPULSION_DIESEL,
        self::PROPULSION_PETROL,
        self::PROPULSION_GAS,
        self::PROPULSION_HYBRID,
        self::PROPULSION_ELECTRIC,
        self::PROPULSION_MUSCLE,
        self::PROPULSION_OTHER,
    ];

    public const OWNERSHIP_OWNED = 'owned';

    public const OWNERSHIP_LEASED = 'leased';

    public const OWNERSHIP_RENTAL = 'rental';

    /** @var list<string> */
    public const OWNERSHIPS = [
        self::OWNERSHIP_OWNED,
        self::OWNERSHIP_LEASED,
        self::OWNERSHIP_RENTAL,
    ];

    protected $fillable = [
        'organization_id',
        'license_plate',
        'label',
        'vehicle_type',
        'propulsion',
        'ownership',
        'rental_provider',
        'rental_start',
        'rental_end',
        'rental_cost_per_day',
        'rental_included_km',
        'rental_extra_cost_per_km',
        'default_user_id',
        'default_rate_per_km',
        'tank_capacity_liters',
        'battery_capacity_kwh',
        'wltp_consumption',
        'odometer_km',
        'notes',
        'archived_at',
    ];

    protected $casts = [
        'odometer_km' => 'integer',
        'archived_at' => 'datetime',
        'rental_start' => 'date',
        'rental_end' => 'date',
        'rental_cost_per_day' => 'decimal:2',
        'rental_included_km' => 'integer',
        'rental_extra_cost_per_km' => 'decimal:4',
        'default_rate_per_km' => 'decimal:4',
        'tank_capacity_liters' => 'decimal:2',
        'battery_capacity_kwh' => 'decimal:2',
        'wltp_consumption' => 'decimal:3',
    ];

    public function isElectric(): bool
    {
        return in_array($this->propulsion, [self::PROPULSION_ELECTRIC, self::PROPULSION_HYBRID], true);
    }

    public function isRental(): bool
    {
        return $this->ownership === self::OWNERSHIP_RENTAL;
    }

    /**
     * Rental cars are bookable only within their rental period; non-rental
     * vehicles are always considered available (active scope governs the rest).
     */
    public function isAvailableOn(\DateTimeInterface $date): bool
    {
        if (! $this->isRental()) {
            return true;
        }
        $day = Carbon::instance($date)->startOfDay();
        if ($this->rental_start !== null && $day->lt($this->rental_start->startOfDay())) {
            return false;
        }
        if ($this->rental_end !== null && $day->gt($this->rental_end->startOfDay())) {
            return false;
        }

        return true;
    }

    public function expectedEnergyUnit(): ?string
    {
        return match ($this->propulsion) {
            self::PROPULSION_ELECTRIC => 'kwh',
            self::PROPULSION_MUSCLE, self::PROPULSION_OTHER => null,
            default => 'liter',
        };
    }

    public function displayName(): string
    {
        $name = trim((string) $this->label);
        if ($name === '') {
            return (string) $this->license_plate;
        }

        return sprintf('%s (%s)', $name, $this->license_plate);
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->whereNull('default_user_id')->orWhere('default_user_id', $userId);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    /** @return HasMany<EnergyLog, $this> */
    public function energyLogs(): HasMany
    {
        return $this->hasMany(EnergyLog::class);
    }

    /** @return HasMany<TravelLog, $this> */
    public function travelLogs(): HasMany
    {
        return $this->hasMany(TravelLog::class);
    }
}
