<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectBillingRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBillingRule extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'project_id',
        'plugin_id',
        'applies_to_kind',
        'lexoffice_article_id',
        'item_type',
        'unit_name',
        'vat_rate',
        'net_unit_price',
        'priority',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'vat_rate' => 'decimal:2',
        'net_unit_price' => 'decimal:4',
        'priority' => 'integer',
    ];

    /**
     * Map item_type-Wert => Anzeige-Label, für <select>-Optionen und Tabellen.
     *
     * @return array<string, string>
     */
    public static function itemTypeOptions(): array {
        return [
            'service' => __('Dienstleistung'),
            'material' => __('Material'),
            'custom' => __('Sonstige'),
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<LexofficeArticle, $this> */
    public function lexofficeArticle(): BelongsTo {
        return $this->belongsTo(LexofficeArticle::class, 'lexoffice_article_id', 'external_id');
    }

    /**
     * Liefert Regeln, die auf $kind passen oder als Fallback (applies_to_kind = null) gelten.
     * Sortiert nach Spezifität (kind-Match vor Fallback) und Priorität (höher zuerst).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForKind(Builder $query, ?string $kind): Builder {
        return $query
            ->where(function (Builder $q) use ($kind): void {
                $q->whereNull('applies_to_kind');
                if ($kind !== null) {
                    $q->orWhere('applies_to_kind', $kind);
                }
            })
            ->orderByRaw('CASE WHEN applies_to_kind IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('priority')
            ->orderBy('id');
    }
}
