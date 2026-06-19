<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockReservation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Inventory\{OwnershipType, ReservationStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Bestandsreservierung als eigene Entität (Feature 048, MVP-068): hält
 * verfügbare Menge mit Priorität und fachlicher Quelle; offene Menge =
 * quantity − consumed_qty.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property numeric-string $quantity
 * @property numeric-string $consumed_qty
 * @property ReservationStatus $status
 * @property int $priority
 */
class StockReservation extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'article_variant_id',
        'warehouse_id',
        'quantity',
        'consumed_qty',
        'ownership_type',
        'owner_ref',
        'status',
        'priority',
        'source_type',
        'source_id',
        'reserved_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'decimal:4',
        'consumed_qty' => 'decimal:4',
        'ownership_type' => OwnershipType::class,
        'status' => ReservationStatus::class,
        'priority' => 'integer',
        'reserved_at' => 'datetime',
    ];

    /**
     * Noch offene (nicht verbrauchte) reservierte Menge.
     *
     * @return numeric-string
     */
    public function openQuantity(): string {
        return bcsub($this->quantity, $this->consumed_qty, 4);
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
