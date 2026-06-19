<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auslieferung eines Fertigerzeugnisses (Feature 047, MVP-074). Lager- und
 * Faktura-Status sind getrennt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property numeric-string $quantity
 * @property string $stock_status
 * @property DeliveryFacturationStatus $facturation_status
 */
class StockDelivery extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'manufacturing_order_id',
        'article_variant_id',
        'warehouse_id',
        'customer_id',
        'quantity',
        'unit',
        'sku_snapshot',
        'name_snapshot',
        'unit_price_snapshot',
        'currency',
        'stock_status',
        'facturation_status',
        'facturation_target',
        'external_id',
        'delivered_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price_snapshot' => 'decimal:4',
        'facturation_status' => DeliveryFacturationStatus::class,
        'delivered_at' => 'datetime',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function order(): BelongsTo {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id');
    }
}
