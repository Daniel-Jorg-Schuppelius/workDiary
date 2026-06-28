<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PricingChangeAlert.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kalkulationswarnung bei einer Einkaufspreisänderung (Feature 050, MVP-094).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_catalog_item_id
 * @property int $article_id
 * @property int|null $supplier_id
 * @property numeric-string|null $old_purchase_price
 * @property numeric-string $new_purchase_price
 * @property numeric-string $sale_price
 * @property numeric-string $new_margin
 * @property numeric-string|null $min_margin
 * @property string $status
 */
class PricingChangeAlert extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    protected $fillable = [
        'organization_id',
        'supplier_catalog_item_id',
        'article_id',
        'supplier_id',
        'old_purchase_price',
        'new_purchase_price',
        'sale_price',
        'new_margin',
        'min_margin',
        'status',
        'acknowledged_by',
        'acknowledged_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'old_purchase_price' => 'decimal:4',
        'new_purchase_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'new_margin' => 'decimal:3',
        'min_margin' => 'decimal:3',
        'acknowledged_at' => 'datetime',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<SupplierCatalogItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(SupplierCatalogItem::class, 'supplier_catalog_item_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }
}
