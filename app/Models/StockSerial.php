<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockSerial.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Inventory\{SerialSource, SerialStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einzelseriennummer mit lückenlosem Lebenslauf (Feature 047/048, E2).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_id
 * @property int $article_variant_id
 * @property string $serial_no
 * @property SerialStatus $status
 * @property SerialSource $source
 * @property int|null $warehouse_id
 * @property int|null $customer_id
 * @property int|null $manufacturing_order_id
 * @property int|null $stock_delivery_id
 * @property string|null $blocked_reason
 */
class StockSerial extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'article_id',
        'article_variant_id',
        'serial_no',
        'status',
        'source',
        'warehouse_id',
        'customer_id',
        'manufacturing_order_id',
        'stock_delivery_id',
        'blocked_reason',
        'note',
        'shipped_at',
        'created_by',
    ];

    protected $casts = [
        'status' => SerialStatus::class,
        'source' => SerialSource::class,
        'shipped_at' => 'datetime',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function manufacturingOrder(): BelongsTo {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    /** @return BelongsTo<StockDelivery, $this> */
    public function delivery(): BelongsTo {
        return $this->belongsTo(StockDelivery::class, 'stock_delivery_id');
    }
}
