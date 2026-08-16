<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleSalePriceHistory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VK-Preisverlauf (Feature 107, W10): ein Eintrag je gesetztem/geändertem
 * Verkaufspreis eines Artikels bzw. einer Variante. Wird vom
 * {@see \App\Observers\ArticleSalePriceObserver} geschrieben; Grundlage des
 * DATPREIS-Exports „Änderungen seit Datum".
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_id
 * @property int|null $article_variant_id
 * @property \CommonToolkit\ValueObjects\Money|null $sale_price
 * @property \Illuminate\Support\Carbon $recorded_at
 */
class ArticleSalePriceHistory extends Model {
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'article_id',
        'article_variant_id',
        'sale_price',
        'currency',
        'recorded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sale_price' => MoneyCast::class . ':currency,4',
        'recorded_at' => 'datetime',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
