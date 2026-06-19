<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleVariantBomOverride.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Manufacturing\{BomOverrideAction, QuantityKind};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Varianten-Override einer Stücklistenposition (Feature 047, MVP-061).
 * Mandantengrenze transitiv über die Variante.
 *
 * @property int $id
 * @property int $article_variant_id
 * @property string $position_code
 * @property BomOverrideAction $action
 * @property QuantityKind|null $quantity_kind
 * @property numeric-string|null $quantity
 * @property bool $is_tool
 */
class ArticleVariantBomOverride extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'article_variant_id',
        'position_code',
        'action',
        'article_id',
        'quantity_kind',
        'quantity',
        'ratio_part',
        'unit',
        'waste_surcharge',
        'is_tool',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'action' => BomOverrideAction::class,
        'quantity_kind' => QuantityKind::class,
        'quantity' => 'decimal:4',
        'ratio_part' => 'decimal:4',
        'waste_surcharge' => 'decimal:3',
        'is_tool' => 'boolean',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }
}
