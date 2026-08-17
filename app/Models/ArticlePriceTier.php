<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticlePriceTier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verkaufs-Staffelpreis eines Artikels (Feature 107, MVP-605): ab `min_qty`
 * ersetzt `unit_price` den Standard-VK — Quelle der Staffelpreis-Z-Sätze im
 * eigenen DATANORM-Export. Währung ist die des Artikels.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_id
 * @property string $min_qty
 * @property string $unit_price
 */
class ArticlePriceTier extends Model {
    protected $fillable = [
        'organization_id',
        'article_id',
        'min_qty',
        'unit_price',
    ];

    protected $casts = [
        'min_qty' => 'decimal:2',
        'unit_price' => 'decimal:4',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
