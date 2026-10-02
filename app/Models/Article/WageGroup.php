<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WageGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Article;

use App\Casts\MoneyCast;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Lohngruppe der Kalkulation (MVP-1055), z. B. Meister, Geselle, Helfer:
 * Stundenlohn und Kopfzahl — gewichtet ergeben sie den Mittellohn des
 * Kalkulationsschemas.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property Money $hourly_wage_amount
 * @property string $currency
 * @property int $headcount
 * @property bool $is_active
 * @property int $position
 * @property int|null $created_by
 */
class WageGroup extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'hourly_wage_amount',
        'currency',
        'headcount',
        'is_active',
        'position',
        'created_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['currency' => 'EUR', 'headcount' => 1, 'is_active' => true];

    /** @var array<string, string> */
    protected $casts = [
        'hourly_wage_amount' => MoneyCast::class . ':currency,2',
        'headcount' => 'integer',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];
}
