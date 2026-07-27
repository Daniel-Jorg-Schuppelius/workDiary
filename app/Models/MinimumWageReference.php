<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MinimumWageReference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Carbon;

/**
 * Eurostat-Referenz: monatlicher gesetzlicher Mindestlohn je Land und
 * Halbjahres-Stichtag. Global (nicht mandantengebunden).
 *
 * @property int $id
 * @property string $country
 * @property Carbon $valid_from
 * @property \CommonToolkit\ValueObjects\Money|null $monthly_amount
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property string $source
 */
class MinimumWageReference extends Model {
    protected $fillable = [
        'country',
        'valid_from',
        'monthly_amount',
        'currency',
        'source',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'valid_from' => 'date',
        'monthly_amount' => MoneyCast::class . ':currency,2',
    ];

    /**
     * Jüngster Referenz-Wert eines Landes (am Stichtag bzw. heute gültig).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLatestForCountry(Builder $query, string $country): Builder {
        return $query->where('country', strtoupper($country))->orderByDesc('valid_from');
    }
}
