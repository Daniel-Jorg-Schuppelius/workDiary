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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eurostat-Referenz: monatlicher gesetzlicher Mindestlohn je Land und
 * Halbjahres-Stichtag. Global (nicht mandantengebunden).
 *
 * @property int $id
 * @property string $country
 * @property Carbon $valid_from
 * @property string $monthly_amount
 * @property string $currency
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
        'valid_from' => 'date',
        'monthly_amount' => 'decimal:2',
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
