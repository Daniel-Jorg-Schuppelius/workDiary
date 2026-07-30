<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerConcession.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Passenger;

use App\Enums\Passenger\RideOperationMode;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Support\Carbon;

/**
 * Konzession/Genehmigung der Personenbeförderung (MVP-456, Konzept §7):
 * Behörde, Nummer, Betriebssitz, Pflichtfahr-/Tarifgebiet, Gültigkeit und
 * Auflagen. Der Dispositions-Guard verlangt eine am Fahrttag gültige
 * Konzession der passenden Betriebsart.
 *
 * @property int $id
 * @property int $organization_id
 * @property RideOperationMode $operation_mode
 * @property string $authority
 * @property string $reference_no
 * @property string|null $business_seat
 * @property string|null $service_area
 * @property string|null $tariff_area
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property int|null $licensed_vehicles
 * @property string|null $conditions
 * @property bool $active
 */
class PassengerConcession extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'operation_mode',
        'authority',
        'reference_no',
        'business_seat',
        'service_area',
        'tariff_area',
        'valid_from',
        'valid_until',
        'licensed_vehicles',
        'conditions',
        'active',
        'created_by',
    ];

    protected $casts = [
        'operation_mode' => RideOperationMode::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
        'licensed_vehicles' => 'integer',
        'active' => 'boolean',
    ];

    /** Gültig am Stichtag (offene Enden gelten als unbefristet). */
    public function isValidOn(?\DateTimeInterface $date = null): bool {
        if (! $this->active) {
            return false;
        }
        $day = Carbon::instance($date instanceof \DateTimeInterface ? $date : now())->startOfDay();

        return ($this->valid_from === null || $this->valid_from->lessThanOrEqualTo($day))
            && ($this->valid_until === null || $this->valid_until->greaterThanOrEqualTo($day));
    }

    /** @param Builder<PassengerConcession> $query */
    public function scopeValidFor(Builder $query, RideOperationMode $mode, ?\DateTimeInterface $date = null): void {
        $day = Carbon::instance($date instanceof \DateTimeInterface ? $date : now())->startOfDay()->toDateString();

        $query->where('active', true)
            ->where('operation_mode', $mode->value)
            ->where(fn(Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $day))
            ->where(fn(Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $day));
    }
}
