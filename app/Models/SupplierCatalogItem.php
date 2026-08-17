<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\Procurement\CatalogItemStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Externer Katalogartikel eines Lieferanten (Feature 050, MVP-092). Snapshot
 * der Lieferantendaten — kein Teil des kanonischen Artikelstamms, bis er
 * explizit verknüpft wird (MVP-093).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_catalog_source_id
 * @property int $supplier_id
 * @property string $external_no
 * @property string|null $manufacturer_no
 * @property string|null $gtin
 * @property string|null $classification_system
 * @property string|null $classification_code
 * @property string|null $image_url
 * @property string|null $datasheet_url
 * @property string $name
 * @property \CommonToolkit\ValueObjects\Money|null $purchase_price
 * @property \CommonToolkit\ValueObjects\Money|null $list_price
 * @property array<string, mixed>|null $extra_attributes Attribut-Werte plus DATANORM-Metadaten (Nachfolger, vorgemerkte Preisstände — Feature 107)
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property CatalogItemStatus $status
 * @property string $raw_hash
 * @property int|null $article_id
 * @property int|null $article_variant_id
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 */
class SupplierCatalogItem extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'supplier_catalog_source_id',
        'supplier_id',
        'external_no',
        'manufacturer_no',
        'manufacturer',
        'brand',
        'gtin',
        'matchcode',
        'category',
        'classification_system',
        'classification_code',
        'name',
        'description',
        'product_url',
        'image_url',
        'datasheet_url',
        'purchase_price',
        'list_price',
        'currency',
        'pack_size',
        'base_qty',
        'unit',
        'discount_group',
        'price_type',
        'price_unit_amount',
        'availability',
        'lead_time_days',
        'extra_attributes',
        'status',
        'raw_hash',
        'article_id',
        'article_variant_id',
        'last_seen_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'status' => CatalogItemStatus::class,
        'purchase_price' => MoneyCast::class . ':currency,4',
        'list_price' => MoneyCast::class . ':currency,4',
        'extra_attributes' => 'array',
        'pack_size' => 'decimal:4',
        'base_qty' => 'decimal:4',
        'price_unit_amount' => 'integer',
        'lead_time_days' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    /** @return BelongsTo<SupplierCatalogSource, $this> */
    public function source(): BelongsTo {
        return $this->belongsTo(SupplierCatalogSource::class, 'supplier_catalog_source_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return HasMany<SupplierCatalogItemPrice, $this> */
    public function prices(): HasMany {
        return $this->hasMany(SupplierCatalogItemPrice::class)->orderByDesc('captured_at');
    }

    /** @return HasMany<SupplierCatalogItemPriceTier, $this> */
    public function priceTiers(): HasMany {
        return $this->hasMany(SupplierCatalogItemPriceTier::class)->orderBy('min_qty');
    }
}
