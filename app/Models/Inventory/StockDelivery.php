<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Inventory;

use App\Casts\{MoneyCast, QuantityCast};
use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
use App\Models\Article\ArticleVariant;
use App\Models\Invoicing\InvoiceItem;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Shipping\Shipment;
use App\Models\Inventory\Warehouse;

/**
 * Auslieferung eines Fertigerzeugnisses (Feature 047, MVP-074). Lager- und
 * Faktura-Status sind getrennt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property \CommonToolkit\ValueObjects\Quantity|null $quantity
 * @property string $stock_status
 * @property DeliveryFacturationStatus $facturation_status
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price_snapshot
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
        'invoice_item_id',
        'delivered_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'quantity' => QuantityCast::class . ':unit,4',
        'unit_price_snapshot' => MoneyCast::class . ':currency,4',
        'facturation_status' => DeliveryFacturationStatus::class,
        'delivered_at' => 'datetime',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function order(): BelongsTo {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id');
    }

    /**
     * Aktive Reservierung durch genau einen Rechnungsposten (Feature 160,
     * MVP-858; Unique auf `invoice_item_id`).
     *
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }

    /**
     * Abrechnungshistorie: alle Posten, die diese Auslieferung je übernommen
     * haben (auch nach Freigabe oder Storno).
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function invoiceItems(): HasMany {
        return $this->hasMany(InvoiceItem::class, 'stock_delivery_id');
    }

    /** @return HasOne<Shipment, $this> Versandauftrag zu dieser Auslieferung (Feature 059, Rang 20). */
    public function shipment(): HasOne {
        return $this->hasOne(Shipment::class);
    }
}
