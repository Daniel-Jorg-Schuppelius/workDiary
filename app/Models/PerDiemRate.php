<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\PerDiemRateFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\{Carbon, Str};

/**
 * @property int $id
 * @property string $country  ISO 3166-1 alpha-2
 * @property string|null $region_label  Stadt/Region für Sondertarife (null = Standard)
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property \CommonToolkit\ValueObjects\Money|null $full_day_amount
 * @property \CommonToolkit\ValueObjects\Money|null $partial_day_amount
 * @property \CommonToolkit\ValueObjects\Money|null $overnight_amount
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property string|null $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PerDiemRate extends Model {
    /** @use HasFactory<PerDiemRateFactory> */
    use HasFactory;

    protected $fillable = [
        'country',
        'region_label',
        'valid_from',
        'valid_to',
        'full_day_amount',
        'partial_day_amount',
        'overnight_amount',
        'currency',
        'source',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'valid_from' => 'date',
        'valid_to' => 'date',
        'full_day_amount' => MoneyCast::class . ':currency,2',
        'partial_day_amount' => MoneyCast::class . ':currency,2',
        'overnight_amount' => MoneyCast::class . ':currency,2',
    ];

    protected static function booted(): void {
        static::saving(function (PerDiemRate $rate): void {
            $rate->country = Str::upper($rate->country);
            // currency ist enum-gecastet (CurrencyCode) — Kleinschreibung im
            // Roh-Attribut normalisieren, bevor der Cast ValueError wirft.
            $raw = $rate->getAttributes()['currency'] ?? null;
            if (is_string($raw)) {
                $rate->setRawAttributes(array_merge($rate->getAttributes(), ['currency' => Str::upper($raw)]));
            }
        });
    }

    /**
     * @param  Builder<PerDiemRate>  $query
     * @return Builder<PerDiemRate>
     */
    public function scopeForCountry(Builder $query, string $country): Builder {
        return $query->where('country', Str::upper($country));
    }

    /**
     * Region-Filter mit Fallback-Semantik:
     *  - $region === null: nur Standard-Sätze (region_label IS NULL).
     *  - $region !== null: exakte Region ODER Standard (region_label IS NULL).
     *
     * Wird typischerweise mit orderByRaw kombiniert, damit Region-Treffer
     * vor dem Standard-Fallback erscheinen.
     *
     * @param  Builder<PerDiemRate>  $query
     * @return Builder<PerDiemRate>
     */
    public function scopeForRegion(Builder $query, ?string $region): Builder {
        if ($region === null || $region === '') {
            return $query->whereNull('region_label');
        }

        return $query->where(function (Builder $q) use ($region): void {
            $q->where('region_label', $region)->orWhereNull('region_label');
        });
    }

    /**
     * @param  Builder<PerDiemRate>  $query
     * @return Builder<PerDiemRate>
     */
    public function scopeActiveOn(Builder $query, DateTimeInterface $date): Builder {
        return $query
            ->whereDate('valid_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            });
    }
}
