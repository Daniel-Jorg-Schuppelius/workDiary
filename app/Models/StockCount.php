<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockCount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Inventory\{StockCountStatus, StockCountType};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Stichtagsbezogene Inventur (Feature 048, MVP-069).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property StockCountStatus $status
 * @property int|null $reviewed_by
 */
class StockCount extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'status',
        'count_type',
        'counted_at',
        'note',
        'created_by',
        'reviewed_by',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => StockCountStatus::class,
        'count_type' => StockCountType::class,
        'counted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<StockCountLine, $this> */
    public function lines(): HasMany {
        return $this->hasMany(StockCountLine::class);
    }
}
