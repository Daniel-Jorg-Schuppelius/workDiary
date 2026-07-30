<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerFareTariff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Passenger;

use App\Enums\Passenger\RideOperationMode;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Versioniertes Tarifgebiet (MVP-456, Konzept §8): Grund-/Km-/Zeitpreise,
 * Zuschlagsregeln und Festpreiskorridor mit Gültigkeitszeitraum — Muster der
 * Phase-23-Regelkataloge (Stichtags-Lookup, Vorgänge frieren ihren Stand als
 * Snapshot ein).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $tariff_area
 * @property RideOperationMode $operation_mode
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property numeric-string $base_price
 * @property numeric-string $price_per_km
 * @property numeric-string $price_per_minute
 * @property numeric-string|null $min_price
 * @property numeric-string|null $fixed_price_min_percent
 * @property numeric-string|null $fixed_price_max_percent
 * @property CurrencyCode $currency
 * @property bool $active
 */
class PassengerFareTariff extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'tariff_area',
        'operation_mode',
        'valid_from',
        'valid_until',
        'base_price',
        'price_per_km',
        'price_per_minute',
        'min_price',
        'fixed_price_min_percent',
        'fixed_price_max_percent',
        'currency',
        'active',
    ];

    protected $casts = [
        'operation_mode' => RideOperationMode::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
        'base_price' => 'decimal:4',
        'price_per_km' => 'decimal:4',
        'price_per_minute' => 'decimal:4',
        'min_price' => 'decimal:4',
        'fixed_price_min_percent' => 'decimal:3',
        'fixed_price_max_percent' => 'decimal:3',
        'currency' => CurrencyCode::class,
        'active' => 'boolean',
    ];

    /** @return HasMany<PassengerFareTariffRule, $this> */
    public function rules(): HasMany {
        return $this->hasMany(PassengerFareTariffRule::class, 'tariff_id')->orderBy('sort_order');
    }

    /** @param Builder<PassengerFareTariff> $query */
    public function scopeValidFor(Builder $query, RideOperationMode $mode, ?\DateTimeInterface $date = null): void {
        $day = Carbon::instance($date instanceof \DateTimeInterface ? $date : now())->startOfDay()->toDateString();

        $query->where('active', true)
            ->where('operation_mode', $mode->value)
            ->where('valid_from', '<=', $day)
            ->where(fn(Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $day));
    }

    /**
     * Unveränderlicher Tarif-Snapshot für die Fahrtakte (Konzept §8:
     * „vor Fahrtbeginn eingefroren").
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array {
        return [
            'tariff_id' => $this->id,
            'name' => $this->name,
            'tariff_area' => $this->tariff_area,
            'operation_mode' => $this->operation_mode->value,
            'valid_from' => $this->valid_from->toDateString(),
            'base_price' => (string) $this->base_price,
            'price_per_km' => (string) $this->price_per_km,
            'price_per_minute' => (string) $this->price_per_minute,
            'min_price' => $this->min_price !== null ? (string) $this->min_price : null,
            'fixed_price_min_percent' => $this->fixed_price_min_percent !== null ? (string) $this->fixed_price_min_percent : null,
            'fixed_price_max_percent' => $this->fixed_price_max_percent !== null ? (string) $this->fixed_price_max_percent : null,
            'currency' => $this->currency->value,
            'rules' => $this->rules->map(fn(PassengerFareTariffRule $rule): array => $rule->snapshot())->all(),
        ];
    }

    /**
     * Fahrpreis nach Tarif (Grund + km + Zeit, Mindestpreis beachtet).
     *
     * @return numeric-string
     */
    public function calculate(string $km, int $seconds): string {
        $price = $this->numeric($this->base_price);
        $price = bcadd($price, bcmul($this->numeric($this->price_per_km), $this->numeric($km), 6), 6);
        $price = bcadd($price, bcmul($this->numeric($this->price_per_minute), bcdiv((string) $seconds, '60', 6), 6), 6);

        $minPrice = $this->min_price !== null ? $this->numeric($this->min_price) : null;
        if ($minPrice !== null && bccomp($price, $minPrice, 6) < 0) {
            $price = $minPrice;
        }

        return bcadd($price, '0', 2);
    }

    /**
     * Dezimal-Casts liefern `string`; bcmath verlangt `numeric-string`.
     * Nicht-numerische Werte gelten als 0 (defensiv, nie Exception im Preis).
     *
     * @return numeric-string
     */
    private function numeric(mixed $value): string {
        $raw = trim((string) $value);

        return is_numeric($raw) ? $raw : '0';
    }

    /**
     * Liegt ein vereinbarter Festpreis im behördlich zulässigen Korridor um
     * den Tarifpreis? Ohne Korridorregeln ist jeder Festpreis zulässig.
     */
    public function fixedPriceWithinCorridor(string $fixedPrice, string $tariffPrice): bool {
        if ($this->fixed_price_min_percent === null && $this->fixed_price_max_percent === null) {
            return true;
        }
        $tariffPrice = $this->numeric($tariffPrice);
        if (bccomp($tariffPrice, '0', 2) <= 0) {
            return true;
        }

        $ratio = bcmul(bcdiv($this->numeric($fixedPrice), $tariffPrice, 8), '100', 4);
        $min = $this->fixed_price_min_percent !== null ? $this->numeric($this->fixed_price_min_percent) : null;
        $max = $this->fixed_price_max_percent !== null ? $this->numeric($this->fixed_price_max_percent) : null;

        return ($min === null || bccomp($ratio, $min, 4) >= 0)
            && ($max === null || bccomp($ratio, $max, 4) <= 0);
    }
}
