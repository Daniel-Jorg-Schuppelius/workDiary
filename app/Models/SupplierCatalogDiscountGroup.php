<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogDiscountGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rabattgruppe einer Katalogquelle (Feature 107, DATANORM R-Satz): macht aus
 * Listenpreisen (Preisart „list") den Netto-EK. `kind` = discount (Prozent),
 * factor (Multiplikator) oder surcharge (Prozent-Zuschlag); `value` trägt den
 * Prozentsatz (20.0000 = 20 %) bzw. Faktor (0.9000).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_catalog_source_id
 * @property string $code
 * @property string $kind
 * @property numeric-string $value
 * @property string|null $label
 */
class SupplierCatalogDiscountGroup extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const KIND_DISCOUNT = 'discount';
    public const KIND_FACTOR = 'factor';
    public const KIND_SURCHARGE = 'surcharge';

    protected $fillable = [
        'organization_id',
        'supplier_catalog_source_id',
        'code',
        'kind',
        'value',
        'label',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'decimal:4',
    ];

    /** @return BelongsTo<SupplierCatalogSource, $this> */
    public function source(): BelongsTo {
        return $this->belongsTo(SupplierCatalogSource::class, 'supplier_catalog_source_id');
    }
}
