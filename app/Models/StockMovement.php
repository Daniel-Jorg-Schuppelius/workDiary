<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockMovement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, QuantityCast};
use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Models\Concerns\{AppendOnly, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Eine Zeile im append-only Lagerjournal (Feature 048, MVP-067). Bestätigte
 * Bewegungen sind UNVERÄNDERLICH: Updates und Deletes werden auf Modellebene
 * blockiert; Korrekturen erfolgen ausschließlich über eine referenzierte
 * Gegenbuchung (StockMovementType::Correction).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $article_variant_id
 * @property int $warehouse_id
 * @property StockState $stock_state
 * @property OwnershipType $ownership_type
 * @property StockMovementType $movement_type
 * @property string $qty_base
 * @property \CommonToolkit\ValueObjects\Money|null $cost_unit
 * @property \CommonToolkit\ValueObjects\Money|null $cost_total
 * @property \CommonToolkit\ValueObjects\Quantity|null $original_qty
 */
class StockMovement extends Model {
    // Korrekturen nur über referenzierte Gegenbuchung (StockMovementType::Correction).
    use AppendOnly;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'article_variant_id',
        'warehouse_id',
        'stock_lot_id',
        'stock_serial_id',
        'stock_state',
        'ownership_type',
        'owner_ref',
        'movement_type',
        'qty_base',
        'original_qty',
        'original_unit',
        'occurred_at',
        'actor_user_id',
        'source_type',
        'source_id',
        'idempotency_key',
        'cost_unit',
        'cost_total',
        'currency',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'stock_state' => StockState::class,
        'ownership_type' => OwnershipType::class,
        'movement_type' => StockMovementType::class,
        'qty_base' => 'decimal:4',
        'original_qty' => QuantityCast::class . ':original_unit,4',
        'cost_unit' => MoneyCast::class . ':currency,4',
        'cost_total' => MoneyCast::class . ':currency,4',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }
}
