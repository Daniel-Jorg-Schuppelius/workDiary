<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerVehicleProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Passenger;

use App\Enums\Passenger\RideOperationMode;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Personenbeförderungs-Profil eines Fahrzeugs (MVP-456, Konzept §7):
 * Ordnungsnummer, zugelassene Betriebsarten, Barrierefreiheitsmerkmale sowie
 * Taxameter-/Eich-/BOKraft-/HU-/TSE-Status. Ergänzt den gemeinsamen
 * Fuhrparkkern ({@see Vehicle}) statt ihn zu ersetzen.
 *
 * WorkDiary erhebt **keine** Konformitätsbehauptung für Taxameter,
 * Wegstreckenzähler oder TSE — hier stehen nur Nachweisstände (Konzept §9).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $vehicle_id
 * @property string|null $order_number
 * @property array<int, string> $operation_modes
 * @property int|null $passenger_seats
 * @property int $wheelchair_places
 * @property bool $barrier_free
 * @property bool $large_capacity
 * @property string|null $meter_kind
 * @property string|null $meter_serial
 * @property Carbon|null $meter_calibrated_until
 * @property string|null $tse_reference
 * @property Carbon|null $bokraft_checked_until
 * @property Carbon|null $hu_valid_until
 */
class PassengerVehicleProfile extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const METER_TAXAMETER = 'taxameter';

    public const METER_ODOMETER = 'wegstreckenzaehler';

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'order_number',
        'operation_modes',
        'passenger_seats',
        'wheelchair_places',
        'barrier_free',
        'large_capacity',
        'meter_kind',
        'meter_serial',
        'meter_calibrated_until',
        'tse_reference',
        'bokraft_checked_until',
        'hu_valid_until',
    ];

    protected $casts = [
        'operation_modes' => 'array',
        'passenger_seats' => 'integer',
        'wheelchair_places' => 'integer',
        'barrier_free' => 'boolean',
        'large_capacity' => 'boolean',
        'meter_calibrated_until' => 'date',
        'bokraft_checked_until' => 'date',
        'hu_valid_until' => 'date',
    ];

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    public function supports(RideOperationMode $mode): bool {
        return in_array($mode->value, $this->operation_modes, true);
    }

    /**
     * Fällige Nachweise am Stichtag (leer = alles gültig). Rückgabe sind
     * Übersetzungs-Keys, damit der Aufrufer sie lokalisiert ausgeben kann.
     *
     * @return list<string>
     */
    public function expiredProofs(?\DateTimeInterface $date = null): array {
        $day = Carbon::instance($date instanceof \DateTimeInterface ? $date : now())->startOfDay();
        $expired = [];

        foreach ([
            'meter_calibrated_until' => 'passenger.proof.meter_calibration',
            'bokraft_checked_until' => 'passenger.proof.bokraft',
            'hu_valid_until' => 'passenger.proof.hu',
        ] as $column => $key) {
            $until = $this->getAttribute($column);
            if ($until instanceof Carbon && $until->lessThan($day)) {
                $expired[] = $key;
            }
        }

        return $expired;
    }
}
