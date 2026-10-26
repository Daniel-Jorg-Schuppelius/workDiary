<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRecurringTemplateLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position einer gespiegelten Serienvorlage (Feature 185, MVP-1409) — gleiche
 * Spalten wie `lexoffice_voucher_lines`, in place über `(template_id, position)`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $template_id
 * @property int $position
 * @property string|null $kind Positionsart laut Lexoffice (service, custom, material)
 * @property string|null $external_article_id
 * @property int|null $lexoffice_article_id
 * @property string $name
 * @property string|null $description
 * @property string $quantity
 * @property string|null $unit_name
 * @property string $unit_net
 * @property string $total_net
 * @property string|null $tax_rate
 * @property string $currency
 * @property-read LexofficeRecurringTemplate $template
 * @property-read LexofficeArticle|null $article
 */
class LexofficeRecurringTemplateLine extends Model {
    use BelongsToOrganization;

    protected $table = 'lexoffice_recurring_template_lines';

    protected $fillable = [
        'organization_id',
        'template_id',
        'position',
        'kind',
        'external_article_id',
        'lexoffice_article_id',
        'name',
        'description',
        'quantity',
        'unit_name',
        'unit_net',
        'total_net',
        'tax_rate',
        'currency',
    ];

    protected $casts = [
        'position' => 'integer',
        'quantity' => 'decimal:3',
        'unit_net' => 'decimal:4',
        'total_net' => 'decimal:2',
        'tax_rate' => 'decimal:2',
    ];

    /** @return BelongsTo<LexofficeRecurringTemplate, $this> */
    public function template(): BelongsTo {
        return $this->belongsTo(LexofficeRecurringTemplate::class, 'template_id');
    }

    /** @return BelongsTo<LexofficeArticle, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(LexofficeArticle::class, 'lexoffice_article_id');
    }
}
