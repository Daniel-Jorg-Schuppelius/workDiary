<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCostApproach.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Article;

use App\Casts\MoneyCast;
use App\Enums\Article\CostKind;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kostenansatz eines Leistungsartikels (MVP-1055): was eine Einheit der
 * Leistung an Lohn (Minuten je Lohngruppe), Material (Komponentenartikel oder
 * fester Preis), Gerät, Sonstigem oder Fremdleistung verbraucht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $article_id
 * @property CostKind $cost_kind
 * @property string|null $description
 * @property int|null $component_article_id
 * @property int|null $wage_group_id
 * @property string $quantity
 * @property string|null $unit
 * @property string|null $minutes
 * @property Money|null $unit_cost_amount
 * @property string $currency
 * @property int $position
 */
class ArticleCostApproach extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'article_id',
        'cost_kind',
        'description',
        'component_article_id',
        'wage_group_id',
        'quantity',
        'unit',
        'minutes',
        'unit_cost_amount',
        'currency',
        'position',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['currency' => 'EUR', 'quantity' => '1'];

    /** @var array<string, string> */
    protected $casts = [
        'cost_kind' => CostKind::class,
        'quantity' => 'decimal:4',
        'minutes' => 'decimal:2',
        'unit_cost_amount' => MoneyCast::class . ':currency,4',
        'position' => 'integer',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function componentArticle(): BelongsTo {
        return $this->belongsTo(Article::class, 'component_article_id');
    }

    /** @return BelongsTo<WageGroup, $this> */
    public function wageGroup(): BelongsTo {
        return $this->belongsTo(WageGroup::class);
    }
}
