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

use Database\Factories\PerDiemRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $country  ISO 3166-1 alpha-2
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property string $full_day_amount
 * @property string $partial_day_amount
 * @property string|null $overnight_amount
 * @property string $currency
 * @property string|null $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PerDiemRate extends Model {
    /** @use HasFactory<PerDiemRateFactory> */
    use HasFactory;

    protected $fillable = [
        'country',
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
        'valid_from' => 'date',
        'valid_to' => 'date',
        'full_day_amount' => 'decimal:2',
        'partial_day_amount' => 'decimal:2',
        'overnight_amount' => 'decimal:2',
    ];

    protected static function booted(): void {
        static::saving(function (PerDiemRate $rate): void {
            $rate->country = Str::upper($rate->country);
            $rate->currency = Str::upper($rate->currency);
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
