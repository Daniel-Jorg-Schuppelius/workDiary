<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockValuationLayer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIFO-Zugangsschicht der Bestandsbewertung (Feature 048, E3).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_variant_id
 * @property int $warehouse_id
 * @property numeric-string $qty_remaining
 * @property numeric-string $unit_cost
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property int|null $source_movement_id
 */
class StockValuationLayer extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'article_variant_id',
        'warehouse_id',
        'stock_lot_id',
        'qty_remaining',
        'unit_cost',
        'currency',
        'source_movement_id',
        'acquired_at',
        'best_before',
    ];

    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'qty_remaining' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'acquired_at' => 'datetime',
        'best_before' => 'date',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }
}
