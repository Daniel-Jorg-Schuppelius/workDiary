<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Material;

use App\Casts\{MoneyCast, PercentageCast};
use App\Enums\Article\ArticleStatus;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $article_id
 * @property string|null $sku
 * @property string $name
 * @property string $unit
 * @property \CommonToolkit\ValueObjects\Money|null $default_unit_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property string|null $external_provider
 * @property string|null $external_id
 * @property bool $is_active
 */
class Material extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
    }

    protected $fillable = [
        'organization_id',
        'article_id',
        'sku',
        'name',
        'unit',
        'default_unit_price',
        'tax_rate',
        'external_provider',
        'external_id',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'default_unit_price' => MoneyCast::class . ':currency,4',
        'tax_rate' => PercentageCast::class . ':2',
        'is_active' => 'boolean',
    ];

    /**
     * Artikelnummer für einen Satz aus einem Fremdkatalog: leer, wenn ein anderes
     * Material der Organisation sie schon trägt (Eindeutigkeit `organization_id`
     * + `sku`) — der Treffer bleibt dann ohne Nummer buchbar.
     */
    public static function freeSku(int $organizationId, ?string $sku, ?int $exceptId = null): ?string {
        $sku = mb_substr(trim((string) $sku), 0, 64);
        if ($sku === '') {
            return null;
        }
        $taken = self::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('sku', $sku)
            ->when($exceptId !== null, static fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        return $taken ? null : $sku;
    }

    /** @return BelongsTo<Article, $this> Optionaler Artikelbezug (MVP-904) für Lieferantenauswertungen. */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /**
     * Wählbare Varianten je Material (MVP-1690): nur bei einem lagergeführten
     * Artikel mit mehr als einer aktiven Variante — sonst gibt es nichts zu
     * wählen, und der Verbrauch bucht die Standardvariante.
     *
     * @param  iterable<Material>  $materials
     * @return array<int, Collection<int, ArticleVariant>> Material-ID → Varianten
     */
    public static function variantChoices(iterable $materials): array {
        $articleIds = [];
        foreach ($materials as $material) {
            if ($material->article_id !== null) {
                $articleIds[(int) $material->article_id] = true;
            }
        }
        if ($articleIds === []) {
            return [];
        }

        $byArticle = ArticleVariant::query()
            ->whereIn('article_id', array_keys($articleIds))
            ->whereHas('article', static fn ($query) => $query->where('stockable', true))
            ->where('status', ArticleStatus::Active->value)
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'article_id', 'sku', 'name', 'is_default', 'sale_price', 'currency'])
            ->groupBy('article_id')
            ->filter(static fn (Collection $variants): bool => $variants->count() > 1);

        $choices = [];
        foreach ($materials as $material) {
            $variants = $material->article_id !== null ? $byArticle->get((int) $material->article_id) : null;
            if ($variants instanceof Collection) {
                $choices[(int) $material->id] = $variants->values();
            }
        }

        return $choices;
    }
}
