<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogItemPrice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historisierter Einkaufspreis eines Katalogartikels (Feature 050, MVP-094).
 * Ein neuer Snapshot entsteht beim ersten Import und bei jeder Preisänderung —
 * historische Vorgänge werden nie überschrieben. Mandantengrenze transitiv über
 * den Katalogartikel.
 *
 * @property int $id
 * @property int $supplier_catalog_item_id
 * @property numeric-string $purchase_price
 * @property string $currency
 * @property \Illuminate\Support\Carbon $captured_at
 */
class SupplierCatalogItemPrice extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'supplier_catalog_item_id',
        'purchase_price',
        'currency',
        'captured_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'purchase_price' => 'decimal:4',
        'captured_at' => 'datetime',
    ];

    /** @return BelongsTo<SupplierCatalogItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(SupplierCatalogItem::class, 'supplier_catalog_item_id');
    }
}
