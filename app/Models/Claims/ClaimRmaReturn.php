<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimRmaReturn.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Enums\Claims\{ClaimRmaDisposition, ClaimRmaStatus};
use App\Models\{Article, ArticleVariant, StockLot, StockSerial, User, Warehouse};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * RMA-Rückläufer (MVP-250): Rücksendenummer, Wareneingang in Quarantäne
 * (Bestandszustand quality/blocked), Prüfung, Verwendungsentscheidung.
 * Bestandswirkung läuft ausschließlich über den InventoryLedger.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_case_id
 * @property string $rma_number
 * @property ClaimRmaStatus $status
 * @property \Illuminate\Support\Carbon|null $expected_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property string|null $serial_no
 * @property string|null $qty
 * @property string|null $stock_state
 * @property ClaimRmaDisposition|null $disposition
 * @property \Illuminate\Support\Carbon|null $disposed_at
 */
class ClaimRmaReturn extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'claim_case_id', 'rma_number', 'status',
        'expected_at', 'received_at', 'received_by', 'warehouse_id',
        'article_id', 'article_variant_id', 'stock_serial_id', 'stock_lot_id',
        'serial_no', 'qty', 'stock_state', 'condition_note', 'disposition',
        'disposition_note', 'disposed_at', 'disposed_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ClaimRmaStatus::class,
        'disposition' => ClaimRmaDisposition::class,
        'expected_at' => 'date',
        'received_at' => 'datetime',
        'disposed_at' => 'datetime',
        'qty' => 'decimal:4',
    ];

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<ArticleVariant, $this> */
    public function articleVariant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class);
    }

    /** @return BelongsTo<StockSerial, $this> */
    public function stockSerial(): BelongsTo {
        return $this->belongsTo(StockSerial::class);
    }

    /** @return BelongsTo<StockLot, $this> */
    public function stockLot(): BelongsTo {
        return $this->belongsTo(StockLot::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<ClaimInspection, $this> */
    public function inspections(): HasMany {
        return $this->hasMany(ClaimInspection::class, 'claim_rma_return_id');
    }
}
