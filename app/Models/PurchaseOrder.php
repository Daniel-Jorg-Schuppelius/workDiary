<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Bestellung (Feature 048, E4).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property int $supplier_id
 * @property int $warehouse_id
 * @property PurchaseOrderStatus $status
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 */
class PurchaseOrder extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'number',
        'supplier_id',
        'warehouse_id',
        'status',
        'currency',
        'freight_cost',
        'ordered_at',
        'expected_at',
        'note',
        'created_by',
    ];

    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'status' => PurchaseOrderStatus::class,
        'freight_cost' => 'decimal:4',
        'ordered_at' => 'datetime',
        'expected_at' => 'date',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /** @return HasMany<PurchaseOrderAdvice, $this> */
    public function advices(): HasMany {
        return $this->hasMany(PurchaseOrderAdvice::class);
    }
}
