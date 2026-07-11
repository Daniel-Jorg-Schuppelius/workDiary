<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Article.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Kanonischer Artikelstamm (Feature 048, MVP-060). Der Hauptartikel beschreibt
 * die Produktfamilie und liefert vererbbare Standarddaten; bestands- und
 * fertigungsführend ist die {@see ArticleVariant}. Ein Artikel ohne Varianten
 * ist selbst die führende Einheit (Default-Variante).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string|null $number
 * @property string|null $gtin
 * @property string $name
 * @property ArticleType $type
 * @property string $base_unit
 * @property ArticleStatus $status
 */
class Article extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'number',
        'gtin',
        'name',
        'description',
        'type',
        'base_unit',
        'tax_class',
        'stockable',
        'purchasable',
        'sellable',
        'manufacturable',
        'batch_required',
        'serial_required',
        'shelf_life_required',
        'valuation_method',
        'serial_scheme',
        'status',
        'default_procedure_template_version_id',
        'default_purchase_price',
        'default_sale_price',
        'currency',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'type' => ArticleType::class,
        'status' => ArticleStatus::class,
        'stockable' => 'boolean',
        'purchasable' => 'boolean',
        'sellable' => 'boolean',
        'manufacturable' => 'boolean',
        'batch_required' => 'boolean',
        'serial_required' => 'boolean',
        'shelf_life_required' => 'boolean',
        'serial_scheme' => 'array',
        'default_purchase_price' => 'decimal:4',
        'default_sale_price' => 'decimal:4',
    ];

    /** @return HasMany<ArticleVariant, $this> */
    public function variants(): HasMany {
        return $this->hasMany(ArticleVariant::class);
    }

    /** @return HasMany<ArticleOptionDefinition, $this> */
    public function optionDefinitions(): HasMany {
        return $this->hasMany(ArticleOptionDefinition::class)->orderBy('position');
    }

    /** @return HasMany<ArticleUnit, $this> */
    public function units(): HasMany {
        return $this->hasMany(ArticleUnit::class);
    }

    /** @return HasMany<ExternalArticleMapping, $this> */
    public function externalMappings(): HasMany {
        return $this->hasMany(ExternalArticleMapping::class);
    }

    /**
     * Bezugsquellen je Lieferant (Feature 048/050): Vergleich Preis/Lieferzeit/MOQ.
     *
     * @return HasMany<ArticleSupply, $this>
     */
    public function supplies(): HasMany {
        return $this->hasMany(ArticleSupply::class);
    }

    /** @return BelongsTo<ProcedureTemplateVersion, $this> */
    public function defaultProcedureVersion(): BelongsTo {
        return $this->belongsTo(ProcedureTemplateVersion::class, 'default_procedure_template_version_id');
    }

    /** Der Artikel führt nur dann selbst Bestand, wenn er keine Varianten hat. */
    public function isStockBearingItself(): bool {
        return $this->variants()->count() === 0;
    }
}
