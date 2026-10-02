<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalculationScheme.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Article;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Concerns\BelongsToOrganization;
use CommonToolkit\ValueObjects\{Money, Percentage};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kalkulationsschema einer Organisation (MVP-1055): Mittellohn (gepflegt oder
 * aus den Lohngruppen), lohngebundene Kosten in Prozent, Lohnnebenkosten je
 * Stunde und je Kostenart die Zuschläge für Baustellengemeinkosten,
 * allgemeine Geschäftskosten sowie Wagnis und Gewinn — die Angaben des
 * EFB-Formblatts 221.
 *
 * @property int $id
 * @property int $organization_id
 * @property Money|null $average_wage_amount
 * @property Percentage $wage_related_percent
 * @property Money $wage_ancillary_amount
 * @property string $currency
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CalculationSchemeMarkup> $markups
 */
class CalculationScheme extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'average_wage_amount',
        'wage_related_percent',
        'wage_ancillary_amount',
        'currency',
        'updated_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['currency' => 'EUR', 'wage_related_percent' => '0', 'wage_ancillary_amount' => '0'];

    /** @var array<string, string> */
    protected $casts = [
        'average_wage_amount' => MoneyCast::class . ':currency,2',
        'wage_related_percent' => PercentageCast::class . ':2',
        'wage_ancillary_amount' => MoneyCast::class . ':currency,2',
    ];

    /** @return HasMany<CalculationSchemeMarkup, $this> */
    public function markups(): HasMany {
        return $this->hasMany(CalculationSchemeMarkup::class);
    }
}
