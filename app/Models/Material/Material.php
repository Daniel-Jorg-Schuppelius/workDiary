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
use App\Models\Article\Article;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $organization_id
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

    /** @return BelongsTo<Article, $this> Optionaler Artikelbezug (MVP-904) für Lieferantenauswertungen. */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
