<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleSupply.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bezugsquelle eines Artikels bei einem Lieferanten (Feature 048, E4).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_id
 * @property int $supplier_id
 * @property string|null $supplier_sku
 * @property numeric-string $moq
 * @property numeric-string $pack_size
 * @property int $lead_time_days
 * @property \CommonToolkit\ValueObjects\Money|null $purchase_price
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property bool $is_preferred
 */
class ArticleSupply extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'article_id',
        'supplier_id',
        'supplier_sku',
        'moq',
        'pack_size',
        'lead_time_days',
        'purchase_price',
        'currency',
        'is_preferred',
    ];

    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'moq' => 'decimal:4',
        'pack_size' => 'decimal:4',
        'lead_time_days' => 'integer',
        'purchase_price' => MoneyCast::class . ':currency,4',
        'is_preferred' => 'boolean',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }
}
