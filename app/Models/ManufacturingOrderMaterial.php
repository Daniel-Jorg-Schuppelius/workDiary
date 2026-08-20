<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderMaterial.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\HasSqid;
use CommonToolkit\Enums\RoundingMode;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aufgelöster Materialbedarf eines Fertigungsauftrags (Feature 047, MVP-062/065):
 * Sollmenge, reservierte und verbrauchte Menge getrennt. Mandantengrenze
 * transitiv über den Auftrag.
 *
 * @property int $id
 * @property int $manufacturing_order_id
 * @property numeric-string $target_qty
 * @property numeric-string $reserved_qty
 * @property numeric-string $consumed_qty
 * @property RoundingMode|null $rounding
 * @property bool $is_tool
 * @property int|null $stock_reservation_id
 * @property \CommonToolkit\ValueObjects\Money|null $cost_snapshot
 * @property \CommonToolkit\ValueObjects\Money|null $actual_cost
 */
class ManufacturingOrderMaterial extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    // Audit 2026-08 (W3.3): Formulare/URLs tragen Sqids, nie rohe IDs.
    use HasSqid;

    protected $fillable = [
        'manufacturing_order_id',
        'article_id',
        'article_variant_id',
        'name_snapshot',
        'target_qty',
        'unit_snapshot',
        'reserved_qty',
        'consumed_qty',
        'stock_reservation_id',
        'cost_snapshot',
        'actual_cost',
        'calc_reason',
        'rounding',
        'is_tool',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'target_qty' => 'decimal:4',
        'reserved_qty' => 'decimal:4',
        'consumed_qty' => 'decimal:4',
        'cost_snapshot' => MoneyCast::class . ':currency,4',
        'actual_cost' => MoneyCast::class . ':currency,4',
        'rounding' => RoundingMode::class,
        'is_tool' => 'boolean',
    ];

    /** @var array<string, mixed> Default, damit actual_cost auch in-memory nie null ist. */
    protected $attributes = [
        'actual_cost' => 0,
    ];

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function order(): BelongsTo {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<StockReservation, $this> */
    public function reservation(): BelongsTo {
        return $this->belongsTo(StockReservation::class, 'stock_reservation_id');
    }
}
