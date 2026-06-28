<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItemMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Verknüpfung einer LV-Position mit dem kanonischen Stamm (Feature 049,
 * MVP-083): Artikel, Material oder Leistung — polymorph, nie autoritativ für
 * Preis/Bestand. `factor` rechnet die LV-Einheit auf die Stamm-Einheit um.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $boq_item_id
 * @property string $mappable_type
 * @property int $mappable_id
 * @property string $factor
 * @property string|null $note
 */
class BoqItemMapping extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'boq_item_id',
        'mappable_type',
        'mappable_id',
        'factor',
        'note',
        'created_by',
    ];

    protected $casts = [
        'factor' => 'decimal:4',
    ];

    /** @return BelongsTo<BoqItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }

    /** @return MorphTo<Model, $this> */
    public function mappable(): MorphTo {
        return $this->morphTo();
    }
}
