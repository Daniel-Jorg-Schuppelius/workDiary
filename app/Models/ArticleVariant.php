<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleVariant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Article\ArticleStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

/**
 * Artikelvariante (Feature 048, MVP-060): die bestands- und fertigungsführende
 * Einheit mit eindeutiger Optionskombination ({@see option_signature}), eigener
 * SKU/GTIN und optionalen Preis-Überschreibungen.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $article_id
 * @property string|null $sku
 * @property string $option_signature
 * @property ArticleStatus $status
 * @property bool $is_default
 */
class ArticleVariant extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'article_id',
        'sku',
        'gtin',
        'name',
        'status',
        'is_default',
        'option_signature',
        'purchase_price',
        'sale_price',
        'currency',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'status' => ArticleStatus::class,
        'is_default' => 'boolean',
        'purchase_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsToMany<ArticleOptionValue, $this> */
    public function optionValues(): BelongsToMany {
        return $this->belongsToMany(
            ArticleOptionValue::class,
            'article_variant_option_values',
            'article_variant_id',
            'article_option_value_id',
        );
    }

    /** @return HasMany<ExternalArticleMapping, $this> */
    public function externalMappings(): HasMany {
        return $this->hasMany(ExternalArticleMapping::class);
    }

    /** Effektiver Verkaufspreis: Variante überschreibt den Artikelstandard. */
    public function effectiveSalePrice(): ?string {
        $own = $this->getAttribute('sale_price');

        return $own !== null ? (string) $own : $this->article?->getAttribute('default_sale_price');
    }
}
