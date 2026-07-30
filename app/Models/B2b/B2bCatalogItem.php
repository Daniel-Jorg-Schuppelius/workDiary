<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bCatalogItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\B2b;

use App\Casts\MoneyCast;
use App\Models\Article;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artikel-Freigabe eines Punchout-Zugangs (Feature 099, MVP-457): nur explizit
 * freigegebene Artikel sind im Katalog-Browse sichtbar. `custom_price`
 * überschreibt den Standard-Verkaufspreis kundenindividuell.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $access_id
 * @property int $article_id
 * @property Money|null $custom_price
 */
class B2bCatalogItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'access_id',
        'article_id',
        'custom_price',
    ];

    protected $casts = [
        'custom_price' => MoneyCast::class . ':article.currency,4',
    ];

    /** @return BelongsTo<B2bCatalogAccess, $this> */
    public function access(): BelongsTo {
        return $this->belongsTo(B2bCatalogAccess::class, 'access_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** Wirksamer Katalogpreis: Kundenpreis, sonst Standard-Verkaufspreis. */
    public function effectivePrice(): ?Money {
        return $this->custom_price ?? $this->article?->default_sale_price;
    }
}
