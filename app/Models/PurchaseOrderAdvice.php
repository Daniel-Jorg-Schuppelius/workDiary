<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderAdvice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procurement\AdviceStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Lieferavis (ASN) zu einer Bestellung (Feature 048, E4).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $purchase_order_id
 * @property string|null $reference
 * @property AdviceStatus $status
 * @property \Illuminate\Support\Carbon|null $expected_at
 */
class PurchaseOrderAdvice extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $table = 'purchase_order_advices';

    protected $fillable = [
        'organization_id',
        'purchase_order_id',
        'reference',
        'carrier',
        'tracking',
        'expected_at',
        'shipped_at',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'status' => AdviceStatus::class,
        'expected_at' => 'date',
        'shipped_at' => 'datetime',
    ];

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return HasMany<PurchaseOrderAdviceLine, $this> */
    public function lines(): HasMany {
        return $this->hasMany(PurchaseOrderAdviceLine::class);
    }
}
