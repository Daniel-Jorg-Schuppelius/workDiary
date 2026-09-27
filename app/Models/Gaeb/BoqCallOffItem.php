<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCallOffItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Gaeb;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abgerufene Menge einer Rahmen-LV-Position (MVP-931). Der Preis bleibt an
 * der LV-Position, daher keine Belegposition.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $boq_call_off_id
 * @property int $boq_item_id
 * @property numeric-string $quantity
 */
class BoqCallOffItem extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'boq_call_off_id', 'boq_item_id', 'quantity'];

    /** @var array<string, string> */
    protected $casts = ['quantity' => 'decimal:4'];

    /** @return BelongsTo<BoqCallOff, $this> */
    public function callOff(): BelongsTo {
        return $this->belongsTo(BoqCallOff::class, 'boq_call_off_id');
    }

    /** @return BelongsTo<BoqItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }
}
