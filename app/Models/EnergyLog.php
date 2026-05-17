<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnergyLog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EnergyLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $vehicle_id
 * @property int $user_id
 * @property string $energy_type
 * @property string|null $fuel_kind
 * @property string $unit
 * @property string $quantity
 * @property string|null $cost_total
 * @property int|null $odometer_km
 * @property int|null $distance_since_last
 * @property string|null $location_address
 * @property string|null $location_lat
 * @property string|null $location_lng
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $duration_minutes
 * @property int|null $soc_before
 * @property int|null $soc_after
 * @property string|null $charger_type
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EnergyLog extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<EnergyLogFactory> */
    use HasFactory;

    public const TYPE_FUEL = 'fuel';

    public const TYPE_ELECTRIC = 'electric';

    /** @var list<string> */
    public const TYPES = [self::TYPE_FUEL, self::TYPE_ELECTRIC];

    public const UNIT_LITER = 'liter';

    public const UNIT_KWH = 'kwh';

    /** @var list<string> */
    public const UNITS = [self::UNIT_LITER, self::UNIT_KWH];

    public const FUEL_DIESEL = 'diesel';

    public const FUEL_PETROL = 'petrol';

    public const FUEL_GAS = 'gas';

    public const FUEL_CNG = 'cng';

    public const FUEL_ADBLUE = 'adblue';

    public const FUEL_OTHER = 'other';

    /** @var list<string> */
    public const FUEL_KINDS = [
        self::FUEL_DIESEL,
        self::FUEL_PETROL,
        self::FUEL_GAS,
        self::FUEL_CNG,
        self::FUEL_ADBLUE,
        self::FUEL_OTHER,
    ];

    public const CHARGER_LEVEL1 = 'level1';

    public const CHARGER_LEVEL2 = 'level2';

    public const CHARGER_DC_FAST = 'dc_fast';

    public const CHARGER_OTHER = 'other';

    /** @var list<string> */
    public const CHARGER_TYPES = [
        self::CHARGER_LEVEL1,
        self::CHARGER_LEVEL2,
        self::CHARGER_DC_FAST,
        self::CHARGER_OTHER,
    ];

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'user_id',
        'energy_type',
        'fuel_kind',
        'unit',
        'quantity',
        'cost_total',
        'odometer_km',
        'distance_since_last',
        'location_address',
        'location_lat',
        'location_lng',
        'started_at',
        'ended_at',
        'duration_minutes',
        'soc_before',
        'soc_after',
        'charger_type',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'odometer_km' => 'integer',
        'distance_since_last' => 'integer',
        'duration_minutes' => 'integer',
        'soc_before' => 'integer',
        'soc_after' => 'integer',
    ];

    public static function booted(): void
    {
        static::saving(function (EnergyLog $log): void {
            // Force unit↔type consistency.
            if ($log->energy_type === self::TYPE_ELECTRIC) {
                $log->unit = self::UNIT_KWH;
                $log->fuel_kind = null;
            } else {
                $log->unit = self::UNIT_LITER;
                $log->soc_before = null;
                $log->soc_after = null;
                $log->charger_type = null;
            }

            if ($log->ended_at !== null) {
                $minutes = $log->started_at->diffInMinutes($log->ended_at, false);
                $log->duration_minutes = max(0, (int) $minutes);
            } else {
                $log->duration_minutes = 0;
            }
        });
    }

    public function costPerUnit(): ?float
    {
        if ($this->cost_total === null || (float) $this->quantity <= 0.0) {
            return null;
        }

        return round((float) $this->cost_total / (float) $this->quantity, 4);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
