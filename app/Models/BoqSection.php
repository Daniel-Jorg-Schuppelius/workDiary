<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Abschnittsknoten der LV-Hierarchie (Feature 049, MVP-082): trägt eine
 * Ordnungszahl-Ebene (Los/Titel/Untertitel) und verschachtelt sich selbst.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bill_of_quantity_id
 * @property int|null $parent_id
 * @property string $reference_no
 * @property string|null $label
 * @property string|null $external_id
 * @property int $position
 */
class BoqSection extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bill_of_quantity_id',
        'parent_id',
        'reference_no',
        'label',
        'external_id',
        'totals',
        'position',
    ];

    protected $casts = [
        'totals' => 'array',
        'position' => 'integer',
    ];

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }

    /** @return BelongsTo<BoqSection, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(BoqSection::class, 'parent_id');
    }

    /** @return HasMany<BoqSection, $this> */
    public function children(): HasMany {
        return $this->hasMany(BoqSection::class, 'parent_id');
    }

    /** @return HasMany<BoqItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(BoqItem::class);
    }
}
