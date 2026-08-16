<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogProductGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Warengruppe einer Katalogquelle (Feature 107, DATANORM S-Satz):
 * Hauptwarengruppe (`group` = null) bzw. Warengruppe mit Klartext-Label.
 * Katalogartikel tragen weiterhin den Code in `category`; das Label dient der
 * Anzeige und Auswertung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_catalog_source_id
 * @property string $main_group
 * @property string|null $group
 * @property string $label
 */
class SupplierCatalogProductGroup extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'supplier_catalog_source_id',
        'main_group',
        'group',
        'label',
    ];

    /** @return BelongsTo<SupplierCatalogSource, $this> */
    public function source(): BelongsTo {
        return $this->belongsTo(SupplierCatalogSource::class, 'supplier_catalog_source_id');
    }
}
