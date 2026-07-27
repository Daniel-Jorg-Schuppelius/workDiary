<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PricingMarginRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, PercentageCast};
use App\Enums\Procurement\PriceRounding;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Margenregel für Verkaufspreisvorschläge (Feature 050, MVP-095).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int|null $supplier_id
 * @property string|null $category
 * @property \CommonToolkit\ValueObjects\Percentage|null $markup_percent
 * @property \CommonToolkit\ValueObjects\Percentage|null $target_margin
 * @property \CommonToolkit\ValueObjects\Percentage|null $min_margin
 * @property \CommonToolkit\ValueObjects\Money|null $min_sale_price
 * @property PriceRounding $rounding
 * @property int $priority
 * @property bool $active
 */
class PricingMarginRule extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'supplier_id',
        'category',
        'markup_percent',
        'target_margin',
        'min_margin',
        'min_sale_price',
        'rounding',
        'priority',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'markup_percent' => PercentageCast::class . ':3',
        'target_margin' => PercentageCast::class . ':3',
        'min_margin' => PercentageCast::class . ':3',
        'min_sale_price' => MoneyCast::class . ':currency,4',
        'rounding' => PriceRounding::class,
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }
}
