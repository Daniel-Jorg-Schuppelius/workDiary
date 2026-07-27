<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, QuantityCast};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bestellzeile (Feature 048, E4).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $purchase_order_id
 * @property int $article_id
 * @property int|null $article_variant_id
 * @property \CommonToolkit\ValueObjects\Quantity|null $ordered_qty
 * @property \CommonToolkit\ValueObjects\Quantity|null $received_qty
 * @property string $unit
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property-read Article $article
 * @property-read ArticleVariant|null $variant
 */
class PurchaseOrderLine extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'purchase_order_id',
        'article_id',
        'article_variant_id',
        'supplier_sku',
        'description',
        'note',
        'ordered_qty',
        'received_qty',
        'unit',
        'unit_price',
        'currency',
    ];

    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'ordered_qty' => QuantityCast::class . ':unit,4',
        'received_qty' => QuantityCast::class . ':unit,4',
        'unit_price' => MoneyCast::class . ':currency,4',
    ];

    /** Noch offene Bestellmenge (>= 0). @return numeric-string */
    public function openQty(): string {
        $open = bcsub($this->ordered_qty?->getNumericValue() ?? '0', $this->received_qty?->getNumericValue() ?? '0', 4);

        return bccomp($open, '0', 4) < 0 ? '0.0000' : $open;
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }
}
