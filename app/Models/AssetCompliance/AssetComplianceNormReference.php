<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceNormReference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionKind;
use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Normen-/Regulatorik-Referenzmatrix (MVP-293, P3/W12): Prüfart →
 * Rechtsraum → Quelle mit Gültigkeit und frame_version. Reine Referenz
 * OHNE Konformitätszusage; organization_id NULL = globaler Katalogeintrag,
 * Org-Zeilen ergänzen/überschreiben (Allow-List im TenantTraitCoverageTest).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property AssetInspectionKind $inspection_kind
 * @property string $jurisdiction
 * @property string $norm_label
 */
class AssetComplianceNormReference extends Model {
    use HasSqid;

    protected $fillable = [
        'organization_id', 'inspection_kind', 'jurisdiction', 'norm_label',
        'source_url', 'valid_from', 'valid_to', 'frame_version', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'inspection_kind' => AssetInspectionKind::class,
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /**
     * Stichtagsfähige Auflösung (P1): globale Zeilen + Org-Zeilen.
     *
     * @param Builder<self> $query
     */
    public function scopeForOrganization(Builder $query, int $organizationId, ?\DateTimeInterface $onDate = null): void {
        $onDate ??= now();

        $query->where(function (Builder $q) use ($organizationId): void {
            $q->whereNull('organization_id')->orWhere('organization_id', $organizationId);
        })
            ->where(function (Builder $q) use ($onDate): void {
                $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $onDate->format('Y-m-d'));
            })
            ->where(function (Builder $q) use ($onDate): void {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $onDate->format('Y-m-d'));
            });
    }
}
