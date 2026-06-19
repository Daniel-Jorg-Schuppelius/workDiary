<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockLot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
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
 * @property string $status
 */
class StockLot extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_MERGED = 'merged';

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
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }
}
