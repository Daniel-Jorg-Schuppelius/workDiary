<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Isms\IsmsScopeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Managementsystem-Geltungsbereich (Feature 046, gemeinsamer Kern):
 * trennt SoA-Aussagen (und perspektivisch Risiken/Audits) je Scope.
 * Pro Organisation existiert genau ein Default-Scope
 * („Gesamtorganisation", is_default = true), den
 * {@see \App\Services\Isms\ScopeService::ensureDefaultScope()} bei Bedarf
 * anlegt. Der Default-Scope ist nicht löschbar (Serviceregel).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 */
class IsmsScope extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsScopeFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /** @return HasMany<IsmsApplicabilityStatement, $this> */
    public function statements(): HasMany {
        return $this->hasMany(IsmsApplicabilityStatement::class, 'isms_scope_id');
    }

    /** @return HasMany<IsmsRisk, $this> */
    public function risks(): HasMany {
        return $this->hasMany(IsmsRisk::class, 'isms_scope_id');
    }
}
