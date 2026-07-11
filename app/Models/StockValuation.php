<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockValuation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Laufende Bewertung je Variante/Lagerort (Feature 048, MVP-070): gleitender
 * Durchschnittspreis und bewertete Menge.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property numeric-string $avg_cost
 * @property numeric-string $qty_on_hand
 */
class StockValuation extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'article_variant_id',
        'warehouse_id',
        'avg_cost',
        'qty_on_hand',
        'currency',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'avg_cost' => 'decimal:4',
        'qty_on_hand' => 'decimal:4',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }
}
