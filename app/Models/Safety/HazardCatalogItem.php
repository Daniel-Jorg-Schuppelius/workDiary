<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardCatalogItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Safety;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Carbon;

/**
 * Gefährdung aus dem Katalog der Organisation (Feature 132, MVP-1002): Vorlage
 * für Positionen der Gefährdungsbeurteilung, von Hand gepflegt oder aus einem
 * Branchenprofil. `code` hält die Profil-Einspielung idempotent.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $category
 * @property string $hazard
 * @property string|null $measure
 * @property int $severity
 * @property int $likelihood
 * @property string|null $source_profile
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HazardCatalogItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'category',
        'hazard',
        'measure',
        'severity',
        'likelihood',
        'source_profile',
        'is_active',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'severity' => 'integer',
        'likelihood' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }
}
