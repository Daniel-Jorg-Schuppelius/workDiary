<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Enums\AssetCompliance\{AssetComplianceBlockMode, AssetInspectionKind};
use App\Models\Concerns\{Auditable, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prüfprofil als Katalogdatum (Feature 075, MVP-283, P1): organization_id
 * NULL = globale Vorlage, Org-Zeilen überschreiben per Code — Auflösung
 * filtert explizit (Muster TaxRule/CrisisDeadlineTemplate), daher bewusst
 * OHNE BelongsToOrganization (Allow-List im TenantTraitCoverageTest).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $code
 * @property string $name
 * @property AssetInspectionKind $inspection_kind
 * @property int $interval_months
 * @property int $warn_days_before
 * @property int $tolerance_days
 * @property int $grace_days
 * @property AssetComplianceBlockMode $blocking_mode
 * @property bool $requires_certificate
 * @property bool $is_active
 */
class AssetComplianceProfile extends Model {
    use Auditable;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'code', 'name', 'inspection_kind', 'interval_months',
        'warn_days_before', 'tolerance_days', 'grace_days', 'blocking_mode',
        'requires_certificate', 'default_authority', 'description',
        'frame_version', 'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'inspection_kind' => AssetInspectionKind::class,
        'blocking_mode' => AssetComplianceBlockMode::class,
        'interval_months' => 'integer',
        'warn_days_before' => 'integer',
        'tolerance_days' => 'integer',
        'grace_days' => 'integer',
        'requires_certificate' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Effektiver Katalog: Org-Profile überschreiben globale per Code.
     *
     * @param Builder<self> $query
     */
    public function scopeForOrganization(Builder $query, int $organizationId): void {
        $query->where('is_active', true)
            ->where(function (Builder $q) use ($organizationId): void {
                $q->whereNull('organization_id')->orWhere('organization_id', $organizationId);
            });
    }

    /** @return HasMany<AssetComplianceRequirement, $this> */
    public function requirements(): HasMany {
        return $this->hasMany(AssetComplianceRequirement::class);
    }

    /** @return HasMany<AssetComplianceAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(AssetComplianceAssignment::class);
    }
}
