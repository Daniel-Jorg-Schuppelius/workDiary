<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\DocumentLineKind;
use App\Models\Article\Article;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Contracts\AuditsChanges;
use App\Models\Inventory\StockDelivery;
use App\Models\Sales\OrderConfirmationItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position eines Lieferscheins (Phase 145, MVP-1163) — Menge ohne Preis.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $delivery_note_id
 * @property int|null $order_confirmation_item_id
 * @property int|null $stock_delivery_id
 * @property int|null $article_id
 * @property int $position
 * @property DocumentLineKind $line_kind
 * @property string|null $sku
 * @property string $description
 * @property string $quantity
 * @property string|null $unit
 * @property-read StockDelivery|null $stockDelivery
 */
class DeliveryNoteItem extends Model implements AuditsChanges {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Billing\DeliveryNoteItemFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'delivery_note_id', 'order_confirmation_item_id', 'stock_delivery_id', 'article_id',
        'position', 'line_kind', 'sku', 'description', 'quantity', 'unit',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['line_kind' => 'item'];

    /** @var array<string, string> */
    protected $casts = [
        'line_kind' => DocumentLineKind::class,
    ];

    /** @return BelongsTo<DeliveryNote, $this> */
    public function deliveryNote(): BelongsTo {
        return $this->belongsTo(DeliveryNote::class);
    }

    /** @return BelongsTo<StockDelivery, $this> */
    public function stockDelivery(): BelongsTo {
        return $this->belongsTo(StockDelivery::class);
    }

    /** @return BelongsTo<OrderConfirmationItem, $this> */
    public function orderConfirmationItem(): BelongsTo {
        return $this->belongsTo(OrderConfirmationItem::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
