<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, PercentageCast, QuantityCast};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $timesheet_id
 * @property int|null $material_id
 * @property int|null $asset_id
 * @property string $description
 * @property \CommonToolkit\ValueObjects\Quantity|null $quantity
 * @property string $unit
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property \CommonToolkit\ValueObjects\Money|null $line_total_net
 * @property bool $billed
 * @property-read \App\Models\Material|null $material
 */
class MaterialUsage extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'timesheet_id',
        'material_id',
        'asset_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_rate',
        'line_total_net',
        'billed',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => QuantityCast::class . ':unit,3',
        'unit_price' => MoneyCast::class . ':currency,4',
        'tax_rate' => PercentageCast::class . ':2',
        'line_total_net' => MoneyCast::class . ':currency,2',
        'billed' => 'boolean',
    ];

    protected static function booted(): void {
        static::saving(function (MaterialUsage $usage): void {
            $qty = ($usage->quantity?->getValue()->toFloat() ?? 0.0);
            $price = $usage->unit_price ?? Money::zero(CurrencyCode::Euro);
            $usage->line_total_net = $price->times($qty)->withScale(2);
        });
    }

    /** @return BelongsTo<Timesheet, $this> */
    public function timesheet(): BelongsTo {
        return $this->belongsTo(Timesheet::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }
}
