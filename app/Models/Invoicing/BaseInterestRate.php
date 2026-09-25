<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BaseInterestRate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Basiszinssatz nach § 247 BGB ab einem Stichtag (MVP-879); installationsweite
 * Referenzdaten aus der Bundesbank-Reihe, nicht mandantenbezogen.
 *
 * @property int $id
 * @property Carbon $valid_from
 * @property string $rate
 * @property string $source
 */
class BaseInterestRate extends Model {
    protected $fillable = ['valid_from', 'rate', 'source'];

    /** @var array<string, string> */
    protected $casts = [
        'valid_from' => 'date',
        'rate' => 'decimal:2',
    ];
}
