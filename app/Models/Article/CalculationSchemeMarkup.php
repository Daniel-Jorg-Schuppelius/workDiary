<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalculationSchemeMarkup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Article;

use App\Casts\PercentageCast;
use App\Enums\Article\CostKind;
use App\Models\Concerns\BelongsToOrganization;
use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zuschläge einer Kostenart im Kalkulationsschema (MVP-1055).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $calculation_scheme_id
 * @property CostKind $cost_kind
 * @property Percentage $site_overhead_percent
 * @property Percentage $general_overhead_percent
 * @property Percentage $risk_profit_percent
 */
class CalculationSchemeMarkup extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'calculation_scheme_id',
        'cost_kind',
        'site_overhead_percent',
        'general_overhead_percent',
        'risk_profit_percent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'cost_kind' => CostKind::class,
        'site_overhead_percent' => PercentageCast::class . ':2',
        'general_overhead_percent' => PercentageCast::class . ':2',
        'risk_profit_percent' => PercentageCast::class . ':2',
    ];

    /** @return BelongsTo<CalculationScheme, $this> */
    public function scheme(): BelongsTo {
        return $this->belongsTo(CalculationScheme::class, 'calculation_scheme_id');
    }

    /** Summe der drei Zuschläge — sie wirken additiv auf die Einzelkosten der Teilleistung. */
    public function totalPercent(): Percentage {
        return Percentage::of(\CommonToolkit\Helper\Data\NumberHelper::toUSFormat(
            (float) $this->site_overhead_percent->getNumericValue()
            + (float) $this->general_overhead_percent->getNumericValue()
            + (float) $this->risk_profit_percent->getNumericValue(),
            2,
        ));
    }
}
