<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItemQuantitySplit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};

/**
 * Teilmenge einer LV-Position (Feature 109, MVP-588). Sie trägt eigene
 * Katalogzuordnungen: erst damit lässt sich eine Position anteilig auf mehrere
 * Kostengruppen, Gebäude oder Bauteile verteilen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $boq_item_id
 * @property string|null $quantity
 * @property string|null $percent
 * @property int $position
 */
class BoqItemQuantitySplit extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'boq_item_id',
        'quantity',
        'percent',
        'position',
    ];

    protected $casts = [
        // Dezimalstellen erhalten: Mengen und Anteile sind keine Ganzzahlen.
        'quantity' => 'decimal:4',
        'percent' => 'decimal:6',
        'position' => 'integer',
    ];

    /** @return BelongsTo<BoqItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }

    /** @return MorphMany<BoqCatalogAssignment, $this> */
    public function catalogAssignments(): MorphMany {
        return $this->morphMany(BoqCatalogAssignment::class, 'assignable');
    }
}
