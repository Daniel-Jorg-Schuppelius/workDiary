<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NegativeStockRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\{NegativeStockRequestStatus, OwnershipType};
use App\Models\Article\ArticleVariant;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Antrag auf eine Entnahme in den Negativbestand (MVP-1626, Feature 048
 * „Berechtigungen und Freigaben“): verlangt die Organisation dafür vier Augen,
 * hält der Antrag die Buchung fest, bis eine zweite Person im
 * Genehmigungs-Eingang entscheidet.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_variant_id
 * @property int $warehouse_id
 * @property int|null $bin_id
 * @property int|null $stock_lot_id
 * @property OwnershipType $ownership_kind
 * @property string $quantity
 * @property string|null $entered_qty
 * @property string|null $entered_unit
 * @property NegativeStockRequestStatus $status
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $note
 * @property-read ArticleVariant $variant
 * @property-read Warehouse $warehouse
 * @property-read User|null $requester
 */
class NegativeStockRequest extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'article_variant_id', 'warehouse_id', 'bin_id', 'stock_lot_id', 'ownership_kind',
        'quantity', 'entered_qty', 'entered_unit', 'status', 'requested_by', 'decided_by', 'decided_at', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'ownership_kind' => OwnershipType::class,
        'status' => NegativeStockRequestStatus::class,
        'quantity' => 'decimal:4',
        'entered_qty' => 'decimal:4',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<WarehouseBin, $this> */
    public function bin(): BelongsTo {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    /** @return BelongsTo<StockLot, $this> */
    public function lot(): BelongsTo {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
