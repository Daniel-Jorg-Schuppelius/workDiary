<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\RecallItemStatus;
use App\Models\Claims\ClaimCase;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Betroffene Auslieferung (bzw. Seriennummer) einer Rückrufaktion (MVP-921).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $recall_id
 * @property int|null $stock_delivery_id
 * @property int|null $customer_id
 * @property int|null $stock_serial_id
 * @property int|null $claim_case_id
 * @property string|null $quantity
 * @property RecallItemStatus $status
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property \Illuminate\Support\Carbon|null $returned_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $note
 */
class RecallItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'recall_id', 'stock_delivery_id', 'customer_id', 'stock_serial_id', 'claim_case_id', 'quantity',
        'status', 'notified_at', 'returned_at', 'resolved_at', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => RecallItemStatus::class,
        'quantity' => 'decimal:4',
        'notified_at' => 'datetime',
        'returned_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<Recall, $this> */
    public function recall(): BelongsTo {
        return $this->belongsTo(Recall::class);
    }

    /** @return BelongsTo<StockDelivery, $this> */
    public function delivery(): BelongsTo {
        return $this->belongsTo(StockDelivery::class, 'stock_delivery_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<StockSerial, $this> */
    public function serial(): BelongsTo {
        return $this->belongsTo(StockSerial::class, 'stock_serial_id');
    }

    /**
     * Reklamation für den Rücklauf (MVP-922).
     *
     * @return BelongsTo<ClaimCase, $this>
     */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }
}
