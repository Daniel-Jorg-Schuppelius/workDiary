<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureMaterialRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\QuantityCast;
use App\Enums\Manufacturing\QuantityKind;
use CommonToolkit\Enums\RoundingMode;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stücklisten-/Rezepturposition einer Arbeitsplan-Version (Feature 047, MVP-061).
 * Mandantengrenze transitiv über die Arbeitsplan-Version.
 *
 * @property int $id
 * @property int $procedure_template_version_id
 * @property string $position_code
 * @property QuantityKind $quantity_kind
 * @property \CommonToolkit\ValueObjects\Quantity|null $quantity
 * @property numeric-string|null $ratio_part
 * @property numeric-string|null $waste_surcharge
 * @property string $unit
 * @property RoundingMode|null $rounding Rundung auf ganze Einheiten; null = keine (SCALE-genau)
 * @property bool $is_tool
 * @property bool $active
 */
class ProcedureMaterialRequirement extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'procedure_template_version_id',
        'position_code',
        'article_id',
        'article_variant_id',
        'quantity_kind',
        'quantity',
        'ratio_part',
        'unit',
        'rounding',
        'waste_surcharge',
        'is_tool',
        'position',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity_kind' => QuantityKind::class,
        'quantity' => QuantityCast::class . ':unit,4',
        'ratio_part' => 'decimal:4',
        'waste_surcharge' => 'decimal:3',
        'rounding' => RoundingMode::class,
        'is_tool' => 'boolean',
        'active' => 'boolean',
        'position' => 'integer',
    ];

    /** @return BelongsTo<ProcedureTemplateVersion, $this> */
    public function version(): BelongsTo {
        return $this->belongsTo(ProcedureTemplateVersion::class, 'procedure_template_version_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }
}
