<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogItemPriceTier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preisstaffel (Mengenstaffel) eines Katalogartikels (Feature 050).
 *
 * @property int $id
 * @property int $supplier_catalog_item_id
 * @property numeric-string $min_qty
 * @property numeric-string $unit_price
 * @property string $currency
 */
class SupplierCatalogItemPriceTier extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'supplier_catalog_item_id',
        'min_qty',
        'unit_price',
        'currency',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'min_qty' => 'decimal:4',
        'unit_price' => 'decimal:4',
    ];

    /** @return BelongsTo<SupplierCatalogItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(SupplierCatalogItem::class, 'supplier_catalog_item_id');
    }
}
