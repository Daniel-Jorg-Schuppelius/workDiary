<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Katalog, auf den sich die Zuordnungen eines LV beziehen (Feature 109,
 * MVP-586). Der Typ trägt die Ausgabe der Norm — „310" bedeutet in DIN 276-1
 * 2008-12 etwas anderes als in DIN 276 2018-12.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bill_of_quantity_id
 * @property string $catalog_key
 * @property string|null $type
 * @property string|null $name
 * @property string|null $assign_type
 */
class BoqCatalog extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bill_of_quantity_id',
        'catalog_key',
        'type',
        'name',
        'assign_type',
    ];

    /** Ist das ein Kostengruppenkatalog nach DIN 276? */
    public function isCostGroup(): bool {
        return str_starts_with((string) $this->type, 'cost group');
    }

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }

    /** @return HasMany<BoqCatalogAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(BoqCatalogAssignment::class, 'catalog_key', 'catalog_key');
    }
}
