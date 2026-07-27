<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, QuantityCast};
use App\Enums\Gaeb\{BoqItemStatus, BoqItemType};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * LV-Position (Feature 049, MVP-082). Trägt Ordnungszahl, Texte, Menge/Einheit
 * und einen phasenabhängigen Preis-Snapshot. Erzeugt keinen Artikelstamm —
 * optionale Verknüpfung folgt in MVP-083.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bill_of_quantity_id
 * @property int|null $boq_section_id
 * @property string $reference_no
 * @property string|null $item_no
 * @property BoqItemType $type
 * @property BoqItemStatus $status
 * @property string|null $short_text
 * @property string|null $long_text
 * @property \CommonToolkit\ValueObjects\Quantity|null $quantity
 * @property string|null $unit
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Money|null $total_price
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property bool $is_addendum
 * @property string|null $external_id
 * @property int $position
 */
class BoqItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'bill_of_quantity_id',
        'boq_section_id',
        'reference_no',
        'item_no',
        'type',
        'status',
        'short_text',
        'long_text',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'currency',
        'is_addendum',
        'external_id',
        'position',
    ];

    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'type' => BoqItemType::class,
        'status' => BoqItemStatus::class,
        'quantity' => QuantityCast::class . ':unit,4',
        'unit_price' => MoneyCast::class . ':currency,4',
        'total_price' => MoneyCast::class . ':currency,4',
        'is_addendum' => 'boolean',
        'position' => 'integer',
    ];

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }

    /** @return BelongsTo<BoqSection, $this> */
    public function section(): BelongsTo {
        return $this->belongsTo(BoqSection::class, 'boq_section_id');
    }

    /** @return HasMany<BoqItemPriceSnapshot, $this> */
    public function priceSnapshots(): HasMany {
        return $this->hasMany(BoqItemPriceSnapshot::class);
    }

    /** @return HasMany<BoqItemProgress, $this> */
    public function progress(): HasMany {
        return $this->hasMany(BoqItemProgress::class);
    }

    /** @return HasMany<BoqItemMapping, $this> */
    public function mappings(): HasMany {
        return $this->hasMany(BoqItemMapping::class);
    }

    /** Bereits gemeldete (aufgemessene) Menge — Summe aller Fortschrittsmeldungen. */
    public function executedQuantity(): float {
        return (float) $this->progress()->sum('quantity');
    }

    /** Restmenge gegenüber der Sollmenge (nie negativ). */
    public function remainingQuantity(): float {
        return max(0.0, ($this->quantity?->getValue()->toFloat() ?? 0.0)- $this->executedQuantity());
    }
}
