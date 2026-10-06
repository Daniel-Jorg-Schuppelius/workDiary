<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockLot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\StockLotStatus;
use App\Models\Article\ArticleVariant;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Charge/Los einer Variante (Feature 047/048, E2).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_variant_id
 * @property string $lot_no
 * @property \Illuminate\Support\Carbon|null $best_before
 * @property StockLotStatus $status
 * @property string|null $blocked_reason
 * @property \Illuminate\Support\Carbon|null $blocked_at
 * @property int|null $blocked_by_user_id
 * @property int|null $merged_into_lot_id
 */
class StockLot extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'article_variant_id',
        'lot_no',
        'mfg_date',
        'best_before',
        'supplier_ref',
        'status',
        'note',
    ];

    protected $casts = [
        'mfg_date' => 'date',
        'best_before' => 'date',
        'status' => StockLotStatus::class,
        'blocked_at' => 'datetime',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function blockedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }

    /** @return BelongsTo<self, $this> */
    public function mergedInto(): BelongsTo {
        return $this->belongsTo(self::class, 'merged_into_lot_id');
    }
}
