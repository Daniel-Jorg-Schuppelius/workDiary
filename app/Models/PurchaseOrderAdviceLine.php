<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderAdviceLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis-Position (Feature 048, E4): angekündigte Menge zu einer Bestellzeile.
 *
 * @property int $id
 * @property int $purchase_order_advice_id
 * @property int $purchase_order_line_id
 * @property numeric-string $qty
 */
class PurchaseOrderAdviceLine extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'purchase_order_advice_id',
        'purchase_order_line_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
    ];

    /** @return BelongsTo<PurchaseOrderAdvice, $this> */
    public function advice(): BelongsTo {
        return $this->belongsTo(PurchaseOrderAdvice::class, 'purchase_order_advice_id');
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function line(): BelongsTo {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }
}
