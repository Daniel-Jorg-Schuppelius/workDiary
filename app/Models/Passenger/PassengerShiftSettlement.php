<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerShiftSettlement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Passenger;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{User, Vehicle};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Fahrer-/Schichtabrechnung (MVP-456, Konzept §8): trennt Geräteumsatz von
 * den Zahlarten (bar, Karte, Gutschein, Rechnung, Vermittler), führt
 * Trinkgeld und Storno separat und hält Differenzen offen, bis sie begründet
 * geklärt sind — das Kassenbuch ersetzt weder Taxameter noch TSE.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $driver_user_id
 * @property int|null $vehicle_id
 * @property Carbon $shift_date
 * @property numeric-string $meter_total
 * @property numeric-string $cash_total
 * @property numeric-string $card_total
 * @property numeric-string $voucher_total
 * @property numeric-string $invoice_total
 * @property numeric-string $mediator_total
 * @property numeric-string $tip_total
 * @property numeric-string $cancelled_total
 * @property numeric-string $difference
 * @property string|null $difference_reason
 * @property string $status
 * @property int|null $cash_entry_id
 */
class PassengerShiftSettlement extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const STATUS_OPEN = 'open';

    public const STATUS_BALANCED = 'balanced';

    public const STATUS_DISPUTED = 'disputed';

    protected $fillable = [
        'organization_id',
        'driver_user_id',
        'vehicle_id',
        'shift_date',
        'started_at',
        'ended_at',
        'meter_total',
        'cash_total',
        'card_total',
        'voucher_total',
        'invoice_total',
        'mediator_total',
        'tip_total',
        'cancelled_total',
        'difference',
        'difference_reason',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'meter_total' => 'decimal:2',
        'cash_total' => 'decimal:2',
        'card_total' => 'decimal:2',
        'voucher_total' => 'decimal:2',
        'invoice_total' => 'decimal:2',
        'mediator_total' => 'decimal:2',
        'tip_total' => 'decimal:2',
        'cancelled_total' => 'decimal:2',
        'difference' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function driver(): BelongsTo {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return HasMany<PassengerRide, $this> */
    public function rides(): HasMany {
        return $this->hasMany(PassengerRide::class, 'shift_settlement_id');
    }

    /**
     * Kassenbuch-Buchung der Übergabe (Issue #74).
     *
     * @return BelongsTo<\App\Models\CashEntry, $this>
     */
    public function cashEntry(): BelongsTo {
        return $this->belongsTo(\App\Models\CashEntry::class);
    }

    /**
     * Summe der Zahlarten (ohne Geräteumsatz, ohne Trinkgeld).
     *
     * @return numeric-string
     */
    public function paymentTotal(): string {
        $total = '0.00';
        foreach (['cash_total', 'card_total', 'voucher_total', 'invoice_total', 'mediator_total'] as $column) {
            $total = bcadd($total, $this->numeric($this->getAttribute($column)), 2);
        }

        return $total;
    }

    /**
     * Dezimal-Casts liefern `string`; bcmath verlangt `numeric-string`.
     *
     * @return numeric-string
     */
    private function numeric(mixed $value): string {
        $raw = trim((string) $value);

        return is_numeric($raw) ? $raw : '0';
    }

    /**
     * Differenz Geräteumsatz − Zahlarten − Storno (Konzept §8: bleibt offen,
     * bis begründet geklärt).
     *
     * @return numeric-string
     */
    public function computeDifference(): string {
        return bcsub(
            bcsub($this->numeric($this->meter_total), $this->paymentTotal(), 2),
            $this->numeric($this->cancelled_total),
            2,
        );
    }

    public function isBalanced(): bool {
        return bccomp($this->computeDifference(), '0', 2) === 0;
    }
}
