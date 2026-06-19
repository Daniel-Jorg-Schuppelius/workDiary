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

use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use RuntimeException;

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
 */
class StockMovement extends Model {
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
        'stock_state' => StockState::class,
        'ownership_type' => OwnershipType::class,
        'movement_type' => StockMovementType::class,
        'qty_base' => 'decimal:4',
        'original_qty' => 'decimal:4',
        'cost_unit' => 'decimal:4',
        'cost_total' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::updating(static function (): void {
            throw new RuntimeException('Lagerbewegungen sind append-only und dürfen nicht geändert werden (nur Gegenbuchung).');
        });
        static::deleting(static function (): void {
            throw new RuntimeException('Lagerbewegungen sind append-only und dürfen nicht gelöscht werden (nur Gegenbuchung).');
        });
    }

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
