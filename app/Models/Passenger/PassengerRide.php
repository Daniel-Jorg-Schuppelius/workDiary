<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerRide.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Passenger;

use App\Enums\Passenger\{RideOperationMode, RideOrderChannel, RidePriceKind, RideStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{DiaryEntry, User, Vehicle};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fahrtakte der Personenbeförderung (MVP-456): 1:1-Erweiterung des
 * {@see DiaryEntry}, damit Taxi-Fachdaten nicht in allgemeine Auftragsfelder
 * oder Freitext ausweichen (Konzept §2/§4).
 *
 * Datenschutz (Konzept §11): Abhol-/Zieladresse, Wegpunkte, Fahrgastname und
 * -kontakt sind `encrypted` at-rest. Diagnosen oder Gesundheitsdaten gehören
 * NICHT in diese Akte — auch nicht in `closing_note`/`route_note`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $diary_entry_id
 * @property RideOperationMode $operation_mode
 * @property RideOrderChannel $order_channel
 * @property RideStatus $status
 * @property string|null $mediator_reference
 * @property string|null $mediator_plugin
 * @property Carbon|null $requested_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $pickup_started_at
 * @property Carbon|null $waiting_started_at
 * @property Carbon|null $picked_up_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $closing_reason
 * @property string|null $pickup_address
 * @property string|null $destination_address
 * @property array<int, array<string, mixed>>|null $waypoints
 * @property bool $destination_open
 * @property int $passenger_count
 * @property bool $wheelchair
 * @property bool $barrier_free_required
 * @property int|null $driver_user_id
 * @property int|null $vehicle_id
 * @property int|null $concession_id
 * @property array<string, mixed>|null $assignment_snapshot
 * @property RidePriceKind|null $price_kind
 * @property int|null $tariff_id
 * @property array<string, mixed>|null $fare_snapshot
 * @property string|null $planned_net
 * @property string|null $meter_net
 * @property string|null $gross_amount
 * @property CurrencyCode $currency
 * @property array<string, mixed>|null $tax_context
 * @property string|null $payment_method
 * @property string $settlement_status
 * @property Carbon|null $order_received_at
 * @property Carbon|null $returned_to_base_at
 * @property Carbon|null $anonymized_at
 */
class PassengerRide extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const SETTLEMENT_OPEN = 'open';

    public const SETTLEMENT_SETTLED = 'settled';

    public const SETTLEMENT_WAIVED = 'waived';

    protected $fillable = [
        'organization_id',
        'diary_entry_id',
        'operation_mode',
        'order_channel',
        'status',
        'mediator_reference',
        'mediator_plugin',
        'requested_at',
        'accepted_at',
        'accepted_by',
        'assigned_at',
        'pickup_started_at',
        'waiting_started_at',
        'picked_up_at',
        'completed_at',
        'cancelled_at',
        'closing_reason',
        'closing_note',
        'pickup_address',
        'destination_address',
        'waypoints',
        'destination_open',
        'window_start',
        'window_end',
        'passenger_count',
        'luggage_count',
        'child_seats',
        'wheelchair',
        'animal',
        'barrier_free_required',
        'passenger_name',
        'passenger_contact',
        'driver_user_id',
        'vehicle_id',
        'concession_id',
        'assignment_snapshot',
        'odometer_start_km',
        'odometer_end_km',
        'occupied_km',
        'empty_km',
        'waiting_seconds',
        'route_note',
        'price_kind',
        'tariff_id',
        'fare_snapshot',
        'planned_net',
        'meter_net',
        'tax_rate',
        'tax_amount',
        'gross_amount',
        'currency',
        'tax_context',
        'payment_method',
        'settlement_status',
        'shift_settlement_id',
        'order_received_at',
        'order_receipt_reference',
        'returned_to_base_at',
        'follow_up_ride_id',
        'created_by',
    ];

    protected $casts = [
        'operation_mode' => RideOperationMode::class,
        'order_channel' => RideOrderChannel::class,
        'status' => RideStatus::class,
        'price_kind' => RidePriceKind::class,
        'currency' => CurrencyCode::class,
        // PII verschlüsselt at-rest (Konzept §11). Leere Strings NIE speichern
        // — "" bricht decrypt (Projektregel), deshalb überall ?: null.
        'pickup_address' => 'encrypted',
        'destination_address' => 'encrypted',
        'waypoints' => 'encrypted:array',
        'passenger_name' => 'encrypted',
        'passenger_contact' => 'encrypted',
        'assignment_snapshot' => 'array',
        'fare_snapshot' => 'array',
        'tax_context' => 'array',
        'destination_open' => 'boolean',
        'wheelchair' => 'boolean',
        'animal' => 'boolean',
        'barrier_free_required' => 'boolean',
        'passenger_count' => 'integer',
        'luggage_count' => 'integer',
        'child_seats' => 'integer',
        'waiting_seconds' => 'integer',
        'occupied_km' => 'decimal:2',
        'empty_km' => 'decimal:2',
        'planned_net' => 'decimal:2',
        'meter_net' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'accepted_at' => 'datetime',
        'assigned_at' => 'datetime',
        'pickup_started_at' => 'datetime',
        'waiting_started_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'order_received_at' => 'datetime',
        'returned_to_base_at' => 'datetime',
        // Retention (Konzept §11): nach Frist werden Orts-/Fahrgastfelder
        // genullt; Beträge/Steuer/Zeiten bleiben als Vorgangsnachweis.
        'anonymized_at' => 'datetime',
    ];

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function driver(): BelongsTo {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<PassengerConcession, $this> */
    public function concession(): BelongsTo {
        return $this->belongsTo(PassengerConcession::class, 'concession_id');
    }

    /** @return BelongsTo<PassengerFareTariff, $this> */
    public function tariff(): BelongsTo {
        return $this->belongsTo(PassengerFareTariff::class, 'tariff_id');
    }

    /** @return BelongsTo<PassengerShiftSettlement, $this> */
    public function shiftSettlement(): BelongsTo {
        return $this->belongsTo(PassengerShiftSettlement::class, 'shift_settlement_id');
    }

    /** @return BelongsTo<PassengerRide, $this> */
    public function followUpRide(): BelongsTo {
        return $this->belongsTo(self::class, 'follow_up_ride_id');
    }

    /** @param Builder<PassengerRide> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereNotIn('status', [
            RideStatus::Completed->value,
            RideStatus::Cancelled->value,
            RideStatus::NoShow->value,
            RideStatus::Aborted->value,
        ]);
    }

    /**
     * Abweichung zwischen geplantem Preis und Gerätewert (Konzept §8).
     *
     * @return numeric-string|null
     */
    public function fareDeviation(): ?string {
        $planned = trim((string) $this->planned_net);
        $meter = trim((string) $this->meter_net);
        if (! is_numeric($planned) || ! is_numeric($meter)) {
            return null;
        }

        return bcsub($meter, $planned, 2);
    }

    public function hasFareDeviation(): bool {
        $deviation = $this->fareDeviation();

        return $deviation !== null && bccomp($deviation, '0', 2) !== 0;
    }

    /** Offener Rückkehr-/Folgeauftragsnachweis (§ 49 Abs. 4 PBefG). */
    public function awaitsReturnProof(): bool {
        return $this->operation_mode->requiresReturnToBase()
            && $this->status === RideStatus::Completed
            && $this->returned_to_base_at === null
            && $this->follow_up_ride_id === null;
    }
}
