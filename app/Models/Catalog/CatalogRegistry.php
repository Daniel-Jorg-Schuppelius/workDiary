<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Catalog;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Katalogstamm (Feature 109, MVP-637): DIN-276-Kostengruppen einer
 * Ausgabe, die STLB-Bau-Leistungsbereiche oder eine freie Liste der
 * Organisation.
 *
 * **Ohne Organisation = ausgelieferter Stamm** für alle Mandanten (D7). Der
 * Stamm trägt deshalb bewusst *kein* `BelongsToOrganization`: Ein globaler
 * Scope würde die ausgelieferten Kataloge wegfiltern. Die Sichtbarkeit regelt
 * {@see visibleFor()}.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $key
 * @property string $kind
 * @property string $name
 * @property string|null $edition
 * @property string|null $gaeb_type
 * @property int $levels
 * @property bool $active
 */
class CatalogRegistry extends Model {
    use HasSqid;

    /** Kostengruppen nach DIN 276 — die Ausgabe steckt in `edition`. */
    public const KIND_COST_GROUP = 'cost_group';

    /** Leistungsbereiche (STLB-Bau 000–098). */
    public const KIND_WORK_CATEGORY = 'work_category';

    /** Freie Listen der Organisation: Gebäude, Kostenträger, Kostenstelle, Raum. */
    public const KIND_LOCALITY = 'locality';
    public const KIND_COST_UNIT = 'cost_unit';
    public const KIND_COST_CENTRE = 'cost_centre';
    public const KIND_OTHER = 'other';

    public const KINDS = [
        self::KIND_COST_GROUP, self::KIND_WORK_CATEGORY, self::KIND_LOCALITY,
        self::KIND_COST_UNIT, self::KIND_COST_CENTRE, self::KIND_OTHER,
    ];

    protected $table = 'catalog_registries';

    protected $fillable = [
        'organization_id', 'key', 'kind', 'name', 'edition', 'gaeb_type', 'levels', 'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'levels' => 'integer',
        'active' => 'boolean',
    ];

    /** @return HasMany<CatalogEntry, $this> */
    public function entries(): HasMany {
        return $this->hasMany(CatalogEntry::class, 'catalog_registry_id')->orderBy('position');
    }

    /**
     * Ausgelieferte Stämme und die der eigenen Organisation.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeVisibleFor(\Illuminate\Database\Eloquent\Builder $query, ?int $organizationId): \Illuminate\Database\Eloquent\Builder {
        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($organizationId): void {
            $q->whereNull('organization_id');
            if ($organizationId !== null) {
                $q->orWhere('organization_id', $organizationId);
            }
        });
    }

    public function isCostGroup(): bool {
        return $this->kind === self::KIND_COST_GROUP;
    }
}
