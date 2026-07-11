<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlStockSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Spiegelbestand der führenden JTL-Wawi (Feature 078, MVP-320): Cache mit
 * sichtbarem Datenalter. Der Provider nutzt ihn nur innerhalb der TTL —
 * danach wird live gelesen und der Snapshot erneuert. Nie Buchungsgrundlage.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_variant_id
 * @property int $warehouse_id
 * @property string $quantity_total
 * @property string $quantity_available
 * @property string $quantity_reserved
 * @property string $quantity_blocked
 * @property Carbon $fetched_at
 */
class JtlStockSnapshot extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'jtl_stock_snapshots';

    protected $fillable = [
        'organization_id',
        'article_variant_id',
        'warehouse_id',
        'quantity_total',
        'quantity_available',
        'quantity_reserved',
        'quantity_blocked',
        'fetched_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity_total' => 'decimal:4',
        'quantity_available' => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'quantity_blocked' => 'decimal:4',
        'fetched_at' => 'datetime',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    public function isFresh(int $ttlSeconds): bool {
        return $this->fetched_at->gt(now()->subSeconds($ttlSeconds));
    }
}
