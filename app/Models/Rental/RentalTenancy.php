<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalTenancy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Casts\MoneyCast;
use App\Enums\Rental\RentalTenancyStatus;
use App\Models\Asset\Asset;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mietverhältnis der Dauervermietung (Branchenprofil Vermieter): offen oder bis
 * einschließlich des letzten Miettags begrenzt. Kaltmiete, Nebenkosten und
 * Kaution sind Verwaltungsdaten, eine Mietabrechnung entsteht daraus nicht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property int $customer_id
 * @property RentalTenancyStatus $status
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property Money|null $rent_amount
 * @property Money|null $ancillary_amount
 * @property Money|null $deposit_amount
 * @property CurrencyCode $currency
 */
class RentalTenancy extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Rental\RentalTenancyFactory> */
    use HasFactory;
    use HasSqid;

    /** Wie der Spaltenstandard, damit ein ungeladenes Modell die Währung kennt. */
    protected $attributes = ['currency' => 'EUR'];

    protected $fillable = [
        'organization_id', 'asset_id', 'customer_id', 'status', 'starts_on', 'ends_on',
        'rent_amount', 'ancillary_amount', 'deposit_amount', 'currency', 'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => RentalTenancyStatus::class,
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'rent_amount' => MoneyCast::class . ':currency,2',
        'ancillary_amount' => MoneyCast::class . ':currency,2',
        'deposit_amount' => MoneyCast::class . ':currency,2',
        'currency' => CurrencyCode::class,
    ];

    /**
     * Bestätigte Mietverhältnisse, die den Zeitraum berühren; ohne Ende reicht
     * jedes spätere Datum.
     *
     * @param Builder<self> $query
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, ?CarbonImmutable $to): void {
        $query->where('status', RentalTenancyStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', DateRange::day($from)));
        if ($to !== null) {
            $query->where('starts_on', '<', DateRange::dayAfter($to));
        }
    }

    /** Ausgezogen: bestätigt, aber der letzte Miettag liegt vor dem Stichtag. */
    public function hasEndedBy(CarbonImmutable $day): bool {
        return $this->status === RentalTenancyStatus::Active && $this->ends_on !== null && $this->ends_on->lt($day->startOfDay());
    }

    /** Warmmiete: Kaltmiete plus Nebenkosten, null ohne Kaltmiete. */
    public function totalRent(): ?Money {
        if ($this->rent_amount === null) {
            return null;
        }

        return $this->ancillary_amount !== null ? $this->rent_amount->plus($this->ancillary_amount) : $this->rent_amount;
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }
}
